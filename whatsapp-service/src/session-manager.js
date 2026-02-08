/**
 * Session Manager - Gerencia múltiplas sessões Baileys
 * Cada sessão = uma conexão WhatsApp com número diferente
 */

const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, makeCacheableSignalKeyStore, Browsers } = require('@whiskeysockets/baileys');
const path = require('path');
const fs = require('fs');
const logger = require('./logger');
const ProxyManager = require('./proxy-manager');
const WebhookSender = require('./webhook-sender');
const MediaHandler = require('./media-handler');

// Active sessions: { sessionId: { socket, status, qrCode, proxy, ... } }
const sessions = {};

const SESSIONS_PATH = process.env.SESSIONS_PATH || path.join(__dirname, 'store');

/**
 * Ensure sessions directory exists
 */
function ensureSessionDir(sessionId) {
    const dir = path.join(SESSIONS_PATH, sessionId);
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
    return dir;
}

/**
 * Get session config file path
 */
function getConfigPath(sessionId) {
    return path.join(SESSIONS_PATH, sessionId, 'config.json');
}

/**
 * Save session config (proxy, webhook, etc.)
 */
function saveSessionConfig(sessionId, config) {
    const configPath = getConfigPath(sessionId);
    fs.writeFileSync(configPath, JSON.stringify(config, null, 2));
}

/**
 * Load session config
 */
function loadSessionConfig(sessionId) {
    const configPath = getConfigPath(sessionId);
    if (fs.existsSync(configPath)) {
        return JSON.parse(fs.readFileSync(configPath, 'utf-8'));
    }
    return {};
}

/**
 * Start a new WhatsApp session
 */
