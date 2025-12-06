<?php
/**
 * Partial: Tree Node para visualização hierárquica de setores
 */
$hasChildren = !empty($root['children']) && count($root['children']) > 0;
$nodeId = 'dept_node_' . $root['id'];
$level = $level ?? 0; // Nível de profundidade na árvore
?>
<div class="tree-node mb-2" data-id="<?= $root['id'] ?>" data-level="<?= $level ?>">
    <div class="d-flex align-items-center p-3 bg-light rounded border border-gray-300 hover-elevate-up transition-all" 
         style="margin-left: <?= $level * 30 ?>px; border-left: 3px solid <?= $level === 0 ? '#009ef7' : ($level === 1 ? '#50cd89' : '#7239ea') ?>;">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center">
                <?php if ($hasChildren): ?>
                    <button class="btn btn-sm btn-icon btn-light-primary me-3 tree-toggle" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#<?= $nodeId ?>_children" 
                            aria-expanded="false"
                            style="min-width: 32px;">
                        <i class="ki-duotone ki-down fs-6 tree-icon">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                <?php else: ?>
                    <span class="btn btn-sm btn-icon btn-light me-3" style="min-width: 32px; visibility: hidden;"></span>
                <?php endif; ?>
                
                <div class="symbol symbol-45px me-3">
                    <div class="symbol-label fs-3 fw-bold text-white" 
                         style="background: linear-gradient(135deg, <?= $level === 0 ? '#009ef7' : ($level === 1 ? '#50cd89' : '#7239ea') ?> 0%, <?= $level === 0 ? '#0054d1' : ($level === 1 ? '#2e7d32' : '#5a2d91') ?> 100%);">
                        <?= mb_substr(htmlspecialchars($root['name']), 0, 1) ?>
                    </div>
                </div>
                
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <a href="<?= \App\Helpers\Url::to('/departments/' . $root['id']) ?>" 
                           class="text-gray-800 fw-bold text-hover-primary fs-5">
                            <?= htmlspecialchars($root['name']) ?>
                        </a>
                        <?php if ($root['agents_count'] > 0): ?>
                            <span class="badge badge-light-primary d-flex align-items-center">
                                <i class="ki-duotone ki-people fs-7 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <?= $root['agents_count'] ?> agente(s)
                            </span>
                        <?php endif; ?>
                        <?php if ($root['children_count'] > 0): ?>
                            <span class="badge badge-light-success d-flex align-items-center">
                                <i class="ki-duotone ki-abstract-26 fs-7 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <?= $root['children_count'] ?> filho(s)
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($root['description'])): ?>
                        <div class="text-muted fs-7 mt-1"><?= htmlspecialchars(mb_substr($root['description'], 0, 100)) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <?php if (\App\Helpers\Permission::can('departments.edit')): ?>
            <button type="button" 
                    class="btn btn-sm btn-icon btn-light-warning" 
                    onclick="editDepartment(<?= $root['id'] ?>)"
                    data-bs-toggle="tooltip" 
                    title="Editar setor">
                <i class="ki-duotone ki-notepad-edit fs-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </button>
            <?php endif; ?>
            <?php if (\App\Helpers\Permission::can('departments.delete')): ?>
            <button type="button" 
                    class="btn btn-sm btn-icon btn-light-danger" 
                    onclick="deleteDepartment(<?= $root['id'] ?>, '<?= htmlspecialchars($root['name'], ENT_QUOTES) ?>')"
                    data-bs-toggle="tooltip" 
                    title="Deletar setor">
                <i class="ki-duotone ki-trash fs-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                    <span class="path5"></span>
                </i>
            </button>
            <?php endif; ?>
            <a href="<?= \App\Helpers\Url::to('/departments/' . $root['id']) ?>" 
               class="btn btn-sm btn-light-primary"
               data-bs-toggle="tooltip" 
               title="Ver detalhes">
                <i class="ki-duotone ki-eye fs-5 me-1">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Ver
            </a>
        </div>
    </div>
    
    <?php if ($hasChildren): ?>
        <div class="collapse ms-10 mt-2" id="<?= $nodeId ?>_children">
            <?php foreach ($root['children'] as $child): ?>
                <?php
                $root = $child; // Reutilizar variável para recursão
                $level = ($level ?? 0) + 1; // Incrementar nível
                include __DIR__ . '/tree-node.php';
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.tree-node .hover-elevate-up {
    transition: all 0.3s ease;
}

.tree-node .hover-elevate-up:hover {
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.tree-icon {
    transition: transform 0.3s ease;
}

.tree-toggle[aria-expanded="true"] .tree-icon {
    transform: rotate(180deg);
}
</style>

