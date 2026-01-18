<?php
$layout = 'layouts.metronic.app';
$title = 'Editor de A/B Testing';
$pageTitle = 'A/B Testing';
?>

<?php ob_start(); ?>
<div class="app-toolbar py-3 py-lg-6">
    <div class="app-container container-fluid d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                Editor de A/B Testing
            </h1>
        </div>
    </div>
</div>
<div class="app-container container-fluid">
            
            <div class="card">
                <div class="card-body">
                    
                    <div class="alert alert-info d-flex align-items-center mb-10">
                        <i class="ki-duotone ki-information-5 fs-2 me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <div>
                            <strong>A/B Testing:</strong> Teste diferentes versões de mensagens para descobrir qual tem melhor taxa de resposta.
                            O sistema dividirá automaticamente seus contatos entre as variantes.
                        </div>
                    </div>
                    
                    <form id="ab_test_form">
                        <div class="mb-10">
                            <label class="form-label required">Nome do Teste</label>
                            <input type="text" class="form-control form-control-lg" name="name" placeholder="Ex: Teste de Abertura - Black Friday" required />
                        </div>
                        
                        <div class="mb-10">
                            <label class="form-label required">Selecione a Lista</label>
                            <select class="form-select form-select-lg" name="contact_list_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($lists as $list): ?>
                                <option value="<?php echo $list['id']; ?>">
                                    <?php echo htmlspecialchars($list['name']); ?> (<?php echo $list['total_contacts']; ?> contatos)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-10">
                            <label class="form-label required">Contas WhatsApp</label>
                            <?php foreach ($whatsappAccounts as $account): ?>
                            <div class="form-check form-check-custom form-check-solid mb-3">
                                <input class="form-check-input" type="checkbox" name="integration_account_ids[]" value="<?php echo $account['id']; ?>" id="acc_<?php echo $account['id']; ?>">
                                <label class="form-check-label" for="acc_<?php echo $account['id']; ?>">
                                    <div class="fw-bold"><?php echo htmlspecialchars($account['name']); ?></div>
                                    <div class="text-muted fs-7"><?php echo htmlspecialchars($account['phone_number']); ?></div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="separator my-10"></div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-5">
                            <h3>Variantes do Teste</h3>
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="addVariant()">
                                <i class="ki-duotone ki-plus fs-3"></i>
                                Adicionar Variante
                            </button>
                        </div>
                        
                        <div id="variants_container"></div>
                        
                        <div class="separator my-10"></div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-light me-3" onclick="window.history.back()">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btn_save" onclick="saveABTest()">
                                <span class="indicator-label">Criar Teste A/B</span>
                                <span class="indicator-progress">Criando...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                            </button>
                        </div>
                    </form>
                    
                </div>
            </div>
            
    </div>
</div>

<script>
let variantCounter = 0;
const variantLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

