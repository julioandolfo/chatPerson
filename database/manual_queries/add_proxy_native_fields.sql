-- ======================================================
-- Adicionar campos de proxy e native session
-- Para suportar provider "native" (Baileys) com proxies
-- ======================================================

ALTER TABLE integration_accounts ADD COLUMN IF NOT EXISTS proxy_host VARCHAR(500) NULL AFTER config;
ALTER TABLE integration_accounts ADD COLUMN IF NOT EXISTS proxy_user VARCHAR(255) NULL AFTER proxy_host;
ALTER TABLE integration_accounts ADD COLUMN IF NOT EXISTS proxy_pass VARCHAR(255) NULL AFTER proxy_user;
ALTER TABLE integration_accounts ADD COLUMN IF NOT EXISTS native_session_id VARCHAR(255) NULL AFTER proxy_pass;
ALTER TABLE integration_accounts ADD COLUMN IF NOT EXISTS native_service_url VARCHAR(500) NULL DEFAULT 'http://127.0.0.1:3100' AFTER native_session_id;
