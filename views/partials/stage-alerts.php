<?php
/**
 * Alerta de cliente sem resposta na etapa do funil (painel na tela + som).
 * Configurado por etapa em Funis → Kanban → editar etapa → aba Alerta.
 * Incluir antes de </body>, depois do sound-manager.js.
 */
try {
    $stageAlertsLoggedIn = \App\Helpers\Auth::check();
} catch (\Throwable $e) {
    $stageAlertsLoggedIn = false;
}
if (!$stageAlertsLoggedIn) {
    return;
}
$stageAlertsConfig = [
    'url' => \App\Helpers\Url::to('stage-alerts'),
    'conversationsUrl' => \App\Helpers\Url::to('conversations'),
    'soundsUrl' => \App\Helpers\Url::to('assets/sounds/'),
    'iconUrl' => \App\Helpers\Url::to('pwa/icon/192'),
];
?>
<style>
.cp-stage-alerts {
    position: fixed; left: 20px; bottom: 20px; z-index: 1090; width: 370px; max-width: calc(100vw - 32px);
    background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #181c32);
    border: 2px solid #f1416c; border-radius: 12px; box-shadow: 0 10px 30px rgba(241, 65, 108, .25);
    overflow: hidden; font-size: 13px; animation: cpSaIn .25s ease-out;
}
.cp-stage-alerts .cp-sa-header {
    display: flex; align-items: center; gap: 8px; padding: 10px 12px; cursor: pointer; user-select: none;
    background: #f1416c; color: #fff; font-weight: 600;
}
.cp-stage-alerts .cp-sa-bell { font-size: 16px; animation: cpSaRing 1.8s ease-in-out infinite; transform-origin: 50% 0; }
.cp-stage-alerts .cp-sa-title { flex: 1; }
.cp-stage-alerts .cp-sa-btn { background: rgba(255,255,255,.2); border: 0; color: #fff; border-radius: 6px; width: 28px; height: 26px; line-height: 1; }
.cp-stage-alerts .cp-sa-btn:hover { background: rgba(255,255,255,.35); }
.cp-stage-alerts .cp-sa-list { max-height: min(55vh, 420px); overflow-y: auto; }
.cp-stage-alerts.cp-sa-collapsed .cp-sa-list { display: none; }
.cp-stage-alerts .cp-sa-item { padding: 10px 12px; border-bottom: 1px solid var(--bs-border-color, #eff2f5); }
.cp-stage-alerts .cp-sa-item:last-child { border-bottom: 0; }
.cp-stage-alerts .cp-sa-main { cursor: pointer; }
.cp-stage-alerts .cp-sa-name { font-weight: 700; font-size: 14px; }
.cp-stage-alerts .cp-sa-meta { color: var(--bs-text-muted, #7e8299); margin-top: 2px; }
.cp-stage-alerts .cp-sa-meta strong { color: #f1416c; }
.cp-stage-alerts .cp-sa-stage { display: inline-block; color: #fff; border-radius: 4px; padding: 1px 6px; font-size: 11px; font-weight: 600; }
.cp-stage-alerts .cp-sa-msg { color: var(--bs-body-color, #5e6278); opacity: .75; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cp-stage-alerts .cp-sa-actions { display: flex; gap: 6px; margin-top: 8px; }
.cp-stage-alerts .cp-sa-act {
    border: 1px solid var(--bs-border-color, #e4e6ef); background: var(--bs-body-bg, #fff); color: inherit;
    border-radius: 6px; padding: 3px 9px; font-size: 12px;
}
.cp-stage-alerts .cp-sa-act:hover { border-color: #f1416c; color: #f1416c; }
.cp-stage-alerts .cp-sa-act.cp-sa-open { background: #f1416c; border-color: #f1416c; color: #fff; font-weight: 600; }
.cp-stage-alerts .cp-sa-act.cp-sa-dismiss { margin-left: auto; }
.cp-stage-alerts .cp-sa-more { padding: 8px 12px; text-align: center; color: var(--bs-text-muted, #7e8299); }
.conversation-item.cp-stage-alert-item {
    box-shadow: inset 4px 0 0 #f1416c !important;
    animation: cpSaPulse 2s ease-in-out infinite;
}
@keyframes cpSaPulse {
    0%, 100% { background-color: rgba(241, 65, 108, .06); }
    50% { background-color: rgba(241, 65, 108, .16); }
}
@keyframes cpSaRing {
    0%, 60%, 100% { transform: rotate(0); }
    10%, 30% { transform: rotate(14deg); }
    20%, 40% { transform: rotate(-14deg); }
}
@keyframes cpSaIn { from { transform: translateY(12px); opacity: 0; } to { transform: none; opacity: 1; } }
@media (max-width: 767.98px) {
    .cp-stage-alerts { left: 8px; right: 8px; bottom: auto; top: calc(8px + env(safe-area-inset-top, 0px)); width: auto; max-width: none; }
    .cp-stage-alerts .cp-sa-list { max-height: 45vh; }
}
@media (prefers-reduced-motion: reduce) {
    .cp-stage-alerts, .cp-stage-alerts .cp-sa-bell, .conversation-item.cp-stage-alert-item { animation: none; }
}
</style>
<script>window.CP_STAGE_ALERTS = <?= json_encode($stageAlertsConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= \App\Helpers\Url::asset('js/stage-alerts.js') ?>"></script>
