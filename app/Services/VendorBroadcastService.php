<?php
/**
 * Service VendorBroadcastService
 * Disparos de templates NotificaMe por vendedores
 * Limite de 30 envios/dia por agente
 */

namespace App\Services;

use App\Models\IntegrationAccount;
use App\Models\User;
use App\Helpers\Database;

class VendorBroadcastService
{
    const DAILY_LIMIT = 30;

    /**
     * Contar envios do agente hoje
     */
    public static function getSentToday(int $agentId): int
    {
        $sql = "SELECT COALESCE(SUM(vb.total_sent), 0) AS total
                FROM vendor_broadcasts vb
                WHERE vb.agent_id = ?
                  AND DATE(vb.created_at) = CURDATE()
                  AND vb.status NOT IN ('cancelled')";
        $row = Database::fetch($sql, [$agentId]);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Limite restante do agente hoje
     */
    public static function getRemainingToday(int $agentId): int
    {
        return max(0, self::DAILY_LIMIT - self::getSentToday($agentId));
    }

    /**
     * Listar contas WhatsApp NotificaMe ativas
     */
    public static function getAvailableAccounts(): array
    {
        $sql = "SELECT id, name, phone_number, account_id, status
                FROM integration_accounts
                WHERE provider = 'notificame'
                  AND channel = 'whatsapp'
                  AND status = 'active'
                ORDER BY name ASC";
        return Database::fetchAll($sql);
    }

    /**
     * Listar templates de uma conta NotificaMe
     */
    public static function getTemplates(int $accountId): array
    {
        try {
            $templates = NotificameService::listTemplates($accountId);
            // Filtrar apenas APPROVED
            return array_values(array_filter($templates, function ($t) {
                $status = strtoupper($t['status'] ?? '');
                return $status === 'APPROVED' || $status === 'approved';
            }));
        } catch (\Exception $e) {
            error_log("VendorBroadcast getTemplates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obter clientes do vendedor (que compraram com ele)
     * Retorna contatos com telefone que podem receber WhatsApp
     */
    public static function getVendorClients(int $agentId, ?string $search = null, int $limit = 100): array
    {
        $agent = User::find($agentId);
        $sellerId = $agent['woocommerce_seller_id'] ?? null;

        if (!$sellerId) {
            return [];
        }

        $validStatuses = ['processing', 'completed', 'producao', 'designer',
            'pedido-enviado', 'pedido-entregue', 'etiqueta-gerada'];
        $statusIn = implode(',', array_fill(0, count($validStatuses), '?'));

        $params = [$sellerId];
        $params = array_merge($params, $validStatuses);

        $searchWhere = '';
        if (!empty($search)) {
            $searchWhere = "AND (c.name LIKE ? OR c.last_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $params[] = $limit;

        $sql = "SELECT
                    c.id AS contact_id,
                    c.name AS contact_name,
                    c.last_name AS contact_last_name,
                    c.phone AS contact_phone,
                    c.email AS contact_email,
                    c.whatsapp_id,
                    COUNT(oc.id) AS order_count,
                    SUM(oc.order_total) AS total_spent,
                    MAX(oc.order_date) AS last_order_date
                FROM woocommerce_order_cache oc
                INNER JOIN contacts c ON c.id = oc.contact_id
                WHERE oc.seller_id = ?
                  AND oc.order_status IN ({$statusIn})
                  AND oc.contact_id IS NOT NULL AND oc.contact_id > 0
                  AND (c.phone IS NOT NULL AND c.phone != '')
                  {$searchWhere}
                GROUP BY c.id, c.name, c.last_name, c.phone, c.email, c.whatsapp_id
                ORDER BY last_order_date DESC
                LIMIT ?";

        $rows = Database::fetchAll($sql, $params);

        foreach ($rows as &$row) {
            $row['full_name'] = trim(($row['contact_name'] ?? '') . ' ' . ($row['contact_last_name'] ?? ''));
            $row['total_spent'] = (float)($row['total_spent'] ?? 0);
            $row['order_count'] = (int)($row['order_count'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    /**
     * Criar e executar disparo
     */
    public static function createBroadcast(
        int $agentId,
        int $accountId,
        string $templateName,
        string $templateLanguage,
        array $contacts,
        array $templateParams = []
    ): array {
        // Validar limite diário
        $remaining = self::getRemainingToday($agentId);
        $contactCount = count($contacts);

        if ($contactCount === 0) {
            return ['success' => false, 'message' => 'Nenhum contato selecionado.'];
        }

        if ($contactCount > $remaining) {
            return [
                'success' => false,
                'message' => "Limite diário excedido. Restam {$remaining} envios hoje. Você selecionou {$contactCount} contatos."
            ];
        }

        // Validar conta
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame' || $account['status'] !== 'active') {
            return ['success' => false, 'message' => 'Conta WhatsApp inválida ou inativa.'];
        }

        // Criar broadcast
        $broadcastId = Database::insert(
            "INSERT INTO vendor_broadcasts (agent_id, integration_account_id, template_name, template_language, status, total_contacts)
             VALUES (?, ?, ?, ?, 'sending', ?)",
            [$agentId, $accountId, $templateName, $templateLanguage, $contactCount]
        );

        if (!$broadcastId) {
            return ['success' => false, 'message' => 'Erro ao criar disparo.'];
        }

        // Inserir mensagens e enviar
        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($contacts as $contact) {
            $contactId = (int)($contact['contact_id'] ?? 0);
            $phone = $contact['phone'] ?? '';
            $name = $contact['name'] ?? '';

            if (empty($phone)) {
                $failed++;
                continue;
            }

            // Resolver parametros personalizados por contato
            $params = self::resolveParams($templateParams, $contact);

            // Inserir registro da mensagem
            $paramsJson = !empty($params) ? json_encode($params) : null;
            $messageId = Database::insert(
                "INSERT INTO vendor_broadcast_messages (broadcast_id, contact_id, contact_phone, contact_name, template_params, status)
                 VALUES (?, ?, ?, ?, ?, 'pending')",
                [$broadcastId, $contactId, $phone, $name, $paramsJson]
            );

            // Enviar template
            try {
                $result = NotificameService::sendTemplate(
                    $accountId,
                    $phone,
                    $templateName,
                    $params,
                    $templateLanguage
                );

                if ($result['success'] ?? false) {
                    $sent++;
                    Database::execute(
                        "UPDATE vendor_broadcast_messages SET status = 'sent', external_message_id = ?, sent_at = ? WHERE id = ?",
                        [$result['message_id'] ?? null, date('Y-m-d H:i:s'), $messageId]
                    );
                } else {
                    $failed++;
                    $errorMsg = $result['message'] ?? 'Erro desconhecido';
                    $errors[] = "{$name} ({$phone}): {$errorMsg}";
                    Database::execute(
                        "UPDATE vendor_broadcast_messages SET status = 'failed', error_message = ? WHERE id = ?",
                        [$errorMsg, $messageId]
                    );
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "{$name} ({$phone}): " . $e->getMessage();
                Database::execute(
                    "UPDATE vendor_broadcast_messages SET status = 'failed', error_message = ? WHERE id = ?",
                    [$e->getMessage(), $messageId]
                );
            }

            // Pequeno delay para não sobrecarregar API
            usleep(500000); // 0.5s
        }

        // Atualizar broadcast
        $finalStatus = $failed === $contactCount ? 'failed' : 'completed';
        $errorSummary = !empty($errors) ? implode("\n", array_slice($errors, 0, 10)) : null;
        Database::execute(
            "UPDATE vendor_broadcasts SET status = ?, total_sent = ?, total_failed = ?, error_message = ?, completed_at = ? WHERE id = ?",
            [$finalStatus, $sent, $failed, $errorSummary, date('Y-m-d H:i:s'), $broadcastId]
        );

        return [
            'success' => true,
            'broadcast_id' => $broadcastId,
            'total_sent' => $sent,
            'total_failed' => $failed,
            'errors' => array_slice($errors, 0, 5),
            'message' => "{$sent} mensagens enviadas" . ($failed > 0 ? ", {$failed} falharam" : '') . ".",
        ];
    }

    /**
     * Substituir placeholders nos params do template
     */
    private static function resolveParams(array $templateParams, array $contact): array
    {
        $replacements = [
            '{{nome}}' => $contact['name'] ?? '',
            '{{telefone}}' => $contact['phone'] ?? '',
            '{{email}}' => $contact['email'] ?? '',
        ];

        $resolved = [];
        foreach ($templateParams as $param) {
            $value = (string)$param;
            foreach ($replacements as $placeholder => $replacement) {
                $value = str_ireplace($placeholder, $replacement, $value);
            }
            $resolved[] = $value;
        }

        return $resolved;
    }

    /**
     * Histórico de disparos do agente
     */
    public static function getBroadcastHistory(int $agentId, int $limit = 20): array
    {
        $sql = "SELECT vb.*, ia.name AS account_name, ia.phone_number AS account_phone
                FROM vendor_broadcasts vb
                LEFT JOIN integration_accounts ia ON ia.id = vb.integration_account_id
                WHERE vb.agent_id = ?
                ORDER BY vb.created_at DESC
                LIMIT ?";
        return Database::fetchAll($sql, [$agentId, $limit]);
    }

    /**
     * Detalhes de um disparo
     */
    public static function getBroadcastDetails(int $broadcastId, int $agentId): ?array
    {
        $sql = "SELECT vb.*, ia.name AS account_name, ia.phone_number AS account_phone
                FROM vendor_broadcasts vb
                LEFT JOIN integration_accounts ia ON ia.id = vb.integration_account_id
                WHERE vb.id = ? AND vb.agent_id = ?";
        $broadcast = Database::fetch($sql, [$broadcastId, $agentId]);

        if (!$broadcast) return null;

        $broadcast['messages'] = Database::fetchAll(
            "SELECT * FROM vendor_broadcast_messages WHERE broadcast_id = ? ORDER BY id ASC",
            [$broadcastId]
        );

        return $broadcast;
    }
}
