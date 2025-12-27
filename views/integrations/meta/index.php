<?php
$layout = 'layouts.metronic.app';
$title = 'Meta - Integrações (Instagram + WhatsApp)';

$instagramAccounts = $instagramAccounts ?? [];
$whatsappPhones = $whatsappPhones ?? [];
$tokens = $tokens ?? [];

ob_start();
?>

<!--begin::Page header-->
<div class="card mb-5">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">
                <i class="ki-duotone ki-abstract-26 fs-2 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Integrações Meta (Instagram + WhatsApp)
            </h3>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
                <a href="/integrations/meta/logs" class="btn btn-sm btn-light-primary">
                    <i class="ki-duotone ki-file-down fs-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Ver Logs
                </a>
                <button class="btn btn-sm btn-primary" onclick="connectAccount()" <?= empty($metaConfig['app_id']) ? 'disabled' : '' ?>>
                    <i class="ki-duotone ki-plus fs-3"></i>
                    Conectar Conta Meta
                </button>
            </div>
        </div>
    </div>
    <div class="card-body pt-3">
        <p class="text-muted">
            Conecte suas contas Instagram e números WhatsApp oficiais via APIs da Meta
        </p>
    </div>
</div>

<!--begin::Configuração de Credenciais-->
<div class="card mb-5">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">
                <i class="ki-duotone ki-setting-2 fs-2 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Configuração do App Meta
            </h3>
        </div>
        <div class="card-toolbar">
            <a href="https://developers.facebook.com/apps/" target="_blank" class="btn btn-sm btn-light-info">
                <i class="ki-duotone ki-external-link fs-3"></i>
                Meta for Developers
            </a>
        </div>
    </div>
    <div class="card-body pt-3">
        <?php if (empty($metaConfig['app_id']) || empty($metaConfig['app_secret'])): ?>
        <div class="alert alert-warning d-flex align-items-center p-5 mb-5">
            <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-warning">Credenciais não configuradas</h4>
                <span>Configure as credenciais do seu App Meta abaixo para poder conectar contas Instagram e WhatsApp.</span>
            </div>
        </div>
        <?php endif; ?>
        
        <form id="metaConfigForm">
            <div class="row g-5">
                <div class="col-md-6">
                    <label class="form-label required">App ID</label>
                    <input type="text" name="app_id" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars($metaConfig['app_id'] ?? '') ?>" 
                           placeholder="123456789012345">
                    <div class="form-text">
                        Obtido em: <a href="https://developers.facebook.com/apps/" target="_blank">Meta for Developers</a> → Seu App → Configurações → Básico
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label required">App Secret</label>
                    <div class="input-group">
                        <input type="password" name="app_secret" id="appSecret" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($metaConfig['app_secret'] ?? '') ?>" 
                               placeholder="abc123def456...">
                        <button class="btn btn-light-secondary" type="button" onclick="toggleSecret()">
                            <i class="ki-duotone ki-eye fs-2" id="eyeIcon">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </button>
                    </div>
                    <div class="form-text">
                        Clique em "Mostrar" na página do App para visualizar
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label required">Webhook Verify Token</label>
                    <div class="input-group">
                        <input type="text" name="webhook_verify_token" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($metaConfig['webhook_verify_token'] ?? '') ?>" 
                               placeholder="seu_token_seguro_aqui">
                        <button class="btn btn-light-info" type="button" onclick="generateToken()">
                            <i class="ki-duotone ki-refresh fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Gerar
                        </button>
                    </div>
                    <div class="form-text">
                        Token de verificação para webhooks (pode gerar um aleatório)
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Redirect URI (OAuth)</label>
                    <input type="text" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars(\App\Helpers\Url::to('/integrations/meta/oauth/callback', true)) ?>" 
                           readonly onclick="this.select()">
                    <div class="form-text">
                        Copie e adicione em: Facebook Login → Configurações → URIs de redirecionamento
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Webhook URL</label>
                    <input type="text" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars(\App\Helpers\Url::to('/webhooks/meta', true)) ?>" 
                           readonly onclick="this.select()">
                    <div class="form-text">
                        Configure em: Webhooks → URL de callback
                    </div>
                </div>
            </div>
            
            <div class="separator my-7"></div>
            
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted fs-7">
                        <i class="ki-duotone ki-information-4 fs-3 text-primary me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        As credenciais são salvas no servidor e não ficam visíveis no código
                    </span>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="ki-duotone ki-check fs-3"></i>
                    Salvar Configurações
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
<div class="alert alert-success d-flex align-items-center p-5 mb-5">
    <i class="ki-duotone ki-shield-tick fs-2hx text-success me-4">
        <span class="path1"></span>
        <span class="path2"></span>
    </i>
    <div class="d-flex flex-column">
        <h4 class="mb-1 text-success">Sucesso!</h4>
        <span>Conta conectada com sucesso.</span>
    </div>
