<?php
/**
 * Diagnóstico da configuração de webhook no lado do Quepasa.
 *
 * Responde: "o Quepasa sabe para onde mandar os webhooks, e é o endereço certo?"
 *
 * Roda no servidor do CRM, lê o token de cada integração direto do banco e
 * consulta a API do Quepasa (que fica em outro servidor). Não precisa de ssh
 * nem de copiar token na mão.
 *
 * Uso: /debug-quepasa-webhook.php
 *
 * Endpoints usados (os mesmos que o WhatsAppService já usa):
 *   GET {api_url}/info     -> estado da conexão
 *   GET {api_url}/webhook  -> webhooks registrados
 */

$appConfig = require __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Helpers/autoload.php';

if (!empty($appConfig['timezone'])) {
    date_default_timezone_set($appConfig['timezone']);
}

use App\Helpers\Database;

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function mascarar(?string $token): string
{
    if (empty($token)) {
        return 'VAZIO';
    }
    $len = strlen($token);
    if ($len <= 8) {
        return str_repeat('*', $len);
    }
    return substr($token, 0, 2) . str_repeat('*', $len - 6) . substr($token, -4);
}

/**
 * Chamada GET simples à API do Quepasa, com timeout curto — esta página é
 * diagnóstico, não pode ficar pendurada como o webhook ficava.
 */
