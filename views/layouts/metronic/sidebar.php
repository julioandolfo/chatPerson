<!--begin::Aside-->
<div id="kt_aside" class="aside py-9" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_toggle">
    <!--begin::Brand-->
    <div class="aside-logo flex-column-auto px-9 mb-9" id="kt_aside_logo">
        <div class="d-flex align-items-center justify-content-between w-100">
            <a href="<?= \App\Helpers\Url::to('/dashboard') ?>" class="d-flex align-items-center flex-grow-1">
                <?php
                $appLogo = \App\Services\SettingService::get('app_logo', '');
                $appName = \App\Services\SettingService::get('app_name', 'Sistema Multiatendimento');
                ?>
                <?php if (!empty($appLogo)): ?>
                    <!-- Logo customizada -->
                    <img alt="<?= htmlspecialchars($appName) ?>" 
                         src="<?= \App\Helpers\Url::to($appLogo) ?>" 
                         class="h-30px logo" 
                         style="max-height: 30px; object-fit: contain;" />
                <?php else: ?>
                    <!-- Logo padrão em texto -->
                    <span class="text-gray-900 fw-bold fs-3 me-2 logo-text"><?= htmlspecialchars($appName) ?></span>
                <?php endif; ?>
            </a>
            <!--begin::Sidebar Toggle Button-->
            <button class="btn btn-icon btn-sm btn-active-color-primary btn-color-gray-700 ms-2" id="kt_aside_toggle_sidebar" title="Recolher/Expandir Menu">
                <i class="ki-duotone ki-left fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </button>
            <!--end::Sidebar Toggle Button-->
        </div>
    </div>
    <!--end::Brand-->
    
    <!--begin::Aside menu-->
    <div class="aside-menu flex-column-fluid ps-5 pe-3 mb-9" id="kt_aside_menu">
        <div class="w-100 hover-scroll-overlay-y d-flex pe-3" id="kt_aside_menu_wrapper">
            <!--begin::Menu-->
            <div class="menu menu-column menu-rounded menu-sub-indention menu-active-bg fw-semibold my-auto" id="#kt_aside_menu" data-kt-menu="true">
                <?php 
                $currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                $basePath = \App\Helpers\Url::basePath();
                
                // Função helper para verificar se rota está ativa
                if (!function_exists('isActive')) {
                    function isActive($path, $currentUri) {
                        $fullPath = \App\Helpers\Url::to($path);
                        return strpos($currentUri, $fullPath) !== false || ($path === '/dashboard' && ($currentUri === $fullPath || $currentUri === \App\Helpers\Url::to('/')));
                    }
                }
                ?>
                
                <!--begin:Menu item-->
                <div class="menu-item">
                    <a class="menu-link <?= isActive('/dashboard', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/dashboard') ?>" data-title="Dashboard">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-home fs-2 text-gray-600"></i>
                        </span>
                        <span class="menu-title">Dashboard</span>
                    </a>
                </div>
                <!--end:Menu item-->
                
                <!--begin:Menu item-->
                <div class="menu-item">
                    <a class="menu-link <?= isActive('/conversations', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/conversations') ?>" data-title="Conversas">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-chat fs-2"></i>
                        </span>
                        <span class="menu-title">Conversas</span>
                    </a>
                </div>
                <!--end:Menu item-->
                
                <!--begin:Menu item-->
                <div class="menu-item">
                    <a class="menu-link <?= isActive('/contacts', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/contacts') ?>" data-title="Contatos">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-profile-user fs-2"></i>
                        </span>
                        <span class="menu-title">Contatos</span>
                    </a>
                </div>
                <!--end:Menu item-->
                
                <!--begin:Menu item-->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                    <span class="menu-link <?= isActive('/funnels', $currentUri) ? 'active' : '' ?>" data-title="Funis">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-category fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </span>
                        <span class="menu-title">Funis</span>
                        <span class="menu-arrow"></span>
                    </span>
                    <!--begin:Menu sub-->
                    <div class="menu-sub menu-sub-accordion">
                        <div class="menu-item">
                            <a class="menu-link <?= isActive('/funnels', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/funnels') ?>">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Todos os Funis</span>
                            </a>
                        </div>
                        <div class="menu-item">
                            <a class="menu-link" href="<?= \App\Helpers\Url::to('/funnels/kanban') ?>">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Kanban</span>
                            </a>
                        </div>
                    </div>
                    <!--end:Menu sub-->
                </div>
                <!--end:Menu item-->
                
                <!--begin:Menu item-->
                <div class="menu-item">
                    <a class="menu-link <?= isActive('/automations', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/automations') ?>" data-title="Automações">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-gear fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <span class="menu-title">Automações</span>
                    </a>
                </div>
                <!--end:Menu item-->
                
                <!--begin:Menu item-->
                <?php if (\App\Helpers\Permission::can('tags.view')): ?>
                <div class="menu-item">
                    <a class="menu-link <?= isActive('/tags', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/tags') ?>" data-title="Tags">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-tag fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <span class="menu-title">Tags</span>
                    </a>
                </div>
                <!--end:Menu item-->
                <?php endif; ?>
                
                <!--begin:Menu item-->
                <?php if (\App\Helpers\Permission::can('message_templates.view')): ?>
                <div class="menu-item">
                    <a class="menu-link <?= isActive('/message-templates', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/message-templates') ?>" data-title="Templates">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-document fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <span class="menu-title">Templates</span>
                    </a>
                </div>
                <!--end:Menu item-->
                <?php endif; ?>
                
                <!--begin:Menu item-->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                    <span class="menu-link <?= isActive('/agents', $currentUri) || isActive('/users', $currentUri) ? 'active' : '' ?>" data-title="Agentes">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-people fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <span class="menu-title">Agentes</span>
                        <span class="menu-arrow"></span>
                    </span>
                    <!--begin:Menu sub-->
                    <div class="menu-sub menu-sub-accordion">
                        <div class="menu-item">
                            <a class="menu-link <?= isActive('/agents', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/agents') ?>">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Lista de Agentes</span>
                            </a>
                        </div>
                        <div class="menu-item">
                            <a class="menu-link <?= isActive('/users', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/users') ?>">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Usuários</span>
                            </a>
                        </div>
                    </div>
                    <!--end:Menu sub-->
                </div>
                <!--end:Menu item-->
                
                <?php if (\App\Helpers\Permission::can('ai_agents.view')): ?>
                <!--begin:Menu item-->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                    <span class="menu-link <?= isActive('/ai-agents', $currentUri) || isActive('/ai-tools', $currentUri) ? 'active' : '' ?>" data-title="Agentes de IA">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-robot fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </span>
                        <span class="menu-title">Agentes de IA</span>
                        <span class="menu-arrow"></span>
                    </span>
                    <!--begin:Menu sub-->
                    <div class="menu-sub menu-sub-accordion">
                        <div class="menu-item">
                            <a class="menu-link <?= isActive('/ai-agents', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/ai-agents') ?>">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Agentes</span>
                            </a>
                        </div>
                        <?php if (\App\Helpers\Permission::can('ai_tools.view')): ?>
                        <div class="menu-item">
                            <a class="menu-link <?= isActive('/ai-tools', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/ai-tools') ?>">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Tools</span>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!--end:Menu sub-->
                </div>
                <!--end:Menu item-->
                <?php endif; ?>
                
                <!--begin:Menu item-->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                    <span class="menu-link <?= isActive('/integrations', $currentUri) || isActive('/whatsapp', $currentUri) ? 'active' : '' ?>" data-title="Integrações">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-sms fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <span class="menu-title">Integrações</span>
                        <span class="menu-arrow"></span>
                    </span>
                    <!--begin:Menu sub-->
                    <div class="menu-sub menu-sub-accordion">
                        <div class="menu-item">
                            <a class="menu-link <?= isActive('/integrations/whatsapp', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/integrations/whatsapp') ?>">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">WhatsApp</span>
                            </a>
                        </div>
                        <div class="menu-item">
                            <a class="menu-link" href="<?= \App\Helpers\Url::to('/integrations') ?>">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Todas as Integrações</span>
                            </a>
                        </div>
                    </div>
                    <!--end:Menu sub-->
                </div>
                <!--end:Menu item-->
                
                      <!--begin:Menu item-->
                      <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= isActive('/roles', $currentUri) || isActive('/departments', $currentUri) ? 'show' : '' ?>">
                          <span class="menu-link" data-title="Permissões">
                              <span class="menu-icon">
                                  <i class="ki-duotone ki-shield fs-2">
                                      <span class="path1"></span>
                                      <span class="path2"></span>
                                  </i>
                              </span>
                              <span class="menu-title">Permissões</span>
                              <span class="menu-arrow"></span>
                          </span>
                          <!--begin:Menu sub-->
                          <div class="menu-sub menu-sub-accordion">
                              <div class="menu-item">
                                  <a class="menu-link <?= isActive('/roles', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/roles') ?>">
                                      <span class="menu-bullet">
                                          <span class="bullet bullet-dot"></span>
                                      </span>
                                      <span class="menu-title">Roles</span>
                                  </a>
                              </div>
                              <div class="menu-item">
                                  <a class="menu-link <?= isActive('/departments', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/departments') ?>">
                                      <span class="menu-bullet">
                                          <span class="bullet bullet-dot"></span>
                                      </span>
                                      <span class="menu-title">Setores</span>
                                  </a>
                              </div>
                              <?php if (\App\Helpers\Permission::can('admin.logs')): ?>
                              <div class="menu-item">
                                  <a class="menu-link <?= isActive('/logs', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/logs') ?>">
                                      <span class="menu-bullet">
                                          <span class="bullet bullet-dot"></span>
                                      </span>
                                      <span class="menu-title">Logs do Sistema</span>
                                  </a>
                              </div>
                              <?php endif; ?>
                          </div>
                          <!--end:Menu sub-->
                      </div>
                      <!--end:Menu item-->
                      
                      <!--begin:Menu item-->
                      <div class="menu-item">
                          <a class="menu-link <?= isActive('/settings', $currentUri) ? 'active' : '' ?>" href="<?= \App\Helpers\Url::to('/settings') ?>" data-title="Configurações">
                              <span class="menu-icon">
                                  <i class="ki-duotone ki-setting-2 fs-2">
                                      <span class="path1"></span>
                                      <span class="path2"></span>
                                  </i>
                              </span>
                              <span class="menu-title">Configurações</span>
                          </a>
                      </div>
                      <!--end:Menu item-->
            </div>
            <!--end::Menu-->
        </div>
    </div>
    <!--end::Aside menu-->
</div>
<!--end::Aside-->

