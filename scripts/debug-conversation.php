<?php
/**
 * Diagnóstico de reabertura de conversa (quepasa / notificame).
 *
 * USO:
 *   php scripts/debug-conversation.php <conversation_id>
 *   php scripts/debug-conversation.php 11199
 *
 * Mostra: estado da conversa, conta de integração, settings de reabertura,
 * TODAS as conversas do contato (para detectar duplicata aberta / mesclada),
 * simulação do que o webhook resolveria e onde caiu a última mensagem do cliente.
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Helpers\Database;
use App\Models\Conversation;
use App\Models\Setting;
use App\Services\ConversationSettingsService;

$convId = (int)($argv[1] ?? 0);
if ($convId <= 0) {
    fwrite(STDERR, "Uso: php scripts/debug-conversation.php <conversation_id>\n");
    exit(1);
}

function hr(string $t = ''): void { echo "\n==================== {$t} ====================\n"; }
function val($v): string { return $v === null ? 'NULL' : (string)$v; }

$conv = Database::fetch("SELECT * FROM conversations WHERE id = ?", [$convId]);
if (!$conv) {
    fwrite(STDERR, "Conversa #{$convId} nao encontrada.\n");
    exit(1);
}

$contactId = (int)$conv['contact_id'];
$channel   = $conv['channel'] ?? 'whatsapp';
$intId     = $conv['integration_account_id'] ?? null;
$waId      = $conv['whatsapp_account_id'] ?? null;

hr("CONVERSA #{$convId}");
foreach ([
    'status','channel','contact_id','agent_id','integration_account_id',
    'whatsapp_account_id','is_merged','linked_account_ids','funnel_id',
    'funnel_stage_id','created_at','updated_at','resolved_at'
] as $k) {
    if (array_key_exists($k, $conv)) {
        printf("  %-22s : %s\n", $k, val($conv[$k]));
    }
}
$minSinceUpd = $conv['updated_at'] ? round((time() - strtotime($conv['updated_at'])) / 60, 1) : null;
printf("  %-22s : %s min\n", '(min desde updated_at)', val($minSinceUpd));

// Conta de integracao
$account = null;
if ($intId) {
    $account = Database::fetch("SELECT id, name, provider, channel, phone_number, status FROM integration_accounts WHERE id = ?", [$intId]);
}
hr("CONTA DE INTEGRACAO (integration_account_id={$intId})");
if ($account) {
    foreach ($account as $k => $v) { printf("  %-22s : %s\n", $k, val($v)); }
    echo "  >> Provider determina o webhook: 'notificame' = NotificameService | 'quepasa'/'native'/'evolution' = WhatsAppService\n";
} else {
    echo "  (sem integration_account_id ou conta nao encontrada)\n";
}

// Settings relevantes
hr("SETTINGS DE REABERTURA / AUTO-CLOSE");
$grace = (int)Setting::get('conversation_reopen_grace_period_minutes', 10);
printf("  %-40s : %s min\n", 'conversation_reopen_grace_period_minutes', $grace);
try {
    $convSettings = ConversationSettingsService::getSettings();
    $autoClose = $convSettings['auto_close'] ?? [];
    echo "  auto_close (fonte real - ConversationSettingsService):\n";
    echo "    " . json_encode($autoClose, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "  (erro ao ler ConversationSettingsService: " . $e->getMessage() . ")\n";
}

// Todas as conversas do contato
hr("TODAS AS CONVERSAS DO CONTATO #{$contactId}");
$all = Database::fetchAll(
    "SELECT id, status, channel, integration_account_id, whatsapp_account_id, is_merged, created_at, updated_at
     FROM conversations WHERE contact_id = ?
     ORDER BY (status='open') DESC, created_at DESC",
    [$contactId]
);
printf("  %-7s %-9s %-9s %-7s %-7s %-7s %-20s %-20s\n", 'ID','STATUS','CHANNEL','INT_ID','WA_ID','MERGED','CREATED','UPDATED');
foreach ($all as $c) {
    $mark = ((int)$c['id'] === $convId) ? ' <== alvo' : '';
    printf("  %-7s %-9s %-9s %-7s %-7s %-7s %-20s %-20s%s\n",
        $c['id'], $c['status'], $c['channel'], val($c['integration_account_id']),
        val($c['whatsapp_account_id']), val($c['is_merged']),
        val($c['created_at']), val($c['updated_at']), $mark);
}

// Simulacao do que o webhook resolveria
hr("SIMULACAO DO WEBHOOK (mesma conta da conversa alvo)");
$merged = null;
if ($intId || $waId) {
    try {
        $merged = \App\Services\ConversationMergeService::findMergedConversation($contactId, (int)($intId ?? $waId));
    } catch (\Throwable $e) { echo "  findMergedConversation erro: " . $e->getMessage() . "\n"; }
}
echo "  findMergedConversation()  -> " . ($merged ? "ID={$merged['id']} (status={$merged['status']})" : 'null') . "\n";

$picked = Conversation::findByContactAndChannel($contactId, $channel, $waId ? (int)$waId : null, $intId ? (int)$intId : null);
echo "  findByContactAndChannel() -> " . ($picked ? "ID={$picked['id']} (status={$picked['status']})" : 'null') . "\n";

$anyOpen = Conversation::findAnyOpenByContact($contactId, $channel);
echo "  findAnyOpenByContact()    -> " . ($anyOpen ? "ID={$anyOpen['id']} (status={$anyOpen['status']})" : 'null') . "\n";

$resolved = $merged ?: $picked;
echo "\n  >> Conversa que o webhook usaria: " . ($resolved ? "ID={$resolved['id']} (status={$resolved['status']})" : 'NENHUMA (criaria nova)') . "\n";
if ($resolved && (int)$resolved['id'] !== $convId) {
    echo "  >> ATENCAO: o webhook NAO escolheria a #{$convId} — escolheria a #{$resolved['id']}. A #{$convId} ficaria fechada.\n";
}
if ($resolved && (int)$resolved['id'] === $convId) {
    if (in_array($resolved['status'], ['closed','resolved'], true)) {
        $decision = ($minSinceUpd !== null && $minSinceUpd >= $grace)
            ? ($anyOpen ? "usaria a aberta #{$anyOpen['id']} (evita duplicata)" : "criaria NOVA conversa (grace {$grace}min ultrapassado)")
            : "REABRIRIA a #{$convId} (dentro do grace de {$grace}min)";
        echo "  >> Bloco de reabertura: status fechado + {$minSinceUpd}min vs grace {$grace}min => {$decision}\n";
    } else {
        echo "  >> #{$convId} ja esta '{$resolved['status']}' — bloco de reabertura nao se aplica.\n";
    }
}

// Ultimas mensagens da conversa alvo
hr("ULTIMAS 15 MENSAGENS DA #{$convId}");
$msgs = Database::fetchAll(
    "SELECT id, sender_type, sender_id, message_type, created_at, LEFT(content,60) AS preview
     FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 15",
    [$convId]
);
foreach (array_reverse($msgs) as $m) {
    printf("  #%-7s [%-7s] %-9s %-20s | %s\n",
        $m['id'], $m['sender_type'], $m['message_type'], $m['created_at'],
        str_replace(["\n","\r"], ' ', (string)$m['preview']));
}

// Historico de status (quem fechou/reabriu e quando)
hr("HISTORICO DE STATUS / ACOES (tabela activities)");
$acts = Database::fetchAll(
    "SELECT a.id, a.activity_type, a.user_id, u.name AS user_name, a.description, a.created_at
     FROM activities a
     LEFT JOIN users u ON u.id = a.user_id
     WHERE a.entity_type = 'conversation' AND a.entity_id = ?
     ORDER BY a.id DESC LIMIT 25",
    [$convId]
);
if (!$acts) {
    echo "  (nenhuma activity registrada para esta conversa)\n";
}
foreach (array_reverse($acts) as $a) {
    $actor = $a['user_id'] === null
        ? 'SISTEMA/AUTOMACAO (user_id NULL)'
        : ("user #{$a['user_id']} " . ($a['user_name'] ?? '?'));
    printf("  %-20s %-26s por %-30s | %s\n",
        $a['created_at'], $a['activity_type'], $actor,
        str_replace(["\n","\r"], ' ', (string)($a['description'] ?? '')));
}
echo "  >> conversation_closed com user_id NULL = fechada por cron/automacao (AutoClose, AutomationService, IA).\n";
echo "  >> conversation_closed com user_id de agente = fechada manualmente por esse agente.\n";

// Onde caiu a ultima mensagem do cliente (todas as conversas do contato)
hr("ULTIMAS MENSAGENS 'contact' EM TODAS AS CONVERSAS DO CONTATO");
$convIds = array_map(fn($c) => (int)$c['id'], $all);
if ($convIds) {
    $in = implode(',', $convIds);
    $clientMsgs = Database::fetchAll(
        "SELECT id, conversation_id, sender_type, message_type, created_at, LEFT(content,50) AS preview
         FROM messages
         WHERE conversation_id IN ({$in}) AND sender_type = 'contact'
         ORDER BY id DESC LIMIT 10"
    );
    foreach ($clientMsgs as $m) {
        $mark = ((int)$m['conversation_id'] === $convId) ? '' : '  <== caiu em OUTRA conversa';
        printf("  conv #%-7s msg #%-7s %-20s | %s%s\n",
            $m['conversation_id'], $m['id'], $m['created_at'],
            str_replace(["\n","\r"], ' ', (string)$m['preview']), $mark);
    }
}

hr("DIAGNOSTICO");
echo "  - Se a ultima msg do cliente caiu em OUTRA conversa: existe DUPLICATA ABERTA (findByContactAndChannel\n";
echo "    prioriza status='open'), por isso a #{$convId} fechada nao reabre.\n";
echo "  - Se findMergedConversation retornou outra: a conversa esta MESCLADA e o trafego vai para a principal.\n";
echo "  - Se a msg do cliente apareceu como sender_type='agent' na #{$convId}: foi classificada como 'fromme'\n";
echo "    (flag fromme/frominternal no payload) e salva como enviada — nao dispara reabertura.\n";
echo "  - Se nao ha msg recente do cliente em conversa nenhuma: a msg pode ter sido criada sob OUTRO contato\n";
echo "    (numero/LID divergente). Procure por contatos com o mesmo telefone.\n";
echo "  - Se findByContactAndChannel retorna a #{$convId} fechada e o grace ja passou: o webhook criaria NOVA\n";
echo "    conversa — confira se ela existe e veja a regra do periodo de graca ({$grace}min).\n";
echo "\n";
