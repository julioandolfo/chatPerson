<?php
/**
 * Model AutomationNode
 */

namespace App\Models;

use App\Helpers\Database;

class AutomationNode extends Model
{
    protected string $table = 'automation_nodes';
    protected string $primaryKey = 'id';
    protected array $fillable = ['automation_id', 'node_type', 'node_data', 'position_x', 'position_y'];
    protected bool $timestamps = true;

    /**
     * Tipos de nós disponíveis
     */
    public static function getNodeTypes(): array
    {
        return [
            'trigger' => [
                'label' => 'Gatilho',
                'icon' => 'ki-play',
                'color' => '#009ef7'
            ],
            'condition' => [
                'label' => 'Condição',
                'icon' => 'ki-question',
                'color' => '#ffc700'
            ],
            'action_send_message' => [
                'label' => 'Enviar Mensagem',
                'icon' => 'ki-send',
                'color' => '#50cd89'
            ],
            'action_assign_agent' => [
                'label' => 'Atribuir Agente',
                'icon' => 'ki-user',
                'color' => '#7239ea'
            ],
            'action_move_stage' => [
                'label' => 'Mover para Estágio',
                'icon' => 'ki-arrow-right',
                'color' => '#f1416c'
            ],
            'action_set_tag' => [
                'label' => 'Adicionar Tag',
                'icon' => 'ki-bookmark',
                'color' => '#181c32'
            ],
            'action_chatbot' => [
                'label' => 'Chatbot',
                'icon' => 'ki-robot',
                'color' => '#00d9ff'
            ],
            'action_create_conversation' => [
                'label' => 'Criar Conversa',
                'icon' => 'ki-message-text',
                'color' => '#009ef7'
            ],
            'action_set_tag' => [
                'label' => 'Adicionar Tag',
                'icon' => 'ki-bookmark',
                'color' => '#181c32'
            ],
            'delay' => [
                'label' => 'Aguardar',
                'icon' => 'ki-time',
                'color' => '#a1a5b7'
            ],
            'end' => [
                'label' => 'Fim',
                'icon' => 'ki-check',
                'color' => '#50cd89'
            ]
        ];
    }

    /**
     * Obter nós conectados a este nó
     */
    public static function getConnectedNodes(int $nodeId): array
    {
        $node = self::find($nodeId);
        if (!$node || empty($node['node_data']['connections'])) {
            return [];
        }
        
        $connectedIds = array_column($node['node_data']['connections'], 'target_node_id');
        if (empty($connectedIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($connectedIds), '?'));
        $sql = "SELECT * FROM automation_nodes WHERE id IN ({$placeholders})";
        return Database::fetchAll($sql, $connectedIds);
    }
}

