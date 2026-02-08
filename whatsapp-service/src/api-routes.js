/**
 * API Routes - REST API compatível com interface Quepasa
 * Todos os endpoints são internos (localhost only)
 */

const express = require('express');
const router = express.Router();
const SessionManager = require('./session-manager');
const ProxyManager = require('./proxy-manager');
const logger = require('./logger');

// ============================================================
// SESSIONS
// ============================================================

/**
 * GET /sessions - List all sessions
 */
router.get('/sessions', (req, res) => {
    const sessions = SessionManager.getAllSessions();
    res.json({ success: true, sessions });
});

/**
 * POST /sessions/:id/start - Start a new session
 * Body: { proxy?: string, webhookUrl?: string, trackId?: string, phoneNumber?: string }
 */
router.post('/sessions/:id/start', async (req, res) => {
    try {
        const { id } = req.params;
        const { proxy, webhookUrl, trackId, phoneNumber } = req.body;

        const options = {
            proxy: proxy || null,
            webhookUrl: webhookUrl || process.env.WEBHOOK_URL,
            trackId: trackId || id,
            phoneNumber: phoneNumber || null,
        };

        const session = await SessionManager.startSession(id, options);

        res.json({
            success: true,
            message: `Session ${id} started`,
            session: {
                status: session.status,
                qrAvailable: !!session.qrCode,
            },
        });
    } catch (err) {
        logger.error(`POST /sessions/${req.params.id}/start error: ${err.message}`);
        res.status(500).json({ success: false, message: err.message });
    }
});

/**
 * POST /sessions/:id/stop - Stop and logout a session
 */
router.post('/sessions/:id/stop', async (req, res) => {
    try {
        const { id } = req.params;
        await SessionManager.stopSession(id);
        res.json({ success: true, message: `Session ${id} stopped` });
    } catch (err) {
        res.status(500).json({ success: false, message: err.message });
    }
});

/**
 * POST /sessions/:id/disconnect - Disconnect without logout
 */
router.post('/sessions/:id/disconnect', async (req, res) => {
    try {
        const { id } = req.params;
        await SessionManager.disconnectSession(id);
        res.json({ success: true, message: `Session ${id} disconnected (auth preserved)` });
    } catch (err) {
        res.status(500).json({ success: false, message: err.message });
    }
});

// ============================================================
// QR CODE
// ============================================================

/**
 * GET /sessions/:id/qrcode - Get QR code for session
 * Returns: { qr: "base64_png_image" } or { qr: "raw_qr_string" }
 */
router.get('/sessions/:id/qrcode', async (req, res) => {
    try {
        const { id } = req.params;
        const format = req.query.format || 'png'; // 'png' or 'raw'
        const session = SessionManager.getSession(id);

        if (!session) {
            return res.status(404).json({ success: false, message: `Session ${id} not found` });
        }

        if (session.status === 'connected') {
            return res.json({ success: true, status: 'connected', message: 'Already connected' });
        }

        if (!session.qrCode) {
            return res.json({ success: true, status: session.status, qr: null, message: 'QR not ready yet, try again in a moment' });
        }

        if (format === 'raw') {
            return res.json({ success: true, status: 'qr_ready', qr: session.qrCode });
        }

        // Convert to PNG base64
        const QRCode = require('qrcode');
        const qrPng = await QRCode.toDataURL(session.qrCode, { width: 300 });

        res.json({ success: true, status: 'qr_ready', qr: qrPng });
    } catch (err) {
        res.status(500).json({ success: false, message: err.message });
    }
});

/**
 * GET /sessions/:id/qrcode/image - Get QR code as PNG image directly
 */
router.get('/sessions/:id/qrcode/image', async (req, res) => {
    try {
        const { id } = req.params;
        const session = SessionManager.getSession(id);

        if (!session || !session.qrCode) {
            return res.status(404).send('QR code not available');
        }

        const QRCode = require('qrcode');
        res.setHeader('Content-Type', 'image/png');
        await QRCode.toFileStream(res, session.qrCode, { width: 300 });
    } catch (err) {
        res.status(500).send('Error generating QR');
    }
});

// ============================================================
// STATUS
// ============================================================

/**
 * GET /sessions/:id/status - Get session status
 */
router.get('/sessions/:id/status', (req, res) => {
    const { id } = req.params;
    const session = SessionManager.getSession(id);

    if (!session) {
        return res.status(404).json({
            success: false,
            status: 'not_found',
            message: `Session ${id} not found`,
        });
    }

    res.json({
        success: true,
        status: session.status,
        phoneNumber: session.phoneNumber,
        connectedAt: session.connectedAt,
        proxy: session.config?.proxy?.host || null,
        qrAvailable: !!session.qrCode,
        // Quepasa-compatible fields
        chatid: session.socket?.user?.id || null,
        connected: session.status === 'connected',
    });
});

