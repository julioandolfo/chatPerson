<?php
/**
 * View: Biblioteca de Melhores Práticas
 */
$title = 'Melhores Práticas';
ob_start();
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                    📚 Biblioteca de Melhores Práticas
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">Aprenda com os melhores</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <!-- Filtro de categorias -->
            <div class="card mb-5">
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?= \App\Helpers\Url::to('/agent-performance/best-practices?category=all') ?>" 
                           class="btn btn-sm <?= $selectedCategory === 'all' ? 'btn-primary' : 'btn-light-primary' ?>">
                            📚 Todas
                        </a>
                        <?php foreach ($categories as $key => $cat): ?>
                        <a href="<?= \App\Helpers\Url::to('/agent-performance/best-practices?category=' . $key) ?>" 
                           class="btn btn-sm <?= $selectedCategory === $key ? 'btn-primary' : 'btn-light-primary' ?>">
                            <?= $cat['icon'] ?> <?= $cat['name'] ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Grid de práticas -->
            <div class="row g-5">
                <?php foreach ($practices as $practice): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card card-flush h-100">
                        <div class="card-header">
                            <h3 class="card-title">
                                <span class="fw-bold text-dark"><?= htmlspecialchars($practice['title']) ?></span>
                            </h3>
                            <div class="card-toolbar">
                                <?php
                                $score = $practice['score'];
                                $stars = str_repeat('⭐', min(5, round($score)));
                                ?>
                                <span class="badge badge-light-success"><?= $stars ?></span>
                            </div>
                        </div>
                        
                        <div class="card-body pt-4">
                            <div class="mb-4">
                                <span class="text-gray-600 fs-7">Por:</span>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($practice['agent_name']) ?></span>
                            </div>
                            
                            <p class="text-gray-700 fs-6 mb-4">
                                <?= htmlspecialchars(mb_substr($practice['description'], 0, 150)) ?>...
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-gray-600 fs-7">
                                    <span class="me-2">👁️ <?= $practice['views'] ?></span>
                                    <span>👍 <?= $practice['helpful_votes'] ?></span>
                                </div>
                                <a href="<?= \App\Helpers\Url::to('/agent-performance/practice?id=' . $practice['id']) ?>" class="btn btn-sm btn-primary">
                                    Ver Exemplo
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($practices)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-20">
                            <span class="svg-icon svg-icon-5x text-gray-500 mb-5">📚</span>
                            <h3 class="text-gray-600">Nenhuma prática encontrada</h3>
                            <p class="text-gray-500">Práticas são criadas automaticamente quando conversas recebem nota >= 4.5</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