</div>
<?php endif; ?>

<!--begin::Instagram Accounts-->
<div class="card mb-5">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#E4405F" viewBox="0 0 24 24" class="me-2">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
                Instagram Accounts (<?= count($instagramAccounts) ?>)
            </h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($instagramAccounts)): ?>
            <div class="text-center py-10">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="#E4405F" viewBox="0 0 24 24" class="mb-5" style="opacity: 0.3;">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
                <p class="text-muted fs-5 mb-5">Nenhuma conta Instagram conectada</p>
                <button class="btn btn-light-primary" onclick="connectAccount('instagram')">
                    <i class="ki-duotone ki-plus fs-3"></i>
                    Conectar Instagram
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th class="min-w-200px">Conta</th>
                            <th class="min-w-150px">Usuário</th>
                            <th class="min-w-100px">Seguidores</th>
                            <th class="min-w-100px">Status</th>
                            <th class="min-w-120px">Última Sync</th>
                            <th class="min-w-100px text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instagramAccounts as $account): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($account['profile_picture_url'])): ?>
                                        <div class="symbol symbol-40px me-3">
                                            <img src="<?= htmlspecialchars($account['profile_picture_url']) ?>" alt="Avatar">
                                        </div>
                                    <?php else: ?>
                                        <div class="symbol symbol-40px me-3">
                                            <span class="symbol-label bg-light-primary text-primary fs-6 fw-bold">
                                                <?= strtoupper(substr($account['name'] ?? 'IG', 0, 2)) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-start flex-column">
                                        <span class="text-dark fw-bold fs-6"><?= htmlspecialchars($account['name'] ?? 'Sem nome') ?></span>
                                        <span class="text-muted fw-semibold d-block fs-7">ID: <?= htmlspecialchars($account['instagram_user_id']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="https://instagram.com/<?= htmlspecialchars($account['username']) ?>" 
                                   target="_blank" class="text-dark fw-bold text-hover-primary">
                                    @<?= htmlspecialchars($account['username']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="text-dark fw-bold"><?= number_format($account['followers_count']) ?></span>
                            </td>
                            <td>
                                <?php if ($account['has_valid_token'] && $account['is_connected']): ?>
                                    <span class="badge badge-light-success">Conectado</span>
                                <?php else: ?>
                                    <span class="badge badge-light-danger">Desconectado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($account['last_synced_at']): ?>
                                    <span class="text-muted fw-semibold d-block fs-7">
                                        <?= date('d/m/Y H:i', strtotime($account['last_synced_at'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fw-semibold d-block fs-7">Nunca</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-icon btn-light-primary btn-sm me-2" 
                                        onclick="syncInstagram(<?= $account['id'] ?>)" 
                                        title="Sincronizar">
                                    <i class="ki-duotone ki-arrows-circle fs-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </button>
                                <button class="btn btn-icon btn-light-info btn-sm" 
                                        onclick="testMessage('instagram', <?= $account['id'] ?>)" 
                                        title="Testar Mensagem">
                                    <i class="ki-duotone ki-send fs-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!--begin::WhatsApp Phones-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#25D366" viewBox="0 0 24 24" class="me-2">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                WhatsApp Phones (<?= count($whatsappPhones) ?>)
            </h3>
        </div>
        <div class="card-toolbar">
            <button class="btn btn-sm btn-light-success" onclick="addWhatsAppPhone()">
                <i class="ki-duotone ki-plus fs-3"></i>
                Adicionar Número
            </button>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($whatsappPhones)): ?>
            <div class="text-center py-10">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="#25D366" viewBox="0 0 24 24" class="mb-5" style="opacity: 0.3;">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                <p class="text-muted fs-5 mb-5">Nenhum número WhatsApp conectado</p>
                <button class="btn btn-light-success" onclick="connectAccount('whatsapp')">
                    <i class="ki-duotone ki-plus fs-3"></i>
                    Conectar WhatsApp
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th class="min-w-200px">Número</th>
                            <th class="min-w-150px">Nome Verificado</th>
                            <th class="min-w-100px">Qualidade</th>
                            <th class="min-w-80px">Modo</th>
                            <th class="min-w-100px">Status</th>
                            <th class="min-w-100px text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($whatsappPhones as $phone): ?>
                        <tr>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="text-dark fw-bold fs-6"><?= htmlspecialchars($phone['display_phone_number'] ?? $phone['phone_number']) ?></span>
                                    <span class="text-muted fw-semibold d-block fs-7">ID: <?= htmlspecialchars($phone['phone_number_id']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="text-dark fw-bold"><?= htmlspecialchars($phone['verified_name'] ?? '-') ?></span>
                            </td>
                            <td>
                                <?php
                                $qualityColors = [
                                    'GREEN' => 'success',
                                    'YELLOW' => 'warning',
                                    'RED' => 'danger',
                                    'UNKNOWN' => 'secondary'
                                ];
                                $color = $qualityColors[$phone['quality_rating']] ?? 'secondary';
                                ?>
                                <span class="badge badge-light-<?= $color ?>"><?= $phone['quality_rating'] ?></span>
                            </td>
                            <td>
                                <span class="badge badge-light-<?= $phone['account_mode'] === 'LIVE' ? 'primary' : 'warning' ?>">
                                    <?= $phone['account_mode'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($phone['has_valid_token'] && $phone['is_connected']): ?>
                                    <span class="badge badge-light-success">Conectado</span>
                                <?php else: ?>
                                    <span class="badge badge-light-danger">Desconectado</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-icon btn-light-primary btn-sm me-2" 
                                        onclick="syncWhatsApp(<?= $phone['id'] ?>)" 
                                        title="Sincronizar">
                                    <i class="ki-duotone ki-arrows-circle fs-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </button>
                                <button class="btn btn-icon btn-light-info btn-sm" 
                                        onclick="testMessage('whatsapp', <?= $phone['id'] ?>)" 
                                        title="Testar Mensagem">
                                    <i class="ki-duotone ki-send fs-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Toggle mostrar/ocultar App Secret
function toggleSecret() {
    const input = document.getElementById('appSecret');
    const icon = document.getElementById('eyeIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('ki-eye');
        icon.classList.add('ki-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('ki-eye-slash');
        icon.classList.add('ki-eye');
    }
}

// Gerar token aleatório seguro
function generateToken() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let token = '';
    for (let i = 0; i < 64; i++) {
        token += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.querySelector('input[name="webhook_verify_token"]').value = token;
}

// Salvar configurações Meta
document.getElementById('metaConfigForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    // Validar
    if (!data.app_id || !data.app_secret || !data.webhook_verify_token) {
        Swal.fire('Erro', 'Preencha todos os campos obrigatórios', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Salvando...',
        text: 'Aguarde enquanto salvamos as configurações',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('/integrations/meta/config/save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Sucesso!', 'Configurações salvas com sucesso', 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Erro', data.error || 'Erro ao salvar', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Erro', 'Erro na requisição', 'error');
    });
});

function connectAccount(type = 'both') {
    Swal.fire({
        title: 'Conectar Conta Meta',
        html: `
            <p class="text-muted mb-3">
                Você será redirecionado para autenticação OAuth da Meta.
            </p>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="connectType" id="typeBoth" value="both" ${type === 'both' ? 'checked' : ''}>
                <label class="form-check-label" for="typeBoth">
                    Instagram + WhatsApp
                </label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="connectType" id="typeInstagram" value="instagram" ${type === 'instagram' ? 'checked' : ''}>
                <label class="form-check-label" for="typeInstagram">
                    Apenas Instagram
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="connectType" id="typeWhatsApp" value="whatsapp" ${type === 'whatsapp' ? 'checked' : ''}>
                <label class="form-check-label" for="typeWhatsApp">
                    Apenas WhatsApp
                </label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Continuar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            return document.querySelector('input[name="connectType"]:checked').value;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `/integrations/meta/oauth/authorize?type=${result.value}`;
        }
    });
}

function syncInstagram(id) {
    Swal.fire({
        title: 'Sincronizando...',
        text: 'Aguarde enquanto sincronizamos os dados do Instagram',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('/integrations/meta/instagram/sync', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Sucesso!', 'Perfil sincronizado com sucesso', 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Erro', data.error || 'Erro ao sincronizar', 'error');
        }
    })
    .catch(err => {
        Swal.fire('Erro', 'Erro na requisição', 'error');
    });
}

function syncWhatsApp(id) {
    Swal.fire({
        title: 'Sincronizando...',
        text: 'Aguarde enquanto sincronizamos os dados do WhatsApp',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('/integrations/meta/whatsapp/sync', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Sucesso!', 'Número sincronizado com sucesso', 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Erro', data.error || 'Erro ao sincronizar', 'error');
        }
    })
    .catch(err => {
        Swal.fire('Erro', 'Erro na requisição', 'error');
    });
}

function addWhatsAppPhone() {
    Swal.fire({
        title: 'Adicionar Número WhatsApp',
        html: `
            <div class="mb-3 text-start">
                <label class="form-label required">Phone Number ID (Meta)</label>
                <input type="text" id="phoneNumberId" class="form-control" placeholder="123456789012345">
            </div>
            <div class="mb-3 text-start">
                <label class="form-label required">Número de Telefone</label>
                <input type="text" id="phoneNumber" class="form-control" placeholder="+5511999999999">
            </div>
            <div class="mb-3 text-start">
                <label class="form-label required">WABA ID</label>
                <input type="text" id="wabaId" class="form-control" placeholder="123456789012345">
            </div>
            <div class="mb-3 text-start">
                <label class="form-label required">Meta User ID (do token OAuth)</label>
                <input type="text" id="metaUserId" class="form-control" placeholder="123456789012345">
            </div>
        `,
        width: '600px',
        showCancelButton: true,
        confirmButtonText: 'Adicionar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            return {
                phone_number_id: document.getElementById('phoneNumberId').value,
                phone_number: document.getElementById('phoneNumber').value,
                waba_id: document.getElementById('wabaId').value,
                meta_user_id: document.getElementById('metaUserId').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Adicionando...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('/integrations/meta/whatsapp/add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(result.value)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', 'Número adicionado com sucesso', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Erro', data.error || 'Erro ao adicionar', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Erro', 'Erro na requisição', 'error');
            });
        }
    });
}

function testMessage(type, accountId) {
    Swal.fire({
        title: `Testar Mensagem ${type === 'instagram' ? 'Instagram' : 'WhatsApp'}`,
        html: `
            <div class="mb-3 text-start">
                <label class="form-label required">${type === 'instagram' ? 'Instagram User ID' : 'Número WhatsApp'}</label>
                <input type="text" id="testTo" class="form-control" 
                       placeholder="${type === 'instagram' ? 'Instagram User ID (numérico)' : '+5511999999999'}">
            </div>
            <div class="mb-3 text-start">
                <label class="form-label required">Mensagem</label>
                <textarea id="testMessage" class="form-control" rows="3" placeholder="Digite sua mensagem..."></textarea>
            </div>
        `,
        width: '600px',
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            return {
                type,
                account_id: accountId,
                to: document.getElementById('testTo').value,
                message: document.getElementById('testMessage').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Enviando...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('/integrations/meta/test-message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(result.value)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', 'Mensagem enviada com sucesso', 'success');
                } else {
                    Swal.fire('Erro', data.error || 'Erro ao enviar', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Erro', 'Erro na requisição', 'error');
            });
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../' . str_replace('.', '/', $layout) . '.php';