async function startSession(sessionId, options = {}) {
    if (sessions[sessionId]?.socket) {
        logger.warn(`Session ${sessionId} already exists, restarting...`);
        await stopSession(sessionId);
    }

    logger.info(`Starting session: ${sessionId}`);

    const sessionDir = ensureSessionDir(sessionId);
    const { state, saveCreds } = await useMultiFileAuthState(sessionDir);

    // Save config
    const config = {
        proxy: options.proxy || null,
        webhookUrl: options.webhookUrl || process.env.WEBHOOK_URL,
        trackId: options.trackId || sessionId,
        phoneNumber: options.phoneNumber || null,
        createdAt: new Date().toISOString()
    };
    saveSessionConfig(sessionId, config);

    // Build socket options
    const socketOptions = {
        auth: {
            creds: state.creds,
            keys: makeCacheableSignalKeyStore(state.keys, logger.pinoLogger),
        },
        browser: Browsers.ubuntu('OrbChat'),
        printQRInTerminal: false,
        logger: logger.pinoLogger,
        generateHighQualityLinkPreview: false,
        markOnlineOnConnect: false,
    };

    // Configure proxy if provided
    if (options.proxy) {
        const agent = ProxyManager.createAgent(options.proxy);
        if (agent) {
            socketOptions.agent = agent;
            logger.info(`Session ${sessionId}: using proxy ${options.proxy.host}`);
        }
    }

    // Create socket
    const socket = makeWASocket(socketOptions);

    // Session state
    sessions[sessionId] = {
        socket,
        status: 'connecting',
        qrCode: null,
        qrRetries: 0,
        config,
        phoneNumber: null,
        connectedAt: null,
    };

    // Handle connection updates
    socket.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            sessions[sessionId].qrCode = qr;
            sessions[sessionId].qrRetries = (sessions[sessionId].qrRetries || 0) + 1;
            sessions[sessionId].status = 'qr_ready';
            logger.info(`Session ${sessionId}: QR code generated (attempt ${sessions[sessionId].qrRetries})`);

            // Send QR via webhook
            WebhookSender.send(config.webhookUrl, {
                event: 'qr',
                sessionId,
                trackId: config.trackId,
                qr,
            });
        }

        if (connection === 'open') {
            const phoneNumber = socket.user?.id?.split(':')[0] || socket.user?.id?.split('@')[0] || '';
            sessions[sessionId].status = 'connected';
            sessions[sessionId].qrCode = null;
            sessions[sessionId].phoneNumber = phoneNumber;
            sessions[sessionId].connectedAt = new Date().toISOString();
            logger.info(`Session ${sessionId}: connected as ${phoneNumber}`);

            // Send status via webhook
            WebhookSender.send(config.webhookUrl, {
                event: 'status',
                sessionId,
                trackId: config.trackId,
                status: 'connected',
                phoneNumber,
                chatId: socket.user?.id,
            });
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

            logger.warn(`Session ${sessionId}: disconnected (code: ${statusCode}, reconnect: ${shouldReconnect})`);
            sessions[sessionId].status = shouldReconnect ? 'reconnecting' : 'disconnected';

            // Send status via webhook
            WebhookSender.send(config.webhookUrl, {
                event: 'status',
                sessionId,
                trackId: config.trackId,
                status: shouldReconnect ? 'reconnecting' : 'disconnected',
                reason: lastDisconnect?.error?.message || 'unknown',
            });

            if (shouldReconnect) {
                logger.info(`Session ${sessionId}: reconnecting in 3s...`);
                setTimeout(() => startSession(sessionId, { ...config, proxy: config.proxy }), 3000);
            } else {
                // Logged out - clear auth
                logger.info(`Session ${sessionId}: logged out, clearing session data`);
                delete sessions[sessionId];
            }
        }
    });

    // Handle credentials update
    socket.ev.on('creds.update', saveCreds);

    // Handle incoming messages
    socket.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') return;

        for (const msg of messages) {
            // Skip status messages
            if (msg.key?.remoteJid === 'status@broadcast') continue;

            logger.debug(`Session ${sessionId}: message from ${msg.key?.remoteJid}`);

            // Cache message for later media download
            MediaHandler.cacheMessage(sessionId, msg);

            // For media messages, save to disk first and include download URL
            let mediaInfo = null;
            const msgContent = msg.message || {};
            const hasMedia = msgContent.imageMessage || msgContent.videoMessage || 
                           msgContent.audioMessage || msgContent.documentMessage || 
                           msgContent.stickerMessage;
            
            if (hasMedia) {
                try {
                    mediaInfo = await MediaHandler.saveMediaToDisk(sessionId, msg.key.id, socket);
                    logger.debug(`Session ${sessionId}: media saved: ${mediaInfo.filename} (${mediaInfo.size} bytes)`);
                } catch (err) {
                    logger.warn(`Session ${sessionId}: failed to save media: ${err.message}`);
                }
            }

            // Convert to Quepasa-compatible format and send webhook
            const webhookPayload = convertMessageToWebhook(sessionId, config, msg, socket, mediaInfo);
            if (webhookPayload) {
                WebhookSender.send(config.webhookUrl, webhookPayload);
            }
        }
    });

    // Handle message updates (delivery receipts, read receipts)
    socket.ev.on('messages.update', async (updates) => {
        for (const update of updates) {
            if (update.update?.status) {
                const statusMap = { 2: 'sent', 3: 'delivered', 4: 'read' };
                const statusName = statusMap[update.update.status] || 'unknown';
                logger.debug(`Session ${sessionId}: message ${update.key?.id} status -> ${statusName}`);

                WebhookSender.send(config.webhookUrl, {
                    event: 'message_status',
                    sessionId,
                    trackId: config.trackId,
                    messageId: update.key?.id,
                    remoteJid: update.key?.remoteJid,
                    status: statusName,
                    statusCode: update.update.status,
                });
            }
        }
    });

    return sessions[sessionId];
}

/**
 * Convert Baileys message to Quepasa-compatible webhook format
 */
