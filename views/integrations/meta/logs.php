<?php
/**
 * View: Logs da Integração Meta
 */

$logs = $logs ?? '';
?>

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <!--begin::Content container-->
    <div id="kt_app_content_container" class="app-container container-fluid">
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-file-text me-2"></i>
                    Logs - Integração Meta (Instagram + WhatsApp)
                </h3>
                <div class="card-toolbar">
                    <a href="/integrations/meta" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <input type="text" id="searchLogs" class="form-control form-control-sm" 
                           placeholder="🔍 Buscar nos logs..." onkeyup="filterLogs()">
                </div>
                
                <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px; max-height: 600px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word;">
                    <div id="logsContent"><?= htmlspecialchars($logs) ?></div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
function filterLogs() {
    const search = document.getElementById('searchLogs').value.toLowerCase();
    const logsContent = document.getElementById('logsContent');
    const originalText = logsContent.textContent;
    
    if (!search) {
        logsContent.innerHTML = originalText;
        return;
    }
    
    const lines = originalText.split('\n');
    const filtered = lines.filter(line => line.toLowerCase().includes(search));
    
    logsContent.innerHTML = filtered.join('\n');
}
</script>

<style>
#logsContent {
    line-height: 1.5;
}
</style>

