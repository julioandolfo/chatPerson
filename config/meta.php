<?php
/**
 * Configuração das APIs da Meta (Instagram Graph API + WhatsApp Cloud API)
 * 
 * IMPORTANTE: Preencher com as credenciais do App Meta criado em:
 * https://developers.facebook.com/apps/
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
    'app_id' => getenv('META_APP_ID') ?: '',
    'app_secret' => getenv('META_APP_SECRET') ?: '',
    
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
        // APENAS permissões TESTADAS e VÁLIDAS para Chat/Mensagens
        // Documentação: https://developers.facebook.com/docs/facebook-login/permissions
        'scopes' => [
            'pages_show_list',              // ✅ Listar páginas conectadas
            'pages_manage_metadata',        // ✅ Gerenciar metadata das páginas
            'pages_messaging',              // ✅ Enviar/receber mensagens Instagram Direct
            'pages_read_engagement',        // ✅ Ler engajamento e acessar Instagram Business Account vinculado
            'instagram_manage_comments',    // ✅ Gerenciar comentários em posts
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
        'webhook_verify_token' => getenv('META_WEBHOOK_VERIFY_TOKEN') ?: 'seu_token_verificacao_aqui_' . bin2hex(random_bytes(16)),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | OAuth 2.0
    |--------------------------------------------------------------------------
    */
    'oauth' => [
        'redirect_uri' => getenv('APP_URL') . '/integrations/meta/oauth/callback',
        'state_lifetime' => 600, // 10 minutos
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'endpoint' => '/webhooks/meta',
        'verify_token' => getenv('META_WEBHOOK_VERIFY_TOKEN') ?: 'seu_token_verificacao_aqui',
        
        // Eventos que queremos receber
        'subscribed_fields' => [
            // Instagram
            'messages',
            'message_reactions',
            'messaging_seen',
            
            // WhatsApp - Mensagens
            'messages',
            'message_status',
            'messaging_postbacks',
            
            // WhatsApp - CoEx (Coexistence)
            'smb_message_echoes',          // Eco de mensagens enviadas pelo app WhatsApp Business
            'smb_app_state_sync',          // Sincronização de estado (leituras, etc.) entre app e API
            'business_capability_update',  // Atualização de capacidades quando CoEx é ativado
            'account_update',              // Notificação quando Embedded Signup é concluído
            'history',                     // Importação de histórico de conversas (até 6 meses)
            
            // WhatsApp - Templates
            'message_template_status_update',   // Mudanças de status de templates
            'message_template_quality_update',  // Mudanças de qualidade de templates
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
        'level' => getenv('APP_DEBUG') ? 'DEBUG' : 'INFO',
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

