<?php    });
}

// Atualizar métricas ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    updateAgentMetrics();
    
    // Atualizar a cada 30 segundos
    setInterval(updateAgentMetrics, 30000);
});
</script>

<!-- SLA Indicator JavaScript -->
<script src="<?= \App\Helpers\Url::asset('js/custom/sla-indicator.js') ?>"></script>

<?php $content = ob_get_clean(); ?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

