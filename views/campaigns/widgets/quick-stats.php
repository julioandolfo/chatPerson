<!-- Widget: Quick Stats (para usar em outras páginas) -->
<div class="card card-flush mb-5">
    <div class="card-header">
        <h3 class="card-title">Campanhas - Resumo Rápido</h3>
        <div class="card-toolbar">
            <a href="/campaigns" class="btn btn-sm btn-light-primary">Ver Todas</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="text-center">
                    <div class="fs-2hx fw-bold text-primary" id="widget_total_campaigns">0</div>
                    <div class="text-muted fs-7">Total</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center">
                    <div class="fs-2hx fw-bold text-success" id="widget_running">0</div>
                    <div class="text-muted fs-7">Rodando</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center">
                    <div class="fs-2hx fw-bold text-info" id="widget_total_sent">0</div>
                    <div class="text-muted fs-7">Enviadas Hoje</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center">
                    <div class="fs-2hx fw-bold text-warning" id="widget_avg_reply">0%</div>
                    <div class="text-muted fs-7">Taxa Média</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Carregar stats do widget
fetch('/api/campaigns/quick-stats')
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('widget_total_campaigns').textContent = data.total || 0;
            document.getElementById('widget_running').textContent = data.running || 0;
            document.getElementById('widget_total_sent').textContent = data.sent_today || 0;
            document.getElementById('widget_avg_reply').textContent = (data.avg_reply_rate || 0).toFixed(1) + '%';
        }
    })
    .catch(err => console.error('Erro ao carregar quick stats'));
</script>