function quepasaGet(string $apiUrl, string $endpoint, array $account): array
{
    $url = rtrim($apiUrl, '/') . $endpoint;

    $headers = [
        'Accept: application/json',
        'X-QUEPASA-TOKEN: ' . ($account['quepasa_token'] ?? ''),
    ];
    if (!empty($account['quepasa_user'])) {
        $headers[] = 'X-QUEPASA-USER: ' . $account['quepasa_user'];
    }
    if (!empty($account['quepasa_trackid'])) {
        $headers[] = 'X-QUEPASA-TRACKID: ' . $account['quepasa_trackid'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $inicio   = microtime(true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro     = curl_error($ch);
    curl_close($ch);

    return [
        'url'      => $url,
        'http'     => $httpCode,
        'erro'     => $erro,
        'ms'       => (int)round((microtime(true) - $inicio) * 1000),
        'body'     => $response === false ? '' : $response,
        'json'     => $response ? json_decode($response, true) : null,
    ];
}

/** Extrai as URLs de webhook da resposta, tolerando formatos diferentes */
function extrairWebhooks($json): array
{
    if (!is_array($json)) {
        return [];
    }
    foreach (['webhooks', 'items', 'data', 'affected'] as $chave) {
        if (isset($json[$chave]) && is_array($json[$chave])) {
            return $json[$chave];
        }
    }
    // Alguns formatos devolvem o próprio array na raiz
    return isset($json[0]) ? $json : (isset($json['url']) ? [$json] : []);
}

$contas = [];
$erroGeral = null;
$urlEsperada = null;

try {
    $urlEsperada = \App\Services\WhatsAppService::configureWebhookUrl();
} catch (\Throwable $e) {
    $urlEsperada = null;
}

try {
    $contas = Database::fetchAll(
        "SELECT id, name, phone_number, provider, status, api_url,
                quepasa_token, quepasa_user, quepasa_trackid, quepasa_chatid
           FROM integration_accounts
          WHERE type = 'whatsapp' AND provider = 'quepasa'
          ORDER BY id ASC"
    );
} catch (\Throwable $e) {
    // Instalações antigas podem não ter a coluna 'type'
    try {
        $contas = Database::fetchAll(
            "SELECT id, name, phone_number, provider, status, api_url,
                    quepasa_token, quepasa_user, quepasa_trackid, quepasa_chatid
               FROM integration_accounts
              WHERE provider = 'quepasa'
              ORDER BY id ASC"
        );
    } catch (\Throwable $e2) {
        $erroGeral = $e2->getMessage();
    }
}

$consultar = isset($_GET['consultar']) && $_GET['consultar'] === '1';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Diagnóstico do webhook no Quepasa</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; margin: 24px; background:#f6f7f9; color:#1f2430; }
  h1 { font-size: 20px; margin: 0 0 4px; }
  h2 { font-size: 15px; margin: 26px 0 8px; }
  .sub { color:#6b7280; font-size: 13px; margin-bottom: 16px; }
  table { border-collapse: collapse; width:100%; background:#fff; font-size:12px; border:1px solid #e3e6ea; border-radius:8px; overflow:hidden; margin-bottom:14px; }
  th, td { padding:6px 9px; border-bottom:1px solid #eef0f3; text-align:left; vertical-align:top; }
  th { background:#f0f2f5; font-weight:600; white-space:nowrap; }
  tr:last-child td { border-bottom:0; }
  code { background:#eef0f3; padding:1px 4px; border-radius:3px; font-size:11px; word-break:break-all; }
  pre { background:#111827; color:#e5e7eb; padding:10px; border-radius:8px; overflow:auto; max-height:260px; font-size:11px; }
  .verdict { padding:11px 13px; border-radius:8px; margin:10px 0; font-size:13px; font-weight:500; }
  .ok   { background:#e7f6ec; border:1px solid #a7d8b8; color:#1a6135; }
  .bad  { background:#fdeceb; border:1px solid #f3b5b0; color:#8f2018; }
  .warn { background:#fdf5e2; border:1px solid #ecd08a; color:#7a5807; }
  button { padding:8px 18px; border:0; border-radius:6px; background:#2563eb; color:#fff; font-size:14px; cursor:pointer; }
  .card { background:#fff; border:1px solid #e3e6ea; border-radius:8px; padding:14px; margin-bottom:16px; }
</style>
</head>
<body>

<h1>Diagnóstico do webhook no Quepasa</h1>
<div class="sub">
  Consulta a API do Quepasa (outro servidor) usando o token guardado no banco.
  Mostra se o Quepasa está conectado e para qual URL ele está mandando os eventos.
</div>

<?php if ($erroGeral): ?>
  <div class="verdict bad">Erro ao ler as integrações: <?= h($erroGeral) ?></div>
<?php endif; ?>

<div class="card">
  <strong>URL de webhook que o CRM espera receber:</strong><br>
  <code><?= h($urlEsperada ?: 'não foi possível determinar') ?></code>
</div>

<?php if (!$consultar): ?>
  <form method="get">
    <input type="hidden" name="consultar" value="1">
    <p class="sub">
      Ao consultar, esta página faz chamadas HTTP ao servidor do Quepasa
      (timeout de 12s por chamada). Nenhuma configuração é alterada — é só leitura.
    </p>
    <button type="submit">Consultar o Quepasa agora</button>
  </form>
<?php endif; ?>

<h2>Contas Quepasa (<?= count($contas) ?>)</h2>

<?php if (empty($contas)): ?>
  <div class="verdict warn">Nenhuma integração com provider = quepasa encontrada.</div>
<?php endif; ?>

<?php foreach ($contas as $conta): ?>
  <div class="card">
    <strong>#<?= h($conta['id']) ?> — <?= h($conta['name']) ?></strong>
    <table style="margin-top:8px">
      <tr><th>Telefone</th><td><?= h($conta['phone_number'] ?: '—') ?></td></tr>
      <tr><th>Status no CRM</th><td><?= h($conta['status']) ?></td></tr>
      <tr><th>api_url</th><td><code><?= h($conta['api_url'] ?: 'VAZIO') ?></code></td></tr>
      <tr><th>token</th><td><code><?= h(mascarar($conta['quepasa_token'])) ?></code></td></tr>
      <tr><th>trackid</th><td><code><?= h($conta['quepasa_trackid'] ?: 'VAZIO') ?></code></td></tr>
      <tr><th>chatid (wid)</th><td><code><?= h($conta['quepasa_chatid'] ?: 'VAZIO') ?></code></td></tr>
    </table>

    <?php if (!$consultar): ?>
      <div class="sub">Clique em "Consultar o Quepasa agora" para checar esta conta.</div>
      <?php continue; ?>
    <?php endif; ?>

    <?php if (empty($conta['api_url']) || empty($conta['quepasa_token'])): ?>
      <div class="verdict bad">Sem api_url ou token — não dá para consultar.</div>
      <?php continue; ?>
    <?php endif; ?>

    <?php $info = quepasaGet($conta['api_url'], '/info', $conta); ?>
    <h2 style="font-size:13px">/info — <?= h($info['http']) ?> em <?= h($info['ms']) ?>ms</h2>
    <?php if ($info['erro']): ?>
      <div class="verdict bad">
        Não foi possível falar com o Quepasa: <?= h($info['erro']) ?><br>
        Se o CRM não alcança o Quepasa, vale checar também o caminho inverso —
        é por ele que os webhooks chegam.
      </div>
    <?php else: ?>
      <pre><?= h(mb_substr($info['body'], 0, 1500)) ?></pre>
    <?php endif; ?>

    <?php $wh = quepasaGet($conta['api_url'], '/webhook', $conta); ?>
    <h2 style="font-size:13px">/webhook — <?= h($wh['http']) ?> em <?= h($wh['ms']) ?>ms</h2>
    <?php if ($wh['erro']): ?>
      <div class="verdict bad">Erro: <?= h($wh['erro']) ?></div>
    <?php else: ?>
      <?php
        $registrados = extrairWebhooks($wh['json']);
        $urls = [];
        foreach ($registrados as $r) {
            if (is_array($r) && !empty($r['url'])) { $urls[] = $r['url']; }
            elseif (is_string($r)) { $urls[] = $r; }
        }
        $bate = $urlEsperada && in_array($urlEsperada, $urls, true);
      ?>
      <?php if (empty($urls)): ?>
        <div class="verdict bad">
          NENHUM webhook registrado nesta conta do Quepasa. Sem isso o Quepasa não
          entrega mensagem nenhuma ao CRM. Reconfigure em Integrações &raquo; WhatsApp.
        </div>
      <?php elseif ($bate): ?>
        <div class="verdict ok">
          Webhook registrado e igual ao esperado pelo CRM.
        </div>
      <?php else: ?>
        <div class="verdict warn">
          O Quepasa está entregando em URL diferente da que o CRM espera.<br>
          Registrado: <?= implode(', ', array_map('h', $urls)) ?><br>
          Esperado: <code><?= h($urlEsperada ?: '?') ?></code><br>
          Se a URL registrada for um endereço antigo ou de outro ambiente, as
          mensagens estão chegando em outro lugar.
        </div>
      <?php endif; ?>
      <pre><?= h(mb_substr($wh['body'], 0, 2000)) ?></pre>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<div class="card">
  <strong>Se você tiver ssh no servidor do Quepasa</strong>
  <p class="sub" style="margin:6px 0 0">
    O que interessa é se o Quepasa registrou falha ao entregar em nós, e se ele
    tenta de novo. Confirme antes o nome do container:
  </p>
  <pre>docker ps --format '{{.Names}}\t{{.Image}}' | grep -i quepasa

docker logs --since 2026-09-03T11:00:00 --until 2026-09-03T12:30:00 &lt;container&gt; 2>&amp;1 \
  | grep -iE 'webhook|error|timeout|5[0-9]{2}'</pre>
</div>

</body>
</html>
