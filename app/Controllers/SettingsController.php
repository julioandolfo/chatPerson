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
use App\Services\PostgreSQLSettingsService;

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
        $aiSettings = SettingService::getDefaultAISettings();
        $availabilitySettings = \App\Services\AvailabilityService::getSettings();
        $businessHoursSettings = \App\Services\AvailabilityService::getBusinessHoursSettings();
        $postgresSettings = PostgreSQLSettingsService::getSettings();
        
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
            'aiSettings' => $aiSettings,
            'users' => $users,
            'departments' => $departments,
            'funnels' => $funnels,
            'allStages' => $allStages,
            'tags' => $tags,
            'aiAssistantFeatures' => $aiAssistantFeatures ?? [],
            'aiAgents' => $aiAgents ?? [],
            'availabilitySettings' => $availabilitySettings,
            'businessHoursSettings' => $businessHoursSettings,
            'postgresSettings' => $postgresSettings
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
                switch ($mimeType) {
                    case 'image/png':
                        $extension = 'png';
                        break;
                    case 'image/jpeg':
                    case 'image/jpg':
                        $extension = 'jpg';
                        break;
                    case 'image/svg+xml':
                        $extension = 'svg';
                        break;
                    case 'image/gif':
                        $extension = 'gif';
                        break;
                    case 'image/webp':
                        $extension = 'webp';
                        break;
                    default:
                        $extension = 'png';
                        break;
                }
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
     * Upload de favicon
     */
    public function uploadFavicon(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            if (!isset($_FILES['favicon']) || $_FILES['favicon']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('Nenhum arquivo enviado ou erro no upload');
            }
            
            $file = $_FILES['favicon'];
            
            // Validar tamanho (500KB)
            if ($file['size'] > 500 * 1024) {
                throw new \Exception('Arquivo muito grande. Tamanho máximo: 500KB');
            }
            
            // Validar tipo
            $allowedTypes = ['image/x-icon', 'image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
            $mimeType = mime_content_type($file['tmp_name']);
            if (!in_array($mimeType, $allowedTypes)) {
                throw new \Exception('Tipo de arquivo não permitido. Use ICO, PNG, JPG ou SVG');
            }
            
            // Criar diretório se não existir
            $faviconDir = __DIR__ . '/../../public/assets/media/logos/custom/';
            if (!is_dir($faviconDir)) {
                mkdir($faviconDir, 0755, true);
            }
            
            // Obter extensão
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (empty($extension)) {
                // Tentar detectar pela extensão do arquivo
                switch ($mimeType) {
                    case 'image/x-icon':
                        $extension = 'ico';
                        break;
                    case 'image/png':
                        $extension = 'png';
                        break;
                    case 'image/jpeg':
                    case 'image/jpg':
                        $extension = 'jpg';
                        break;
                    case 'image/svg+xml':
                        $extension = 'svg';
                        break;
                    default:
                        $extension = 'ico';
                        break;
                }
            }
            
            // Gerar nome único
            $filename = 'favicon_' . time() . '.' . $extension;
            $filepath = $faviconDir . $filename;
            
            // Remover favicon antigo se existir
            $oldFavicon = SettingService::get('app_favicon', '');
            if (!empty($oldFavicon) && file_exists(__DIR__ . '/../../public/' . $oldFavicon)) {
                @unlink(__DIR__ . '/../../public/' . $oldFavicon);
            }
            
            // Mover arquivo
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new \Exception('Erro ao salvar arquivo');
            }
            
            // Salvar caminho nas configurações
            $faviconPath = 'assets/media/logos/custom/' . $filename;
            SettingService::set('app_favicon', $faviconPath, 'string', 'general');
            
            Response::json([
                'success' => true,
                'message' => 'Favicon enviado com sucesso!',
                'favicon_path' => $faviconPath,
                'favicon_url' => \App\Helpers\Url::to($faviconPath)
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Remover favicon
     */
    public function removeFavicon(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $faviconPath = SettingService::get('app_favicon', '');
            
            if (!empty($faviconPath)) {
                $fullPath = __DIR__ . '/../../public/' . $faviconPath;
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
            
            SettingService::set('app_favicon', '', 'string', 'general');
            
            Response::json([
                'success' => true,
                'message' => 'Favicon removido com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao remover favicon: ' . $e->getMessage()
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
            SettingService::set('elevenlabs_api_key', $data['elevenlabs_api_key'] ?? '', 'string', 'general');
            
            Response::successOrRedirect(
                'Configurações gerais salvas com sucesso!',
                '/settings?tab=general'
            );
        } catch (\Exception $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
                ], 500);
            } else {
                Response::redirect('/settings?tab=general&error=' . urlencode('Erro ao salvar configurações: ' . $e->getMessage()));
            }
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
            
            Response::successOrRedirect(
                'Configurações de email salvas com sucesso!',
                '/settings?tab=email'
            );
        } catch (\Exception $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
                ], 500);
            } else {
                Response::redirect('/settings?tab=email&error=' . urlencode('Erro ao salvar configurações: ' . $e->getMessage()));
            }
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
            
            Response::successOrRedirect(
                'Configurações de WhatsApp salvas com sucesso!',
                '/settings?tab=whatsapp'
            );
        } catch (\Exception $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
                ], 500);
            } else {
                Response::redirect('/settings?tab=whatsapp&error=' . urlencode('Erro ao salvar configurações: ' . $e->getMessage()));
            }
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
            
            Response::successOrRedirect(
                'Configurações de segurança salvas com sucesso!',
                '/settings?tab=security'
            );
        } catch (\Exception $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
                ], 500);
            } else {
                Response::redirect('/settings?tab=security&error=' . urlencode('Erro ao salvar configurações: ' . $e->getMessage()));
            }
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
            
            Response::successOrRedirect(
                'Configurações de tempo real salvas com sucesso!',
                '/settings?tab=websocket'
            );
        } catch (\Exception $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
                ], 500);
            } else {
                Response::redirect('/settings?tab=websocket&error=' . urlencode('Erro ao salvar configurações: ' . $e->getMessage()));
            }
        }
    }

    /**
     * Salvar configurações de IA/Fallback
     */
    public function saveAI(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $data = Request::post();
            
            SettingService::set('ai_fallback_enabled', isset($data['ai_fallback_enabled']), 'boolean', 'ai');
            SettingService::set('ai_fallback_check_interval_minutes', (int)($data['ai_fallback_check_interval_minutes'] ?? 15), 'integer', 'ai');
            SettingService::set('ai_fallback_min_delay_minutes', (int)($data['ai_fallback_min_delay_minutes'] ?? 5), 'integer', 'ai');
            SettingService::set('ai_fallback_max_delay_hours', (int)($data['ai_fallback_max_delay_hours'] ?? 24), 'integer', 'ai');
            SettingService::set('ai_fallback_max_retries', (int)($data['ai_fallback_max_retries'] ?? 3), 'integer', 'ai');
            SettingService::set('ai_fallback_escalate_after_hours', (int)($data['ai_fallback_escalate_after_hours'] ?? 2), 'integer', 'ai');
            SettingService::set('ai_fallback_detect_closing_messages', isset($data['ai_fallback_detect_closing_messages']), 'boolean', 'ai');
            SettingService::set('ai_fallback_use_ai_for_closing_detection', isset($data['ai_fallback_use_ai_for_closing_detection']), 'boolean', 'ai');
            
            Response::successOrRedirect(
                'Configurações de IA salvas com sucesso!',
                '/settings?tab=ai'
            );
        } catch (\Exception $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
                ], 500);
            } else {
                Response::redirect('/settings?tab=ai&error=' . urlencode('Erro ao salvar configurações: ' . $e->getMessage()));
            }
        }
    }

    /**
     * Salvar configurações de disponibilidade
     */
    public function saveAvailability(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $data = Request::post();
            
            // Configurações de disponibilidade
            SettingService::set('availability.auto_online_on_login', isset($data['auto_online_on_login']), 'boolean', 'availability');
            SettingService::set('availability.auto_offline_on_logout', isset($data['auto_offline_on_logout']), 'boolean', 'availability');
            SettingService::set('availability.auto_away_enabled', isset($data['auto_away_enabled']), 'boolean', 'availability');
            SettingService::set('availability.away_timeout_minutes', (int)($data['away_timeout_minutes'] ?? 15), 'integer', 'availability');
            SettingService::set('availability.activity_tracking_enabled', isset($data['activity_tracking_enabled']), 'boolean', 'availability');
            SettingService::set('availability.heartbeat_interval_seconds', (int)($data['heartbeat_interval_seconds'] ?? 30), 'integer', 'availability');
            SettingService::set('availability.offline_timeout_minutes', (int)($data['offline_timeout_minutes'] ?? 5), 'integer', 'availability');
            SettingService::set('availability.track_mouse_movement', isset($data['track_mouse_movement']), 'boolean', 'availability');
            SettingService::set('availability.track_keyboard', isset($data['track_keyboard']), 'boolean', 'availability');
            SettingService::set('availability.track_page_visibility', isset($data['track_page_visibility']), 'boolean', 'availability');
            
            // Configurações de horário comercial
            SettingService::set('business_hours.enabled', isset($data['business_hours_enabled']), 'boolean', 'business_hours');
            SettingService::set('business_hours.timezone', $data['business_hours_timezone'] ?? 'America/Sao_Paulo', 'string', 'business_hours');
            
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            foreach ($days as $day) {
                SettingService::set("business_hours.{$day}_start", $data["{$day}_start"] ?? '', 'string', 'business_hours');
                SettingService::set("business_hours.{$day}_end", $data["{$day}_end"] ?? '', 'string', 'business_hours');
            }
            
            Response::successOrRedirect(
                'Configurações de disponibilidade salvas com sucesso!',
                '/settings?tab=availability'
            );
        } catch (\Exception $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
                ], 500);
            } else {
                Response::redirect('/settings?tab=availability&error=' . urlencode('Erro ao salvar configurações: ' . $e->getMessage()));
            }
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
                    'enable_resolution_sla' => isset($data['enable_resolution_sla']),
                    'ongoing_response_time' => (int)($data['sla_ongoing_response_time'] ?? 15),
                    'working_hours_enabled' => isset($data['sla_working_hours_enabled']),
                    'working_hours_start' => $data['sla_working_hours_start'] ?? '08:00',
                    'working_hours_end' => $data['sla_working_hours_end'] ?? '18:00',
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
                'realtime_coaching' => [
                    'enabled' => isset($data['realtime_coaching']['enabled']),
                    'model' => $data['realtime_coaching']['model'] ?? 'gpt-3.5-turbo',
                    'temperature' => isset($data['realtime_coaching']['temperature']) ? (float)$data['realtime_coaching']['temperature'] : 0.5,
                    'max_analyses_per_minute' => isset($data['realtime_coaching']['max_analyses_per_minute']) ? (int)$data['realtime_coaching']['max_analyses_per_minute'] : 10,
                    'min_interval_between_analyses' => isset($data['realtime_coaching']['min_interval_between_analyses']) ? (int)$data['realtime_coaching']['min_interval_between_analyses'] : 10,
                    'use_queue' => isset($data['realtime_coaching']['use_queue']),
                    'queue_processing_delay' => isset($data['realtime_coaching']['queue_processing_delay']) ? (int)$data['realtime_coaching']['queue_processing_delay'] : 3,
                    'max_queue_size' => isset($data['realtime_coaching']['max_queue_size']) ? (int)$data['realtime_coaching']['max_queue_size'] : 100,
                    'analyze_only_client_messages' => isset($data['realtime_coaching']['analyze_only_client_messages']),
                    'min_message_length' => isset($data['realtime_coaching']['min_message_length']) ? (int)$data['realtime_coaching']['min_message_length'] : 10,
                    'skip_if_agent_typing' => isset($data['realtime_coaching']['skip_if_agent_typing']),
                    'use_cache' => isset($data['realtime_coaching']['use_cache']),
                    'cache_ttl_minutes' => isset($data['realtime_coaching']['cache_ttl_minutes']) ? (int)$data['realtime_coaching']['cache_ttl_minutes'] : 60,
                    'cache_similarity_threshold' => isset($data['realtime_coaching']['cache_similarity_threshold']) ? (float)$data['realtime_coaching']['cache_similarity_threshold'] : 0.85,
                    'cost_limit_per_hour' => isset($data['realtime_coaching']['cost_limit_per_hour']) ? (float)$data['realtime_coaching']['cost_limit_per_hour'] : 1.00,
                    'cost_limit_per_day' => isset($data['realtime_coaching']['cost_limit_per_day']) ? (float)$data['realtime_coaching']['cost_limit_per_day'] : 10.00,
                    'hint_types' => isset($data['realtime_coaching']['hint_types']) ? $data['realtime_coaching']['hint_types'] : [],
                    'auto_show_hint' => isset($data['realtime_coaching']['auto_show_hint']),
                    'hint_display_duration' => isset($data['realtime_coaching']['hint_display_duration']) ? (int)$data['realtime_coaching']['hint_display_duration'] : 30,
                    'play_sound' => isset($data['realtime_coaching']['play_sound']),
                ],
                'agent_performance_analysis' => [
                    'enabled' => isset($data['agent_performance_analysis']['enabled']),
                    'model' => $data['agent_performance_analysis']['model'] ?? 'gpt-4-turbo',
                    'temperature' => isset($data['agent_performance_analysis']['temperature']) ? (float)$data['agent_performance_analysis']['temperature'] : 0.3,
                    'check_interval_hours' => isset($data['agent_performance_analysis']['check_interval_hours']) ? (int)$data['agent_performance_analysis']['check_interval_hours'] : 6,
                    'analyze_on_close' => isset($data['agent_performance_analysis']['analyze_on_close']),
                    'min_agent_messages' => isset($data['agent_performance_analysis']['min_agent_messages']) ? (int)$data['agent_performance_analysis']['min_agent_messages'] : 5,
                    'min_conversation_duration' => isset($data['agent_performance_analysis']['min_conversation_duration']) ? (int)$data['agent_performance_analysis']['min_conversation_duration'] : 5,
                    'cost_limit_per_day' => isset($data['agent_performance_analysis']['cost_limit_per_day']) ? (float)$data['agent_performance_analysis']['cost_limit_per_day'] : 10.00,
                    'dimensions' => isset($data['agent_performance_analysis']['dimensions']) ? $data['agent_performance_analysis']['dimensions'] : [],
                    'gamification' => [
                        'enabled' => isset($data['agent_performance_analysis']['gamification']['enabled']),
                        'auto_award_badges' => isset($data['agent_performance_analysis']['gamification']['auto_award_badges']),
                    ],
                    'coaching' => [
                        'enabled' => isset($data['agent_performance_analysis']['coaching']['enabled']),
                        'auto_create_goals' => isset($data['agent_performance_analysis']['coaching']['auto_create_goals']),
                        'goal_threshold' => isset($data['agent_performance_analysis']['coaching']['goal_threshold']) ? (float)$data['agent_performance_analysis']['coaching']['goal_threshold'] : 3.5,
                        'save_best_practices' => isset($data['agent_performance_analysis']['coaching']['save_best_practices']),
                        'min_score_for_best_practice' => isset($data['agent_performance_analysis']['coaching']['min_score_for_best_practice']) ? (float)$data['agent_performance_analysis']['coaching']['min_score_for_best_practice'] : 4.5,
                    ],
                    'best_practices' => [
                        'enabled' => isset($data['agent_performance_analysis']['best_practices']['enabled']),
                        'auto_save' => isset($data['agent_performance_analysis']['best_practices']['auto_save']),
                        'min_score_threshold' => isset($data['agent_performance_analysis']['best_practices']['min_score']) ? (float)$data['agent_performance_analysis']['best_practices']['min_score'] : 4.5,
                    ],
                    'reports' => [
                        'send_weekly_summary' => isset($data['agent_performance_analysis']['reports_send_weekly_summary']),
                        'send_monthly_ranking' => isset($data['agent_performance_analysis']['reports_send_monthly_ranking']),
                    ],
                ],
                'audio_transcription' => [
                    'enabled' => isset($data['audio_transcription_enabled']),
                    'auto_transcribe' => isset($data['audio_transcription_auto_transcribe']),
                    'only_for_ai_agents' => isset($data['audio_transcription_only_for_ai_agents']),
                    'language' => $data['audio_transcription_language'] ?? 'pt',
                    'model' => 'whisper-1',
                    'update_message_content' => isset($data['audio_transcription_update_message_content']),
                    'max_file_size_mb' => isset($data['audio_transcription_max_file_size_mb']) ? (int)$data['audio_transcription_max_file_size_mb'] : 25,
                    'cost_limit_per_day' => isset($data['audio_transcription_cost_limit_per_day']) ? (float)$data['audio_transcription_cost_limit_per_day'] : 10.00,
                ],
                'text_to_speech' => [
                    'enabled' => isset($data['text_to_speech_enabled']),
                    'provider' => $data['text_to_speech_provider'] ?? 'openai',
                    'auto_generate_audio' => isset($data['text_to_speech_auto_generate_audio']),
                    'only_for_ai_agents' => isset($data['text_to_speech_only_for_ai_agents']),
                    'send_mode' => $data['text_to_speech_send_mode'] ?? 'intelligent', // 'text_only', 'audio_only', 'both', 'intelligent'
                    'voice_id' => $data['text_to_speech_provider'] === 'elevenlabs' 
                        ? ($data['text_to_speech_voice_id_elevenlabs'] ?? '21m00Tcm4TlvDq8ikWAM')
                        : ($data['text_to_speech_voice_id_openai'] ?? 'alloy'),
                    'model' => $data['text_to_speech_provider'] === 'elevenlabs'
                        ? ($data['text_to_speech_model_elevenlabs'] ?? 'eleven_multilingual_v2')
                        : ($data['text_to_speech_model_openai'] ?? 'tts-1'),
                    'language' => $data['text_to_speech_language'] ?? 'pt',
                    'speed' => isset($data['text_to_speech_speed']) ? (float)$data['text_to_speech_speed'] : 1.0,
                    'stability' => isset($data['text_to_speech_stability']) ? (float)$data['text_to_speech_stability'] : 0.5,
                    'similarity_boost' => isset($data['text_to_speech_similarity_boost']) ? (float)$data['text_to_speech_similarity_boost'] : 0.75,
                    'output_format' => $data['text_to_speech_output_format'] ?? 'mp3',
                    'convert_to_whatsapp_format' => isset($data['text_to_speech_convert_to_whatsapp_format']),
                    'cost_limit_per_day' => isset($data['text_to_speech_cost_limit_per_day']) ? (float)$data['text_to_speech_cost_limit_per_day'] : 5.00,
                    'intelligent_rules' => [
                        // ✅ NOVO: Modo Adaptativo e Primeira Mensagem
                        'adaptive_mode' => ($data['text_to_speech_send_mode'] ?? '') === 'adaptive',
                        'first_message_always_text' => isset($data['tts_intelligent_first_message_always_text']),
                        'custom_behavior_prompt' => $data['tts_intelligent_custom_behavior_prompt'] ?? '',
                        
                        'use_text_length' => isset($data['tts_intelligent_use_text_length']),
                        'max_chars_for_audio' => isset($data['tts_intelligent_max_chars_for_audio']) ? (int)$data['tts_intelligent_max_chars_for_audio'] : 500,
                        'min_chars_for_text' => isset($data['tts_intelligent_min_chars_for_text']) ? (int)$data['tts_intelligent_min_chars_for_text'] : 1000,
                        'use_content_type' => true,
                        'force_text_if_urls' => isset($data['tts_intelligent_force_text_if_urls']),
                        'force_text_if_code' => isset($data['tts_intelligent_force_text_if_code']),
                        'force_text_if_numbers' => false,
                        'max_numbers_for_audio' => 5,
                        'use_complexity' => isset($data['tts_intelligent_use_complexity']),
                        'force_text_if_complex' => true,
                        'complexity_keywords' => !empty($data['tts_intelligent_complexity_keywords']) 
                            ? array_map('trim', explode(',', $data['tts_intelligent_complexity_keywords']))
                            : ['instrução', 'passo a passo', 'tutorial', 'configuração', 'instalar', 'configurar', 'ajustar'],
                        'use_emojis' => isset($data['tts_intelligent_use_emojis']),
                        'max_emojis_for_audio' => isset($data['tts_intelligent_max_emojis_for_audio']) ? (int)$data['tts_intelligent_max_emojis_for_audio'] : 3,
                        'use_time' => false,
                        'audio_hours_start' => 8,
                        'audio_hours_end' => 20,
                        'timezone' => 'America/Sao_Paulo',
                        'use_conversation_history' => true,
                        'prefer_audio_if_client_sent_audio' => isset($data['tts_intelligent_prefer_audio_if_client_sent_audio']),
                        'prefer_text_if_client_sent_text' => false,
                        'default_mode' => $data['tts_intelligent_default_mode'] ?? 'audio_only',
                    ],
                ],
            ];
            
            if (ConversationSettingsService::saveSettings($settings)) {
                Response::successOrRedirect(
                    'Configurações de conversas salvas com sucesso!',
                    '/settings?tab=conversations'
                );
            } else {
                if (Request::isAjax()) {
                    Response::json([
                        'success' => false,
                        'message' => 'Falha ao salvar configurações'
                    ], 500);
                } else {
                    Response::redirect('/settings?tab=conversations&error=' . urlencode('Falha ao salvar configurações'));
                }
            }
        } catch (\Exception $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
                ], 500);
            } else {
                Response::redirect('/settings?tab=conversations&error=' . urlencode('Erro ao salvar configurações: ' . $e->getMessage()));
            }
        }
    }
    
    /**
     * Obter configurações de SLA (API pública para frontend)
     */
    public function getSLAConfig(): void
    {
        try {
            $settings = ConversationSettingsService::getSettings();
            $sla = $settings['sla'] ?? [];
            
            Response::json([
                'success' => true,
                'sla' => $sla
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao obter configurações de SLA'
            ], 500);
        }
    }
    
    /**
     * Salvar configurações do PostgreSQL
     */
    public function savePostgreSQL(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $data = Request::post();
            
            PostgreSQLSettingsService::saveSettings([
                'postgres_enabled' => isset($data['postgres_enabled']),
                'postgres_host' => $data['postgres_host'] ?? 'localhost',
                'postgres_port' => (int)($data['postgres_port'] ?? 5432),
                'postgres_database' => $data['postgres_database'] ?? 'chat_rag',
                'postgres_username' => $data['postgres_username'] ?? 'chat_user',
                'postgres_password' => $data['postgres_password'] ?? '',
            ]);
            
            Response::successOrRedirect(
                'Configurações do PostgreSQL salvas com sucesso!',
                '/settings?tab=postgres'
            );
        } catch (\Exception $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
                ], 500);
            } else {
                Response::redirect('/settings?tab=postgres&error=' . urlencode('Erro ao salvar configurações: ' . $e->getMessage()));
            }
        }
    }

    /**
     * Testar conexão PostgreSQL
     */
    public function testPostgreSQL(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            // Primeiro, salvar temporariamente as configurações do POST para testar
            $data = Request::post();
            
            if (empty($data['postgres_enabled']) || !isset($data['postgres_enabled'])) {
                throw new \Exception('PostgreSQL não está habilitado. Marque a opção "Habilitar PostgreSQL" primeiro.');
            }
            
            // Salvar temporariamente para teste
            PostgreSQLSettingsService::saveSettings([
                'postgres_enabled' => isset($data['postgres_enabled']),
                'postgres_host' => $data['postgres_host'] ?? 'localhost',
                'postgres_port' => (int)($data['postgres_port'] ?? 5432),
                'postgres_database' => $data['postgres_database'] ?? 'chat_rag',
                'postgres_username' => $data['postgres_username'] ?? 'chat_user',
                'postgres_password' => $data['postgres_password'] ?? '',
            ]);
            
            // Tentar conectar
            $conn = \App\Helpers\PostgreSQL::getConnection();
            
            // Testar query simples
            $result = \App\Helpers\PostgreSQL::query("SELECT version()");
            $version = $result[0]['version'] ?? 'Desconhecida';
            
            // Verificar extensão pgvector
            $pgvectorResult = \App\Helpers\PostgreSQL::query("SELECT * FROM pg_extension WHERE extname = 'vector'");
            $pgvectorInstalled = !empty($pgvectorResult);
            
            $message = 'Conexão PostgreSQL estabelecida com sucesso!';
            if (!$pgvectorInstalled) {
                $message .= ' ⚠️ A extensão pgvector não está instalada. Execute: CREATE EXTENSION vector;';
            }
            
            Response::json([
                'success' => true,
                'message' => $message,
                'version' => $version,
                'pgvector_installed' => $pgvectorInstalled
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao conectar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter vozes disponíveis do ElevenLabs
     */
    public function getElevenLabsVoices(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $voices = \App\Services\ElevenLabsService::getAvailableVoices();
            
            Response::json([
                'success' => true,
                'voices' => $voices
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao obter vozes: ' . $e->getMessage()
            ], 500);
        }
    }
}
