<?php

namespace App\Services;

use App\Models\IntegrationAccount;
use App\Models\WhatsAppPhone;
use App\Models\MetaOAuthToken;
use App\Helpers\Logger;

/**
 * WhatsAppCloudApiService
 * 
 * Bridge entre IntegrationService (envio unificado via integration_accounts)
 * e WhatsAppCloudService (envio direto via Meta Cloud API).
 * 
 * Este service é chamado pelo IntegrationService::sendMessage() quando
 * o provider é 'meta_coex' ou 'meta_cloud'.
 * 
 * Resolve automaticamente:
 * - phone_number_id (da tabela whatsapp_phones)
 * - access_token (da tabela meta_oauth_tokens)
 * - Tipo de mensagem (texto, mídia, template)
 */
class WhatsAppCloudApiService
{
    /**
     * Enviar mensagem via WhatsApp Cloud API
     * 
     * @param int $accountId ID da integration_accounts
     * @param string $to Número de destino (ex: 5511999999999)
     * @param string $message Texto da mensagem
     * @param array $options Opções (media_url, media_type, template_name, etc.)
     * @return array Resultado do envio
     */
    public function sendMessage(int $accountId, string $to, string $message, array $options = []): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            throw new \Exception("Conta de integração #{$accountId} não encontrada");
        }
        
        // Resolver phone_number_id e access_token
        $resolved = $this->resolveCredentials($account);
        $phoneNumberId = $resolved['phone_number_id'];
        $accessToken = $resolved['access_token'];
        
        // Normalizar número de destino (remover +, espaços, traços)
        $to = preg_replace('/[^0-9]/', '', $to);
        
        Logger::meta('INFO', "WhatsAppCloudApiService::sendMessage", [
            'account_id' => $accountId,
            'phone_number_id' => $phoneNumberId,
            'to' => $to,
            'has_media' => !empty($options['media_url']),
            'has_template' => !empty($options['template_name']),
        ]);
        
        try {
            // Envio com mídia
            if (!empty($options['media_url'])) {
                $mediaType = $options['media_type'] ?? 'document';
                $caption = $options['caption'] ?? $message;
                
                // Mapear tipos
                $mediaTypeMap = [
                    'image' => 'image',
                    'video' => 'video',
                    'audio' => 'audio',
                    'document' => 'document',
                    'file' => 'document',
                    'sticker' => 'sticker',
                ];
                $mediaType = $mediaTypeMap[$mediaType] ?? 'document';
                
                $result = WhatsAppCloudService::sendMedia(
                    $phoneNumberId,
                    $to,
                    $mediaType,
                    $options['media_url'],
                    $caption,
                    $accessToken
                );
                
                return [
                    'success' => true,
                    'message_id' => $result['messages'][0]['id'] ?? null,
                    'provider' => 'meta_cloud',
                    'type' => 'media',
                ];
            }
            
            // Envio com template
            if (!empty($options['template_name'])) {
                $result = WhatsAppCloudService::sendTemplateMessage(
                    $phoneNumberId,
                    $to,
                    $options['template_name'],
                    $options['template_language'] ?? 'pt_BR',
                    $options['template_parameters'] ?? [],
                    $accessToken
                );
                
                return [
                    'success' => true,
                    'message_id' => $result['messages'][0]['id'] ?? null,
                    'provider' => 'meta_cloud',
                    'type' => 'template',
                ];
            }
            
            // Envio de texto simples
            if (!empty($message)) {
                $result = WhatsAppCloudService::sendTextMessage(
                    $phoneNumberId,
                    $to,
                    $message,
                    $accessToken
                );
                
                return [
                    'success' => true,
                    'message_id' => $result['messages'][0]['id'] ?? null,
                    'provider' => 'meta_cloud',
                    'type' => 'text',
                ];
            }
            
            throw new \Exception("Nenhum conteúdo para enviar (sem texto, mídia ou template)");
            
        } catch (\Exception $e) {
            Logger::meta('ERROR', "WhatsAppCloudApiService::sendMessage ERRO: {$e->getMessage()}", [
                'account_id' => $accountId,
                'to' => $to,
            ]);
            throw $e;
        }
    }
    
    /**
     * Verificar conexão/status
     */
    public function checkConnection(int $accountId): array
    {
        try {
            $account = IntegrationAccount::find($accountId);
            if (!$account) {
                return ['status' => 'error', 'connected' => false, 'message' => 'Conta não encontrada'];
            }
            
            $resolved = $this->resolveCredentials($account);
            
            // Tentar obter business profile para verificar conexão
            $profile = WhatsAppCloudService::getBusinessProfile(
                $resolved['phone_number_id'],
                $resolved['access_token']
            );
            
            return [
                'status' => 'active',
                'connected' => true,
                'message' => 'Conectado via Cloud API',
                'profile' => $profile,
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'connected' => false,
                'message' => 'Erro: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Resolver credenciais (phone_number_id e access_token)
     * a partir da integration_account
     */
    private function resolveCredentials(array $account): array
    {
        $phoneNumberId = null;
        $accessToken = null;
        
        // 1. Tentar pegar do config JSON da integration_account
        $config = [];
        if (!empty($account['config'])) {
            $config = is_string($account['config'])
                ? json_decode($account['config'], true) ?? []
                : $account['config'];
        }
        
        $phoneNumberId = $config['phone_number_id'] ?? null;
        $wabaId = $config['waba_id'] ?? $account['account_id'] ?? null;
        
        // 2. Buscar na tabela whatsapp_phones pelo integration_account_id
        if (!$phoneNumberId) {
            $phone = WhatsAppPhone::findByIntegrationAccount($account['id']);
            if ($phone) {
                $phoneNumberId = $phone['phone_number_id'];
                
                // Obter token do phone
                if (!empty($phone['meta_oauth_token_id'])) {
                    $token = MetaOAuthToken::find($phone['meta_oauth_token_id']);
                    if ($token && MetaOAuthToken::isValid($token)) {
                        $accessToken = $token['access_token'];
                    }
                }
            }
        }
        
        // 3. Buscar pelo phone_number
        if (!$phoneNumberId && !empty($account['phone_number'])) {
            $phone = WhatsAppPhone::findByPhoneNumber($account['phone_number']);
            if ($phone) {
                $phoneNumberId = $phone['phone_number_id'];
                
                if (!$accessToken && !empty($phone['meta_oauth_token_id'])) {
                    $token = MetaOAuthToken::find($phone['meta_oauth_token_id']);
                    if ($token && MetaOAuthToken::isValid($token)) {
                        $accessToken = $token['access_token'];
                    }
                }
            }
        }
        
        // 4. Se ainda não tem token, buscar qualquer token válido do WABA
        if (!$accessToken && $wabaId) {
            $wabaPhones = WhatsAppPhone::findByWabaId($wabaId);
            foreach ($wabaPhones as $p) {
                if (!empty($p['meta_oauth_token_id'])) {
                    $token = MetaOAuthToken::find($p['meta_oauth_token_id']);
                    if ($token && MetaOAuthToken::isValid($token)) {
                        $accessToken = $token['access_token'];
                        if (!$phoneNumberId) {
                            $phoneNumberId = $p['phone_number_id'];
                        }
                        break;
                    }
                }
            }
        }
        
        // 5. Último fallback: api_token da integration_account
        if (!$accessToken && !empty($account['api_token'])) {
            $accessToken = $account['api_token'];
        }
        
        // Validar
        if (!$phoneNumberId) {
            throw new \Exception(
                "Não foi possível resolver phone_number_id para a conta #{$account['id']} ({$account['name']}). " .
                "Verifique se existe um número registrado na tabela whatsapp_phones vinculado a esta integração."
            );
        }
        
        if (!$accessToken) {
            throw new \Exception(
                "Nenhum token OAuth válido encontrado para a conta #{$account['id']} ({$account['name']}). " .
                "Reconecte a conta Meta na página de integrações."
            );
        }
        
        return [
            'phone_number_id' => $phoneNumberId,
            'access_token' => $accessToken,
        ];
    }
}