function convertMessageToWebhook(sessionId, config, msg, socket, mediaInfo = null) {
    try {
        const messageContent = msg.message;
        if (!messageContent) return null;

        const isFromMe = msg.key?.fromMe || false;
        const remoteJid = msg.key?.remoteJid || '';
        const isGroup = remoteJid.endsWith('@g.us');
        const participant = msg.key?.participant || '';

        // Determine sender
        const from = isFromMe ? socket.user?.id : remoteJid;
        const senderPhone = isGroup
            ? (participant.split('@')[0] || '')
            : (remoteJid.split('@')[0] || '');

        // Determine message type and content
        let type = 'text';
        let body = '';
        let attachment = null;
        let inReaction = false;
        let inReply = null;

        // Text messages
        if (messageContent.conversation) {
            type = 'text';
            body = messageContent.conversation;
        } else if (messageContent.extendedTextMessage) {
            type = 'text';
            body = messageContent.extendedTextMessage.text || '';
            inReply = messageContent.extendedTextMessage.contextInfo?.stanzaId || null;
        }

        // Image
        else if (messageContent.imageMessage) {
            type = 'image';
            body = messageContent.imageMessage.caption || '';
            attachment = {
                mime: messageContent.imageMessage.mimetype,
                filelength: messageContent.imageMessage.fileLength,
                filename: `image_${Date.now()}.${getExtFromMime(messageContent.imageMessage.mimetype)}`,
            };
            inReply = messageContent.imageMessage.contextInfo?.stanzaId || null;
        }

        // Video
        else if (messageContent.videoMessage) {
            type = 'video';
            body = messageContent.videoMessage.caption || '';
            attachment = {
                mime: messageContent.videoMessage.mimetype,
                filelength: messageContent.videoMessage.fileLength,
                filename: `video_${Date.now()}.${getExtFromMime(messageContent.videoMessage.mimetype)}`,
                seconds: messageContent.videoMessage.seconds,
            };
            inReply = messageContent.videoMessage.contextInfo?.stanzaId || null;
        }

        // Audio
        else if (messageContent.audioMessage) {
            type = messageContent.audioMessage.ptt ? 'ptt' : 'audio';
            attachment = {
                mime: messageContent.audioMessage.mimetype,
                filelength: messageContent.audioMessage.fileLength,
                filename: `audio_${Date.now()}.ogg`,
                seconds: messageContent.audioMessage.seconds,
            };
            inReply = messageContent.audioMessage.contextInfo?.stanzaId || null;
        }

        // Document
        else if (messageContent.documentMessage) {
            type = 'document';
            body = messageContent.documentMessage.caption || '';
            attachment = {
                mime: messageContent.documentMessage.mimetype,
                filelength: messageContent.documentMessage.fileLength,
                filename: messageContent.documentMessage.fileName || `doc_${Date.now()}`,
            };
            inReply = messageContent.documentMessage.contextInfo?.stanzaId || null;
        }

        // Sticker
        else if (messageContent.stickerMessage) {
            type = 'sticker';
            attachment = {
                mime: messageContent.stickerMessage.mimetype,
                filelength: messageContent.stickerMessage.fileLength,
                filename: `sticker_${Date.now()}.webp`,
            };
        }

        // Contact (vCard)
        else if (messageContent.contactMessage) {
            type = 'contact';
            body = messageContent.contactMessage.displayName || '';
            attachment = {
                mime: 'text/x-vcard',
                content: messageContent.contactMessage.vcard,
                filename: `${body || 'contact'}.vcf`,
            };
        }

        // Contacts array
        else if (messageContent.contactsArrayMessage) {
            type = 'contact';
            const contacts = messageContent.contactsArrayMessage.contacts || [];
            if (contacts.length > 0) {
                body = contacts[0].displayName || '';
                attachment = {
                    mime: 'text/x-vcard',
                    content: contacts[0].vcard,
                    filename: `${body || 'contact'}.vcf`,
                };
            }
        }

        // Location
        else if (messageContent.locationMessage) {
            type = 'location';
            body = messageContent.locationMessage.name || '';
            attachment = {
                mime: 'text/x-uri; location',
                latitude: messageContent.locationMessage.degreesLatitude,
                longitude: messageContent.locationMessage.degreesLongitude,
            };
        }

        // Reaction
        else if (messageContent.reactionMessage) {
            type = 'text';
            body = messageContent.reactionMessage.text || '';
            inReaction = true;
            inReply = messageContent.reactionMessage.key?.id || null;
        }

        // Enrich attachment with media download info
        if (attachment && mediaInfo) {
            attachment.url = MediaHandler.getMediaUrl(sessionId, msg.key?.id);
            attachment.localPath = mediaInfo.filePath;
            attachment.filelength = mediaInfo.size;
        }

        // Build Quepasa-compatible webhook payload
        return {
            // Identification
            id: msg.key?.id,
            trackid: config.trackId,
            sessionId,

            // Message data
            type,
            body,
            text: body,
            from: from,
            fromme: isFromMe,
            chatid: remoteJid,

            // Group info
            isGroup,
            participant: isGroup ? participant : null,

            // Reply / Reaction
            inreply: inReply,
            inreaction: inReaction,

            // Attachment
            attachment: attachment || undefined,

            // Timestamp
            timestamp: (msg.messageTimestamp || Math.floor(Date.now() / 1000)),

            // Source
            source: 'native',
        };
    } catch (err) {
        logger.error(`Error converting message to webhook: ${err.message}`);
        return null;
    }
}

