<?php
/**
 * Debug de recebimento de mensagens do WhatsApp por número.
 *
 * Responde à pergunta: "o cliente mandou mensagem, chegou no WhatsApp, mas não
 * apareceu no CRM — o que aconteceu?"
 *
 * Uso: /debug-whatsapp-numero.php?phone=5511999927468
 *      /debug-whatsapp-numero.php?phone=5511999927468&horas=72
 *
 * Ordem de investigação da tela:
 *  1. Auditoria de webhooks   → o webhook chegou? foi descartado? por qual regra?
 *  2. Contato(s)              → existe contato? ficou preso em @lid?
 *  3. Conversas e mensagens   → a mensagem foi gravada em algum lugar?
 *  4. Linhas de log           → rastro bruto em logs/quepasa.log
 */

$appConfig = require __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Helpers/autoload.php';

if (!empty($appConfig['timezone'])) {
    date_default_timezone_set($appConfig['timezone']);
}

use App\Helpers\Database;
use App\Models\Contact;

$phoneInput = isset($_GET['phone']) ? trim((string)$_GET['phone']) : '';
$hours      = isset($_GET['horas']) ? max(1, min(720, (int)$_GET['horas'])) : 48;
$dataRef    = isset($_GET['data']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['data'])
                ? (string)$_GET['data'] : date('Y-m-d');
$deInput    = isset($_GET['de']) ? trim((string)$_GET['de']) : '';
$ateInput   = isset($_GET['ate']) ? trim((string)$_GET['ate']) : '';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/**
 * Extrai o timestamp "Y-m-d H:i:s" do prefixo de uma linha de log.
 */
function logLineTimestamp(string $line): ?string
{
    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Busca binária pelo primeiro byte cujo timestamp de linha é >= $target.
 * Evita varrer um arquivo de vários GB para achar um instante específico.
 */
function logSeekToTimestamp($fh, int $size, string $target): int
{
    $low = 0;
    $high = $size;
    $best = 0;

    // 40 iterações cobrem qualquer tamanho realista de arquivo
    for ($i = 0; $i < 40 && $low < $high; $i++) {
        $mid = intdiv($low + $high, 2);
        fseek($fh, $mid);
        if ($mid > 0) {
            fgets($fh); // alinhar no início da próxima linha completa
        }

        $ts = null;
        // pular linhas sem prefixo de data (payloads multi-linha)
        for ($j = 0; $j < 50; $j++) {
            $line = fgets($fh);
            if ($line === false) {
                break;
            }
            $ts = logLineTimestamp($line);
            if ($ts !== null) {
                break;
            }
        }

        if ($ts === null) {
            $high = $mid;
            continue;
        }

        if ($ts < $target) {
            $best = $low = ftell($fh);
        } else {
            $high = $mid;
        }
    }

    return $best;
}

/**
 * Marcos do processWebhook, na ordem em que o código os grava.
 * O último marco atingido diz onde a request parou — e o marco seguinte,
 * que ficou faltando, aponta o trecho de código responsável.
 */
function marcosWebhook(): array
{
    return [
        'entrou' => [
            'needle' => 'processWebhook - Raw body',
            'label'  => 'Webhook entrou no processWebhook',
            'falha'  => 'Nada a apontar — é o primeiro marco.',
        ],
        'parse' => [
            'needle' => 'processWebhook - Payload recebido',
            'label'  => 'Payload lido (trackid/chatid/from extraídos)',
            'falha'  => 'Morreu na leitura do payload.',
        ],
        'conta' => [
            'needle' => 'CONTA IDENTIFICADA PARA WEBHOOK',
            'label'  => 'Conta WhatsApp identificada',
            'falha'  => 'Conta não resolvida: sem trackid/chatid/wid que bata com uma integração cadastrada.',
        ],
        'midia' => [
            'needle' => 'processWebhook - media detect',
            'label'  => 'Extração de mídia concluída',
            'falha'  => 'Morreu na extração de mídia. Causa conhecida: campo "url" vindo como objeto de preview de link ({"reference":...}) estoura TypeError em str_contains(). Corrigido no commit 633e8ad.',
        ],
        'contato' => [
            'needle' => 'Buscando contato pelo telefone normalizado',
            'label'  => 'Resolução de contato iniciada',
            'falha'  => 'Parou entre a mídia e o contato — normalmente a guarda de "dados incompletos" ou detecção de mensagem enviada (fromme).',
        ],
        'conversa' => [
            'needle' => 'Iniciando criação/busca de conversa',
            'label'  => 'Criação/busca de conversa iniciada',
            'falha'  => 'Contato resolvido mas parou antes da conversa. Causa conhecida: resolução de @lid e busca de avatar estourando o max_execution_time. Corrigido nos commits 053ca1d e fdc5f9c.',
        ],
        'gravou' => [
            'needle' => 'Mensagem processada com sucesso',
            'label'  => 'Mensagem gravada no CRM',
            'falha'  => 'Conversa resolvida mas a mensagem não foi gravada — ver exceção do ConversationService no bloco bruto abaixo.',
        ],
    ];
}

/** Normaliza "16:40" → "Y-m-d 16:40:00" usando a data de referência */
function normalizeJanela(string $value, string $refDate, string $defaultSeconds): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^\d{2}:\d{2}$/', $value)) {
        return "{$refDate} {$value}:{$defaultSeconds}";
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
        return "{$refDate} {$value}";
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        return "{$value}:{$defaultSeconds}";
    }
    return $value;
}

