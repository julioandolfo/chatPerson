/**
 * JavaScript Customizado - Layout
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Sistema Multiatendimento carregado');
    
    // Garantir que Bootstrap está disponível antes de usar modais
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap não está disponível! Verifique se plugins.bundle.js está carregado.');
        return;
    }
    
    // Inicializar modais de forma segura
    function initModals() {
        // Aguardar um pouco para garantir que Bootstrap está totalmente carregado
        setTimeout(function() {
            var modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');
            modalTriggers.forEach(function(trigger) {
                trigger.addEventListener('click', function(e) {
                    var targetId = this.getAttribute('data-bs-target');
                    if (targetId) {
                        var modalElement = document.querySelector(targetId);
                        if (modalElement) {
                            // Verificar se já existe uma instância
                            var existingModal = bootstrap.Modal.getInstance(modalElement);
                            if (!existingModal) {
                                try {
                                    // Criar nova instância com configurações padrão
                                    var modal = new bootstrap.Modal(modalElement, {
                                        backdrop: true,
                                        keyboard: true,
                                        focus: true
                                    });
                                } catch (error) {
                                    console.error('Erro ao criar modal:', error);
                                }
                            }
                        }
                    }
                });
            });
        }, 200);
    }
    
    // Inicializar modais quando a página estiver pronta
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModals);
    } else {
        initModals();
    }
});