/**
 * Get file extension from MIME type
 */
function getExtFromMime(mime) {
    const map = {
        'image/jpeg': 'jpg', 'image/png': 'png', 'image/gif': 'gif', 'image/webp': 'webp',
        'video/mp4': 'mp4', 'video/3gpp': '3gp',
        'audio/ogg': 'ogg', 'audio/ogg; codecs=opus': 'ogg', 'audio/mpeg': 'mp3', 'audio/mp4': 'm4a',
        'application/pdf': 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
    };
    return map[mime] || 'bin';
}

/**
 * Stop a session
 */
async function stopSession(sessionId) {
    const session = sessions[sessionId];
    if (!session) return;

    logger.info(`Stopping session: ${sessionId}`);

    try {
        if (session.socket) {
            session.socket.ev.removeAllListeners();
            await session.socket.logout().catch(() => {});
            session.socket.end();
        }
    } catch (err) {
        logger.error(`Error stopping session ${sessionId}: ${err.message}`);
    }

    delete sessions[sessionId];
}

/**
 * Disconnect session without logging out (keeps auth)
 */
async function disconnectSession(sessionId) {
    const session = sessions[sessionId];
    if (!session) return;

    logger.info(`Disconnecting session: ${sessionId}`);

    try {
        if (session.socket) {
            session.socket.ev.removeAllListeners();
            session.socket.end();
        }
    } catch (err) {
        logger.error(`Error disconnecting session ${sessionId}: ${err.message}`);
    }

    session.status = 'disconnected';
    session.socket = null;
}

/**
 * Get session info
 */
function getSession(sessionId) {
    return sessions[sessionId] || null;
}

/**
 * Get all sessions
 */
function getAllSessions() {
    const result = {};
    for (const [id, session] of Object.entries(sessions)) {
        result[id] = {
            status: session.status,
            phoneNumber: session.phoneNumber,
            connectedAt: session.connectedAt,
            proxy: session.config?.proxy?.host || null,
            qrAvailable: !!session.qrCode,
        };
    }
    return result;
}

/**
 * Send a message via a session
 */
