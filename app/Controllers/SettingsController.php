<?php
/**
 * Controller de Configurações
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\SettingService;
use App\Services\ConversationSettingsService;

class SettingsController
{
    /**
     * Mostrar configurações
     */
    public function index(): void
    {
        // Verificar permissão específica para aba do Assistente IA
        $tab = Request::get('tab', 'general');
        if ($tab === 'ai-assistant') {
            Permission::abortIfCannot('ai_assistant.configure');
        } else {
            Permission::abortIfCannot('admin.settings');
        }
        
        $tab = Request::get('tab', 'general');
        
        $generalSettings = SettingService::getDefaultGeneralSettings();
        $emailSettings = SettingService::getDefaultEmailSettings();
        $whatsappSettings = SettingService::getDefaultWhatsAppSettings();
        $securitySettings = SettingService::getDefaultSecuritySettings();
        $websocketSettings = SettingService::getDefaultWebSocketSettings();
        $conversationSettings = ConversationSettingsService::getSettings();
        
        // Obter dados para preencher selects
        $users = \App\Helpers\Database::fetchAll(
            "SELECT id, name FROM users WHERE status = 'active' AND role IN ('agent', 'admin', 'supervisor') ORDER BY name ASC"
        );
        $departments = \App\Models\Department::all();
        $funnels = \App\Models\Funnel::where('status', '=', 'active');
        $tags = \App\Services\TagService::getAll();
        
        // Dados para aba Assistente IA
        $aiAssistantFeatures = \App\Services\AIAssistantFeatureService::listAll();
        $aiAgents = \App\Models\AIAgent::getAvailableAgents();
        
        // Obter todos os estágios de todos os funis para condições
        $allStages = [];
        foreach ($funnels as $funnel) {
            $stages = \App\Models\Funnel::getStages($funnel['id']);
            foreach ($stages as $stage) {
                $allStages[] = [
                    'id' => $stage['id'],
                    'name' => $stage['name'],
                    'funnel_id' => $funnel['id'],
                    'funnel_name' => $funnel['name']
                ];
            }
        }
        
        Response::view('settings/index', [
            'tab' => $tab,
            'generalSettings' => $generalSettings,
            'emailSettings' => $emailSettings,
            'whatsappSettings' => $whatsappSettings,
            'securitySettings' => $securitySettings,
            'websocketSettings' => $websocketSettings,
            'conversationSettings' => $conversationSettings,
            'users' => $users,
            'departments' => $departments,
            'funnels' => $funnels,
            'allStages' => $allStages,
            'aiAssistantFeatures' => $aiAssistantFeatures ?? [],
            'aiAgents' => $aiAgents ?? []
        ]);
    }

    /**
     * Upload de logo
     */
    public function uploadLogo(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('Nenhum arquivo enviado ou erro no upload');
            }
            
            $file = $_FILES['logo'];
            
            // Validar tamanho (2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new \Exception('Arquivo muito grande. Tamanho máximo: 2MB');
            }
            
            // Validar tipo
            $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/gif', 'image/webp'];
            $mimeType = mime_content_type($file['tmp_name']);
            if (!in_array($mimeType, $allowedTypes)) {
                throw new \Exception('Tipo de arquivo não permitido. Use PNG, JPG, SVG, GIF ou WEBP');
            }
            
            // Criar diretório se não existir
            $logoDir = __DIR__ . '/../../public/assets/media/logos/custom/';
            if (!is_dir($logoDir)) {
                mkdir($logoDir, 0755, true);
            }
            
            // Obter extensão
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (empty($extension)) {
                // Tentar detectar pela extensão do arquivo
                $extension = match($mimeType) {
                    'image/png' => 'png',
                    'image/jpeg', 'image/jpg' => 'jpg',
                    'image/svg+xml' => 'svg',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                    default => 'png'
                };
            }
            
            // Gerar nome único
            $filename = 'logo_' . time() . '.' . $extension;
            $filepath = $logoDir . $filename;
            
            // Remover logo antiga se existir
            $oldLogo = SettingService::get('app_logo', '');
            if (!empty($oldLogo) && file_exists(__DIR__ . '/../../public/' . $oldLogo)) {
                @unlink(__DIR__ . '/../../public/' . $oldLogo);
            }
            
            // Mover arquivo
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new \Exception('Erro ao salvar arquivo');
            }
            
            // Salvar caminho nas configurações
            $logoPath = 'assets/media/logos/custom/' . $filename;
            SettingService::set('app_logo', $logoPath, 'string', 'general');
            
            Response::json([
                'success' => true,
                'message' => 'Logo enviada com sucesso!',
                'logo_path' => $logoPath,
                'logo_url' => \App\Helpers\Url::to($logoPath)
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Remover logo
     */
    public function removeLogo(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $logoPath = SettingService::get('app_logo', '');
            
            if (!empty($logoPath)) {
                $fullPath = __DIR__ . '/../../public/' . $logoPath;
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
            
            SettingService::set('app_logo', '', 'string', 'general');
            
            Response::json([
                'success' => true,
                'message' => 'Logo removida com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao remover logo: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Salvar configurações gerais
     */
    public function saveGeneral(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $data = Request::post();
            
            SettingService::set('app_name', $data['app_name'] ?? '', 'string', 'general');
            SettingService::set('app_timezone', $data['app_timezone'] ?? 'America/Sao_Paulo', 'string', 'general');
            SettingService::set('app_locale', $data['app_locale'] ?? 'pt_BR', 'string', 'general');
            SettingService::set('max_conversations_per_agent', (int)($data['max_conversations_per_agent'] ?? 10), 'integer', 'general');
            SettingService::set('auto_assign_conversations', isset($data['auto_assign_conversations']), 'boolean', 'general');
            SettingService::set('conversation_timeout_minutes', (int)($data['conversation_timeout_minutes'] ?? 30), 'integer', 'general');
            SettingService::set('openai_api_key', $data['openai_api_key'] ?? '', 'string', 'general');
            
            Response::json([
                'success' => true,
                'message' => 'Configurações gerais salvas com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salvar configurações de email
     */
    public function saveEmail(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $data = Request::post();
            
            SettingService::set('email_enabled', isset($data['email_enabled']), 'boolean', 'email');
            SettingService::set('email_host', $data['email_host'] ?? '', 'string', 'email');
            SettingService::set('email_port', (int)($data['email_port'] ?? 587), 'integer', 'email');
            SettingService::set('email_username', $data['email_username'] ?? '', 'string', 'email');
            SettingService::set('email_password', $data['email_password'] ?? '', 'string', 'email');
            SettingService::set('email_encryption', $data['email_encryption'] ?? 'tls', 'string', 'email');
            SettingService::set('email_from_address', $data['email_from_address'] ?? '', 'string', 'email');
            SettingService::set('email_from_name', $data['email_from_name'] ?? '', 'string', 'email');
            
            Response::json([
                'success' => true,
                'message' => 'Configurações de email salvas com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salvar configurações de WhatsApp
     */
    public function saveWhatsApp(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $data = Request::post();
            
            SettingService::set('whatsapp_provider', $data['whatsapp_provider'] ?? 'quepasa', 'string', 'whatsapp');
            SettingService::set('whatsapp_quepasa_url', $data['whatsapp_quepasa_url'] ?? '', 'string', 'whatsapp');
            SettingService::set('whatsapp_quepasa_token', $data['whatsapp_quepasa_token'] ?? '', 'string', 'whatsapp');
            SettingService::set('whatsapp_evolution_url', $data['whatsapp_evolution_url'] ?? '', 'string', 'whatsapp');
            SettingService::set('whatsapp_evolution_api_key', $data['whatsapp_evolution_api_key'] ?? '', 'string', 'whatsapp');
            SettingService::set('whatsapp_webhook_url', $data['whatsapp_webhook_url'] ?? '', 'string', 'whatsapp');
            SettingService::set('whatsapp_allow_group_messages', isset($data['whatsapp_allow_group_messages']), 'boolean', 'whatsapp');
            
            Response::json([
                'success' => true,
                'message' => 'Configurações de WhatsApp salvas com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salvar configurações de segurança
     */
    public function saveSecurity(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $data = Request::post();
            
            SettingService::set('password_min_length', (int)($data['password_min_length'] ?? 6), 'integer', 'security');
            SettingService::set('password_require_uppercase', isset($data['password_require_uppercase']), 'boolean', 'security');
            SettingService::set('password_require_lowercase', isset($data['password_require_lowercase']), 'boolean', 'security');
            SettingService::set('password_require_numbers', isset($data['password_require_numbers']), 'boolean', 'security');
            SettingService::set('password_require_symbols', isset($data['password_require_symbols']), 'boolean', 'security');
            SettingService::set('session_lifetime', (int)($data['session_lifetime'] ?? 120), 'integer', 'security');
            SettingService::set('max_login_attempts', (int)($data['max_login_attempts'] ?? 5), 'integer', 'security');
            SettingService::set('lockout_duration', (int)($data['lockout_duration'] ?? 15), 'integer', 'security');
            
            Response::json([
                'success' => true,
                'message' => 'Configurações de segurança salvas com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salvar configurações de WebSocket/Tempo Real
     */
    public function saveWebSocket(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $data = Request::post();
            
            SettingService::set('websocket_enabled', isset($data['websocket_enabled']), 'boolean', 'websocket');
            SettingService::set('websocket_connection_type', $data['websocket_connection_type'] ?? 'auto', 'string', 'websocket');
            SettingService::set('websocket_port', (int)($data['websocket_port'] ?? 8080), 'integer', 'websocket');
            SettingService::set('websocket_path', $data['websocket_path'] ?? '/ws', 'string', 'websocket');
            SettingService::set('websocket_custom_url', $data['websocket_custom_url'] ?? '', 'string', 'websocket');
            SettingService::set('websocket_polling_interval', (int)($data['websocket_polling_interval'] ?? 3000), 'integer', 'websocket');
            
            Response::json([
                'success' => true,
                'message' => 'Configurações de tempo real salvas com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salvar configurações de conversas
     */
    public function saveConversations(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $data = Request::post();
            
            // Salvar configuração de nome do agente no chat
            SettingService::set('chat_agent_name_enabled', isset($data['chat_agent_name_enabled']), 'boolean', 'general');
            
            // Processar dados do formulário e converter para estrutura esperada
            $settings = [
                'global_limits' => [
                    'max_conversations_per_agent' => !empty($data['max_conversations_per_agent']) ? (int)$data['max_conversations_per_agent'] : null,
                    'max_conversations_per_department' => !empty($data['max_conversations_per_department']) ? (int)$data['max_conversations_per_department'] : null,
                    'max_conversations_per_funnel' => !empty($data['max_conversations_per_funnel']) ? (int)$data['max_conversations_per_funnel'] : null,
                    'max_conversations_per_stage' => !empty($data['max_conversations_per_stage']) ? (int)$data['max_conversations_per_stage'] : null,
                ],
                'sla' => [
                    'first_response_time' => (int)($data['sla_first_response_time'] ?? 15),
                    'resolution_time' => (int)($data['sla_resolution_time'] ?? 60),
                    'enable_sla_monitoring' => isset($data['enable_sla_monitoring']),
                    'auto_reassign_on_sla_breach' => isset($data['auto_reassign_on_sla_breach']),
                    'reassign_after_minutes' => (int)($data['reassign_after_minutes'] ?? 30),
                ],
                'distribution' => [
                    'method' => $data['distribution_method'] ?? 'round_robin',
                    'enable_auto_assignment' => isset($data['enable_auto_assignment']),
                    'assign_to_ai_agent' => isset($data['assign_to_ai_agent']),
                    'consider_availability' => isset($data['consider_availability']),
                    'consider_max_conversations' => isset($data['consider_max_conversations']),
                ],
                'percentage_distribution' => [
                    'enabled' => isset($data['percentage_distribution_enabled']),
                    'rules' => json_decode($data['percentage_distribution_rules'] ?? '[]', true) ?: [],
                ],
                'reassignment' => [
                    'enable_auto_reassignment' => isset($data['enable_auto_reassignment']),
                    'reassign_on_inactivity_minutes' => (int)($data['reassign_on_inactivity_minutes'] ?? 60),
                    'reassign_on_sla_breach' => isset($data['reassign_on_sla_breach']),
                    'reassign_on_agent_offline' => isset($data['reassign_on_agent_offline']),
                    'max_reassignments' => (int)($data['max_reassignments'] ?? 3),
                ],
                'contact_agents' => [
                    'auto_set_primary_agent_on_first_assignment' => isset($data['auto_set_primary_agent_on_first_assignment']),
                    'auto_assign_on_reopen' => isset($data['auto_assign_on_reopen']) ? (bool)$data['auto_assign_on_reopen'] : true,
                ],
                'sentiment_analysis' => [
                    'enabled' => isset($data['sentiment_analysis_enabled']),
                    'model' => $data['sentiment_analysis_model'] ?? 'gpt-3.5-turbo',
                    'temperature' => isset($data['sentiment_analysis_temperature']) ? (float)$data['sentiment_analysis_temperature'] : 0.3,
                    'check_interval_hours' => isset($data['sentiment_check_interval_hours']) ? (int)$data['sentiment_check_interval_hours'] : 5,
                    'max_conversation_age_days' => isset($data['sentiment_max_conversation_age_days']) ? (int)$data['sentiment_max_conversation_age_days'] : 30,
                    'analyze_on_new_message' => isset($data['sentiment_analyze_on_new_message']),
                    'analyze_on_message_count' => isset($data['sentiment_analyze_on_message_count']) ? (int)$data['sentiment_analyze_on_message_count'] : 5,
                    'min_messages_to_analyze' => isset($data['sentiment_min_messages_to_analyze']) ? (int)$data['sentiment_min_messages_to_analyze'] : 3,
                    'analyze_last_messages' => !empty($data['sentiment_analyze_last_messages']) ? (int)$data['sentiment_analyze_last_messages'] : null,
                    'include_emotions' => isset($data['sentiment_include_emotions']),
                    'include_urgency' => isset($data['sentiment_include_urgency']),
                    'auto_tag_negative' => isset($data['sentiment_auto_tag_negative']),
                    'negative_tag_id' => !empty($data['sentiment_negative_tag_id']) ? (int)$data['sentiment_negative_tag_id'] : null,
                    'cost_limit_per_day' => isset($data['sentiment_cost_limit_per_day']) ? (float)$data['sentiment_cost_limit_per_day'] : 5.00,
                ],
            ];
            
            if (ConversationSettingsService::saveSettings($settings)) {
                Response::json([
                    'success' => true,
                    'message' => 'Configurações de conversas salvas com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao salvar configurações'
                ], 500);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
            ], 500);
        }
    }
}