function addVariant() {
    if (variantCounter >= 10) {
        toastr.error('Máximo de 10 variantes');
        return;
    }
    
    const letter = variantLetters[variantCounter];
    const defaultPercentage = Math.floor(100 / (variantCounter + 1));
    
    const html = `
        <div class="card mb-5 variant-card" data-variant="${letter}">
            <div class="card-header" style="background: linear-gradient(135deg, ${getVariantColor(variantCounter)} 0%, ${getVariantColor(variantCounter)}dd 100%);">
                <h3 class="card-title text-white">
                    <span class="badge badge-circle badge-white me-2">${letter}</span>
                    Variante ${letter}
                </h3>
                <div class="card-toolbar">
                    ${variantCounter > 1 ? `<button type="button" class="btn btn-sm btn-light-danger" onclick="removeVariant('${letter}')">
                        <i class="ki-duotone ki-trash fs-6"></i>
                    </button>` : ''}
                </div>
            </div>
            <div class="card-body">
                <div class="mb-5">
                    <label class="form-label">Porcentagem</label>
                    <div class="input-group">
                        <input type="number" class="form-control variant-percentage" data-variant="${letter}" value="${defaultPercentage}" min="1" max="100" onchange="recalculatePercentages()" />
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Quantos % dos contatos receberão esta variante</div>
                </div>
                
                <div class="mb-5">
                    <label class="form-label required">Mensagem da Variante ${letter}</label>
                    <textarea class="form-control variant-message" data-variant="${letter}" rows="6" placeholder="Olá {{nome}}! Mensagem da variante ${letter}..." required></textarea>
                    <div class="form-text">Variáveis: {{nome}}, {{telefone}}, {{email}}</div>
                </div>
                
                <div class="p-4 bg-light rounded">
                    <strong>Preview:</strong>
                    <div class="preview-box mt-3 p-3 bg-white rounded border" data-variant="${letter}">
                        Digite a mensagem para ver o preview...
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('variants_container').insertAdjacentHTML('beforeend', html);
    variantCounter++;
    
    // Recalcular porcentagens
    recalculatePercentages();
    
    // Adicionar listener para preview
    document.querySelectorAll('.variant-message').forEach(textarea => {
        textarea.addEventListener('input', function() {
            updatePreview(this.dataset.variant, this.value);
        });
    });
}

function removeVariant(letter) {
    if (!confirm(`Deseja remover a variante ${letter}?`)) return;
    document.querySelector(`[data-variant="${letter}"]`).remove();
    variantCounter--;
    recalculatePercentages();
}

function recalculatePercentages() {
    const percentageInputs = document.querySelectorAll('.variant-percentage');
    const total = Array.from(percentageInputs).reduce((sum, input) => sum + parseInt(input.value || 0), 0);
    
    document.querySelectorAll('.variant-card').forEach(card => {
        const input = card.querySelector('.variant-percentage');
        const percentage = parseInt(input.value || 0);
        const progressBar = card.querySelector('.card-header');
        
        if (total > 100) {
            progressBar.style.borderBottom = '3px solid #F1416C';
        } else {
            progressBar.style.borderBottom = 'none';
        }
    });
    
    if (total !== 100 && percentageInputs.length > 0) {
        document.getElementById('btn_save').disabled = true;
        toastr.warning('A soma das porcentagens deve ser 100%');
    } else {
        document.getElementById('btn_save').disabled = false;
    }
}

function updatePreview(variant, message) {
    const preview = document.querySelector(`.preview-box[data-variant="${variant}"]`);
    if (preview) {
        preview.innerHTML = message.replace(/\n/g, '<br>') || 'Digite a mensagem para ver o preview...';
    }
}

function getVariantColor(index) {
    const colors = ['#009EF7', '#50CD89', '#FFC700', '#7239EA', '#F1416C', '#00A3FF', '#28C76F', '#EA5455', '#FF9F43', '#1E1E2D'];
    return colors[index % colors.length];
}

function saveABTest() {
    const btn = document.getElementById('btn_save');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const formData = new FormData(document.getElementById('ab_test_form'));
    const data = {};
    
    formData.forEach((value, key) => {
        if (key.includes('[]')) {
            const arrayKey = key.replace('[]', '');
            if (!data[arrayKey]) data[arrayKey] = [];
            data[arrayKey].push(value);
        } else {
            data[key] = value;
        }
    });
    
    // Coletar variantes
    const variants = [];
    document.querySelectorAll('.variant-card').forEach(card => {
        const variant = card.dataset.variant;
        variants.push({
            variant_name: variant,
            message_content: card.querySelector('.variant-message').value,
            percentage: parseInt(card.querySelector('.variant-percentage').value)
        });
    });
    
    if (variants.length < 2) {
        toastr.error('Adicione pelo menos 2 variantes');
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        return;
    }
    
    data.is_ab_test = true;
    data.variants = variants;
    data.channel = 'whatsapp';
    data.target_type = 'list';
    
    fetch('/campaigns/ab-test', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (result.success) {
            toastr.success('Teste A/B criado com sucesso!');
            setTimeout(() => {
                window.location.href = `/campaigns/${result.campaign_id}`;
            }, 1000);
        } else {
            toastr.error(result.message || 'Erro ao criar teste');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede');
    });
}

// Inicializar com 2 variantes
document.addEventListener('DOMContentLoaded', () => {
    addVariant(); // A
    addVariant(); // B
});
</script>

<?php 
$content = ob_get_clean(); 
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

<style>
.variant-card {
    transition: all 0.3s ease;
}
.variant-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.preview-box {
    min-height: 100px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
</style>
