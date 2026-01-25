<?php
/**
 * Aba de configurações de Prospecção de Leads
 * APIs para buscar leads externos (Google Maps, Outscraper, etc)
 */

$prospectingSettings = $prospectingSettings ?? [];
?>

<form id="kt_settings_prospecting_form" class="form">
    
    <div class="alert alert-info d-flex align-items-center mb-10">
        <i class="ki-duotone ki-information-5 fs-2 me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
        <div>
            <strong>Prospecção de Leads:</strong> Configure as APIs para buscar empresas automaticamente do Google Maps e outras fontes.
        </div>
    </div>
    
    <h4 class="fw-bold mb-4">Google Places API (Oficial)</h4>
    <div class="fv-row mb-7">
        <label class="fw-semibold fs-6 mb-2">API Key</label>
        <input type="password" name="google_places_api_key" class="form-control form-control-solid" 
               value="<?= htmlspecialchars($prospectingSettings['google_places_api_key'] ?? '') ?>" 
               placeholder="AIza..." autocomplete="off" />
        <div class="form-text">
            Chave da API do Google Places para buscar empresas no Google Maps.<br>
            <strong>Como obter:</strong>
            <ol class="mb-0 mt-2">
                <li>Acesse <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a></li>
                <li>Crie um projeto ou selecione um existente</li>
                <li>Ative as APIs: <strong>Places API</strong> e <strong>Geocoding API</strong></li>
                <li>Crie uma credencial de API Key</li>
            </ol>
            <span class="text-muted">Custo: ~$0.032/busca + $0.017/detalhe</span>
        </div>
    </div>
    
    <div class="separator separator-dashed my-8"></div>
    
    <h4 class="fw-bold mb-4">Outscraper API (Alternativa)</h4>
    <div class="fv-row mb-7">
        <label class="fw-semibold fs-6 mb-2">API Key</label>
        <input type="password" name="outscraper_api_key" class="form-control form-control-solid" 
               value="<?= htmlspecialchars($prospectingSettings['outscraper_api_key'] ?? '') ?>" 
               placeholder="..." autocomplete="off" />
        <div class="form-text">
            Alternativa mais barata para buscar em volume.<br>
            <strong>Como obter:</strong> Registre-se em <a href="https://outscraper.com" target="_blank">outscraper.com</a> e copie sua API Key.<br>
            <span class="text-muted">Custo: ~$0.002/lead (muito mais barato para volume)</span>
        </div>
    </div>
    
    <div class="separator separator-dashed my-8"></div>
    
    <h4 class="fw-bold mb-4">Testar Conexões</h4>
    <div class="d-flex gap-3 mb-7">
        <button type="button" class="btn btn-light-primary" onclick="testGooglePlacesApi()">
            <i class="ki-duotone ki-flash fs-3"></i>
            Testar Google Places
        </button>
        <button type="button" class="btn btn-light-info" onclick="testOutscraperApi()">
            <i class="ki-duotone ki-flash fs-3"></i>
            Testar Outscraper
        </button>
        <span id="api_test_status" class="align-self-center"></span>
    </div>
    
    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            <span class="indicator-label">Salvar Configurações</span>
            <span class="indicator-progress">Aguarde...
            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('kt_settings_prospecting_form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.setAttribute('data-kt-indicator', 'on');
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            
            fetch('<?= \App\Helpers\Url::to('/settings/prospecting') ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                submitBtn.removeAttribute('data-kt-indicator');
                submitBtn.disabled = false;
                
                if (data.success) {
                    toastr.success(data.message || 'Configurações salvas!');
                } else {
                    toastr.error(data.message || 'Erro ao salvar');
                }
            })
            .catch(err => {
                submitBtn.removeAttribute('data-kt-indicator');
                submitBtn.disabled = false;
                toastr.error('Erro de rede');
            });
        });
    }
});

function testGooglePlacesApi() {
    const status = document.getElementById('api_test_status');
    status.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testando...';
    
    // Pegar a key do campo (pode ser a nova, ainda não salva)
    const apiKey = document.querySelector('[name="google_places_api_key"]').value;
    
    fetch('<?= \App\Helpers\Url::to('/api/external-sources/test-google-maps') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ provider: 'google_places', api_key: apiKey })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            status.innerHTML = '<span class="badge badge-light-success">Google Places: OK</span>';
            toastr.success(data.message);
        } else {
            status.innerHTML = '<span class="badge badge-light-danger">Google Places: Erro</span>';
            toastr.error(data.message);
        }
    })
    .catch(err => {
        status.innerHTML = '<span class="badge badge-light-danger">Erro</span>';
        toastr.error('Erro de rede');
    });
}

function testOutscraperApi() {
    const status = document.getElementById('api_test_status');
    status.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testando...';
    
    fetch('<?= \App\Helpers\Url::to('/api/external-sources/test-google-maps') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ provider: 'outscraper' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            status.innerHTML = '<span class="badge badge-light-success">Outscraper: OK</span>';
            toastr.success(data.message);
        } else {
            status.innerHTML = '<span class="badge badge-light-danger">Outscraper: Erro</span>';
            toastr.error(data.message);
        }
    })
    .catch(err => {
        status.innerHTML = '<span class="badge badge-light-danger">Erro</span>';
        toastr.error('Erro de rede');
    });
}
</script>
