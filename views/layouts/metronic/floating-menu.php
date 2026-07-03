<?php
/**
 * Menu flutuante — renderiza a partir de menu-config.php:
 *  - Rail vertical flutuante (desktop >= 992px) com flyouts
 *  - Dock inferior flutuante (mobile < 992px)
 *  - Bottom sheet com o menu completo (mobile)
 *
 * Estilos: public/assets/css/custom/floating-menu.css
 * Comportamento: public/assets/js/custom/floating-menu.js
 */

$fmItems = require __DIR__ . '/menu-config.php';
$fmUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$fmUserId = \App\Helpers\Auth::check() ? (\App\Helpers\Auth::user()['id'] ?? 0) : 0;

if (!function_exists('fmCan')) {
    /** Verifica as permissões de um item do menu (permission = AND, permissionAny = OR). */
    function fmCan(array $item): bool
    {
        if (!empty($item['permission'])) {
            foreach ((array) $item['permission'] as $perm) {
                if (!\App\Helpers\Permission::can($perm)) {
                    return false;
                }
            }
        }
        if (!empty($item['permissionAny'])) {
            foreach ($item['permissionAny'] as $perm) {
                if (\App\Helpers\Permission::can($perm)) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }
}

if (!function_exists('fmUrl')) {
    /** URL final de uma rota do config (resolve {userId}). */
    function fmUrl(string $route, int $userId): string
    {
        return \App\Helpers\Url::to(str_replace('{userId}', (string) $userId, $route));
    }
}

if (!function_exists('fmIsActive')) {
    /** Item ativo com base na URI atual (prefixo de caminho, com exceções). */
    function fmIsActive(array $item, string $uri): bool
    {
        $route = $item['activeMatch'] ?? ($item['route'] ?? null);
        if (!$route) {
            return false;
        }
        $path = parse_url($route, PHP_URL_PATH) ?: $route;
        $full = \App\Helpers\Url::to($path);

        if (!empty($item['exact'])) {
            return rtrim($uri, '/') === rtrim($full, '/');
        }

        // Caso especial: dashboard também é a raiz do sistema
        if ($path === '/dashboard' && rtrim($uri, '/') === rtrim(\App\Helpers\Url::to('/'), '/')) {
            return empty($item['activeNot']) || !fmMatchesAny($uri, $item['activeNot']);
        }

        $matches = strpos($uri, $full) === 0;
        if (!$matches) {
            return false;
        }
        if (!empty($item['activeNot']) && fmMatchesAny($uri, $item['activeNot'])) {
            return false;
        }
        return true;
    }
}

if (!function_exists('fmMatchesAny')) {
    function fmMatchesAny(string $uri, array $routes): bool
    {
        foreach ($routes as $route) {
            if (strpos($uri, \App\Helpers\Url::to($route)) === 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('fmVisibleChildren')) {
    /**
     * Filtra filhos por permissão e remove cabeçalhos/separadores órfãos
     * (heading sem nenhum link visível até o próximo heading).
     */
    function fmVisibleChildren(array $children): array
    {
        $allowed = array_values(array_filter($children, 'fmCan'));
        $result = [];
        foreach ($allowed as $child) {
            if (isset($child['heading'])) {
                // Remove heading anterior sem links
                $last = end($result);
                if ($last !== false && isset($last['heading'])) {
                    array_pop($result);
                }
                $result[] = $child;
            } else {
                $result[] = $child;
            }
        }
        $last = end($result);
        if ($last !== false && isset($last['heading'])) {
            array_pop($result);
        }
        return $result;
    }
}

if (!function_exists('fmIcon')) {
    function fmIcon(string $icon, int $paths, string $extraClass = 'fs-2'): string
    {
        $spans = '';
        for ($i = 1; $i <= $paths; $i++) {
            $spans .= '<span class="path' . $i . '"></span>';
        }
        return '<i class="ki-duotone ' . htmlspecialchars($icon) . ' ' . $extraClass . '">' . $spans . '</i>';
    }
}

// Pré-processamento: visibilidade, filhos filtrados e estado ativo por item
$fmProcessed = [];
foreach ($fmItems as $item) {
    if (!fmCan($item)) {
        continue;
    }
    $children = !empty($item['children']) ? fmVisibleChildren($item['children']) : [];
    $hasLinks = false;
    $groupActive = false;
    foreach ($children as $child) {
        if (isset($child['heading'])) {
            continue;
        }
        $hasLinks = true;
        if (fmIsActive($child, $fmUri)) {
            $groupActive = true;
        }
    }
    if (empty($item['route']) && !$hasLinks) {
        continue; // grupo sem nenhum filho visível
    }
    $item['children'] = $children;
    $item['isActive'] = !empty($item['route']) ? fmIsActive($item, $fmUri) : $groupActive;
    $fmProcessed[] = $item;
}

$fmAppLogo = \App\Services\SettingService::get('app_logo', '');
$fmAppName = \App\Services\SettingService::get('app_name', 'Sistema Multiatendimento');
?>

<!--begin::Floating menu: rail (desktop)-->
<div id="fm_rail" class="fm-rail" role="navigation" aria-label="Menu principal">
    <a class="fm-logo" href="<?= \App\Helpers\Url::to('/dashboard') ?>" title="<?= htmlspecialchars($fmAppName) ?>">
        <?php if (!empty($fmAppLogo)): ?>
            <img src="<?= \App\Helpers\Url::to($fmAppLogo) ?>" alt="<?= htmlspecialchars($fmAppName) ?>" />
        <?php else: ?>
            <span class="fm-logo-letter"><?= htmlspecialchars(mb_strtoupper(mb_substr($fmAppName, 0, 1))) ?></span>
        <?php endif; ?>
    </a>
    <nav class="fm-rail-items">
        <?php foreach ($fmProcessed as $item): ?>
            <?php if (!empty($item['children'])): ?>
                <button type="button"
                        class="fm-rail-item <?= $item['isActive'] ? 'active' : '' ?>"
                        data-fm-flyout="fm_flyout_<?= htmlspecialchars($item['id']) ?>"
                        data-fm-tooltip="<?= htmlspecialchars($item['label']) ?>"
                        aria-haspopup="true" aria-expanded="false">
                    <?= fmIcon($item['icon'], $item['iconPaths']) ?>
                </button>
            <?php else: ?>
                <a class="fm-rail-item <?= $item['isActive'] ? 'active' : '' ?>"
                   href="<?= fmUrl($item['route'], $fmUserId) ?>"
                   data-fm-tooltip="<?= htmlspecialchars($item['label']) ?>">
                    <?= fmIcon($item['icon'], $item['iconPaths']) ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</div>
<!--end::Floating menu: rail-->

<!--begin::Floating menu: flyouts (desktop)-->
<?php foreach ($fmProcessed as $item): ?>
    <?php if (empty($item['children'])) continue; ?>
    <div id="fm_flyout_<?= htmlspecialchars($item['id']) ?>"
         class="fm-flyout <?= !empty($item['wide']) ? 'fm-flyout-wide' : '' ?>"
         role="menu" aria-label="<?= htmlspecialchars($item['label']) ?>">
        <div class="fm-flyout-title"><?= htmlspecialchars($item['label']) ?></div>
        <div class="fm-flyout-body">
            <?php foreach ($item['children'] as $child): ?>
                <?php if (isset($child['heading'])): ?>
                    <div class="fm-flyout-heading"><?= htmlspecialchars($child['heading']) ?></div>
                <?php else: ?>
                    <?php if (!empty($child['separator'])): ?>
                        <div class="fm-flyout-separator"></div>
                    <?php endif; ?>
                    <a class="fm-flyout-link <?= fmIsActive($child, $fmUri) ? 'active' : '' ?>"
                       href="<?= fmUrl($child['route'], $fmUserId) ?>" role="menuitem">
                        <span class="fm-flyout-link-label"><?= htmlspecialchars($child['label']) ?></span>
                        <?php if (!empty($child['badge']) && $child['badge'] === 'pulse-success'): ?>
                            <span class="badge badge-success badge-circle pulse pulse-success fm-badge-pulse"></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
<!--end::Floating menu: flyouts-->

<!--begin::Floating menu: dock (mobile)-->
<nav id="fm_dock" class="fm-dock" role="navigation" aria-label="Navegação rápida">
    <?php foreach ($fmProcessed as $item): ?>
        <?php if (empty($item['dock'])) continue; ?>
        <?php $dockRoute = $item['dockRoute'] ?? $item['route']; ?>
        <a class="fm-dock-item <?= $item['isActive'] ? 'active' : '' ?>" href="<?= fmUrl($dockRoute, $fmUserId) ?>">
            <?= fmIcon($item['icon'], $item['iconPaths'], 'fs-2x') ?>
            <span class="fm-dock-label"><?= htmlspecialchars($item['label']) ?></span>
        </a>
    <?php endforeach; ?>
    <button type="button" class="fm-dock-item" id="fm_dock_menu_btn" aria-haspopup="true" aria-expanded="false" aria-controls="fm_sheet">
        <?= fmIcon('ki-burger-menu', 4, 'fs-2x') ?>
        <span class="fm-dock-label">Menu</span>
    </button>
</nav>
<!--end::Floating menu: dock-->

<!--begin::Floating menu: bottom sheet (mobile)-->
<div id="fm_sheet" class="fm-sheet" hidden>
    <div class="fm-sheet-backdrop" data-fm-sheet-close></div>
    <div class="fm-sheet-panel" role="dialog" aria-modal="true" aria-label="Menu completo">
        <div class="fm-sheet-handle" data-fm-sheet-close></div>
        <div class="fm-sheet-header">
            <span class="fm-sheet-title">Menu</span>
            <button type="button" class="fm-sheet-close" data-fm-sheet-close aria-label="Fechar menu">
                <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
            </button>
        </div>
        <div class="fm-sheet-body">
            <?php
            // Seção "Principal": itens de nível superior com rota direta
            $fmDirect = array_filter($fmProcessed, fn($i) => !empty($i['route']));
            $fmGroups = array_filter($fmProcessed, fn($i) => !empty($i['children']));
            ?>
            <?php if (!empty($fmDirect)): ?>
                <div class="fm-sheet-section">
                    <div class="fm-sheet-section-title">Principal</div>
                    <div class="fm-sheet-grid">
                        <?php foreach ($fmDirect as $item): ?>
                            <a class="fm-sheet-cell <?= $item['isActive'] ? 'active' : '' ?>" href="<?= fmUrl($item['route'], $fmUserId) ?>">
                                <?= fmIcon($item['icon'], $item['iconPaths'], 'fs-2') ?>
                                <span><?= htmlspecialchars($item['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php foreach ($fmGroups as $item): ?>
                <div class="fm-sheet-section">
                    <div class="fm-sheet-section-title">
                        <?= fmIcon($item['icon'], $item['iconPaths'], 'fs-4') ?>
                        <?= htmlspecialchars($item['label']) ?>
                    </div>
                    <div class="fm-sheet-grid">
                        <?php foreach ($item['children'] as $child): ?>
                            <?php if (isset($child['heading'])): ?>
                                <div class="fm-sheet-subheading"><?= htmlspecialchars($child['heading']) ?></div>
                            <?php else: ?>
                                <a class="fm-sheet-cell <?= fmIsActive($child, $fmUri) ? 'active' : '' ?>"
                                   href="<?= fmUrl($child['route'], $fmUserId) ?>">
                                    <span><?= htmlspecialchars($child['label']) ?></span>
                                    <?php if (!empty($child['badge']) && $child['badge'] === 'pulse-success'): ?>
                                        <span class="badge badge-success badge-circle pulse pulse-success fm-badge-pulse"></span>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<!--end::Floating menu: bottom sheet-->
