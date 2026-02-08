/**
 * Simple logger for the WhatsApp service
 */

const LOG_LEVELS = { fatal: 0, error: 1, warn: 2, info: 3, debug: 4, trace: 5 };
const currentLevel = LOG_LEVELS[process.env.LOG_LEVEL || 'info'] ?? 3;

function timestamp() {
    return new Date().toISOString().replace('T', ' ').substring(0, 19);
}

const logger = {
    fatal: (...args) => currentLevel >= 0 && console.error(`[${timestamp()}] [FATAL]`, ...args),
    error: (...args) => currentLevel >= 1 && console.error(`[${timestamp()}] [ERROR]`, ...args),
    warn:  (...args) => currentLevel >= 2 && console.warn(`[${timestamp()}] [WARN]`, ...args),
    info:  (...args) => currentLevel >= 3 && console.log(`[${timestamp()}] [INFO]`, ...args),
    debug: (...args) => currentLevel >= 4 && console.log(`[${timestamp()}] [DEBUG]`, ...args),
    trace: (...args) => currentLevel >= 5 && console.log(`[${timestamp()}] [TRACE]`, ...args),

    // Pino-compatible silent logger for Baileys
    pinoLogger: {
        level: process.env.LOG_LEVEL || 'info',
        fatal: () => {},
        error: () => {},
        warn: () => {},
        info: () => {},
        debug: () => {},
        trace: () => {},
        child: () => logger.pinoLogger,
    }
};

module.exports = logger;
