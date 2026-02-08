/**
 * Proxy Manager - Gerencia proxies para conexões WhatsApp
 * Suporta SOCKS5, HTTP e HTTPS
 */

const { SocksProxyAgent } = require('socks-proxy-agent');
const { HttpsProxyAgent } = require('https-proxy-agent');
const { HttpProxyAgent } = require('http-proxy-agent');
const logger = require('./logger');

/**
 * Parse proxy string into structured object
 * Formats supported:
 *   socks5://user:pass@host:port
 *   http://user:pass@host:port
 *   https://user:pass@host:port
 *   host:port (defaults to socks5)
 *   user:pass@host:port (defaults to socks5)
 */
function parseProxy(proxyString) {
    if (!proxyString) return null;

    try {
        // If it's already an object
        if (typeof proxyString === 'object') return proxyString;

        let protocol = 'socks5';
        let auth = null;
        let host = '';
        let port = 1080;

        let url = proxyString.trim();

        // Extract protocol
        const protoMatch = url.match(/^(socks5|socks4|http|https):\/\//i);
        if (protoMatch) {
            protocol = protoMatch[1].toLowerCase();
            url = url.substring(protoMatch[0].length);
        }

        // Extract auth
        const atIndex = url.lastIndexOf('@');
        if (atIndex !== -1) {
            const authPart = url.substring(0, atIndex);
            url = url.substring(atIndex + 1);
            const colonIndex = authPart.indexOf(':');
            if (colonIndex !== -1) {
                auth = {
                    username: authPart.substring(0, colonIndex),
                    password: authPart.substring(colonIndex + 1),
                };
            }
        }

        // Extract host and port
        const colonIndex = url.lastIndexOf(':');
        if (colonIndex !== -1) {
            host = url.substring(0, colonIndex);
            port = parseInt(url.substring(colonIndex + 1)) || 1080;
        } else {
            host = url;
        }

        return { protocol, host, port, auth };
    } catch (err) {
        logger.error(`Failed to parse proxy string "${proxyString}": ${err.message}`);
        return null;
    }
}

/**
 * Create an HTTP/SOCKS agent from proxy config
 * Returns agent suitable for Baileys socket options
 */
function createAgent(proxyConfig) {
    if (!proxyConfig) return null;

    // Parse if string
    if (typeof proxyConfig === 'string') {
        proxyConfig = parseProxy(proxyConfig);
    }
    if (!proxyConfig || !proxyConfig.host) return null;

    try {
        const { protocol, host, port, auth } = proxyConfig;

        // Build proxy URL
        let proxyUrl = `${protocol}://`;
        if (auth) {
            proxyUrl += `${encodeURIComponent(auth.username)}:${encodeURIComponent(auth.password)}@`;
        }
        proxyUrl += `${host}:${port}`;

        logger.info(`Creating ${protocol} proxy agent: ${host}:${port}`);

        if (protocol === 'socks5' || protocol === 'socks4') {
            return new SocksProxyAgent(proxyUrl);
        } else if (protocol === 'https') {
            return new HttpsProxyAgent(proxyUrl);
        } else {
            return new HttpProxyAgent(proxyUrl);
        }
    } catch (err) {
        logger.error(`Failed to create proxy agent: ${err.message}`);
        return null;
    }
}

/**
 * Test proxy connectivity
 */
async function testProxy(proxyConfig) {
    if (!proxyConfig) return { success: false, message: 'No proxy configured' };

    try {
        const agent = createAgent(proxyConfig);
        if (!agent) return { success: false, message: 'Failed to create proxy agent' };

        const https = require('https');
        const http = require('http');

        return new Promise((resolve) => {
            const startTime = Date.now();
            const options = {
                hostname: 'web.whatsapp.com',
                port: 443,
                path: '/',
                method: 'HEAD',
                agent,
                timeout: 10000,
            };

            const req = https.request(options, (res) => {
                const latency = Date.now() - startTime;
                resolve({
                    success: true,
                    statusCode: res.statusCode,
                    latency: `${latency}ms`,
                    message: `Proxy working (${latency}ms latency)`,
                });
            });

            req.on('error', (err) => {
                resolve({
                    success: false,
                    message: `Proxy connection failed: ${err.message}`,
                });
            });

            req.on('timeout', () => {
                req.destroy();
                resolve({
                    success: false,
                    message: 'Proxy connection timed out (10s)',
                });
            });

            req.end();
        });
    } catch (err) {
        return { success: false, message: err.message };
    }
}

module.exports = {
    parseProxy,
    createAgent,
    testProxy,
};
