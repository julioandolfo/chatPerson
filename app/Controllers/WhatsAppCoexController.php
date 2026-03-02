<?php

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\WhatsAppCoexService;
use App\Services\WhatsAppTemplateService;
use App\Services\WhatsAppCloudService;
use App\Models\WhatsAppPhone;
use App\Models\WhatsAppTemplate;
use App\Models\MetaOAuthToken;
use App\Models\IntegrationAccount;

/**
 * WhatsAppCoexController
 * 
 * Controller para gerenciamento do WhatsApp Coexistence (CoEx)
 * - Dashboard CoEx com status dos números
 * - Embedded Signup para onboarding
 * - Gerenciamento de templates (criar, enviar, verificar status)
 */
class WhatsAppCoexController
{
    /**
     * Página principal do WhatsApp CoEx
     */
    public function index(): void
    {
        Permission::abortIfCannot('integrations.view');
        
        $metaConfig = self::getMetaConfig();
        
        // Buscar números WhatsApp com info de CoEx
        $phones = [];
        try {
            $allPhones = WhatsAppPhone::all();
            foreach ($allPhones as $phone) {
                $phone['has_valid_token'] = WhatsAppPhone::hasValidToken($phone['id']);
                $phone['coex_capabilities_decoded'] = [];
                if (!empty($phone['coex_capabilities'])) {
                    $phone['coex_capabilities_decoded'] = is_string($phone['coex_capabilities'])
                        ? json_decode($phone['coex_capabilities'], true) ?? []
                        : $phone['coex_capabilities'];
                }
                $phones[] = $phone;
            }
        } catch (\Exception $e) {
            // Tabela pode não existir ainda
        }
        
        // Buscar tokens OAuth
        $tokens = [];
        try {
            $tokens = MetaOAuthToken::all();
        } catch (\Exception $e) {
            // Ignorar
        }
        
        Response::view('integrations/whatsapp-coex/index', [
            'metaConfig' => $metaConfig,
            'phones' => $phones,
            'tokens' => $tokens,
            'activeTab' => Request::get('tab', 'overview'),
        ]);
    }
    
    /**
     * Aba de templates
     */
    public function templates(): void
    {
        Permission::abortIfCannot('integrations.view');
        
        $wabaId = Request::get('waba_id', '');
        $filter = Request::get('filter', 'all');
        
        $templates = [];
        $stats = [];
        $phones = [];
        
        try {
            $phones = WhatsAppPhone::all();
            
            if (!empty($wabaId)) {
                switch ($filter) {
                    case 'approved':
                        $templates = WhatsAppTemplate::getApproved($wabaId);
                        break;
                    case 'pending':
                        $templates = WhatsAppTemplate::getPending($wabaId);
                        break;
                    case 'rejected':
                        $templates = WhatsAppTemplate::getRejected($wabaId);
                        break;
                    case 'drafts':
                        $templates = WhatsAppTemplate::getDrafts($wabaId);
                        break;
                    default:
                        $templates = WhatsAppTemplate::getByWabaId($wabaId);
                }
                $stats = WhatsAppTemplate::getStats($wabaId);
            } elseif (!empty($phones)) {
                // Usar o primeiro WABA disponível
                $wabaId = $phones[0]['waba_id'] ?? '';
                if ($wabaId) {
                    $templates = WhatsAppTemplate::getByWabaId($wabaId);
                    $stats = WhatsAppTemplate::getStats($wabaId);
                }
            }
        } catch (\Exception $e) {
            // Tabela pode não existir
        }
        
        Response::view('integrations/whatsapp-coex/index', [
            'metaConfig' => self::getMetaConfig(),
            'phones' => $phones,
            'tokens' => [],
            'activeTab' => 'templates',
            'templates' => $templates,
            'templateStats' => $stats,
            'selectedWabaId' => $wabaId,
            'templateFilter' => $filter,
        ]);
    }
    
    // ==================== ACTIONS CoEx ====================
    
