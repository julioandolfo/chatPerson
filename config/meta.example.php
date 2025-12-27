<?php
/**
 * Configuração EXEMPLO das APIs da Meta (Instagram Graph API + WhatsApp Cloud API)
 * 
 * COPIE ESTE ARQUIVO PARA config/meta.php E PREENCHA COM SUAS CREDENCIAIS
 * 
 * Para obter as credenciais:
 * 1. Acesse: https://developers.facebook.com/apps/
 * 2. Crie um app ou selecione um existente
 * 3. Adicione produtos: Instagram + WhatsApp
 * 4. Em Configurações > Básico, copie App ID e App Secret
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Meta App Credentials
    |--------------------------------------------------------------------------
    |
    | App ID e App Secret do seu aplicativo Meta
    | Obtido em: Meta for Developers > Seu App > Configurações > Básico
    |
    */
    'app_id' => 'SEU_APP_ID_AQUI', // Ex: 123456789012345
    'app_secret' => 'SEU_APP_SECRET_AQUI', // Ex: abc123def456ghi789...
    
    /*
    |--------------------------------------------------------------------------
    | Instagram Graph API
    |--------------------------------------------------------------------------
    */
    'instagram' => [
        'enabled' => true,
        'api_version' => 'v21.0',
        'base_url' => 'https://graph.instagram.com',
        
        // Permissões necessárias (Instagram + Facebook Login)
        // Usando apenas permissões BÁSICAS e VÁLIDAS
        // Documentação: https://developers.facebook.com/docs/facebook-login/permissions
        'scopes' => [
            'pages_show_list',              // ✅ Listar páginas conectadas
            'pages_manage_metadata',        // ✅ Gerenciar metadata das páginas
            'pages_messaging',              // ✅ Enviar/receber mensagens (substitui instagram_manage_messages)
            'instagram_manage_comments',    // ✅ Gerenciar comentários em posts
            'instagram_content_publish',    // ✅ Publicar conteúdo no Instagram (opcional)
        ],
        
        // Endpoints
        'oauth_url' => 'https://www.instagram.com/oauth/authorize',
        'token_url' => 'https://graph.instagram.com/oauth/access_token',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    */
    'whatsapp' => [
        'enabled' => true,
        'api_version' => 'v21.0',
        'base_url' => 'https://graph.facebook.com',
        
        // Permissões necessárias
        'scopes' => [
            'whatsapp_business_management',
            'whatsapp_business_messaging',
        ],
        
        // Configurações
        'webhook_verify_token' => 'SEU_TOKEN_VERIFICACAO_AQUI', // Gere com: openssl rand -hex 32
    ],
    
    /*
    |--------------------------------------------------------------------------
    | OAuth 2.0
    |--------------------------------------------------------------------------
    */
    'oauth' => [
        'redirect_uri' => 'https://SEUDOMINIO.com/integrations/meta/oauth/callback',
        'state_lifetime' => 600, // 10 minutos
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'endpoint' => '/webhooks/meta',
        'verify_token' => 'SEU_TOKEN_VERIFICACAO_AQUI', // O mesmo acima
        
        // Eventos que queremos receber
        'subscribed_fields' => [
            // Instagram
            'messages',
            'message_reactions',
            'messaging_seen',
            
            // WhatsApp
            'messages',
            'message_status',
            'messaging_postbacks',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Limites da API da Meta:
    | - Instagram: 200 requests/hora por usuário
    | - WhatsApp: 80 mensagens/segundo (tier TIER_1K)
    |
    */
    'rate_limits' => [
        'instagram' => [
            'requests_per_hour' => 200,
            'burst_limit' => 50, // Quantas requests seguidas
        ],
        'whatsapp' => [
            'messages_per_second' => 80,
            'burst_limit' => 20,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logs
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'file' => 'meta.log', // storage/logs/meta.log
        'level' => 'DEBUG', // DEBUG, INFO, WARNING, ERROR
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hora
        'prefix' => 'meta_',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Retry Policy
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => 3,
        'backoff_multiplier' => 2, // Espera exponencial (2s, 4s, 8s)
        'initial_delay' => 2000, // ms
    ],
];

