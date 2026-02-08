/**
 * Simple .env loader (no external dependency)
 */
const fs = require('fs');
const path = require('path');

const envPath = path.join(__dirname, '..', '.env');

if (fs.existsSync(envPath)) {
    const content = fs.readFileSync(envPath, 'utf-8');
    content.split('\n').forEach(line => {
        line = line.trim();
        if (!line || line.startsWith('#')) return;
        const eqIndex = line.indexOf('=');
        if (eqIndex === -1) return;
        const key = line.substring(0, eqIndex).trim();
        const value = line.substring(eqIndex + 1).trim();
        if (!process.env[key]) {
            process.env[key] = value;
        }
    });
}