// ============================================================
// SEND MESSAGE
// ============================================================

/**
 * POST /sessions/:id/send - Send a message
 * Body: { chatid: string, text?: string, inreply?: string }
 * Also accepts Quepasa-compatible headers
 */
router.post('/sessions/:id/send', async (req, res) => {
    try {
        const { id } = req.params;
        const chatId = req.body.chatid || req.body.chatId || req.headers['x-quepasa-chatid'];
        const text = req.body.text || req.body.message || '';
        const inReply = req.body.inreply || req.body.inReply || null;

        if (!chatId) {
            return res.status(400).json({ success: false, message: 'chatid is required' });
        }

        // Check if it's a reaction
        if (req.body.reaction || req.body.inreaction) {
            const result = await SessionManager.sendMessage(id, chatId, { text }, {
                reaction: true,
                inReply: inReply || req.body.inreply,
                reactionFromMe: req.body.reactionFromMe || false,
            });
            return res.json({ success: true, result });
        }

        const result = await SessionManager.sendMessage(id, chatId, { text }, { inReply });

        res.json({
            success: true,
            result: {
                id: result.id,
                chatId: result.chatId,
                timestamp: result.timestamp,
            },
        });
    } catch (err) {
        logger.error(`POST /sessions/${req.params.id}/send error: ${err.message}`);
        res.status(500).json({ success: false, message: err.message });
    }
});

/**
 * POST /sessions/:id/send-media - Send media message
 * Body: { chatid, url?, base64?, mimetype, filename, caption, inreply? }
 * 
 * Media source priority: url > base64 > localPath
 */
router.post('/sessions/:id/send-media', async (req, res) => {
    try {
        const { id } = req.params;
        const chatId = req.body.chatid || req.body.chatId;
        const { url, base64, localPath, mimetype, filename, caption, inreply } = req.body;

        if (!chatId) {
            return res.status(400).json({ success: false, message: 'chatid is required' });
        }

        // Determine media source
        let mediaSource;
        if (url) {
            mediaSource = { url };
        } else if (base64) {
            mediaSource = Buffer.from(base64, 'base64');
        } else if (localPath) {
            const fs = require('fs');
            if (!fs.existsSync(localPath)) {
                return res.status(400).json({ success: false, message: 'File not found: ' + localPath });
            }
            mediaSource = { url: localPath };
        } else {
            return res.status(400).json({ success: false, message: 'url, base64 or localPath is required' });
        }

        // Determine media type from MIME
        let content = {};
        const mime = (mimetype || '').toLowerCase();

        if (mime.startsWith('image/')) {
            content = { image: mediaSource, caption, mimetype: mime };
        } else if (mime.startsWith('video/')) {
            content = { video: mediaSource, caption, mimetype: mime };
        } else if (mime.startsWith('audio/')) {
            content = { audio: mediaSource, mimetype: mime, ptt: mime.includes('ogg') };
        } else {
            content = { document: mediaSource, mimetype: mime, fileName: filename || 'document' };
        }

        const result = await SessionManager.sendMessage(id, chatId, content, { inReply: inreply });

        res.json({
            success: true,
            result: {
                id: result.id,
                chatId: result.chatId,
            },
        });
    } catch (err) {
        logger.error(`POST /sessions/${req.params.id}/send-media error: ${err.message}`);
        res.status(500).json({ success: false, message: err.message });
    }
});

// ============================================================
// DOWNLOAD MEDIA
// ============================================================

/**
 * GET /sessions/:id/download/:messageId - Download media from message
 * Serves the cached media file or downloads on-the-fly
 */
router.get('/sessions/:id/download/:messageId', async (req, res) => {
    try {
        const { id, messageId } = req.params;
        const session = SessionManager.getSession(id);
        const MediaHandler = require('./media-handler');
        const path = require('path');
        const fs = require('fs');

        // First check if file exists on disk
        const mediaDir = path.join(__dirname, '..', 'media', id);
        if (fs.existsSync(mediaDir)) {
            const files = fs.readdirSync(mediaDir).filter(f => f.includes(messageId));
            if (files.length > 0) {
                const filePath = path.join(mediaDir, files[0]);
                return res.sendFile(filePath);
            }
        }

        // If not on disk, try to download from cache
        if (!session?.socket) {
            return res.status(404).json({ success: false, message: 'Session not found or media not available' });
        }

        try {
            const mediaInfo = await MediaHandler.saveMediaToDisk(id, messageId, session.socket);
            res.setHeader('Content-Type', mediaInfo.mimetype);
            res.setHeader('Content-Disposition', `attachment; filename="${mediaInfo.filename}"`);
            res.sendFile(mediaInfo.filePath);
        } catch (downloadErr) {
            res.status(404).json({
                success: false,
                message: 'Media not found in cache. Messages are cached for 30 minutes after receipt.',
            });
        }
    } catch (err) {
        res.status(500).json({ success: false, message: err.message });
    }
});

