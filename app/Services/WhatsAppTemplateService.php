<?php

namespace App\Services;

use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppPhone;
use App\Helpers\Logger;

/**
 * WhatsAppTemplateService
 * 
 * Gerencia templates de mensagem do WhatsApp Cloud API:
 * - CRUD local (rascunhos)
 * - Envio para aprovação na Meta
 * - Sincronização de status
 * - Listagem de templates aprovados
 * - Exclusão de templates
 */
class WhatsAppTemplateService extends MetaIntegrationService
{
    private static string $apiVersion = 'v21.0';
    private static string $baseUrl = 'https://graph.facebook.com';
    
    /**
     * Criar template como rascunho local
     */
    public static function createDraft(array $data): int
    {
        // Validar campos obrigatórios
        if (empty($data['waba_id']) || empty($data['name']) || empty($data['body_text'])) {
            throw new \InvalidArgumentException('waba_id, name e body_text são obrigatórios');
        }
        
        // Normalizar nome (slug)
        $data['name'] = self::normalizeName($data['name']);
        
        // Verificar duplicata
        $existing = WhatsAppTemplate::findByNameAndLanguage(
            $data['waba_id'],
            $data['name'],
            $data['language'] ?? 'pt_BR'
        );
        
        if ($existing) {
            throw new \Exception("Já existe um template com o nome '{$data['name']}' neste idioma");
        }
        
        // Preparar botões como JSON
        if (!empty($data['buttons']) && is_array($data['buttons'])) {
            $data['buttons'] = json_encode($data['buttons']);
        }
        
        $data['status'] = 'DRAFT';
        $data['language'] = $data['language'] ?? 'pt_BR';
        $data['category'] = $data['category'] ?? 'UTILITY';
        $data['header_type'] = $data['header_type'] ?? 'NONE';
        
        $id = WhatsAppTemplate::create($data);
        
        self::logInfo("Template rascunho criado: #{$id} - {$data['name']}");
        
        return $id;
    }
    
    /**
     * Atualizar rascunho local
     */
    public static function updateDraft(int $id, array $data): bool
    {
        $template = WhatsAppTemplate::find($id);
        if (!$template) {
            throw new \Exception("Template #{$id} não encontrado");
        }
        
        if ($template['status'] !== 'DRAFT') {
            throw new \Exception("Apenas rascunhos podem ser editados. Status atual: {$template['status']}");
        }
        
        if (!empty($data['name'])) {
            $data['name'] = self::normalizeName($data['name']);
        }
        
        if (!empty($data['buttons']) && is_array($data['buttons'])) {
            $data['buttons'] = json_encode($data['buttons']);
        }
        
        return WhatsAppTemplate::update($id, $data);
    }
    
    /**
     * Enviar template para aprovação na Meta
     */
    public static function submitForApproval(int $templateId, string $accessToken): array
    {
        self::initConfig();
        
        $template = WhatsAppTemplate::find($templateId);
        if (!$template) {
            throw new \Exception("Template #{$templateId} não encontrado");
        }
        
        if (!in_array($template['status'], ['DRAFT', 'REJECTED'])) {
            throw new \Exception("Apenas rascunhos ou rejeitados podem ser enviados. Status: {$template['status']}");
        }
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$template['waba_id']}/message_templates";
        
        // Montar componentes
        $components = self::buildComponents($template);
        
        $payload = [
            'name' => $template['name'],
            'language' => $template['language'],
            'category' => $template['category'],
            'components' => $components,
        ];
        
        self::logInfo("Enviando template para aprovação: {$template['name']}", $payload);
        
