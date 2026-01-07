<?php

namespace App\Controllers;

use App\Helpers\Permission;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Auth;
use App\Models\ConversationActionButton;
use App\Models\ConversationActionStep;
use App\Services\ConversationActionButtonService;

class ConversationActionButtonController
{
    public function index(): void
    {
        Permission::abortIfCannot('conversations.actions.manage');

        $buttons = \App\Helpers\Database::fetchAll("SELECT * FROM conversation_action_buttons ORDER BY sort_order ASC, id ASC") ?? [];
        $steps = \App\Helpers\Database::fetchAll("SELECT * FROM conversation_action_steps ORDER BY sort_order ASC, id ASC") ?? [];

        $stepsByButton = [];
        foreach ($steps as $step) {
            $stepsByButton[$step['button_id']][] = $step;
        }

        Response::view('settings/action-buttons/index', [
            'buttons' => $buttons,
            'stepsByButton' => $stepsByButton
        ]);
    }

    public function store(): void
    {
        Permission::abortIfCannot('conversations.actions.manage');

        $data = [
            'name' => Request::post('name'),
            'description' => Request::post('description'),
            'color' => Request::post('color') ?: '#009ef7',
            'icon' => Request::post('icon') ?: 'ki-bolt',
            'sort_order' => (int)(Request::post('sort_order') ?? 0),
            'is_active' => (int)(Request::post('is_active') ?? 1)
        ];

        $buttonId = ConversationActionButton::create($data);

        $steps = Request::post('steps') ?? [];
        self::syncSteps($buttonId, $steps);

        Response::redirect('/settings/action-buttons');
    }

    public function update(int $id): void
    {
        Permission::abortIfCannot('conversations.actions.manage');

        $data = [
            'name' => Request::post('name'),
            'description' => Request::post('description'),
            'color' => Request::post('color') ?: '#009ef7',
            'icon' => Request::post('icon') ?: 'ki-bolt',
            'sort_order' => (int)(Request::post('sort_order') ?? 0),
            'is_active' => (int)(Request::post('is_active') ?? 1)
        ];

        ConversationActionButton::update($id, $data);

        $steps = Request::post('steps') ?? [];
        self::syncSteps($id, $steps);

        Response::redirect('/settings/action-buttons');
    }

    public function delete(int $id): void
    {
        Permission::abortIfCannot('conversations.actions.manage');
        ConversationActionButton::delete($id);
        Response::json(['success' => true]);
    }

    public function listForConversation(int $conversationId): void
    {
        Permission::abortIfCannot('conversations.actions.run');
        $buttons = ConversationActionButtonService::listWithSteps();
        Response::json(['success' => true, 'buttons' => $buttons]);
    }

    public function run(int $conversationId, int $buttonId): void
    {
        Permission::abortIfCannot('conversations.actions.run');

        try {
            $result = ConversationActionButtonService::run($conversationId, $buttonId, Auth::id());
            Response::json($result);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    private static function syncSteps(int $buttonId, array $steps): void
    {
        \App\Helpers\Database::execute("DELETE FROM conversation_action_steps WHERE button_id = ?", [$buttonId]);
        $order = 0;
        foreach ($steps as $step) {
            if (empty($step['type'])) {
                continue;
            }
            
            // Processar payload - pode vir como string JSON ou array
            $payload = $step['payload'] ?? [];
            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $payload = $decoded;
                }
            }
            
            ConversationActionStep::create([
                'button_id' => $buttonId,
                'type' => $step['type'],
                'payload' => json_encode($payload),
                'sort_order' => $order++
            ]);
        }
    }
}