// ============================================================
// WEBHOOK CONFIG
// ============================================================

/**
 * POST /sessions/:id/webhook - Configure webhook URL
 * Body: { url: string, trackid?: string }
 */
router.post('/sessions/:id/webhook', (req, res) => {
    const { id } = req.params;
    const { url, trackid } = req.body;
    const session = SessionManager.getSession(id);

    if (!session) {
        return res.status(404).json({ success: false, message: `Session ${id} not found` });
    }

    session.config.webhookUrl = url;
    if (trackid) session.config.trackId = trackid;

    // Save config
    const fs = require('fs');
    const path = require('path');
    const configPath = path.join(process.env.SESSIONS_PATH || './src/store', id, 'config.json');
    fs.writeFileSync(configPath, JSON.stringify(session.config, null, 2));

    res.json({ success: true, message: 'Webhook configured' });
});

// ============================================================
// PROXY
// ============================================================

/**
 * POST /proxy/test - Test proxy connectivity
 * Body: { proxy: "socks5://user:pass@host:port" }
 */
router.post('/proxy/test', async (req, res) => {
    try {
        const { proxy } = req.body;
        const result = await ProxyManager.testProxy(proxy);
        res.json(result);
    } catch (err) {
        res.status(500).json({ success: false, message: err.message });
    }
});

// ============================================================
// QUEPASA-COMPATIBLE ENDPOINTS (aliases)
// ============================================================

/**
 * POST /send - Quepasa-compatible send (uses headers for auth)
 * Headers: X-QUEPASA-TOKEN, X-QUEPASA-CHATID, X-QUEPASA-TRACKID
 */
router.post('/send', async (req, res) => {
    try {
        const trackId = req.headers['x-quepasa-trackid'] || req.body.trackid;
        const chatId = req.headers['x-quepasa-chatid'] || req.body.chatid;
        const text = req.body.text || '';

        if (!trackId) {
            return res.status(400).json({ success: false, message: 'trackid required' });
        }

        // Find session by trackId
        const sessions = SessionManager.getAllSessions();
        let sessionId = null;
        for (const [id, session] of Object.entries(sessions)) {
            if (id === trackId) {
                sessionId = id;
                break;
            }
        }

        if (!sessionId) {
            return res.status(404).json({ success: false, message: `No session found for trackId: ${trackId}` });
        }

        // Check if reaction
        if (req.body.inreaction || req.body.reaction) {
            const result = await SessionManager.sendMessage(sessionId, chatId, { text }, {
                reaction: true,
                inReply: req.body.inreply,
            });
            return res.json({ success: true, result });
        }

        const result = await SessionManager.sendMessage(sessionId, chatId, { text }, {
            inReply: req.body.inreply,
        });

        res.json({ success: true, result });
    } catch (err) {
        res.status(500).json({ success: false, message: err.message });
    }
});

/**
 * GET /info - Quepasa-compatible info endpoint
 */
router.get('/info', (req, res) => {
    const sessions = SessionManager.getAllSessions();
    const sessionList = Object.entries(sessions).map(([id, s]) => ({
        id,
        status: s.status,
        phoneNumber: s.phoneNumber,
        connected: s.status === 'connected',
    }));

    res.json({
        success: true,
        service: 'native-whatsapp-service',
        version: '1.0.0',
        sessions: sessionList,
    });
});

/**
 * GET /scan - Quepasa-compatible QR endpoint
 * Uses X-QUEPASA-TOKEN header to identify session
 */
router.get('/scan', async (req, res) => {
    const trackId = req.headers['x-quepasa-trackid'] || req.query.trackid;
    if (!trackId) {
        return res.status(400).json({ success: false, message: 'trackid required' });
    }

    const session = SessionManager.getSession(trackId);
    if (!session || !session.qrCode) {
        return res.status(404).json({ success: false, message: 'QR not available' });
    }

    try {
        const QRCode = require('qrcode');
        const qrPng = await QRCode.toBuffer(session.qrCode, { width: 300 });
        res.setHeader('Content-Type', 'image/png');
        res.send(qrPng);
    } catch (err) {
        res.status(500).json({ success: false, message: err.message });
    }
});

module.exports = router;
