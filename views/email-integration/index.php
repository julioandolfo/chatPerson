<?php
/** @var array $accounts @var array $funnels @var array $departments @var array $agents */
use App\Helpers\Url;

$layout = 'layouts.metronic.app';
$title = 'Canal de Email';

$flashMsg = $_GET['msg'] ?? '';
$flashOk = isset($_GET['ok']);
$flashErr = isset($_GET['error']);

$enc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<div class="d-flex flex-column gap-5">

    <?php if ($flashMsg !== ''): ?>
        <div class="alert <?= $flashErr ? 'alert-danger' : 'alert-success' ?>"><?= $enc($flashMsg) ?></div>
    <?php endif; ?>

    <!--begin::Nova conta-->
    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title"><h2><i class="bi bi-envelope-at me-2"></i>Contas de Email</h2></div>
            <div class="card-toolbar">
                <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#newAccountForm">
                    <i class="bi bi-plus-lg"></i> Nova conta
                </button>
            </div>
        </div>
        <div class="collapse" id="newAccountForm">
            <div class="card-body border-top">
                <form method="post" action="<?= Url::to('/email-integration/accounts') ?>">
                    <?= self_email_account_fields([], $enc, $funnels) ?>
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary">Salvar conta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!--end::Nova conta-->

    <?php if (empty($accounts)): ?>
        <div class="card"><div class="card-body text-muted">Nenhuma conta de email cadastrada ainda.</div></div>
    <?php endif; ?>

    <?php foreach ($accounts as $acc):
        $cfg = $acc['cfg'] ?? [];
        $accId = (int)$acc['id'];
    ?>
    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title flex-column align-items-start">
                <h3 class="mb-1"><?= $enc($acc['name']) ?>
                    <span class="badge <?= ($acc['status'] ?? '') === 'active' ? 'badge-light-success' : 'badge-light-danger' ?> ms-2">
                        <?= $enc($acc['status'] ?? '') ?>
                    </span>
                </h3>
                <span class="text-muted fs-7">
                    <?= $enc($cfg['auth_user'] ?? $acc['username'] ?? '') ?>
                    &middot; IMAP <?= $enc($cfg['imap_host'] ?? '?') ?>:<?= $enc($cfg['imap_port'] ?? '') ?>
                    &middot; última sync: <?= $enc($acc['last_sync_at'] ?? 'nunca') ?>
                </span>
                <?php if (!empty($acc['error_message'])): ?>
                    <span class="text-danger fs-8 mt-1">⚠ <?= $enc($acc['error_message']) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-toolbar gap-2">
                <button class="btn btn-sm btn-light-primary" onclick="emailTest(<?= $accId ?>)">Testar conexão</button>
                <button class="btn btn-sm btn-light-info" onclick="emailPoll(<?= $accId ?>)">Buscar agora</button>
                <button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#editAcc<?= $accId ?>">Editar</button>
                <form method="post" action="<?= Url::to('/email-integration/accounts/' . $accId . '/delete') ?>"
                      onsubmit="return confirm('Remover esta conta de email?');" class="d-inline">
                    <button class="btn btn-sm btn-light-danger">Remover</button>
                </form>
            </div>
        </div>

        <!-- Editar conta -->
        <div class="collapse" id="editAcc<?= $accId ?>">
            <div class="card-body border-top">
                <form method="post" action="<?= Url::to('/email-integration/accounts/' . $accId) ?>">
                    <?= self_email_account_fields($acc, $enc, $funnels) ?>
                    <div class="text-end mt-4"><button class="btn btn-primary">Atualizar conta</button></div>
                </form>
            </div>
        </div>

        <div class="card-body border-top">
            <!-- Regras -->
            <h4 class="mb-3"><i class="bi bi-funnel me-2"></i>Regras de validação</h4>
            <div class="alert alert-light-primary fs-8">
                Emails que <strong>não casarem</strong> nenhuma regra:
                <strong><?= ($cfg['unmatched_action'] ?? 'ignore') === 'ingest' ? 'ENTRAM como conversa' : 'são IGNORADOS' ?></strong>
                (configurável na conta).
            </div>

            <div class="table-responsive">
                <table class="table table-row-bordered align-middle gy-2">
                    <thead><tr class="fw-bold text-muted fs-8">
                        <th>#</th><th>Nome</th><th>Combinação</th><th>Condições</th><th>Ação</th><th>Ativa</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($acc['rules'] as $rule):
                        $conds = is_array($rule['conditions'] ?? null) ? $rule['conditions'] : [];
                        $acts = is_array($rule['actions'] ?? null) ? $rule['actions'] : [];
                        $condTxt = [];
                        foreach ($conds as $c) { $condTxt[] = ($c['field'] ?? '?') . ' ' . ($c['op'] ?? '') . ' "' . ($c['value'] ?? '') . '"'; }
                    ?>
                        <tr>
                            <td><?= (int)$rule['priority'] ?></td>
                            <td><?= $enc($rule['name']) ?></td>
                            <td><span class="badge badge-light"><?= strtoupper($enc($rule['match_type'])) ?></span></td>
                            <td class="fs-8"><?= $enc(implode(' / ', $condTxt)) ?: '<sem condição>' ?></td>
                            <td class="fs-8">
                                <?= empty($acts['ingest']) ? '<span class="text-danger">ignorar</span>' : '<span class="text-success">ingerir</span>' ?>
                                <?php if (!empty($acts['priority'])): ?> · prio:<?= $enc($acts['priority']) ?><?php endif; ?>
                                <?php if (!empty($acts['tag'])): ?> · tag:<?= $enc($acts['tag']) ?><?php endif; ?>
                            </td>
                            <td><?= !empty($rule['is_active']) ? '✅' : '—' ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= Url::to('/email-integration/rules/' . (int)$rule['id'] . '/delete') ?>"
                                      onsubmit="return confirm('Remover regra?');" class="d-inline">
                                    <button class="btn btn-sm btn-icon btn-light-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($acc['rules'])): ?>
                        <tr><td colspan="7" class="text-muted">Nenhuma regra — defina ao menos uma para emails entrarem.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Nova regra -->
            <button class="btn btn-sm btn-light-primary mt-2" data-bs-toggle="collapse" data-bs-target="#newRule<?= $accId ?>">
                <i class="bi bi-plus-lg"></i> Nova regra
            </button>
            <div class="collapse mt-3" id="newRule<?= $accId ?>">
                <form method="post" action="<?= Url::to('/email-integration/accounts/' . $accId . '/rules') ?>" class="border rounded p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fs-8">Nome da regra</label>
                            <input name="name" class="form-control form-control-sm" placeholder="Ex.: Orçamentos" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fs-8">Palavras-chave (separadas por vírgula)</label>
                            <input name="keywords" class="form-control form-control-sm" placeholder="orçamento, cotação, proposta">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-8">Combinação</label>
                            <select name="match_type" class="form-select form-select-sm">
                                <option value="any">Qualquer (OU)</option>
                                <option value="all">Todas (E)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-8">Procurar em</label>
                            <div class="d-flex gap-3">
                                <label class="form-check form-check-sm"><input type="checkbox" class="form-check-input" name="search_in[]" value="subject" checked> <span class="form-check-label">Assunto</span></label>
                                <label class="form-check form-check-sm"><input type="checkbox" class="form-check-input" name="search_in[]" value="body" checked> <span class="form-check-label">Corpo</span></label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-8">Ordem (prioridade)</label>
                            <input name="priority" type="number" value="0" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-8">Prioridade da conversa</label>
                            <select name="action_priority" class="form-select form-select-sm">
                                <option value="">(padrão)</option>
                                <option value="low">Baixa</option>
                                <option value="medium">Média</option>
                                <option value="high">Alta</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-8">Tag (opcional)</label>
                            <input name="action_tag" class="form-control form-control-sm" placeholder="Orçamento">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fs-8">Funil (ID, opcional)</label>
                            <select name="funnel_id" class="form-select form-select-sm">
                                <option value="">(padrão da conta)</option>
                                <?php foreach ($funnels as $f): ?>
                                    <option value="<?= (int)$f['id'] ?>"><?= $enc($f['name'] ?? ('#' . $f['id'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-8">Etapa (ID, opcional)</label>
                            <input name="stage_id" type="number" class="form-control form-control-sm" placeholder="ID da etapa">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-8">Departamento (opcional)</label>
                            <select name="department_id" class="form-select form-select-sm">
                                <option value="">(nenhum)</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>"><?= $enc($d['name'] ?? ('#' . $d['id'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-8">Agente (opcional)</label>
                            <select name="agent_id" class="form-select form-select-sm">
                                <option value="">(automático)</option>
                                <?php foreach ($agents as $a): ?>
                                    <option value="<?= (int)$a['id'] ?>"><?= $enc($a['name'] ?? ('#' . $a['id'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fs-8">Avançado: condições em JSON (sobrepõe palavras-chave)</label>
                            <textarea name="conditions_json" class="form-control form-control-sm font-monospace" rows="2"
                                placeholder='[{"field":"subject","op":"contains","value":"orçamento"}]'></textarea>
                        </div>

                        <div class="col-12 d-flex gap-4">
                            <label class="form-check"><input type="checkbox" class="form-check-input" name="ingest" value="1" checked> <span class="form-check-label">Ingerir (criar conversa)</span></label>
                            <label class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1" checked> <span class="form-check-label">Ativa</span></label>
                            <label class="form-check"><input type="checkbox" class="form-check-input" name="stop_on_match" value="1" checked> <span class="form-check-label">Parar na 1ª regra que casar</span></label>
                        </div>
                    </div>
                    <div class="text-end mt-3"><button class="btn btn-sm btn-primary">Salvar regra</button></div>
                </form>
            </div>

            <!-- Log recente -->
            <h4 class="mt-6 mb-3"><i class="bi bi-clock-history me-2"></i>Ingestões recentes</h4>
            <div class="table-responsive">
                <table class="table table-row-bordered align-middle gy-1 fs-8">
                    <thead><tr class="fw-bold text-muted"><th>Quando</th><th>De</th><th>Assunto</th><th>Decisão</th><th>Motivo</th></tr></thead>
                    <tbody>
                    <?php foreach ($acc['logs'] as $log): ?>
                        <tr>
                            <td><?= $enc($log['created_at']) ?></td>
                            <td><?= $enc($log['from_email']) ?></td>
                            <td><?= $enc(mb_substr((string)$log['subject'], 0, 60)) ?></td>
                            <td>
                                <?php $d = $log['decision']; $cls = $d === 'ingested' ? 'success' : ($d === 'ignored' ? 'warning' : ($d === 'duplicate' ? 'secondary' : 'danger')); ?>
                                <span class="badge badge-light-<?= $cls ?>"><?= $enc($d) ?></span>
                            </td>
                            <td class="text-muted"><?= $enc($log['reason']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($acc['logs'])): ?>
                        <tr><td colspan="5" class="text-muted">Sem registros ainda.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
/**
 * Renderiza os campos do formulário de conta (criação/edição).
 */
function self_email_account_fields(array $acc, callable $enc, array $funnels): string
{
    $cfg = $acc['cfg'] ?? [];
    $v = fn($k, $d = '') => $enc($cfg[$k] ?? $d);
    $sel = fn($k, $opt, $d = '') => (($cfg[$k] ?? $d) === $opt) ? 'selected' : '';
    $chk = fn($k, $d = false) => !empty($cfg[$k] ?? $d) ? 'checked' : '';
    ob_start(); ?>
    <div class="row g-4">
        <div class="col-md-6">
            <label class="form-label required fs-8">Nome da conta</label>
            <input name="name" class="form-control form-control-sm" value="<?= $enc($acc['name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label required fs-8">Email / usuário (login)</label>
            <input name="auth_user" type="email" class="form-control form-control-sm" value="<?= $v('auth_user', $acc['username'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fs-8">Senha <?= !empty($acc) ? '(deixe vazio para manter)' : '' ?></label>
            <input name="auth_pass" type="password" class="form-control form-control-sm" autocomplete="new-password" <?= empty($acc) ? 'required' : '' ?>>
        </div>
        <div class="col-md-6">
            <label class="form-label fs-8">Nome do remetente (From)</label>
            <input name="from_name" class="form-control form-control-sm" value="<?= $v('from_name') ?>">
        </div>

        <div class="col-12"><div class="separator my-1"></div><span class="text-muted fs-8 fw-bold">IMAP (recebimento)</span></div>
        <div class="col-md-4">
            <label class="form-label fs-8">Servidor IMAP</label>
            <input name="imap_host" class="form-control form-control-sm" value="<?= $v('imap_host') ?>" placeholder="imap.seudominio.com.br">
        </div>
        <div class="col-md-2">
            <label class="form-label fs-8">Porta</label>
            <input name="imap_port" type="number" class="form-control form-control-sm" value="<?= $v('imap_port', '993') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label fs-8">Criptografia</label>
            <select name="imap_encryption" class="form-select form-select-sm">
                <option value="ssl" <?= $sel('imap_encryption','ssl','ssl') ?>>SSL</option>
                <option value="tls" <?= $sel('imap_encryption','tls') ?>>TLS/STARTTLS</option>
                <option value="none" <?= $sel('imap_encryption','none') ?>>Nenhuma</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fs-8">Pasta</label>
            <input name="imap_folder" class="form-control form-control-sm" value="<?= $v('imap_folder', 'INBOX') ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <label class="form-check"><input type="checkbox" class="form-check-input" name="imap_validate_cert" value="1" <?= $chk('imap_validate_cert', true) ?>> <span class="form-check-label fs-8">Validar cert.</span></label>
        </div>

        <div class="col-12"><div class="separator my-1"></div><span class="text-muted fs-8 fw-bold">SMTP (envio/resposta)</span></div>
        <div class="col-md-4">
            <label class="form-label fs-8">Servidor SMTP</label>
            <input name="smtp_host" class="form-control form-control-sm" value="<?= $v('smtp_host') ?>" placeholder="smtp.seudominio.com.br">
        </div>
        <div class="col-md-2">
            <label class="form-label fs-8">Porta</label>
            <input name="smtp_port" type="number" class="form-control form-control-sm" value="<?= $v('smtp_port', '587') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fs-8">Criptografia</label>
            <select name="smtp_encryption" class="form-select form-select-sm">
                <option value="tls" <?= $sel('smtp_encryption','tls','tls') ?>>TLS/STARTTLS</option>
                <option value="ssl" <?= $sel('smtp_encryption','ssl') ?>>SSL</option>
                <option value="none" <?= $sel('smtp_encryption','none') ?>>Nenhuma</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fs-8">Email do remetente (From)</label>
            <input name="from_address" class="form-control form-control-sm" value="<?= $v('from_address') ?>" placeholder="(usa o login)">
        </div>

        <div class="col-12"><div class="separator my-1"></div><span class="text-muted fs-8 fw-bold">Comportamento</span></div>
        <div class="col-md-4">
            <label class="form-label fs-8">Emails sem regra correspondente</label>
            <select name="unmatched_action" class="form-select form-select-sm">
                <option value="ignore" <?= $sel('unmatched_action','ignore','ignore') ?>>Ignorar (recomendado)</option>
                <option value="ingest" <?= $sel('unmatched_action','ingest') ?>>Entrar como conversa</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fs-8">Buscar emails dos últimos (dias)</label>
            <input name="poll_lookback_days" type="number" min="1" class="form-control form-control-sm" value="<?= $v('poll_lookback_days', '2') ?>">
        </div>
        <div class="col-md-5 d-flex align-items-end gap-4">
            <label class="form-check"><input type="checkbox" class="form-check-input" name="strip_quoted" value="1" <?= $chk('strip_quoted', true) ?>> <span class="form-check-label fs-8">Remover histórico citado das respostas</span></label>
        </div>

        <div class="col-md-3">
            <label class="form-label fs-8">Funil padrão (ID, opcional)</label>
            <select name="default_funnel_id" class="form-select form-select-sm">
                <option value="">(nenhum)</option>
                <?php foreach ($funnels as $f): ?>
                    <option value="<?= (int)$f['id'] ?>" <?= (int)($acc['default_funnel_id'] ?? 0) === (int)$f['id'] ? 'selected' : '' ?>><?= $enc($f['name'] ?? ('#' . $f['id'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fs-8">Etapa padrão (ID, opcional)</label>
            <input name="default_stage_id" type="number" class="form-control form-control-sm" value="<?= $enc($acc['default_stage_id'] ?? '') ?>">
        </div>
        <?php if (!empty($acc)): ?>
        <div class="col-md-3">
            <label class="form-label fs-8">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="active" <?= ($acc['status'] ?? '') === 'active' ? 'selected' : '' ?>>Ativa</option>
                <option value="inactive" <?= ($acc['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativa</option>
            </select>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

$content = ob_get_clean();

$scripts = "
<script>
function emailTest(id){
    const u = '" . Url::to('/email-integration/accounts/') . "' + id + '/test';
    fetch(u, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r=>r.json())
      .then(d=>{
         const imap = d.imap||{}; const smtp = d.smtp||{};
         alert('IMAP: ' + (imap.success?'OK':'FALHA') + ' - ' + (imap.message||'') + '\\n' +
               'SMTP: ' + (smtp.success?'OK':'FALHA') + ' - ' + (smtp.message||''));
      })
      .catch(e=>alert('Erro: '+e));
}
function emailPoll(id){
    const u = '" . Url::to('/email-integration/accounts/') . "' + id + '/poll';
    fetch(u, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r=>r.json())
      .then(d=>{ alert(JSON.stringify(d.stats||d, null, 2)); location.reload(); })
      .catch(e=>alert('Erro: '+e));
}
</script>";

include __DIR__ . '/../layouts/metronic/app.php';
