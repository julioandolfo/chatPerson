/**
 * PM2 Ecosystem Configuration
 * 
 * Uso:
 *   pm2 start ecosystem.config.js
 *   pm2 stop whatsapp-service
 *   pm2 restart whatsapp-service
 *   pm2 logs whatsapp-service
 *   pm2 monit
 * 
 * Para iniciar automaticamente no boot:
 *   pm2 startup
 *   pm2 save
 */

module.exports = {
    apps: [
        {
            name: 'whatsapp-service',
            script: 'src/index.js',
            cwd: __dirname,
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: '2G',
            env: {
                NODE_ENV: 'production',
                PORT: 3100,
                HOST: '127.0.0.1',
            },
            // Log configuration
            error_file: './logs/error.log',
            out_file: './logs/output.log',
            merge_logs: true,
            log_date_format: 'YYYY-MM-DD HH:mm:ss',
            // Restart policy
            exp_backoff_restart_delay: 1000,
            max_restarts: 10,
            restart_delay: 3000,
            // Graceful shutdown
            kill_timeout: 10000,
            listen_timeout: 5000,
        },
    ],
};