        try {
            $response = self::makeRequest($url, $accessToken, 'POST', $payload);
            
            // Atualizar status local
            $updateData = [
                'status' => $response['status'] ?? 'PENDING',
                'template_id' => $response['id'] ?? null,
                'last_synced_at' => date('Y-m-d H:i:s'),
            ];
            
            WhatsAppTemplate::update($templateId, $updateData);
            
            self::logInfo("Template enviado para aprovação com sucesso", [
                'template_id' => $response['id'] ?? null,
                'status' => $response['status'] ?? 'PENDING',
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            self::logError("Erro ao enviar template para aprovação: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Sincronizar templates da Meta para o banco local
     */
    public static function syncFromMeta(string $wabaId, string $accessToken): array
    {
        self::initConfig();
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$wabaId}/message_templates";
        
        $synced = 0;
        $created = 0;
        $updated = 0;
        $nextUrl = null;
        
        try {
            // Paginar resultados
            do {
                $requestUrl = $nextUrl ?: $url;
                $params = $nextUrl ? [] : [
                    'fields' => 'id,name,status,language,category,components,quality_score,rejected_reason',
                    'limit' => 100,
                ];
                
                $response = self::makeRequest($requestUrl, $accessToken, 'GET', $params);
                
                $templates = $response['data'] ?? [];
                $nextUrl = $response['paging']['next'] ?? null;
                
                foreach ($templates as $metaTemplate) {
                    $name = $metaTemplate['name'] ?? '';
                    $language = $metaTemplate['language'] ?? 'pt_BR';
                    
                    $existing = WhatsAppTemplate::findByNameAndLanguage($wabaId, $name, $language);
                    
                    $templateData = self::mapMetaTemplateToLocal($metaTemplate, $wabaId);
                    
                    if ($existing) {
                        // Atualizar
                        WhatsAppTemplate::update($existing['id'], $templateData);
                        $updated++;
                    } else {
                        // Criar
                        WhatsAppTemplate::create($templateData);
                        $created++;
                    }
                    
                    $synced++;
                }
                
            } while ($nextUrl);
            
            self::logInfo("Templates sincronizados da Meta", [
                'waba_id' => $wabaId,
                'total' => $synced,
                'created' => $created,
                'updated' => $updated,
            ]);
            
            return [
                'total' => $synced,
                'created' => $created,
                'updated' => $updated,
            ];
            
        } catch (\Exception $e) {
            self::logError("Erro ao sincronizar templates: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Verificar status de um template específico na Meta
     */
    public static function checkStatus(int $templateId, string $accessToken): array
    {
        self::initConfig();
        
        $template = WhatsAppTemplate::find($templateId);
        if (!$template) {
            throw new \Exception("Template #{$templateId} não encontrado");
        }
        
        if (empty($template['template_id'])) {
            throw new \Exception("Template ainda não foi enviado para a Meta");
        }
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$template['template_id']}";
        
        try {
            $response = self::makeRequest($url, $accessToken, 'GET', [
                'fields' => 'id,name,status,language,category,components,quality_score,rejected_reason'
            ]);
            
            // Atualizar localmente
            $updateData = [
                'status' => $response['status'] ?? $template['status'],
                'quality_score' => $response['quality_score']['score'] ?? $template['quality_score'],
                'rejection_reason' => $response['rejected_reason'] ?? null,
                'last_synced_at' => date('Y-m-d H:i:s'),
            ];
            
            WhatsAppTemplate::update($templateId, $updateData);
            
            return array_merge($template, $updateData);
            
        } catch (\Exception $e) {
            self::logError("Erro ao verificar status do template: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Excluir template na Meta
     */
    public static function deleteFromMeta(int $templateId, string $accessToken): bool
    {
        self::initConfig();
        
        $template = WhatsAppTemplate::find($templateId);
        if (!$template) {
            throw new \Exception("Template #{$templateId} não encontrado");
        }
        
        // Se é rascunho, apenas excluir localmente
        if ($template['status'] === 'DRAFT') {
            return WhatsAppTemplate::destroy($templateId);
        }
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$template['waba_id']}/message_templates";
        
        try {
            // A Meta exige nome para deletar
            $response = self::makeRequest($url, $accessToken, 'DELETE', [
                'name' => $template['name'],
            ]);
            
            // Excluir localmente
            WhatsAppTemplate::destroy($templateId);
            
            self::logInfo("Template excluído: {$template['name']}");
            
            return true;
            
        } catch (\Exception $e) {
            self::logError("Erro ao excluir template na Meta: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Editar template já existente na Meta (para templates aprovados)
     */
    public static function editOnMeta(int $templateId, array $data, string $accessToken): array
    {
        self::initConfig();
        
        $template = WhatsAppTemplate::find($templateId);
        if (!$template) {
            throw new \Exception("Template #{$templateId} não encontrado");
        }
        
        if (empty($template['template_id'])) {
            throw new \Exception("Template ainda não existe na Meta");
        }
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$template['template_id']}";
        
        // Montar componentes atualizados
        $merged = array_merge($template, $data);
        $components = self::buildComponents($merged);
        
        $payload = [
            'components' => $components,
        ];
        
        // Categoria pode ser alterada (se necessário)
        if (!empty($data['category']) && $data['category'] !== $template['category']) {
            $payload['category'] = $data['category'];
        }
        
        try {
            $response = self::makeRequest($url, $accessToken, 'POST', $payload);
            
            // Atualizar localmente
            $data['last_synced_at'] = date('Y-m-d H:i:s');
            if (!empty($data['buttons']) && is_array($data['buttons'])) {
                $data['buttons'] = json_encode($data['buttons']);
            }
            WhatsAppTemplate::update($templateId, $data);
            
            return $response;
            
        } catch (\Exception $e) {
            self::logError("Erro ao editar template na Meta: {$e->getMessage()}");
            throw $e;
        }
    }
    
    // ==================== HELPERS ====================
    
    /**
     * Normalizar nome do template (slug)
     */
    private static function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        return $name;
    }
    
    /**
     * Montar componentes para API da Meta
     */
    private static function buildComponents(array $template): array
    {
        $components = [];
        
        // Header
        $headerType = $template['header_type'] ?? 'NONE';
        if ($headerType !== 'NONE') {
            $header = ['type' => 'HEADER'];
            
            if ($headerType === 'TEXT') {
                $header['format'] = 'TEXT';
                $header['text'] = $template['header_text'] ?? '';
            } else {
                $header['format'] = strtoupper($headerType);
                // Para mídia, exemplo de handle seria adicionado no envio
                if (!empty($template['header_media_url'])) {
                    $header['example'] = [
                        'header_handle' => [$template['header_media_url']]
                    ];
                }
            }
            
            $components[] = $header;
        }
        
        // Body
        $body = [
            'type' => 'BODY',
            'text' => $template['body_text'] ?? '',
        ];
        
        // Adicionar exemplos de variáveis se houver
        $varCount = 0;
        preg_match_all('/\{\{(\d+)\}\}/', $body['text'], $matches);
        $varCount = count($matches[0]);
        
        if ($varCount > 0) {
            $examples = array_fill(0, $varCount, 'exemplo');
            $body['example'] = [
                'body_text' => [$examples]
            ];
        }
        
        $components[] = $body;
        
        // Footer
        if (!empty($template['footer_text'])) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $template['footer_text'],
            ];
        }
        
        // Buttons
        $buttons = [];
        if (!empty($template['buttons'])) {
            $btnData = is_string($template['buttons']) 
                ? json_decode($template['buttons'], true) 
                : $template['buttons'];
            
            if (is_array($btnData)) {
                $buttons = $btnData;
            }
        }
        
        if (!empty($buttons)) {
            $buttonComponents = [];
            foreach ($buttons as $btn) {
                $buttonComponent = [
                    'type' => strtoupper($btn['type'] ?? 'QUICK_REPLY'),
                    'text' => $btn['text'] ?? '',
                ];
                
                if (($btn['type'] ?? '') === 'URL' && !empty($btn['url'])) {
                    $buttonComponent['url'] = $btn['url'];
                }
                if (($btn['type'] ?? '') === 'PHONE_NUMBER' && !empty($btn['phone'])) {
                    $buttonComponent['phone_number'] = $btn['phone'];
                }
                
                $buttonComponents[] = $buttonComponent;
            }
            
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttonComponents,
            ];
        }
        
        return $components;
    }
    
    /**
     * Mapear template da Meta para formato local
     */
    private static function mapMetaTemplateToLocal(array $metaTemplate, string $wabaId): array
    {
        $components = $metaTemplate['components'] ?? [];
        
        // Extrair header, body, footer e botões dos componentes
        $headerType = 'NONE';
        $headerText = null;
        $bodyText = '';
        $footerText = null;
        $buttons = [];
        
        foreach ($components as $component) {
            $type = strtoupper($component['type'] ?? '');
            
            switch ($type) {
                case 'HEADER':
                    $format = strtoupper($component['format'] ?? 'TEXT');
                    $headerType = $format;
                    if ($format === 'TEXT') {
                        $headerText = $component['text'] ?? '';
                    }
                    break;
                    
                case 'BODY':
                    $bodyText = $component['text'] ?? '';
                    break;
                    
                case 'FOOTER':
                    $footerText = $component['text'] ?? '';
                    break;
                    
                case 'BUTTONS':
                    foreach ($component['buttons'] ?? [] as $btn) {
                        $buttons[] = [
                            'type' => strtolower($btn['type'] ?? 'quick_reply'),
                            'text' => $btn['text'] ?? '',
                            'url' => $btn['url'] ?? null,
                            'phone' => $btn['phone_number'] ?? null,
                        ];
                    }
                    break;
            }
        }
        
        return [
            'waba_id' => $wabaId,
            'template_id' => $metaTemplate['id'] ?? null,
            'name' => $metaTemplate['name'] ?? '',
            'language' => $metaTemplate['language'] ?? 'pt_BR',
            'category' => strtoupper($metaTemplate['category'] ?? 'UTILITY'),
            'status' => strtoupper($metaTemplate['status'] ?? 'PENDING'),
            'quality_score' => $metaTemplate['quality_score']['score'] ?? null,
            'header_type' => $headerType,
            'header_text' => $headerText,
            'body_text' => $bodyText,
            'footer_text' => $footerText,
            'buttons' => !empty($buttons) ? json_encode($buttons) : null,
            'components' => json_encode($components),
            'rejection_reason' => $metaTemplate['rejected_reason'] ?? null,
            'last_synced_at' => date('Y-m-d H:i:s'),
        ];
    }
}