async function sendMessage(sessionId, chatId, content, options = {}) {
    const session = sessions[sessionId];
    if (!session?.socket) {
        throw new Error(`Session ${sessionId} not found or not connected`);
    }

    if (session.status !== 'connected') {
        throw new Error(`Session ${sessionId} is not connected (status: ${session.status})`);
    }

    const socket = session.socket;

    // Normalize chatId
    if (!chatId.includes('@')) {
        chatId = chatId + '@s.whatsapp.net';
    }

    // Build message
    let messageContent = {};
    let sendOptions = {};

    // Reply context
    if (options.inReply) {
        sendOptions.quoted = {
            key: {
                remoteJid: chatId,
                id: options.inReply,
            },
            message: {},
        };
    }

    // Reaction
    if (options.reaction) {
        return await socket.sendMessage(chatId, {
            react: {
                text: content.text || content,
                key: {
                    remoteJid: chatId,
                    fromMe: options.reactionFromMe || false,
                    id: options.inReply,
                },
            },
        });
    }

    // Text message
    if (typeof content === 'string' || content.text) {
        messageContent.text = content.text || content;
    }

    // Media (buffer or URL)
    else if (content.image) {
        messageContent = {
            image: content.image.url ? { url: content.image.url } : content.image.buffer,
            caption: content.caption || '',
            mimetype: content.mimetype,
        };
    }
    else if (content.video) {
        messageContent = {
            video: content.video.url ? { url: content.video.url } : content.video.buffer,
            caption: content.caption || '',
            mimetype: content.mimetype,
        };
    }
    else if (content.audio) {
        messageContent = {
            audio: content.audio.url ? { url: content.audio.url } : content.audio.buffer,
            mimetype: content.mimetype || 'audio/ogg; codecs=opus',
            ptt: content.ptt || false,
        };
    }
    else if (content.document) {
        messageContent = {
            document: content.document.url ? { url: content.document.url } : content.document.buffer,
            mimetype: content.mimetype || 'application/octet-stream',
            fileName: content.fileName || 'document',
        };
    }

    // Contact (vCard)
    else if (content.contact) {
        messageContent = {
            contacts: {
                displayName: content.contact.name,
                contacts: [{ vcard: content.contact.vcard }],
            },
        };
    }

    // Location
    else if (content.location) {
        messageContent = {
            location: {
                degreesLatitude: content.location.latitude,
                degreesLongitude: content.location.longitude,
            },
        };
    }

    const result = await socket.sendMessage(chatId, messageContent, sendOptions);
    logger.info(`Session ${sessionId}: message sent to ${chatId}, id: ${result?.key?.id}`);

    return {
        id: result?.key?.id,
        chatId,
        timestamp: result?.messageTimestamp,
    };
}

/**
 * Download media from a message
 */
async function downloadMedia(sessionId, message) {
    const session = sessions[sessionId];
    if (!session?.socket) {
        throw new Error(`Session ${sessionId} not found`);
    }

    const { downloadMediaMessage } = require('@whiskeysockets/baileys');
    const buffer = await downloadMediaMessage(message, 'buffer', {}, {
        reuploadRequest: session.socket.updateMediaMessage,
    });

    return buffer;
}

/**
 * Restore all saved sessions on startup
 */
async function restoreAllSessions() {
    if (!fs.existsSync(SESSIONS_PATH)) return 0;

    const dirs = fs.readdirSync(SESSIONS_PATH, { withFileTypes: true })
        .filter(d => d.isDirectory())
        .map(d => d.name);

    let restored = 0;
    for (const sessionId of dirs) {
        const credsPath = path.join(SESSIONS_PATH, sessionId, 'creds.json');
        if (!fs.existsSync(credsPath)) continue;

        try {
            const config = loadSessionConfig(sessionId);
            logger.info(`Restoring session: ${sessionId}`);
            await startSession(sessionId, config);
            restored++;
        } catch (err) {
            logger.error(`Failed to restore session ${sessionId}: ${err.message}`);
        }
    }

    return restored;
}

/**
 * Disconnect all sessions gracefully
 */
async function disconnectAll() {
    for (const sessionId of Object.keys(sessions)) {
        await disconnectSession(sessionId);
    }
}

module.exports = {
    startSession,
    stopSession,
    disconnectSession,
    getSession,
    getAllSessions,
    sendMessage,
    downloadMedia,
    restoreAllSessions,
    disconnectAll,
};
