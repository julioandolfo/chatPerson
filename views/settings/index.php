<?php
$layout = 'layouts.metronic.app';
$title = 'Configurações';

$activeTab = $tab ?? 'general';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Configurações do Sistema</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <!--begin::Tabs-->
        <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary ms-0 me-10 <?= $activeTab === 'general' ? 'active' : '' ?>" 
                   href="<?= \App\Helpers\Url::to('/settings?tab=general') ?>">
                    <i class="ki-duotone ki-setting-2 fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Gerais
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10 <?= $activeTab === 'email' ? 'active' : '' ?>" 
                   href="<?= \App\Helpers\Url::to('/settings?tab=email') ?>">
                    <i class="ki-duotone ki-sms fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Email
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10 <?= $activeTab === 'whatsapp' ? 'active' : '' ?>" 
                   href="<?= \App\Helpers\Url::to('/settings?tab=whatsapp') ?>">
                    <i class="ki-duotone ki-phone fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    WhatsApp
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10 <?= $activeTab === 'security' ? 'active' : '' ?>" 
                   href="<?= \App\Helpers\Url::to('/settings?tab=security') ?>">
                    <i class="ki-duotone ki-shield fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Segurança
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10 <?= $activeTab === 'conversations' ? 'active' : '' ?>" 
                   href="<?= \App\Helpers\Url::to('/settings?tab=conversations') ?>">
                    <i class="ki-duotone ki-chat fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Conversas
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10 <?= $activeTab === 'ai-assistant' ? 'active' : '' ?>" 
                   href="<?= \App\Helpers\Url::to('/settings?tab=ai-assistant') ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
                        <rect x="4" y="8" width="16" height="12" rx="2"></rect>
                        <rect x="9" y="2" width="6" height="6" rx="1"></rect>
                        <circle cx="9" cy="13" r="1.5" fill="currentColor"></circle>
                        <circle cx="15" cy="13" r="1.5" fill="currentColor"></circle>
                        <path d="M9 17h6"></path>
                    </svg>
                    Assistente IA
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10 <?= $activeTab === 'websocket' ? 'active' : '' ?>" 
                   href="<?= \App\Helpers\Url::to('/settings?tab=websocket') ?>">
                    <i class="ki-duotone ki-wifi fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Tempo Real
                </a>
            </li>
        </ul>
        <!--end::Tabs-->
        
        <div class="separator mb-6"></div>
        
        <!--begin::Tab Content-->
        <?php if ($activeTab === 'general'): ?>
            <!--begin::Form Geral-->
            <form id="kt_settings_general_form" class="form">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">Nome da Aplicação</label>
                            <input type="text" name="app_name" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($generalSettings['app_name'] ?? '') ?>" required />
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">Fuso Horário</label>
                            <select name="app_timezone" class="form-select form-select-solid" required>
                                <option value="America/Sao_Paulo" <?= ($generalSettings['app_timezone'] ?? '') === 'America/Sao_Paulo' ? 'selected' : '' ?>>America/Sao_Paulo</option>
                                <option value="America/Manaus" <?= ($generalSettings['app_timezone'] ?? '') === 'America/Manaus' ? 'selected' : '' ?>>America/Manaus</option>
                                <option value="America/Fortaleza" <?= ($generalSettings['app_timezone'] ?? '') === 'America/Fortaleza' ? 'selected' : '' ?>>America/Fortaleza</option>
                                <option value="UTC" <?= ($generalSettings['app_timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">Idioma</label>
                            <select name="app_locale" class="form-select form-select-solid" required>
                                <option value="pt_BR" <?= ($generalSettings['app_locale'] ?? '') === 'pt_BR' ? 'selected' : '' ?>>Português (Brasil)</option>
                                <option value="en_US" <?= ($generalSettings['app_locale'] ?? '') === 'en_US' ? 'selected' : '' ?>>English (US)</option>
                                <option value="es_ES" <?= ($generalSettings['app_locale'] ?? '') === 'es_ES' ? 'selected' : '' ?>>Español</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">Máx. Conversas por Agente</label>
                            <input type="number" name="max_conversations_per_agent" class="form-control form-control-solid" 
                                   value="<?= (int)($generalSettings['max_conversations_per_agent'] ?? 10) ?>" min="1" required />
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Timeout de Conversa (minutos)</label>
                            <input type="number" name="conversation_timeout_minutes" class="form-control form-control-solid" 
                                   value="<?= (int)($generalSettings['conversation_timeout_minutes'] ?? 30) ?>" min="1" />
                            <div class="form-text">Tempo de inatividade antes de considerar a conversa inativa</div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Período Mínimo para Reabertura (minutos)</label>
                            <input type="number" name="conversation_reopen_grace_period_minutes" class="form-control form-control-solid" 
                                   value="<?= (int)($generalSettings['conversation_reopen_grace_period_minutes'] ?? 10) ?>" min="0" />
                            <div class="form-text"><strong>Conversas fechadas SÓ reabrem APÓS esse tempo.</strong> Mensagens recebidas ANTES desse período são salvas mas a conversa continua fechada (ideal para ignorar "Ok", "Obrigado"). Após esse período, cria nova conversa com todas as regras.</div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2 d-flex align-items-center">
                                <input type="checkbox" name="auto_assign_conversations" class="form-check-input me-2" 
                                       <?= ($generalSettings['auto_assign_conversations'] ?? false) ? 'checked' : '' ?> />
                                Atribuir conversas automaticamente
                            </label>
                        </div>
                    </div>
                </div>
                <div class="separator separator-dashed my-5"></div>
                <h4 class="fw-bold mb-4">Logo do Sistema</h4>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Logo</label>
                    <div class="d-flex align-items-center gap-5">
                        <div class="symbol symbol-100px">
                            <img id="logo_preview" 
                                 src="<?= !empty($generalSettings['app_logo']) ? \App\Helpers\Url::to($generalSettings['app_logo']) : \App\Helpers\Url::asset('media/logos/default.svg') ?>" 
                                 alt="Logo" 
                                 class="w-100 h-100 object-fit-contain" 
                                 style="max-height: 100px; object-fit: contain;" />
                        </div>
                        <div class="flex-grow-1">
                            <input type="file" 
                                   id="logo_upload" 
                                   name="app_logo" 
                                   class="form-control form-control-solid" 
                                   accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/gif,image/webp" />
                            <div class="form-text">Formatos aceitos: PNG, JPG, SVG, GIF, WEBP. Tamanho máximo: 2MB</div>
                            <?php if (!empty($generalSettings['app_logo'])): ?>
                                <button type="button" class="btn btn-sm btn-light-danger mt-2" onclick="removeLogo()">
                                    <i class="ki-duotone ki-trash fs-5 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                        <span class="path5"></span>
                                    </i>
                                    Remover Logo
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="separator separator-dashed my-5"></div>
                <h4 class="fw-bold mb-4">Favicon</h4>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Favicon</label>
                    <div class="d-flex align-items-center gap-5">
                        <div class="symbol symbol-50px">
                            <img id="favicon_preview" 
                                 src="<?= !empty($generalSettings['app_favicon']) ? \App\Helpers\Url::to($generalSettings['app_favicon']) : \App\Helpers\Url::asset('media/logos/favicon.ico') ?>" 
                                 alt="Favicon" 
                                 class="w-100 h-100 object-fit-contain" 
                                 style="max-height: 50px; object-fit: contain;" />
                        </div>
                        <div class="flex-grow-1">
                            <input type="file" 
                                   id="favicon_upload" 
                                   name="app_favicon" 
                                   class="form-control form-control-solid" 
                                   accept="image/x-icon,image/png,image/jpeg,image/jpg,image/svg+xml" />
                            <div class="form-text">Formatos aceitos: ICO, PNG, JPG, SVG. Tamanho recomendado: 32x32px ou 16x16px. Tamanho máximo: 500KB</div>
                            <?php if (!empty($generalSettings['app_favicon'])): ?>
                                <button type="button" class="btn btn-sm btn-light-danger mt-2" onclick="removeFavicon()">
                                    <i class="ki-duotone ki-trash fs-5 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                        <span class="path5"></span>
                                    </i>
                                    Remover Favicon
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="separator separator-dashed my-5"></div>
                <h4 class="fw-bold mb-4">OpenAI (Agentes de IA)</h4>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">API Key</label>
                    <input type="password" name="openai_api_key" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars($generalSettings['openai_api_key'] ?? '') ?>" 
                           placeholder="sk-..." autocomplete="off" />
                    <div class="form-text">Chave de API da OpenAI para uso com Agentes de IA. Obtenha em <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></div>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar Configurações</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
            <!--end::Form Geral-->
        <?php elseif ($activeTab === 'email'): ?>
            <!--begin::Form Email-->
            <form id="kt_settings_email_form" class="form">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2 d-flex align-items-center">
                        <input type="checkbox" name="email_enabled" class="form-check-input me-2" 
                               <?= ($emailSettings['email_enabled'] ?? false) ? 'checked' : '' ?> />
                        Habilitar envio de emails
                    </label>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Servidor SMTP</label>
                            <input type="text" name="email_host" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($emailSettings['email_host'] ?? '') ?>" placeholder="smtp.exemplo.com" />
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Porta</label>
                            <input type="number" name="email_port" class="form-control form-control-solid" 
                                   value="<?= (int)($emailSettings['email_port'] ?? 587) ?>" placeholder="587" />
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Usuário</label>
                            <input type="text" name="email_username" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($emailSettings['email_username'] ?? '') ?>" />
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Senha</label>
                            <input type="password" name="email_password" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($emailSettings['email_password'] ?? '') ?>" />
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Criptografia</label>
                            <select name="email_encryption" class="form-select form-select-solid">
                                <option value="tls" <?= ($emailSettings['email_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= ($emailSettings['email_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="" <?= empty($emailSettings['email_encryption']) ? 'selected' : '' ?>>Nenhuma</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Email Remetente</label>
                            <input type="email" name="email_from_address" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($emailSettings['email_from_address'] ?? '') ?>" />
                        </div>
                    </div>
                </div>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Nome do Remetente</label>
                    <input type="text" name="email_from_name" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars($emailSettings['email_from_name'] ?? '') ?>" />
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar Configurações</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
            <!--end::Form Email-->
        <?php elseif ($activeTab === 'whatsapp'): ?>
            <!--begin::Form WhatsApp-->
            <form id="kt_settings_whatsapp_form" class="form">
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Provedor</label>
                    <select name="whatsapp_provider" class="form-select form-select-solid" required>
                        <option value="quepasa" <?= ($whatsappSettings['whatsapp_provider'] ?? '') === 'quepasa' ? 'selected' : '' ?>>Quepasa</option>
                        <option value="evolution" <?= ($whatsappSettings['whatsapp_provider'] ?? '') === 'evolution' ? 'selected' : '' ?>>Evolution API</option>
                    </select>
                </div>
                <div class="separator separator-dashed my-5"></div>
                <h4 class="fw-bold mb-4">Quepasa</h4>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">URL da API</label>
                            <input type="url" name="whatsapp_quepasa_url" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($whatsappSettings['whatsapp_quepasa_url'] ?? '') ?>" placeholder="https://api.quepasa.com.br" />
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Token</label>
                            <input type="text" name="whatsapp_quepasa_token" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($whatsappSettings['whatsapp_quepasa_token'] ?? '') ?>" />
                        </div>
                    </div>
                </div>
                <div class="separator separator-dashed my-5"></div>
                <h4 class="fw-bold mb-4">Evolution API</h4>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">URL da API</label>
                            <input type="url" name="whatsapp_evolution_url" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($whatsappSettings['whatsapp_evolution_url'] ?? '') ?>" placeholder="https://api.evolution.com.br" />
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">API Key</label>
                            <input type="text" name="whatsapp_evolution_api_key" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($whatsappSettings['whatsapp_evolution_api_key'] ?? '') ?>" />
                        </div>
                    </div>
                </div>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Webhook URL</label>
                    <input type="url" name="whatsapp_webhook_url" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars($whatsappSettings['whatsapp_webhook_url'] ?? '') ?>" placeholder="https://seusite.com.br/webhooks/whatsapp" />
                    <div class="form-text">URL para receber eventos do WhatsApp</div>
                </div>
                <div class="separator separator-dashed my-5"></div>
                <div class="fv-row mb-7">
                    <label class="form-check form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" name="whatsapp_allow_group_messages" 
                               value="1" <?= ($whatsappSettings['whatsapp_allow_group_messages'] ?? true) ? 'checked' : '' ?> />
                        <span class="form-check-label fw-semibold">
                            Permitir mensagens de grupos
                        </span>
                    </label>
                    <div class="form-text">Se desabilitado, mensagens recebidas de grupos do WhatsApp serão ignoradas</div>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar Configurações</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
            <!--end::Form WhatsApp-->
        <?php elseif ($activeTab === 'security'): ?>
            <!--begin::Form Segurança-->
            <form id="kt_settings_security_form" class="form">
                <h4 class="fw-bold mb-4">Política de Senhas</h4>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">Tamanho Mínimo</label>
                            <input type="number" name="password_min_length" class="form-control form-control-solid" 
                                   value="<?= (int)($securitySettings['password_min_length'] ?? 6) ?>" min="4" max="32" required />
                        </div>
                    </div>
                </div>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Requisitos de Senha</label>
                    <div class="d-flex flex-column gap-3">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="password_require_uppercase" class="form-check-input me-2" 
                                   <?= ($securitySettings['password_require_uppercase'] ?? false) ? 'checked' : '' ?> />
                            Exigir letras maiúsculas
                        </label>
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="password_require_lowercase" class="form-check-input me-2" 
                                   <?= ($securitySettings['password_require_lowercase'] ?? false) ? 'checked' : '' ?> />
                            Exigir letras minúsculas
                        </label>
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="password_require_numbers" class="form-check-input me-2" 
                                   <?= ($securitySettings['password_require_numbers'] ?? false) ? 'checked' : '' ?> />
                            Exigir números
                        </label>
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="password_require_symbols" class="form-check-input me-2" 
                                   <?= ($securitySettings['password_require_symbols'] ?? false) ? 'checked' : '' ?> />
                            Exigir símbolos especiais
                        </label>
                    </div>
                </div>
                <div class="separator separator-dashed my-5"></div>
                <h4 class="fw-bold mb-4">Sessão e Login</h4>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">Tempo de Sessão (minutos)</label>
                            <input type="number" name="session_lifetime" class="form-control form-control-solid" 
                                   value="<?= (int)($securitySettings['session_lifetime'] ?? 120) ?>" min="5" required />
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">Máx. Tentativas de Login</label>
                            <input type="number" name="max_login_attempts" class="form-control form-control-solid" 
                                   value="<?= (int)($securitySettings['max_login_attempts'] ?? 5) ?>" min="1" required />
                        </div>
                    </div>
                </div>
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Duração do Bloqueio (minutos)</label>
                    <input type="number" name="lockout_duration" class="form-control form-control-solid" 
                           value="<?= (int)($securitySettings['lockout_duration'] ?? 15) ?>" min="1" required />
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar Configurações</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
            <!--end::Form Segurança-->
        <?php elseif ($activeTab === 'conversations'): ?>
            <!--begin::Form Conversas-->
            <?php 
            $cs = $conversationSettings ?? [];
            include __DIR__ . '/conversations-tab.php';
            ?>
            <!--end::Form Conversas-->
        <?php elseif ($activeTab === 'ai-assistant'): ?>
            <!--begin::Form Assistente IA-->
            <?php 
            include __DIR__ . '/ai-assistant-tab.php';
            ?>
            <!--end::Form Assistente IA-->
        <?php endif; ?>
        
        <?php if ($activeTab === 'websocket'): ?>
            <!--begin::Form Tempo Real-->
            <?php
            include __DIR__ . '/websocket-tab.php';
            ?>
            <!--end::Form Tempo Real-->
        <?php endif; ?>
        <!--end::Tab Content-->
    </div>
</div>
<!--end::Card-->

<?php 
$content = ob_get_clean(); 
$scripts = "
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preview de logo ao selecionar arquivo
    const logoUpload = document.getElementById('logo_upload');
    const logoPreview = document.getElementById('logo_preview');
    if (logoUpload && logoPreview) {
        logoUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validar tamanho (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Arquivo muito grande. Tamanho máximo: 2MB');
                    e.target.value = '';
                    return;
                }
                
                // Validar tipo
                const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Tipo de arquivo não permitido. Use PNG, JPG, SVG, GIF ou WEBP');
                    e.target.value = '';
                    return;
                }
                
                // Mostrar preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    logoPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Preview de favicon ao selecionar arquivo
    const faviconUpload = document.getElementById('favicon_upload');
    const faviconPreview = document.getElementById('favicon_preview');
    if (faviconUpload && faviconPreview) {
        faviconUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validar tamanho (500KB)
                if (file.size > 500 * 1024) {
                    alert('Arquivo muito grande. Tamanho máximo: 500KB');
                    e.target.value = '';
                    return;
                }
                
                // Validar tipo
                const allowedTypes = ['image/x-icon', 'image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Tipo de arquivo não permitido. Use ICO, PNG, JPG ou SVG');
                    e.target.value = '';
                    return;
                }
                
                // Mostrar preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    faviconPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Remover logo
    window.removeLogo = function() {
        if (confirm('Tem certeza que deseja remover a logo? O sistema voltará a usar a logo padrão.')) {
            fetch('" . \App\Helpers\Url::to('/settings/remove-logo') . "', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    logoPreview.src = '" . \App\Helpers\Url::asset('media/logos/default.svg') . "';
                    if (logoUpload) logoUpload.value = '';
                    location.reload();
                } else {
                    alert('Erro ao remover logo: ' + (data.message || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao remover logo');
            });
        }
    };
    
    // Form Geral
    const generalForm = document.getElementById('kt_settings_general_form');
    if (generalForm) {
        generalForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Se há logo para upload, fazer upload primeiro
            const logoFile = logoUpload?.files[0];
            if (logoFile) {
                const formData = new FormData();
                formData.append('logo', logoFile);
                
                fetch('" . \App\Helpers\Url::to('/settings/upload-logo') . "', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Erro ao fazer upload da logo: ' + (data.message || 'Erro desconhecido'));
                        return;
                    }
                    // Continuar com favicon se houver
                    uploadFaviconIfNeeded();
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao fazer upload da logo');
                    uploadFaviconIfNeeded();
                });
            } else {
                uploadFaviconIfNeeded();
            }
            
            function uploadFaviconIfNeeded() {
                const faviconFile = faviconUpload?.files[0];
                if (faviconFile) {
                    const formData = new FormData();
                    formData.append('favicon', faviconFile);
                    
                    fetch('" . \App\Helpers\Url::to('/settings/upload-favicon') . "', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            alert('Erro ao fazer upload do favicon: ' + (data.message || 'Erro desconhecido'));
                        }
                        // Depois do upload, salvar outras configurações
                        submitForm(generalForm, '" . \App\Helpers\Url::to('/settings/general') . "');
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        alert('Erro ao fazer upload do favicon');
                        // Continuar mesmo assim
                        submitForm(generalForm, '" . \App\Helpers\Url::to('/settings/general') . "');
                    });
                } else {
                    // Sem favicon para upload, salvar outras configurações
                    submitForm(generalForm, '" . \App\Helpers\Url::to('/settings/general') . "');
                }
            }
        });
    }
    
    // Form Email
    const emailForm = document.getElementById('kt_settings_email_form');
    if (emailForm) {
        emailForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, '" . \App\Helpers\Url::to('/settings/email') . "');
        });
    }
    
    // Form WhatsApp
    const whatsappForm = document.getElementById('kt_settings_whatsapp_form');
    if (whatsappForm) {
        whatsappForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, '" . \App\Helpers\Url::to('/settings/whatsapp') . "');
        });
    }
    
    // Form Segurança
    const securityForm = document.getElementById('kt_settings_security_form');
    if (securityForm) {
        securityForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, '" . \App\Helpers\Url::to('/settings/security') . "');
        });
    }
    
    // Form Tempo Real (WebSocket/Polling)
    const websocketForm = document.getElementById('kt_settings_websocket_form');
    if (websocketForm) {
        websocketForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, '" . \App\Helpers\Url::to('/settings/websocket') . "');
        });
    }
    
    function submitForm(form, url) {
        const submitBtn = form.querySelector('button[type=\"submit\"]');
        submitBtn.setAttribute('data-kt-indicator', 'on');
        submitBtn.disabled = true;
        
        const formData = new FormData(form);
        
        fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.removeAttribute('data-kt-indicator');
            submitBtn.disabled = false;
            
            if (data.success) {
                alert(data.message || 'Configurações salvas com sucesso!');
            } else {
                alert('Erro: ' + (data.message || 'Erro ao salvar configurações'));
            }
        })
        .catch(error => {
            submitBtn.removeAttribute('data-kt-indicator');
            submitBtn.disabled = false;
            alert('Erro ao salvar configurações');
        });
    }
});
</script>";

include __DIR__ . '/../layouts/metronic/app.php';
?>