/**
 * Variantes do número para busca (com/sem 9º dígito, com/sem 55, sufixos WhatsApp)
 */
function phoneVariants(string $phone): array
{
    $normalized = Contact::normalizePhoneNumber($phone);
    $variants = [$normalized];

    $national = strpos($normalized, '55') === 0 ? substr($normalized, 2) : $normalized;

    if (strlen($national) === 10) {
        $variants[] = '55' . substr($national, 0, 2) . '9' . substr($national, 2);
    }
    if (strlen($national) === 11 && substr($national, 2, 1) === '9') {
        $variants[] = '55' . substr($national, 0, 2) . substr($national, 3);
    }
    $variants[] = $national;

    $all = [];
    foreach (array_unique(array_filter($variants)) as $v) {
        $all[] = $v;
        $all[] = '+' . $v;
        $all[] = $v . '@s.whatsapp.net';
        $all[] = $v . '@c.us';
        $all[] = $v . '@lid';
    }

    return array_values(array_unique($all));
}

$janelaDe  = normalizeJanela($deInput, $dataRef, '00');
$janelaAte = normalizeJanela($ateInput, $dataRef, '59');

$normalized   = $phoneInput !== '' ? Contact::normalizePhoneNumber($phoneInput) : '';
$variants     = $phoneInput !== '' ? phoneVariants($phoneInput) : [];
$audit        = [];
$auditExists  = true;
$contacts     = [];
$conversations = [];
$messages     = [];
$logLines     = [];
$webhookBlocos = [];
$errors       = [];

