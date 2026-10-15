<?php
/**
 * StageAlertService
 * Alerta na tela (com som) para conversas paradas numa etapa do funil:
 * cliente esperando resposta há X minutos → avisa o agente.
 *
 * "Esperando resposta" = a última mensagem da conversa (sem contar notas internas e mensagens
 * de sistema) é do cliente. O tempo conta da primeira mensagem do cliente ainda sem resposta
 * ou da entrada na etapa, o que for mais recente.
 */

namespace App\Services;

use App\Helpers\Database;

class StageAlertService
{
    /** Sons disponíveis (public/assets/sounds) */
    public const SOUNDS = [
        'alert.mp3' => 'Alerta',
        'urgent.mp3' => 'Urgente',
        'sla-warning.mp3' => 'Aviso de SLA',
        'sla-breached.mp3' => 'SLA estourado',
        'chime.mp3' => 'Sino',
        'ding.mp3' => 'Ding',
        'pop.mp3' => 'Pop',
        'new-message.mp3' => 'Nova mensagem',
        'mention.mp3' => 'Menção',
    ];

    public const DEFAULTS = [
        'enabled' => false,
        'after_minutes' => 30,
        'sound' => 'alert.mp3',
        'repeat_minutes' => 5,
        'include_unassigned' => false,
        'notify_user_ids' => [],
        'only_business_hours' => false,
    ];

    private const MAX_MINUTES = 43200; // 30 dias
    private const MAX_RESULTS = 100;

    public static function getStageConfig(int $stageId): array
    {
        $row = Database::fetch("SELECT * FROM funnel_stage_alerts WHERE funnel_stage_id = ?", [$stageId]);
        return $row ? self::normalizeRow($row) : self::DEFAULTS;
    }

