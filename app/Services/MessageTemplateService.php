<?php
/**
 * Service MessageTemplateService
 * Lógica de negócio para templates de mensagens
 */

namespace App\Services;

use App\Models\MessageTemplate;
use App\Helpers\Validator;

class MessageTemplateService
{
    /**
     * Listar templates
     */
    public static function list(array $filters = []): array
    {
        $sql = "SELECT mt.*, 
                       d.name as department_name,
                       u.name as created_by_name
                FROM message_templates mt
                LEFT JOIN departments d ON mt.department_id = d.id
                LEFT JOIN users u ON mt.created_by = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['category'])) {
            $sql .= " AND mt.category = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND (mt.department_id = ? OR mt.department_id IS NULL)";
            $params[] = $filters['department_id'];
        }

        if (!empty($filters['channel'])) {
            $sql .= " AND (mt.channel = ? OR mt.channel IS NULL)";
            $params[] = $filters['channel'];
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND mt.is_active = ?";
            $params[] = $filters['is_active'] ? 1 : 0;
        } else {
            // Por padrão, mostrar apenas ativos
            $sql .= " AND mt.is_active = TRUE";
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (mt.name LIKE ? OR mt.content LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY mt.name ASC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        return \App\Helpers\Database::fetchAll($sql, $params);
    }

    /**
     * Obter template específico
     */
    public static function get(int $templateId): ?array
    {
        return MessageTemplate::find($templateId);
    }

    /**
     * Criar template
     */
    public static function create(array $data): int
    {
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'content' => 'required|string',
            'description' => 'nullable|string',
            'department_id' => 'nullable|integer',
            'channel' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Valores padrão
        $data['is_active'] = $data['is_active'] ?? true;
        $data['created_by'] = \App\Helpers\Auth::id();

        return MessageTemplate::create($data);
    }

    /**
     * Atualizar template
     */
    public static function update(int $templateId, array $data): bool
    {
        $template = MessageTemplate::find($templateId);
        if (!$template) {
            throw new \InvalidArgumentException('Template não encontrado');
        }

        $errors = Validator::validate($data, [
            'name' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'content' => 'nullable|string',
            'description' => 'nullable|string',
            'department_id' => 'nullable|integer',
            'channel' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        return MessageTemplate::update($templateId, $data);
    }

    /**
     * Deletar template
     */
    public static function delete(int $templateId): bool
    {
        $template = MessageTemplate::find($templateId);
        if (!$template) {
            throw new \InvalidArgumentException('Template não encontrado');
        }

        return MessageTemplate::delete($templateId);
    }

    /**
     * Preview de template com variáveis preenchidas (sem incrementar contador)
     */
    public static function preview(int $templateId, ?int $conversationId = null): array
    {
        $template = MessageTemplate::find($templateId);
        if (!$template) {
            throw new \InvalidArgumentException('Template não encontrado');
        }

        // Obter variáveis da conversa se fornecida
        $variables = [];
        $variablesUsed = [];
        if ($conversationId) {
            $variables = self::getConversationVariables($conversationId);
        }
        
        // Extrair variáveis usadas no template
        preg_match_all('/\{\{([^}]+)\}\}/', $template['content'], $matches);
        if (!empty($matches[1])) {
            $variablesUsed = array_unique(array_map('trim', $matches[1]));
        }
        
        // Adicionar variáveis de data/hora
        $variables['date'] = date('d/m/Y');
        $variables['time'] = date('H:i');
        $variables['datetime'] = date('d/m/Y H:i');

        $processedContent = MessageTemplate::processTemplate($template['content'], $variables);
        
        return [
            'content' => $template['content'],
            'processed_content' => $processedContent,
            'variables_used' => $variablesUsed,
            'variables' => $variables
        ];
    }

    /**
     * Obter variáveis de uma conversa
     */
    public static function getConversationVariables(int $conversationId): array
    {
        $variables = [];
        
        try {
            $conversation = \App\Models\Conversation::find($conversationId);
            if ($conversation) {
                // Variáveis do contato
                if (!empty($conversation['contact_id'])) {
                    $contact = \App\Models\Contact::find($conversation['contact_id']);
                    if ($contact) {
                        $variables['contact.name'] = $contact['name'] ?? '';
                        $variables['contact.phone'] = $contact['phone'] ?? '';
                        $variables['contact.email'] = $contact['email'] ?? '';
                    }
                }
                
                // Variáveis do agente atual
                $userId = \App\Helpers\Auth::id();
                if ($userId) {
                    $user = \App\Models\User::find($userId);
                    if ($user) {
                        $variables['agent.name'] = $user['name'] ?? '';
                        $variables['agent.email'] = $user['email'] ?? '';
                    }
                }
                
                // Variáveis da conversa
                $variables['conversation.id'] = $conversation['id'] ?? '';
                $variables['conversation.subject'] = $conversation['subject'] ?? '';
            }
        } catch (\Exception $e) {
            // Ignorar erros e continuar com variáveis padrão
        }
        
        return $variables;
    }

    /**
     * Processar template com variáveis
     */
    public static function process(int $templateId, array $variables = []): string
    {
        $template = MessageTemplate::find($templateId);
        if (!$template) {
            throw new \InvalidArgumentException('Template não encontrado');
        }

        // Incrementar contador de uso
        MessageTemplate::incrementUsage($templateId);

        return MessageTemplate::processTemplate($template['content'], $variables);
    }

    /**
     * Obter templates disponíveis para uso
     */
    public static function getAvailable(?int $departmentId = null, ?string $channel = null): array
    {
        return MessageTemplate::getAvailable($departmentId, $channel);
    }

    /**
     * Obter variáveis disponíveis para templates
     */
    public static function getAvailableVariables(): array
    {
        return [
            'contact' => [
                'name' => 'Nome do contato',
                'phone' => 'Telefone do contato',
                'email' => 'Email do contato'
            ],
            'agent' => [
                'name' => 'Nome do agente',
                'email' => 'Email do agente'
            ],
            'conversation' => [
                'id' => 'ID da conversa',
                'subject' => 'Assunto da conversa'
            ],
            'date' => 'Data atual',
            'time' => 'Hora atual',
            'datetime' => 'Data e hora atuais'
        ];
    }
}

