/**
 * WhatsApp Native Service - Entry Point
 * Microserviço Node.js usando Baileys para conexão direta com WhatsApp
 */

const express = require('express');
const path = require('path');

// Load .env
require('./env-loader');

const apiRoutes = require('./api-routes');
const SessionManager = require('./session-manager');
const logger = require('./logger');

const app = express();
const PORT = process.env.PORT || 3100;
const HOST = process.env.HOST || '127.0.0.1';

// Middleware
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

// API token authentication middleware
app.use((req, res, next) => {
    const apiToken = process.env.API_TOKEN;
    if (!apiToken) return next(); // No token configured = no auth required

    const providedToken = req.headers['x-api-token'] || req.headers['authorization']?.replace('Bearer ', '') || req.query.token;
    if (providedToken === apiToken) return next();

    // Allow health check without auth
    if (req.path === '/health') return next();

    res.status(401).json({ success: false, message: 'Unauthorized: invalid API token' });
});

// Request logging
app.use((req, res, next) => {
    logger.info(`${req.method} ${req.path}`);
    next();
});

// Routes
app.use('/', apiRoutes);

// Health check
app.get('/health', (req, res) => {
    const sessions = SessionManager.getAllSessions();
    res.json({
        status: 'ok',
        uptime: process.uptime(),
        sessions: Object.keys(sessions).length,
        timestamp: new Date().toISOString()
    });
});

// Error handler
app.use((err, req, res, next) => {
    logger.error(`Unhandled error: ${err.message}`);
    res.status(500).json({ success: false, message: err.message });
});

// Startup
app.listen(PORT, HOST, () => {
    logger.info(`=== WhatsApp Native Service started ===`);
    logger.info(`Listening on http://${HOST}:${PORT}`);
    logger.info(`Webhook URL: ${process.env.WEBHOOK_URL || 'not configured'}`);

    // Auto-restart saved sessions
    SessionManager.restoreAllSessions().then(count => {
        if (count > 0) logger.info(`Restored ${count} session(s)`);
    }).catch(err => {
        logger.error(`Failed to restore sessions: ${err.message}`);
    });
});

// Graceful shutdown
process.on('SIGINT', async () => {
    logger.info('Shutting down...');
    await SessionManager.disconnectAll();
    process.exit(0);
});

process.on('SIGTERM', async () => {
    logger.info('Shutting down (SIGTERM)...');
    await SessionManager.disconnectAll();
    process.exit(0);
});