    public static function saveStageConfig(int $stageId, array $data): array
    {
        $stage = Database::fetch("SELECT id FROM funnel_stages WHERE id = ?", [$stageId]);
        if (!$stage) {
            throw new \InvalidArgumentException('Etapa não encontrada');
        }

        $enabled = self::toBool($data['enabled'] ?? false);
        $afterMinutes = (int)($data['after_minutes'] ?? 0);
        $repeatMinutes = (int)($data['repeat_minutes'] ?? 0);
        $sound = trim((string)($data['sound'] ?? ''));

        if ($afterMinutes < 1 || $afterMinutes > self::MAX_MINUTES) {
            throw new \InvalidArgumentException('O tempo sem resposta deve ficar entre 1 minuto e 30 dias.');
        }
        if ($repeatMinutes < 0 || $repeatMinutes > 1440) {
            throw new \InvalidArgumentException('A repetição do som deve ficar entre 0 (não repetir) e 1440 minutos.');
        }
        if ($sound !== '' && !isset(self::SOUNDS[$sound])) {
            throw new \InvalidArgumentException('Som inválido.');
        }

        $userIds = [];
        foreach ((array)($data['notify_user_ids'] ?? []) as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $userIds[$id] = $id;
            }
        }
        $userIds = array_values($userIds);
        if ($userIds) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $valid = Database::fetchAll("SELECT id FROM users WHERE id IN ($placeholders)", $userIds);
            $userIds = array_map('intval', array_column($valid, 'id'));
        }

        Database::execute(
            "INSERT INTO funnel_stage_alerts
                (funnel_stage_id, enabled, after_minutes, sound, repeat_minutes, include_unassigned, notify_user_ids, only_business_hours)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled),
                after_minutes = VALUES(after_minutes),
                sound = VALUES(sound),
                repeat_minutes = VALUES(repeat_minutes),
                include_unassigned = VALUES(include_unassigned),
                notify_user_ids = VALUES(notify_user_ids),
                only_business_hours = VALUES(only_business_hours)",
            [
                $stageId,
                $enabled ? 1 : 0,
                $afterMinutes,
                $sound !== '' ? $sound : null,
                $repeatMinutes,
                self::toBool($data['include_unassigned'] ?? false) ? 1 : 0,
                json_encode($userIds),
                self::toBool($data['only_business_hours'] ?? false) ? 1 : 0,
            ]
        );

        return self::getStageConfig($stageId);
    }

    /**
     * Conversas em alerta para o usuário
     *
     * @return array{has_rules: bool, alerts: array}
     */
    public static function getAlertsForUser(int $userId): array
    {
        $rules = Database::fetchAll(
            "SELECT a.*, fs.name AS stage_name, fs.color AS stage_color, f.name AS funnel_name
             FROM funnel_stage_alerts a
             INNER JOIN funnel_stages fs ON fs.id = a.funnel_stage_id
             LEFT JOIN funnels f ON f.id = fs.funnel_id
             WHERE a.enabled = 1"
        );
        if (!$rules) {
            return ['has_rules' => false, 'alerts' => []];
        }

        $config = [];
        $assignedStages = [];
        $unassignedStages = [];
        $watchedStages = [];
        $outsideHours = null;

        foreach ($rules as $row) {
            $rule = self::normalizeRow($row) + [
                'stage_name' => $row['stage_name'],
                'stage_color' => $row['stage_color'] ?? null,
                'funnel_name' => $row['funnel_name'] ?? null,
            ];
            if ($rule['only_business_hours']) {
                $outsideHours ??= self::isOutsideBusinessHours();
                if ($outsideHours) {
                    continue;
                }
            }
            $stageId = (int)$row['funnel_stage_id'];
            $config[$stageId] = $rule;

            if (in_array($userId, $rule['notify_user_ids'], true)) {
                $watchedStages[] = $stageId;
            } else {
                $assignedStages[] = $stageId;
                if ($rule['include_unassigned']) {
                    $unassignedStages[] = $stageId;
                }
            }
        }
        if (!$config) {
            return ['has_rules' => true, 'alerts' => []];
        }

        $scope = [];
        $params = [];
        if ($assignedStages) {
            $scope[] = '(c.agent_id = ? AND c.funnel_stage_id IN (' . self::placeholders($assignedStages) . '))';
            $params = array_merge($params, [$userId], $assignedStages);
        }
        if ($unassignedStages) {
            $scope[] = '((c.agent_id IS NULL OR c.agent_id = 0) AND c.funnel_stage_id IN (' . self::placeholders($unassignedStages) . '))';
            $params = array_merge($params, $unassignedStages);
        }
        if ($watchedStages) {
            $scope[] = '(c.funnel_stage_id IN (' . self::placeholders($watchedStages) . '))';
            $params = array_merge($params, $watchedStages);
        }

        $minMinutes = min(array_column($config, 'after_minutes'));
        $params[] = $minMinutes;

        // Mensagem "relevante" = não é nota interna nem mensagem de sistema
        $relevant = "m.sender_type <> 'system' AND COALESCE(m.message_type, 'text') NOT IN ('note', 'system')";

        $rows = Database::fetchAll(
            "SELECT x.*,
                    (SELECT MIN(m.created_at) FROM messages m
                      WHERE m.conversation_id = x.id AND m.sender_type = 'contact'
                        AND m.id > COALESCE(x.last_reply_id, 0)) AS first_waiting_at
            FROM (
                SELECT c.id, c.agent_id, c.funnel_id, c.funnel_stage_id, c.moved_at, c.department_id, c.status,
                       c.contact_id, c.channel,
                       ct.name AS contact_name, ct.last_name AS contact_last_name, ct.phone AS contact_phone,
                       u.name AS agent_name,
                       lm.sender_type AS last_sender, lm.content AS last_content, lm.message_type AS last_message_type,
                       (SELECT MAX(m.id) FROM messages m
                         WHERE m.conversation_id = c.id AND {$relevant} AND m.sender_type <> 'contact') AS last_reply_id
                FROM conversations c
                INNER JOIN messages lm ON lm.id = (
                    SELECT m.id FROM messages m
                    WHERE m.conversation_id = c.id AND {$relevant}
                    ORDER BY m.id DESC LIMIT 1
                )
                LEFT JOIN contacts ct ON ct.id = c.contact_id
                LEFT JOIN users u ON u.id = c.agent_id
                WHERE c.status IN ('open', 'pending')
                  AND COALESCE(c.is_spam, 0) = 0
                  AND COALESCE(c.is_merged, 0) = 0
                  AND (" . implode(' OR ', $scope) . ")
                  AND lm.sender_type = 'contact'
            ) x
            HAVING first_waiting_at <= NOW() - INTERVAL ? MINUTE
            LIMIT 500",
            $params
        );

        $alerts = [];
        $now = time();
        foreach ($rows as $row) {
            $rule = $config[(int)$row['funnel_stage_id']] ?? null;
            if (!$rule || empty($row['first_waiting_at'])) {
                continue;
            }

            // Entrou na etapa depois que o cliente escreveu: conta a partir da entrada
            $sinceTs = strtotime($row['first_waiting_at']);
            if (!empty($row['moved_at']) && strtotime($row['moved_at']) > $sinceTs) {
                $sinceTs = strtotime($row['moved_at']);
            }
            if ($sinceTs > $now) {
                continue;
            }

            $waitingMinutes = $rule['only_business_hours']
                ? self::businessMinutes($sinceTs, $now)
                : intdiv($now - $sinceTs, 60);
            if ($waitingMinutes < $rule['after_minutes']) {
                continue;
            }

            $isMine = (int)($row['agent_id'] ?? 0) === $userId;
            $isWatcher = in_array($userId, $rule['notify_user_ids'], true);
            if (!$isMine && !$isWatcher && !\App\Services\PermissionService::canViewConversation($userId, $row)) {
                continue;
            }

            $alerts[] = [
                'conversation_id' => (int)$row['id'],
                // Muda quando o agente responde ou a conversa troca de etapa (novo episódio de espera)
                'alert_key' => $row['id'] . ':' . $row['funnel_stage_id'] . ':' . (int)($row['last_reply_id'] ?? 0),
                'contact_name' => trim(($row['contact_name'] ?? '') . ' ' . ($row['contact_last_name'] ?? '')) ?: ($row['contact_phone'] ?? 'Contato'),
                'contact_phone' => $row['contact_phone'] ?? null,
                'channel' => $row['channel'] ?? null,
                'stage_id' => (int)$row['funnel_stage_id'],
                'stage_name' => $rule['stage_name'],
                'stage_color' => $rule['stage_color'],
                'funnel_name' => $rule['funnel_name'],
                'agent_name' => $row['agent_name'] ?? null,
                'assigned_to_me' => $isMine,
                'waiting_since' => date('c', $sinceTs),
                'waiting_minutes' => $waitingMinutes,
                'threshold_minutes' => $rule['after_minutes'],
                'last_message' => self::preview($row['last_content'] ?? '', $row['last_message_type'] ?? 'text'),
                'sound' => $rule['sound'],
                'repeat_minutes' => $rule['repeat_minutes'],
            ];
            if (count($alerts) >= self::MAX_RESULTS) {
                break;
            }
        }

        // Quem espera há mais tempo primeiro
        usort($alerts, fn($a, $b) => $b['waiting_minutes'] <=> $a['waiting_minutes']);

        return ['has_rules' => true, 'alerts' => $alerts];
    }

    private static function normalizeRow(array $row): array
    {
        $userIds = json_decode((string)($row['notify_user_ids'] ?? '[]'), true);
        return [
            'enabled' => !empty($row['enabled']),
            'after_minutes' => max(1, (int)$row['after_minutes']),
            'sound' => isset(self::SOUNDS[$row['sound'] ?? '']) ? $row['sound'] : null,
            'repeat_minutes' => max(0, (int)$row['repeat_minutes']),
            'include_unassigned' => !empty($row['include_unassigned']),
            'notify_user_ids' => is_array($userIds) ? array_values(array_map('intval', $userIds)) : [],
            'only_business_hours' => !empty($row['only_business_hours']),
        ];
    }

    private static function isOutsideBusinessHours(): bool
    {
        try {
            $now = new \DateTime();
            return \App\Helpers\WorkingHoursCalculator::getNextWorkingTime($now)->getTimestamp() > $now->getTimestamp() + 60;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function businessMinutes(int $fromTs, int $toTs): int
    {
        try {
            return \App\Helpers\WorkingHoursCalculator::calculateWorkingMinutes(
                (new \DateTime())->setTimestamp($fromTs),
                (new \DateTime())->setTimestamp($toTs)
            );
        } catch (\Throwable $e) {
            return intdiv($toTs - $fromTs, 60);
        }
    }

    private static function preview(?string $content, ?string $type): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$content)));
        if ($text === '') {
            $labels = ['image' => '📷 Imagem', 'audio' => '🎤 Áudio', 'video' => '🎥 Vídeo', 'document' => '📄 Documento', 'sticker' => 'Figurinha', 'location' => '📍 Localização'];
            return $labels[$type ?? ''] ?? 'Mensagem';
        }
        return mb_strlen($text) > 90 ? mb_substr($text, 0, 90) . '…' : $text;
    }

    private static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    private static function toBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
