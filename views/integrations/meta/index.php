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
        <!--begin::Alerta Importante - Domínio do App-->
        <div class="alert alert-info d-flex align-items-start p-5 mb-5">
            <i class="ki-duotone ki-information-4 fs-2hx text-info me-4 mt-1">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-2 text-info">📋 IMPORTANTE: Configure o domínio no Meta App</h4>
                <span class="mb-3">Antes de conectar contas, você DEVE adicionar o domínio abaixo nas configurações do seu App Meta:</span>
                <div class="bg-light-info rounded p-3 mb-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <code class="fs-5 fw-bold text-dark"><?= parse_url(\App\Helpers\Url::fullUrl('/'), PHP_URL_HOST) ?></code>
                        <button class="btn btn-sm btn-info" type="button" onclick="copyDomain()">
                            <i class="ki-duotone ki-copy fs-3"></i> Copiar
                        </button>
                    </div>
                </div>
                <span class="text-muted fs-7">
                    <strong>Onde configurar:</strong> Meta for Developers → Seu App → 
                    <span class="badge badge-light-info">Configurações</span> → 
                    <span class="badge badge-light-info">Básico</span> → 
                    Campo <strong>"Domínios do app"</strong> → Adicionar domínio
                </span>
            </div>
        </div>
        <!--end::Alerta Importante-->
        
        <!--begin::Alerta Nova Permissão Instagram-->
        <?php if (!empty($tokens)): ?>
        <div class="alert alert-warning d-flex align-items-start p-5 mb-5">
            <i class="ki-duotone ki-shield-tick fs-2hx text-warning me-4 mt-1">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-2 text-warning">⚠️ NOVA PERMISSÃO: Reconecte sua conta Instagram</h4>
                <span class="mb-3">
                    Uma nova permissão (<code>pages_read_engagement</code>) foi adicionada para acessar contas Instagram Business vinculadas às páginas do Facebook.
                </span>
                <span class="mb-3 fw-bold text-dark">
                    📱 Se suas contas Instagram não estão aparecendo, clique em <strong>"Conectar Instagram"</strong> novamente para atualizar as permissões.
                </span>
                <span class="text-muted fs-7">
                    Essa permissão permite que o sistema identifique quais páginas do Facebook têm contas Instagram Business vinculadas.
                </span>
            </div>
        </div>
        <?php endif; ?>
        <!--end::Alerta Nova Permissão-->
        
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
                    <label class="form-label required">Configuration ID (Facebook Login for Business)</label>
                    <input type="text" name="config_id" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars($metaConfig['config_id'] ?? '') ?>" 
                           placeholder="123456789012345">
                    <div class="form-text">
                        <a href="https://developers.facebook.com/apps/" target="_blank">developers.facebook.com</a> → 
                        Seu App → Menu lateral <strong>"Casos de uso"</strong> → 
                        Clique em <strong>"Personalizar"</strong> ao lado de "WhatsApp Embedded Signup" → 
                        Copie o <strong>Configuration ID</strong> exibido.
                        <br>
                        <small class="text-muted">
                            Se não existir, clique em "Criar configuração", selecione as permissões 
                            <code>whatsapp_business_management</code> e <code>whatsapp_business_messaging</code>, 
                            e salve.
                        </small>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Redirect URI (OAuth)</label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars(\App\Helpers\Url::fullUrl('/integrations/meta/oauth/callback')) ?>" 
                               readonly onclick="this.select()" id="redirect_uri_field">
                        <button class="btn btn-light-primary" type="button" onclick="copyToClipboard('redirect_uri_field', this)">
                            <i class="ki-duotone ki-copy fs-3"></i>
                            Copiar
                        </button>
                    </div>
                    <div class="form-text">
                        Copie e adicione em: Facebook Login → Configurações → URIs de redirecionamento
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Webhook URL</label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars(\App\Helpers\Url::fullUrl('/webhooks/meta')) ?>" 
                               readonly onclick="this.select()" id="webhook_url_field">
                        <button class="btn btn-light-primary" type="button" onclick="copyToClipboard('webhook_url_field', this)">
                            <i class="ki-duotone ki-copy fs-3"></i>
                            Copiar
                        </button>
                    </div>
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
            <button class="btn btn-sm btn-light-success" onclick="addWhatsAppPhone()" <?= empty($metaConfig['app_id']) ? 'disabled title="Configure o App ID primeiro"' : '' ?>>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="me-1">
                    <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0 0 3.603 0 8.05 0 12.07 2.93 15.44 6.75 16v-5.625h-2.03v-2.33h2.03V6.272c0-2 1.194-3.105 3.015-3.105.874 0 1.79.156 1.79.156v1.964h-1.009c-.993 0-1.303.616-1.303 1.248v1.5h2.219l-.355 2.326H10.24V16c3.824-.56 6.762-3.927 6.762-7.951z"/>
                </svg>
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
                <div class="row g-5">
                    <?php foreach ($whatsappPhones as $phone): 
                        $qualityColors = ['GREEN' => 'success', 'YELLOW' => 'warning', 'RED' => 'danger', 'UNKNOWN' => 'secondary'];
                        $color = $qualityColors[$phone['quality_rating']] ?? 'secondary';
                        $isConnected = $phone['has_valid_token'] && $phone['is_connected'];
                        $iaId = $phone['integration_account_id'] ?? null;
                        $ia = $phone['integration_account'] ?? null;
                    ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card border border-<?= $isConnected ? 'success' : 'danger' ?> border-dashed h-100">
                            <div class="card-body p-6">
                                <!-- Header: Número + Status -->
                                <div class="d-flex align-items-center mb-4">
                                    <div class="symbol symbol-45px me-4">
                                        <div class="symbol-label bg-light-success">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#25D366" viewBox="0 0 24 24">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <span class="text-dark fw-bold fs-5 d-block"><?= htmlspecialchars($phone['verified_name'] ?? 'WhatsApp') ?></span>
                                        <span class="text-muted fw-semibold fs-7"><?= htmlspecialchars($phone['display_phone_number'] ?? $phone['phone_number']) ?></span>
                                    </div>
                                    <span class="badge badge-light-<?= $isConnected ? 'success' : 'danger' ?>">
                                        <?= $isConnected ? 'Conectado' : 'Desconectado' ?>
                                    </span>
                                </div>
                                
                                <!-- Info badges -->
                                <div class="d-flex flex-wrap gap-2 mb-4">
                                    <span class="badge badge-light-<?= $color ?> fs-8">
                                        <i class="ki-duotone ki-chart-simple fs-7 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                        <?= $phone['quality_rating'] ?>
                                    </span>
                                    <span class="badge badge-light-<?= $phone['account_mode'] === 'LIVE' ? 'primary' : 'warning' ?> fs-8">
                                        <?= $phone['account_mode'] ?>
                                    </span>
                                    <span class="badge badge-light-secondary fs-8" title="Phone Number ID">
                                        ID: <?= htmlspecialchars($phone['phone_number_id']) ?>
                                    </span>
                                </div>
                                
                                <!-- Funil/Etapa configurados -->
                                <div class="separator separator-dashed mb-4"></div>
                                <?php if (!empty($phone['default_funnel_name'])): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="ki-duotone ki-element-11 fs-5 text-primary me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                    <span class="text-muted fw-semibold fs-7 me-2">Funil:</span>
                                    <span class="fw-bold fs-7"><?= htmlspecialchars($phone['default_funnel_name']) ?></span>
                                </div>
                                <?php if (!empty($phone['default_stage_name'])): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="ki-duotone ki-abstract-26 fs-5 text-info me-2"><span class="path1"></span><span class="path2"></span></i>
                                    <span class="text-muted fw-semibold fs-7 me-2">Etapa:</span>
                                    <span class="fw-bold fs-7"><?= htmlspecialchars($phone['default_stage_name']) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="ki-duotone ki-information-5 fs-5 text-muted me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    <span class="text-muted fs-8">Usando funil/etapa padrão do sistema</span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Ações -->
                                <div class="separator separator-dashed my-4"></div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if ($iaId): ?>
                                    <button class="btn btn-light-success btn-sm flex-grow-1"
                                            onclick="editPhoneSettings(<?= $phone['id'] ?>, <?= $iaId ?>, '<?= htmlspecialchars($phone['verified_name'] ?? $phone['phone_number'], ENT_QUOTES) ?>', <?= $ia['default_funnel_id'] ?? 'null' ?>, <?= $ia['default_stage_id'] ?? 'null' ?>)"
                                            title="Configurar Funil/Etapa">
                                        <i class="ki-duotone ki-setting-2 fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        Configurar
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-light-primary btn-sm" 
                                            onclick="syncWhatsApp(<?= $phone['id'] ?>)" 
                                            title="Sincronizar">
                                        <i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span></i>
                                    </button>
                                    <button class="btn btn-light-info btn-sm" 
                                            onclick="testMessage('whatsapp', <?= $phone['id'] ?>)" 
                                            title="Testar Mensagem">
                                        <i class="ki-duotone ki-send fs-4"><span class="path1"></span><span class="path2"></span></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Configurar Funil/Etapa do Número WhatsApp -->
