/**
 * Webhook Sender - Envia webhooks para o PHP app
 * Formato compatível com Quepasa para reusar processWebhook()
 */

const logger = require('./logger');

// Queue para retry
const retryQueue = [];
const MAX_RETRIES = 3;
const RETRY_DELAY = 5000; // 5 seconds

/**
 * Send webhook to PHP app
 */
async function send(webhookUrl, payload) {
    if (!webhookUrl) {
        logger.warn('Webhook URL not configured, skipping');
        return;
    }

    try {
        const axios = require('axios');
        const response = await axios.post(webhookUrl, payload, {
            headers: {
                'Content-Type': 'application/json',
                'X-Webhook-Source': 'native-whatsapp-service',
                'X-Track-Id': payload.trackId || payload.trackid || '',
            },
            timeout: 15000,
            validateStatus: () => true, // Don't throw on non-2xx
        });

        if (response.status >= 200 && response.status < 300) {
            logger.debug(`Webhook sent successfully: ${payload.event || payload.type || 'message'} -> ${response.status}`);
        } else {
            logger.warn(`Webhook response ${response.status}: ${JSON.stringify(response.data || '').substring(0, 200)}`);
        }

        return response;
    } catch (err) {
        logger.error(`Webhook send failed: ${err.message}`);

        // Queue for retry
        const retries = payload._retries || 0;
        if (retries < MAX_RETRIES) {
            payload._retries = retries + 1;
            logger.info(`Queueing webhook for retry (${payload._retries}/${MAX_RETRIES})`);
            setTimeout(() => send(webhookUrl, payload), RETRY_DELAY * payload._retries);
        }

        return null;
    }
}

module.exports = { send };