if ($phoneInput !== '') {
    // ── 1. Auditoria de webhooks ────────────────────────────────────────────
    try {
        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        $params = array_merge($variants, $variants, [$hours]);

        $audit = Database::fetchAll(
            "SELECT id, request_id, provider, entrypoint, account_id, external_id, from_raw,
                    phone, message_type, direction, status, drop_reason, detail,
                    conversation_id, message_id, duration_ms, created_at
               FROM whatsapp_webhook_audit
              WHERE (phone IN ({$placeholders}) OR from_raw IN ({$placeholders}))
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
              ORDER BY id DESC
              LIMIT 200",
            $params
        );
    } catch (\Throwable $e) {
        $auditExists = false;
        $errors[] = "Auditoria indisponível: " . $e->getMessage()
            . " — rode a migration 156_create_whatsapp_webhook_audit_table.";
    }

    // ── 2. Contatos ─────────────────────────────────────────────────────────
    try {
        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        $contacts = Database::fetchAll(
            "SELECT id, name, phone, whatsapp_id, created_at, updated_at
               FROM contacts
              WHERE phone IN ({$placeholders}) OR whatsapp_id IN ({$placeholders})
              ORDER BY id DESC
              LIMIT 50",
            array_merge($variants, $variants)
        );
    } catch (\Throwable $e) {
        $errors[] = "Erro ao buscar contatos: " . $e->getMessage();
    }

    $contactIds = array_column($contacts, 'id');

    // ── 3. Conversas e mensagens ────────────────────────────────────────────
    if (!empty($contactIds)) {
        $in = implode(',', array_fill(0, count($contactIds), '?'));
        try {
            $conversations = Database::fetchAll(
                "SELECT c.id, c.contact_id, c.status, c.channel, c.integration_account_id,
                        c.whatsapp_account_id, c.funnel_id, c.agent_id, c.created_at, c.updated_at,
                        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) AS total_mensagens
                   FROM conversations c
                  WHERE c.contact_id IN ({$in})
                  ORDER BY c.id DESC
                  LIMIT 50",
                $contactIds
            );

            $messages = Database::fetchAll(
                "SELECT m.id, m.conversation_id, m.sender_type, m.sender_id, m.message_type,
                        m.external_id, LEFT(m.content, 200) AS content, m.created_at
                   FROM messages m
                   JOIN conversations c ON c.id = m.conversation_id
                  WHERE c.contact_id IN ({$in})
                    AND m.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                  ORDER BY m.id DESC
                  LIMIT 100",
                array_merge($contactIds, [$hours])
            );
        } catch (\Throwable $e) {
            $errors[] = "Erro ao buscar conversas/mensagens: " . $e->getMessage();
        }
    }

    // ── 4. Linhas de log ────────────────────────────────────────────────────
    // quepasa.log tem centenas de MB / GBs. As linhas são cronológicas e
    // prefixadas com [Y-m-d H:i:s], então localizamos o ponto por busca binária
    // em vez de varrer o arquivo inteiro.
    $logFile = __DIR__ . '/../logs/quepasa.log';

    if (!is_readable($logFile)) {
        $errors[] = "logs/quepasa.log não encontrado ou sem permissão de leitura.";
    } else {
        $fh   = @fopen($logFile, 'r');
        $size = @filesize($logFile);

        if ($fh && $size !== false) {
            if ($janelaDe !== '') {
                // MODO JANELA: mostra TODAS as linhas do intervalo, sem filtrar por
                // número. É o que revela o erro real — as linhas da falha (exceção,
                // criação de conversa, etc) normalmente não contêm o telefone.
                $offset = logSeekToTimestamp($fh, $size, $janelaDe);
                fseek($fh, $offset);

                while (($line = fgets($fh)) !== false) {
                    $ts = logLineTimestamp($line);
                    if ($ts !== null && $janelaAte !== '' && $ts > $janelaAte) {
                        break;
                    }
                    $logLines[] = rtrim($line);
                    if (count($logLines) >= 3000) {
                        $logLines[] = '... [truncado em 3000 linhas — reduza a janela]';
                        break;
                    }
                }
            } else {
                // MODO NÚMERO: agrupa o log em BLOCOS de webhook e, para cada bloco
                // que mencione o telefone, marca até onde o processamento chegou.
                // É isso que responde "por que não chegou": o último marco atingido
                // aponta a linha exata onde a request morreu.
                $needles = array_values(array_unique(array_filter([
                    $normalized,
                    preg_replace('/^55/', '', $normalized),
                    $phoneInput,
                ])));

                if ($dataRef !== '') {
                    // Restringe ao dia informado — busca binária evita varrer o arquivo inteiro
                    fseek($fh, logSeekToTimestamp($fh, $size, $dataRef . ' 00:00:00'));
                    $limiteData = $dataRef . ' 23:59:59';
                } else {
                    $chunk = 40 * 1024 * 1024;
                    if ($size > $chunk) {
                        fseek($fh, -$chunk, SEEK_END);
                        fgets($fh);
                    }
                    $limiteData = null;
                }

                $blocoAtual = null;
                $lidos = 0;

                $fecharBloco = static function (?array $bloco) use (&$webhookBlocos) {
                    if ($bloco !== null && $bloco['match']) {
                        $webhookBlocos[] = $bloco;
                    }
                };

                while (($line = fgets($fh)) !== false) {
                    $lidos++;
                    $ts = logLineTimestamp($line);

                    if ($ts !== null && $limiteData !== null && $ts > $limiteData) {
                        break;
                    }

                    // Início de um novo webhook
                    if (strpos($line, 'processWebhook - Raw body') !== false
                        || strpos($line, '=== WEBHOOK WHATSAPP RECEBIDO') !== false) {
                        $fecharBloco($blocoAtual);
                        $blocoAtual = ['ts' => $ts, 'linhas' => [], 'match' => false, 'marcos' => []];
                    }

                    if ($blocoAtual === null) {
                        continue;
                    }

                    foreach ($needles as $needle) {
                        if (strpos($line, $needle) !== false) {
                            $blocoAtual['match'] = true;
                            break;
                        }
                    }

                    if (count($blocoAtual['linhas']) < 250) {
                        $blocoAtual['linhas'][] = rtrim($line);
                    }

                    foreach (marcosWebhook() as $chave => $m) {
                        if (!isset($blocoAtual['marcos'][$chave]) && strpos($line, $m['needle']) !== false) {
                            $blocoAtual['marcos'][$chave] = $ts ?? '';
                        }
                    }

                    // Trava de segurança: quepasa.log pode ter centenas de MB por dia
                    if ($lidos > 4000000 || count($webhookBlocos) >= 40) {
                        $errors[] = 'Varredura interrompida por limite de segurança. Use "De/Até" para reduzir o intervalo.';
                        break;
                    }
                }

                $fecharBloco($blocoAtual);

                // Só as linhas do bloco mais recente vão para a visão bruta
                if (!empty($webhookBlocos)) {
                    $logLines = end($webhookBlocos)['linhas'];
                }
            }

            fclose($fh);
        }
    }
}