<div class="modal fade" id="kt_modal_phone_settings" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="fw-bold">
                    <i class="ki-duotone ki-setting-2 fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                    Configurar Número
                </h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <form id="phoneSettingsForm">
                <div class="modal-body py-6 px-lg-10">
                    <input type="hidden" id="ps_phone_id" name="phone_id">
                    <input type="hidden" id="ps_account_id" name="account_id">
                    
                    <div class="d-flex align-items-center p-4 rounded mb-6" style="background: var(--bs-gray-100);">
                        <div class="symbol symbol-40px me-3">
                            <div class="symbol-label bg-light-success">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#25D366" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                </svg>
                            </div>
                        </div>
                        <div>
                            <span class="fw-bold fs-6" id="ps_phone_name"></span>
                        </div>
                    </div>
                    
                    <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-4 mb-6">
                        <i class="ki-duotone ki-information fs-2x text-info me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <div class="d-flex flex-column">
                            <span class="fs-7">Conversas criadas por este número entrarão automaticamente neste funil/etapa quando não houver automação específica.</span>
                        </div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Funil Padrão</label>
                        <select name="default_funnel_id" id="ps_funnel_select" class="form-select form-select-solid" onchange="loadPhoneFunnelStages(this.value)">
                            <option value="">Usar padrão do sistema</option>
                            <?php if (!empty($funnels)): ?>
                                <?php foreach ($funnels as $funnel): ?>
                                    <option value="<?= $funnel['id'] ?>"><?= htmlspecialchars($funnel['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">Deixe vazio para usar o funil padrão do sistema</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Etapa Padrão</label>
                        <select name="default_stage_id" id="ps_stage_select" class="form-select form-select-solid">
                            <option value="">Selecione um funil primeiro</option>
                        </select>
                        <div class="form-text">Deixe vazio para usar a primeira etapa do funil</div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="button" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="ki-duotone ki-check fs-3 me-1"></i>
                        Salvar Configurações
                    </button>
                </div>
            </form>
        </div>
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
    const appId = '<?= htmlspecialchars($metaConfig['app_id'] ?? '') ?>';
    
    if (!appId) {
        Swal.fire('Configuração Necessária', 'Configure o App ID da Meta nas configurações acima primeiro.', 'warning');
        return;
    }
    
    Swal.fire({
        title: '<span style="color:#25D366"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="#25D366" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:8px"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>Conectar WhatsApp</span>',
        html: `
            <div class="text-start">
                <p class="text-muted mb-4">Clique no botão abaixo para conectar seu número WhatsApp via <strong>Facebook Login</strong>. O processo é seguro e automático.</p>
                
                <div class="d-flex flex-column gap-3 mb-4">
                    <div class="d-flex align-items-center gap-3 p-3 rounded" style="background: var(--bs-gray-100);">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:#1877f2;flex-shrink:0">
                            <span class="text-white fw-bold">1</span>
                        </div>
                        <div>
                            <div class="fw-semibold">Login com Facebook</div>
                            <div class="text-muted fs-7">Faça login na conta Meta que gerencia o WhatsApp Business</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3 p-3 rounded" style="background: var(--bs-gray-100);">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:#25D366;flex-shrink:0">
                            <span class="text-white fw-bold">2</span>
                        </div>
                        <div>
                            <div class="fw-semibold">Selecione o Número</div>
                            <div class="text-muted fs-7">Escolha qual conta WhatsApp Business e número deseja conectar</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3 p-3 rounded" style="background: var(--bs-gray-100);">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:#00a884;flex-shrink:0">
                            <span class="text-white fw-bold">3</span>
                        </div>
                        <div>
                            <div class="fw-semibold">Pronto!</div>
                            <div class="text-muted fs-7">Seu número será conectado automaticamente com token e webhook configurados</div>
                        </div>
                    </div>
                </div>
                
                <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-4">
                    <i class="ki-duotone ki-information-5 fs-2tx text-info me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    <div class="d-flex flex-stack flex-grow-1">
                        <div class="fs-7 text-gray-700">
                            <strong>Requisitos:</strong> Seu app Meta deve ter os produtos <em>WhatsApp</em> e <em>Facebook Login for Business</em> configurados.
                        </div>
                    </div>
                </div>
            </div>
        `,
        width: '550px',
        showCancelButton: true,
        confirmButtonText: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" class="me-2"><path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0 0 3.603 0 8.05 0 12.07 2.93 15.44 6.75 16v-5.625h-2.03v-2.33h2.03V6.272c0-2 1.194-3.105 3.015-3.105.874 0 1.79.156 1.79.156v1.964h-1.009c-.993 0-1.303.616-1.303 1.248v1.5h2.219l-.355 2.326H10.24V16c3.824-.56 6.762-3.927 6.762-7.951z"/></svg> Conectar com Facebook',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#1877f2',
        customClass: {
            confirmButton: 'btn btn-primary px-6',
            cancelButton: 'btn btn-light'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            initFacebookSignup(appId);
        }
    });
}

// Session Logging Event Listener (oficial Meta) - captura dados do Embedded Signup
let metaSignupSessionData = null;
window.addEventListener('message', (event) => {
    if (!event.origin || !event.origin.endsWith('facebook.com')) return;
    try {
        const data = JSON.parse(event.data);
        if (data.type === 'WA_EMBEDDED_SIGNUP') {
            console.log('[Meta Signup] event:', data);
            if (data.event === 'FINISH' || data.event === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') {
                metaSignupSessionData = {
                    phone_number_id: data.data?.phone_number_id || null,
                    waba_id: data.data?.waba_id || null,
                    event_type: data.event,
                };
            }
        }
    } catch {}
});

function initFacebookSignup(appId) {
    if (typeof FB === 'undefined') {
        Swal.fire({
            title: 'Carregando...',
            text: 'Inicializando Facebook SDK',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        
        window.fbAsyncInit = function() {
            FB.init({
                appId: appId,
                autoLogAppEvents: true,
                xfbml: true,
                version: 'v21.0'
            });
            Swal.close();
            executeFBLogin();
        };
        
        const script = document.createElement('script');
        script.src = 'https://connect.facebook.net/pt_BR/sdk.js';
        script.async = true;
        script.defer = true;
        script.crossOrigin = 'anonymous';
        script.onerror = () => {
            Swal.fire('Erro', 'Não foi possível carregar o Facebook SDK. Verifique sua conexão.', 'error');
        };
        document.body.appendChild(script);
    } else {
        executeFBLogin();
    }
}

function executeFBLogin() {
    FB.login(function(response) {
        if (response.authResponse && response.authResponse.code) {
            processSignupCode(response.authResponse.code, response);
        } else {
            Swal.fire({
                icon: 'info',
                title: 'Cancelado',
                text: 'O processo de login foi cancelado ou não foi autorizado.',
                confirmButtonColor: '#6c757d'
            });
        }
    }, {
        config_id: '<?= htmlspecialchars($metaConfig['config_id'] ?? '') ?>',
        response_type: 'code',
        override_default_response_type: true,
        extras: {
            setup: {}
        }
    });
}

function processSignupCode(code, fullResponse) {
    Swal.fire({
        title: 'Conectando WhatsApp...',
        html: `
            <div class="d-flex flex-column align-items-center gap-3">
                <div class="spinner-border text-success" role="status" style="width:3rem;height:3rem">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="text-muted">
                    <div id="signup-step" class="fw-semibold">Trocando código por token de acesso...</div>
                    <div class="text-muted fs-7 mt-1">Isso pode levar alguns segundos</div>
                </div>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false
    });
    
    fetch('<?= \App\Helpers\Url::to('/integrations/meta/whatsapp/signup') ?>', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ 
            code: code, 
            session_info: fullResponse 
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const count = data.registered?.length || 0;
            const phones = (data.registered || []).map(r => 
                `<div class="d-flex align-items-center gap-2 p-2 rounded mb-1" style="background:var(--bs-gray-100)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#25D366" viewBox="0 0 16 16">
                        <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326z"/>
                    </svg>
                    <div>
                        <div class="fw-semibold">${r.name || 'WhatsApp'}</div>
                        <div class="text-muted fs-7">${r.phone}</div>
                    </div>
                    <span class="badge badge-light-success ms-auto">Conectado</span>
                </div>`
            ).join('');
            
            Swal.fire({
                icon: 'success',
                title: 'WhatsApp Conectado!',
                html: `
                    <div class="text-start">
                        <p class="text-muted">${count} número(s) conectado(s) com sucesso:</p>
                        ${phones}
                    </div>
                `,
                confirmButtonText: 'OK',
                confirmButtonColor: '#25D366'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro ao Conectar',
                text: data.error || 'Erro desconhecido ao processar o signup.',
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Erro de Conexão',
            text: 'Erro na requisição: ' + err.message,
            confirmButtonColor: '#dc3545'
        });
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

/**
 * Copiar domínio para clipboard
 */
function copyDomain() {
    const domain = '<?= parse_url(\App\Helpers\Url::fullUrl('/'), PHP_URL_HOST) ?>';
    
    // Criar elemento temporário para copiar
    const temp = document.createElement('textarea');
    temp.value = domain;
    document.body.appendChild(temp);
    temp.select();
    
    try {
        document.execCommand('copy');
        Swal.fire({
            icon: 'success',
            title: 'Copiado!',
            text: `Domínio "${domain}" copiado para a área de transferência`,
            timer: 2000,
            showConfirmButton: false
        });
    } catch (err) {
        console.error('Erro ao copiar:', err);
        Swal.fire('Erro', 'Não foi possível copiar o domínio', 'error');
    }
    
    document.body.removeChild(temp);
}

/**
 * Copiar texto para clipboard
 */
function copyToClipboard(fieldId, button) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    // Selecionar e copiar
    field.select();
    field.setSelectionRange(0, 99999); // Para mobile
    
    try {
        document.execCommand('copy');
        
        // Feedback visual
        const originalHtml = button.innerHTML;
        button.innerHTML = '<i class="ki-duotone ki-check fs-3"></i> Copiado!';
        button.classList.remove('btn-light-primary');
        button.classList.add('btn-success');
        
        setTimeout(() => {
            button.innerHTML = originalHtml;
            button.classList.remove('btn-success');
            button.classList.add('btn-light-primary');
        }, 2000);
        
    } catch (err) {
        console.error('Erro ao copiar:', err);
        Swal.fire('Erro', 'Não foi possível copiar', 'error');
    }
}

// ==================== CONFIGURAÇÕES DO NÚMERO (FUNIL/ETAPA) ====================

function editPhoneSettings(phoneId, accountId, phoneName, currentFunnelId, currentStageId) {
    document.getElementById('ps_phone_id').value = phoneId;
    document.getElementById('ps_account_id').value = accountId;
    document.getElementById('ps_phone_name').textContent = phoneName;
    
    const funnelSelect = document.getElementById('ps_funnel_select');
    funnelSelect.value = currentFunnelId || '';
    
    if (currentFunnelId) {
        loadPhoneFunnelStages(currentFunnelId, currentStageId);
    } else {
        document.getElementById('ps_stage_select').innerHTML = '<option value="">Selecione um funil primeiro</option>';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_phone_settings'));
    modal.show();
}

function loadPhoneFunnelStages(funnelId, selectedStageId = null) {
    const stageSelect = document.getElementById('ps_stage_select');
    
    if (!funnelId) {
        stageSelect.innerHTML = '<option value="">Selecione um funil primeiro</option>';
        return;
    }
    
    stageSelect.innerHTML = '<option value="">Carregando...</option>';
    stageSelect.disabled = true;
    
    fetch(`<?= \App\Helpers\Url::to('/funnels') ?>/${funnelId}/stages`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        const stages = data.stages || data.data || [];
        let html = '<option value="">Usar primeira etapa</option>';
        
        if (Array.isArray(stages)) {
            stages.forEach(s => {
                const selected = (selectedStageId && s.id == selectedStageId) ? ' selected' : '';
                html += `<option value="${s.id}"${selected}>${s.name || 'Etapa #' + s.id}</option>`;
            });
        }
        
        stageSelect.innerHTML = html;
        stageSelect.disabled = false;
    })
    .catch(() => {
        stageSelect.innerHTML = '<option value="">Erro ao carregar etapas</option>';
        stageSelect.disabled = false;
    });
}

document.getElementById('phoneSettingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const accountId = document.getElementById('ps_account_id').value;
    const funnelId = document.getElementById('ps_funnel_select').value;
    const stageId = document.getElementById('ps_stage_select').value;
    
    Swal.fire({ title: 'Salvando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(`<?= \App\Helpers\Url::to('/integrations/whatsapp') ?>/${accountId}/settings`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
            default_funnel_id: funnelId || null,
            default_stage_id: stageId || null
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Salvo!', 'Configurações atualizadas com sucesso', 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Erro', data.error || 'Erro ao salvar configurações', 'error');
        }
    })
    .catch(err => {
        Swal.fire('Erro', 'Erro na requisição: ' + err.message, 'error');
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../' . str_replace('.', '/', $layout) . '.php';
