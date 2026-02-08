/**
 * Media Handler - Gerencia download, cache e upload de mídia
 * Permite servir arquivos de mídia para o PHP app via HTTP
 */

const { downloadMediaMessage, getContentType } = require('@whiskeysockets/baileys');
const fs = require('fs');
const path = require('path');
const logger = require('./logger');

// In-memory message cache para download posterior
// Estrutura: { sessionId: { messageId: messageObject } }
const messageCache = {};

// Limite de mensagens em cache por sessão
const MAX_CACHE_PER_SESSION = 500;

// Tempo de vida do cache (30 minutos)
const CACHE_TTL = 30 * 60 * 1000;

// Diretório para mídia temporária
const MEDIA_DIR = path.join(__dirname, '..', 'media');

/**
 * Ensure media directory exists
 */
function ensureMediaDir() {
    if (!fs.existsSync(MEDIA_DIR)) {
        fs.mkdirSync(MEDIA_DIR, { recursive: true });
    }
}

/**
 * Cache a message for later media download
 */
function cacheMessage(sessionId, msg) {
    if (!msg?.key?.id) return;

    if (!messageCache[sessionId]) {
        messageCache[sessionId] = {};
    }

    messageCache[sessionId][msg.key.id] = {
        message: msg,
        timestamp: Date.now(),
    };

    // Cleanup old entries
    const entries = Object.entries(messageCache[sessionId]);
    if (entries.length > MAX_CACHE_PER_SESSION) {
        // Remove oldest entries
        entries.sort((a, b) => a[1].timestamp - b[1].timestamp);
        const toRemove = entries.slice(0, entries.length - MAX_CACHE_PER_SESSION);
        for (const [id] of toRemove) {
            delete messageCache[sessionId][id];
        }
    }
}

/**
 * Get cached message
 */
function getCachedMessage(sessionId, messageId) {
    const cached = messageCache[sessionId]?.[messageId];
    if (!cached) return null;

    // Check TTL
    if (Date.now() - cached.timestamp > CACHE_TTL) {
        delete messageCache[sessionId][messageId];
        return null;
    }

    return cached.message;
}

/**
 * Download media from a cached message
 * Returns: { buffer, mimetype, filename }
 */
async function downloadMedia(sessionId, messageId, socket) {
    const msg = getCachedMessage(sessionId, messageId);
    if (!msg) {
        throw new Error(`Message ${messageId} not found in cache`);
    }

    try {
        const buffer = await downloadMediaMessage(msg, 'buffer', {}, {
            reuploadRequest: socket?.updateMediaMessage,
        });

        const messageContent = msg.message;
        let mimetype = 'application/octet-stream';
        let filename = `media_${messageId}`;

        // Detect type and filename
        if (messageContent.imageMessage) {
            mimetype = messageContent.imageMessage.mimetype || 'image/jpeg';
            filename = `image_${messageId}.${getExtFromMime(mimetype)}`;
        } else if (messageContent.videoMessage) {
            mimetype = messageContent.videoMessage.mimetype || 'video/mp4';
            filename = `video_${messageId}.${getExtFromMime(mimetype)}`;
        } else if (messageContent.audioMessage) {
            mimetype = messageContent.audioMessage.mimetype || 'audio/ogg';
            filename = `audio_${messageId}.${getExtFromMime(mimetype)}`;
        } else if (messageContent.documentMessage) {
            mimetype = messageContent.documentMessage.mimetype || 'application/octet-stream';
            filename = messageContent.documentMessage.fileName || `doc_${messageId}`;
        } else if (messageContent.stickerMessage) {
            mimetype = messageContent.stickerMessage.mimetype || 'image/webp';
            filename = `sticker_${messageId}.webp`;
        }

        return { buffer, mimetype, filename };
    } catch (err) {
        logger.error(`Failed to download media ${messageId}: ${err.message}`);
        throw err;
    }
}

/**
 * Save media to disk and return the file path
 */
async function saveMediaToDisk(sessionId, messageId, socket) {
    ensureMediaDir();

    const { buffer, mimetype, filename } = await downloadMedia(sessionId, messageId, socket);
    const sessionDir = path.join(MEDIA_DIR, sessionId);

    if (!fs.existsSync(sessionDir)) {
        fs.mkdirSync(sessionDir, { recursive: true });
    }

    const filePath = path.join(sessionDir, filename);
    fs.writeFileSync(filePath, buffer);

    return { filePath, mimetype, filename, size: buffer.length };
}

/**
 * Generate a media URL that the PHP app can fetch
 */
function getMediaUrl(sessionId, messageId) {
    const baseUrl = `http://127.0.0.1:${process.env.PORT || 3100}`;
    return `${baseUrl}/sessions/${sessionId}/download/${messageId}`;
}

/**
 * Cleanup expired cache entries
 */
function cleanupCache() {
    const now = Date.now();
    for (const sessionId of Object.keys(messageCache)) {
        for (const [msgId, entry] of Object.entries(messageCache[sessionId])) {
            if (now - entry.timestamp > CACHE_TTL) {
                delete messageCache[sessionId][msgId];
            }
        }
        // Remove empty sessions
        if (Object.keys(messageCache[sessionId]).length === 0) {
            delete messageCache[sessionId];
        }
    }
}

/**
 * Cleanup old media files (older than 1 hour)
 */
function cleanupMediaFiles() {
    if (!fs.existsSync(MEDIA_DIR)) return;

    const maxAge = 60 * 60 * 1000; // 1 hour
    const now = Date.now();

    const sessionDirs = fs.readdirSync(MEDIA_DIR, { withFileTypes: true })
        .filter(d => d.isDirectory());

    for (const dir of sessionDirs) {
        const dirPath = path.join(MEDIA_DIR, dir.name);
        const files = fs.readdirSync(dirPath);

        for (const file of files) {
            const filePath = path.join(dirPath, file);
            try {
                const stat = fs.statSync(filePath);
                if (now - stat.mtimeMs > maxAge) {
                    fs.unlinkSync(filePath);
                }
            } catch (e) { }
        }
    }
}

// Run cleanup every 10 minutes
setInterval(cleanupCache, 10 * 60 * 1000);
setInterval(cleanupMediaFiles, 10 * 60 * 1000);

/**
 * Get file extension from MIME type
 */
function getExtFromMime(mime) {
    const map = {
        'image/jpeg': 'jpg', 'image/png': 'png', 'image/gif': 'gif', 'image/webp': 'webp',
        'video/mp4': 'mp4', 'video/3gpp': '3gp',
        'audio/ogg': 'ogg', 'audio/ogg; codecs=opus': 'ogg', 'audio/mpeg': 'mp3', 'audio/mp4': 'm4a',
        'application/pdf': 'pdf',
    };
    return map[mime] || 'bin';
}

module.exports = {
    cacheMessage,
    getCachedMessage,
    downloadMedia,
    saveMediaToDisk,
    getMediaUrl,
    cleanupCache,
};