/** Resumo automático: o que aconteceu com esse número */
$verdict = null;
if ($phoneInput !== '') {
    $dropped = array_values(array_filter($audit, fn($a) => in_array($a['status'], ['dropped', 'error', 'fatal'], true)));

    // O log costuma ser mais conclusivo que o banco: se um webhook do número parou
    // no meio, o marco faltante diz exatamente onde.
    $blocoIncompleto = null;
    foreach (array_reverse($webhookBlocos) as $b) {
        if (!isset($b['marcos']['gravou'])) {
            $blocoIncompleto = $b;
            break;
        }
    }

    if ($blocoIncompleto !== null) {
        $marcos = marcosWebhook();
        $ultimo = null;
        $primeiroFaltante = null;
        foreach ($marcos as $chave => $m) {
            if (isset($blocoIncompleto['marcos'][$chave])) {
                $ultimo = $m['label'];
            } elseif ($primeiroFaltante === null) {
                $primeiroFaltante = $m;
            }
        }
        $verdict = ['bad',
            'Webhook de ' . $blocoIncompleto['ts'] . ' CHEGOU mas NÃO gravou a mensagem. '
            . 'Último marco atingido: "' . ($ultimo ?? 'nenhum') . '". '
            . 'Parou antes de: "' . ($primeiroFaltante['label'] ?? '—') . '". '
            . ($primeiroFaltante['falha'] ?? '')];
    } elseif (!$auditExists) {
        $verdict = ['warn', 'Auditoria não instalada — rode a migration 156 para capturar os próximos webhooks. Abaixo, só o que já existe no banco e nos logs.'];
    } elseif (empty($audit)) {
        $verdict = ['bad', 'NENHUM webhook recebido para este número nas últimas ' . $hours . 'h. O problema está ANTES do CRM: verifique a entrega do webhook no Quepasa (URL, fila, retentativas) e o access log do servidor.'];
    } elseif (!empty($dropped)) {
        $first = $dropped[0];
        $verdict = ['bad', 'Webhook CHEGOU e foi descartado/falhou. Status: ' . $first['status']
            . ' | Motivo: ' . ($first['drop_reason'] ?: '—') . ' | Detalhe: ' . ($first['detail'] ?: '—')];
    } else {
        $verdict = ['ok', 'Webhooks recebidos e processados. Se a mensagem não aparece na tela, o problema está na exibição/filtro da conversa, não na ingestão.'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Debug WhatsApp por número</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; margin: 24px; background:#f6f7f9; color:#1f2430; }
  h1 { font-size: 20px; margin: 0 0 4px; }
  h2 { font-size: 15px; margin: 28px 0 8px; }
  .sub { color:#6b7280; font-size: 13px; margin-bottom: 18px; }
  form { background:#fff; padding:14px; border-radius:8px; border:1px solid #e3e6ea; margin-bottom:18px; }
  input[type=text], input[type=number] { padding:7px 9px; border:1px solid #cbd2da; border-radius:6px; font-size:14px; }
  button { padding:7px 16px; border:0; border-radius:6px; background:#2563eb; color:#fff; font-size:14px; cursor:pointer; }
  table { border-collapse: collapse; width:100%; background:#fff; font-size:12px; border:1px solid #e3e6ea; border-radius:8px; overflow:hidden; }
  th, td { padding:6px 8px; border-bottom:1px solid #eef0f3; text-align:left; vertical-align:top; }
  th { background:#f0f2f5; font-weight:600; white-space:nowrap; }
  tr:last-child td { border-bottom:0; }
  code { background:#eef0f3; padding:1px 4px; border-radius:3px; font-size:11px; }
  pre { background:#111827; color:#e5e7eb; padding:12px; border-radius:8px; overflow:auto; max-height:420px; font-size:11px; line-height:1.5; }
  .verdict { padding:12px 14px; border-radius:8px; margin-bottom:18px; font-size:14px; font-weight:500; }
  .verdict.ok   { background:#e7f6ec; border:1px solid #a7d8b8; color:#1a6135; }
  .verdict.bad  { background:#fdeceb; border:1px solid #f3b5b0; color:#8f2018; }
  .verdict.warn { background:#fdf5e2; border:1px solid #ecd08a; color:#7a5807; }
  .tag { display:inline-block; padding:1px 6px; border-radius:4px; font-size:11px; font-weight:600; }
  .st-processed { background:#e7f6ec; color:#1a6135; }
  .st-dropped   { background:#fdeceb; color:#8f2018; }
  .st-error, .st-fatal { background:#3a0d09; color:#ffd7d2; }
  .st-received  { background:#fdf5e2; color:#7a5807; }
  .empty { color:#6b7280; font-size:13px; padding:10px 0; }
</style>
</head>
<body>

<h1>Debug WhatsApp por número</h1>
<div class="sub">Rastreia o caminho completo de uma mensagem: webhook recebido → contato → conversa → mensagem.</div>

<form method="get">
  <div>
    Número: <input type="text" name="phone" value="<?= h($phoneInput) ?>" placeholder="5511999927468" size="22">
    &nbsp;Janela do banco (horas): <input type="number" name="horas" value="<?= (int)$hours ?>" min="1" max="720" size="5">
  </div>
  <div style="margin-top:10px; padding-top:10px; border-top:1px solid #eef0f3;">
    <strong>Trecho bruto do log</strong> — mostra <em>todas</em> as linhas do intervalo, sem filtrar por número.
    Use quando souber o horário: o erro real quase nunca contém o telefone.<br>
    Data: <input type="text" name="data" value="<?= h($dataRef) ?>" size="10" placeholder="2026-08-10">
    &nbsp;De: <input type="text" name="de" value="<?= h($deInput) ?>" size="8" placeholder="16:44">
    &nbsp;Até: <input type="text" name="ate" value="<?= h($ateInput) ?>" size="8" placeholder="16:50">
  </div>
  <div style="margin-top:10px;"><button type="submit">Investigar</button></div>
</form>

<?php if ($phoneInput !== ''): ?>
  <div class="sub">
    Normalizado: <code><?= h($normalized) ?></code> &nbsp;|&nbsp;
    Variantes testadas: <?= count($variants) ?>
  </div>

  <?php if ($verdict): ?>
    <div class="verdict <?= h($verdict[0]) ?>"><?= h($verdict[1]) ?></div>
  <?php endif; ?>

  <?php foreach ($errors as $err): ?>
    <div class="verdict warn"><?= h($err) ?></div>
  <?php endforeach; ?>

  <h2>1. Auditoria de webhooks (<?= count($audit) ?>)</h2>
  <?php if (empty($audit)): ?>
    <div class="empty">Nenhum webhook registrado para este número na janela.</div>
  <?php else: ?>
    <table>
      <tr>
        <th>Quando</th><th>Status</th><th>Motivo</th><th>Detalhe</th>
        <th>Dir</th><th>Tipo</th><th>from_raw</th><th>external_id</th>
        <th>Conv</th><th>Msg</th><th>ms</th><th>rid</th>
      </tr>
      <?php foreach ($audit as $a): ?>
      <tr>
        <td><?= h($a['created_at']) ?></td>
        <td><span class="tag st-<?= h($a['status']) ?>"><?= h($a['status']) ?></span></td>
        <td><?= h($a['drop_reason'] ?: '—') ?></td>
        <td><?= h(mb_substr((string)$a['detail'], 0, 220)) ?></td>
        <td><?= h($a['direction']) ?></td>
        <td><?= h($a['message_type'] ?: '—') ?></td>
        <td><code><?= h($a['from_raw'] ?: '—') ?></code></td>
        <td><code><?= h(mb_substr((string)$a['external_id'], 0, 26)) ?></code></td>
        <td><?= h($a['conversation_id'] ?: '—') ?></td>
        <td><?= h($a['message_id'] ?: '—') ?></td>
        <td><?= h($a['duration_ms'] ?: '—') ?></td>
        <td><code><?= h($a['request_id']) ?></code></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <h2>2. Contatos (<?= count($contacts) ?>)</h2>
  <?php if (empty($contacts)): ?>
    <div class="empty">Nenhum contato com esse número. Se houve webhook, ele parou antes de criar o contato — veja o motivo acima.</div>
  <?php else: ?>
    <table>
      <tr><th>ID</th><th>Nome</th><th>Telefone</th><th>whatsapp_id</th><th>Criado</th><th>Atualizado</th></tr>
      <?php foreach ($contacts as $c): ?>
      <tr>
        <td><?= h($c['id']) ?></td>
        <td><?= h($c['name']) ?></td>
        <td><code><?= h($c['phone']) ?></code></td>
        <td><code><?= h($c['whatsapp_id']) ?></code></td>
        <td><?= h($c['created_at']) ?></td>
        <td><?= h($c['updated_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <h2>3. Conversas (<?= count($conversations) ?>)</h2>
  <?php if (empty($conversations)): ?>
    <div class="empty">Nenhuma conversa para os contatos encontrados.</div>
  <?php else: ?>
    <table>
      <tr><th>ID</th><th>Contato</th><th>Status</th><th>Canal</th><th>int_account</th><th>wa_account</th><th>Funil</th><th>Atribuído</th><th>Msgs</th><th>Criada</th><th>Atualizada</th></tr>
      <?php foreach ($conversations as $cv): ?>
      <tr>
        <td><?= h($cv['id']) ?></td>
        <td><?= h($cv['contact_id']) ?></td>
        <td><?= h($cv['status']) ?></td>
        <td><?= h($cv['channel']) ?></td>
        <td><?= h($cv['integration_account_id'] ?: '—') ?></td>
        <td><?= h($cv['whatsapp_account_id'] ?: '—') ?></td>
        <td><?= h($cv['funnel_id'] ?: '—') ?></td>
        <td><?= h($cv['agent_id'] ?: '—') ?></td>
        <td><?= h($cv['total_mensagens']) ?></td>
        <td><?= h($cv['created_at']) ?></td>
        <td><?= h($cv['updated_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <h2>4. Mensagens (<?= count($messages) ?>)</h2>
  <?php if (empty($messages)): ?>
    <div class="empty">Nenhuma mensagem gravada na janela.</div>
  <?php else: ?>
    <table>
      <tr><th>ID</th><th>Conv</th><th>Remetente</th><th>Tipo</th><th>external_id</th><th>Conteúdo</th><th>Criada</th></tr>
      <?php foreach ($messages as $m): ?>
      <tr>
        <td><?= h($m['id']) ?></td>
        <td><?= h($m['conversation_id']) ?></td>
        <td><?= h($m['sender_type']) ?> #<?= h($m['sender_id']) ?></td>
        <td><?= h($m['message_type']) ?></td>
        <td><code><?= h(mb_substr((string)$m['external_id'], 0, 26)) ?></code></td>
        <td><?= h($m['content']) ?></td>
        <td><?= h($m['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <h2>5. Webhooks encontrados no log (<?= count($webhookBlocos) ?>)</h2>
  <?php if (empty($webhookBlocos)): ?>
    <div class="empty">
      Nenhum webhook desse número no trecho varrido<?= $dataRef !== '' ? ' em ' . h($dataRef) : '' ?>.
      Se a cliente mandou mensagem nesse dia e nada aparece aqui, o webhook não chegou ao PHP —
      verifique a entrega no Quepasa e o access log do servidor.
    </div>
  <?php else: ?>
    <?php $marcos = marcosWebhook(); ?>
    <table>
      <tr>
        <th>Horário</th>
        <?php foreach ($marcos as $m): ?><th><?= h($m['label']) ?></th><?php endforeach; ?>
      </tr>
      <?php foreach ($webhookBlocos as $b): ?>
      <tr>
        <td><?= h($b['ts'] ?: '—') ?></td>
        <?php foreach ($marcos as $chave => $m): ?>
          <td style="text-align:center">
            <?php if (isset($b['marcos'][$chave])): ?>
              <span class="tag st-processed">ok</span>
            <?php else: ?>
              <span class="tag st-dropped">não</span>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="sub" style="margin-top:8px">
      A primeira coluna "não" depois de uma sequência de "ok" é onde a request morreu.
    </div>
  <?php endif; ?>

  <h2>
    6. logs/quepasa.log (<?= count($logLines) ?> linhas)
    <?php if ($janelaDe !== ''): ?>
      — trecho bruto de <code><?= h($janelaDe) ?></code> até <code><?= h($janelaAte ?: 'fim') ?></code>
    <?php elseif (!empty($webhookBlocos)): ?>
      — bloco do último webhook encontrado para o número
    <?php else: ?>
      — filtrado pelo número
    <?php endif; ?>
  </h2>
  <?php if (empty($logLines)): ?>
    <div class="empty">
      <?= $janelaDe !== ''
        ? 'Nenhuma linha nesse intervalo (confira a data e se o log já rotacionou).'
        : 'Nenhuma linha de log menciona esse número no trecho lido. Se o evento é antigo, use o trecho bruto por horário acima.' ?>
    </div>
  <?php else: ?>
    <pre><?= h(implode("\n", $logLines)) ?></pre>
  <?php endif; ?>

<?php endif; ?>

</body>
</html>