    /**
     * Processar callback do Embedded Signup
     */
    public function embeddedSignupCallback(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        try {
            $code = Request::post('code', '');
            $sessionInfo = Request::post('session_info', []);
            $signupData = Request::post('signup_data', []);
            $eventType = Request::post('event_type', '');
            
            if (empty($code)) {
                Response::json(['success' => false, 'error' => 'Código não fornecido']);
                return;
            }
            
            // Trocar code por token
            $tokenResult = WhatsAppCoexService::processEmbeddedSignupCallback($code);
            $accessToken = $tokenResult['access_token'];
            
            // Determinar se veio via CoEx (QR Code) baseado no session data
            $isCoex = ($eventType === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') 
                      || !empty($signupData['is_coex']);
            
            // Se o session listener capturou phone_number_id e waba_id diretamente, usar
            $directPhoneId = $signupData['phone_number_id'] ?? null;
            $directWabaId = $signupData['waba_id'] ?? null;
            
            // Obter info das contas via API (sempre necessário para dados completos)
            $accountInfo = WhatsAppCoexService::getSignupAccountInfo($accessToken);
            
            if (empty($accountInfo)) {
                Response::json(['success' => false, 'error' => 'Nenhuma conta WhatsApp encontrada. Verifique se o número tem pelo menos 7 dias de atividade no app.']);
                return;
            }
            
            $registered = [];
            
            foreach ($accountInfo as $waba) {
                foreach ($waba['phones'] as $phone) {
                    // Se temos dados diretos do session listener, filtrar para registrar apenas esse número
                    if ($directPhoneId && $phone['id'] !== $directPhoneId) {
                        continue;
                    }
                    
                    // Salvar/atualizar token OAuth
                    $existingToken = MetaOAuthToken::getByMetaUserId($phone['id']);
                    if ($existingToken) {
                        MetaOAuthToken::update($existingToken['id'], [
                            'access_token' => $accessToken,
                            'app_type' => $isCoex ? 'whatsapp_coex' : 'whatsapp',
                            'is_valid' => true,
                            'expires_at' => !empty($tokenResult['expires_in']) 
                                ? date('Y-m-d H:i:s', time() + $tokenResult['expires_in']) 
                                : null,
                        ]);
                        $tokenId = $existingToken['id'];
                    } else {
                        $tokenId = MetaOAuthToken::create([
                            'meta_user_id' => $phone['id'],
                            'app_type' => $isCoex ? 'whatsapp_coex' : 'whatsapp',
                            'access_token' => $accessToken,
                            'token_type' => 'bearer',
                            'expires_at' => !empty($tokenResult['expires_in']) 
                                ? date('Y-m-d H:i:s', time() + $tokenResult['expires_in']) 
                                : null,
                            'scopes' => 'whatsapp_business_management,whatsapp_business_messaging',
                            'is_valid' => true,
                        ]);
                    }
                    
                    // Registrar número CoEx
                    $phoneId = WhatsAppCoexService::registerCoexPhone([
                        'phone_number_id' => $phone['id'],
                        'phone_number' => $phone['display_phone_number'] ?? '',
                        'display_phone_number' => $phone['display_phone_number'] ?? '',
                        'waba_id' => $waba['waba_id'],
                        'verified_name' => $phone['verified_name'] ?? '',
                        'quality_rating' => $phone['quality_rating'] ?? 'UNKNOWN',
                        'account_mode' => $phone['account_mode'] ?? 'SANDBOX',
                        'meta_oauth_token_id' => $tokenId,
                    ]);
                    
                    // Se veio via CoEx QR Code, marcar como ativo imediatamente
                    if ($isCoex) {
                        WhatsAppPhone::update($phoneId, [
                            'coex_enabled' => true,
                            'coex_status' => 'active',
                            'coex_activated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                    
                    // Criar/atualizar integration_account
                    $existingIA = IntegrationAccount::findByPhone(
                        $phone['display_phone_number'] ?? '',
                        'whatsapp'
                    );
                    
                    if ($existingIA) {
                        IntegrationAccount::update($existingIA['id'], [
                            'name' => $phone['verified_name'] ?? 'WhatsApp CoEx',
                            'provider' => 'meta_coex',
                            'status' => 'active',
                            'config' => json_encode([
                                'coex_enabled' => $isCoex,
                                'waba_id' => $waba['waba_id'],
                                'phone_number_id' => $phone['id'],
                                'onboarding_type' => $eventType,
                            ]),
                        ]);
                        $iaId = $existingIA['id'];
                        WhatsAppPhone::update($phoneId, ['integration_account_id' => $iaId]);
                    } else {
                        $iaId = IntegrationAccount::create([
                            'name' => $phone['verified_name'] ?? 'WhatsApp CoEx',
                            'provider' => 'meta_coex',
                            'channel' => 'whatsapp',
                            'phone_number' => $phone['display_phone_number'] ?? '',
                            'account_id' => $waba['waba_id'],
                            'status' => 'active',
                            'config' => json_encode([
                                'coex_enabled' => $isCoex,
                                'waba_id' => $waba['waba_id'],
                                'phone_number_id' => $phone['id'],
                                'onboarding_type' => $eventType,
                            ]),
                        ]);
                        
                        WhatsAppPhone::update($phoneId, ['integration_account_id' => $iaId]);
                    }
                    
                    // Vincular token à integration_account
                    MetaOAuthToken::update($tokenId, ['integration_account_id' => $iaId]);
                    
                    // Subscrever webhook
                    WhatsAppCoexService::subscribeCoexWebhookFields($waba['waba_id'], $accessToken);
                    
                    $registered[] = [
                        'phone' => $phone['display_phone_number'] ?? '',
                        'name' => $phone['verified_name'] ?? '',
                        'coex' => $isCoex,
                    ];
                }
            }
            
            if (empty($registered)) {
                Response::json([
                    'success' => false, 
                    'error' => 'Nenhum número foi registrado. Verifique se o número é elegível para CoEx.'
                ]);
                return;
            }
            
            $coexLabel = $isCoex ? ' com CoEx (QR Code)' : '';
            Response::json([
                'success' => true,
                'registered' => $registered,
                'message' => count($registered) . ' número(s) registrado(s)' . $coexLabel,
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Obter status CoEx de um número
     */
    public function getCoexStatus(): void
    {
        Permission::abortIfCannot('integrations.view');
        
        try {
            $phoneId = (int)Request::get('phone_id');
            $status = WhatsAppCoexService::getCoexStatus($phoneId);
            Response::json(['success' => true, 'data' => $status]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    // ==================== ACTIONS TEMPLATES ====================
    
    /**
     * Criar template (rascunho)
     */
    public function createTemplate(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        try {
            $data = Request::postJson();
            
            // Montar botões
            $buttons = [];
            if (!empty($data['buttons'])) {
                foreach ($data['buttons'] as $btn) {
                    if (!empty($btn['text'])) {
                        $buttons[] = $btn;
                    }
                }
            }
            $data['buttons'] = $buttons;
            
            $id = WhatsAppTemplateService::createDraft($data);
            
            Response::json([
                'success' => true,
                'id' => $id,
                'message' => 'Rascunho criado com sucesso',
            ]);
            
        } catch (\Exception $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Atualizar template (rascunho)
     */
    public function updateTemplate(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        try {
            $data = Request::postJson();
            $id = (int)($data['id'] ?? 0);
            unset($data['id']);
            
            if (!empty($data['buttons']) && is_array($data['buttons'])) {
                $buttons = [];
                foreach ($data['buttons'] as $btn) {
                    if (!empty($btn['text'])) {
                        $buttons[] = $btn;
                    }
                }
                $data['buttons'] = $buttons;
            }
            
            WhatsAppTemplateService::updateDraft($id, $data);
            
            Response::json([
                'success' => true,
                'message' => 'Rascunho atualizado',
            ]);
            
        } catch (\Exception $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Enviar template para aprovação
     */
    public function submitTemplate(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        try {
            $data = Request::postJson();
            $templateId = (int)($data['template_id'] ?? 0);
            
            // Obter token válido
            $accessToken = $this->getAccessTokenForTemplate($templateId);
            
            $result = WhatsAppTemplateService::submitForApproval($templateId, $accessToken);
            
            Response::json([
                'success' => true,
                'data' => $result,
                'message' => 'Template enviado para aprovação na Meta',
            ]);
            
        } catch (\Exception $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Verificar status de um template
     */
    public function checkTemplateStatus(): void
    {
        Permission::abortIfCannot('integrations.view');
        
        try {
            $data = Request::postJson();
            $templateId = (int)($data['template_id'] ?? 0);
            
            $accessToken = $this->getAccessTokenForTemplate($templateId);
            
            $result = WhatsAppTemplateService::checkStatus($templateId, $accessToken);
            
            Response::json([
                'success' => true,
                'data' => $result,
            ]);
            
        } catch (\Exception $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Sincronizar templates da Meta
     */
    public function syncTemplates(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        try {
            $data = Request::postJson();
            $wabaId = $data['waba_id'] ?? '';
            
            if (empty($wabaId)) {
                throw new \Exception('WABA ID é obrigatório');
            }
            
            $accessToken = $this->getAccessTokenForWaba($wabaId);
            
            $result = WhatsAppTemplateService::syncFromMeta($wabaId, $accessToken);
            
            Response::json([
                'success' => true,
                'data' => $result,
                'message' => "Sincronizados: {$result['total']} templates ({$result['created']} novos, {$result['updated']} atualizados)",
            ]);
            
        } catch (\Exception $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Excluir template
     */
    public function deleteTemplate(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        try {
            $data = Request::postJson();
            $templateId = (int)($data['template_id'] ?? 0);
            
            $template = WhatsAppTemplate::find($templateId);
            if (!$template) {
                throw new \Exception('Template não encontrado');
            }
            
            if ($template['status'] === 'DRAFT') {
                // Deletar rascunho direto
                WhatsAppTemplate::destroy($templateId);
            } else {
                $accessToken = $this->getAccessTokenForTemplate($templateId);
                WhatsAppTemplateService::deleteFromMeta($templateId, $accessToken);
            }
            
            Response::json([
                'success' => true,
                'message' => 'Template excluído com sucesso',
            ]);
            
        } catch (\Exception $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Obter detalhes de um template
     */
    public function getTemplate(): void
    {
        Permission::abortIfCannot('integrations.view');
        
        try {
            $id = (int)Request::get('id');
            $template = WhatsAppTemplate::find($id);
            
            if (!$template) {
                throw new \Exception('Template não encontrado');
            }
            
            // Decodificar JSON
            $template['buttons_decoded'] = WhatsAppTemplate::getButtons($template);
            $template['components_decoded'] = WhatsAppTemplate::getComponents($template);
            $template['variable_count'] = WhatsAppTemplate::countVariables($template);
            
            Response::json(['success' => true, 'data' => $template]);
            
        } catch (\Exception $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    // ==================== HELPERS ====================
    
    /**
     * Obter config da Meta
     */
    private static function getMetaConfig(): array
    {
        $configFile = __DIR__ . '/../../config/meta.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            return [
                'app_id' => $config['app_id'] ?? '',
                'app_secret' => !empty($config['app_secret']) ? '***configurado***' : '',
                'webhook_verify_token' => $config['webhooks']['verify_token'] ?? '',
            ];
        }
        return [];
    }
    
    /**
     * Obter access token para operações com templates
     */
    private function getAccessTokenForTemplate(int $templateId): string
    {
        $template = WhatsAppTemplate::find($templateId);
        if (!$template) {
            throw new \Exception("Template #{$templateId} não encontrado");
        }
        
        return $this->getAccessTokenForWaba($template['waba_id']);
    }
    
    /**
     * Obter access token para um WABA
     */
    private function getAccessTokenForWaba(string $wabaId): string
    {
        // Buscar número deste WABA
        $phones = WhatsAppPhone::findByWabaId($wabaId);
        
        foreach ($phones as $phone) {
            if (!empty($phone['meta_oauth_token_id'])) {
                $token = MetaOAuthToken::find($phone['meta_oauth_token_id']);
                if ($token && MetaOAuthToken::isValid($token)) {
                    return $token['access_token'];
                }
            }
        }
        
        // Fallback: buscar qualquer token válido
        try {
            $allTokens = MetaOAuthToken::all();
            foreach ($allTokens as $token) {
                if (MetaOAuthToken::isValid($token)) {
                    return $token['access_token'];
                }
            }
        } catch (\Exception $e) {
            // Ignorar
        }
        
        throw new \Exception("Nenhum token OAuth válido encontrado. Conecte uma conta Meta primeiro.");
    }
}
