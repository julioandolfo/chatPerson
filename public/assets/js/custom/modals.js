/**
 * Inicialização segura de Modais Bootstrap
 * Previne erro de "backdrop undefined"
 */

(function() {
    'use strict';
    
    // Desabilitar inicialização automática do Bootstrap para modais
    // Isso previne o erro de backdrop undefined
    var originalGetOrCreateInstance = null;
    
    // Função para inicializar um modal de forma segura
    function initModal(modalElement) {
        if (!modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return null;
        }
        
        try {
            // Verificar se já existe uma instância
            var existingModal = bootstrap.Modal.getInstance(modalElement);
            if (existingModal) {
                return existingModal;
            }
            
            // Criar nova instância com configurações padrão
            var modal = new bootstrap.Modal(modalElement, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
            
            return modal;
        } catch (error) {
            console.error('Erro ao criar modal:', error, modalElement);
            return null;
        }
    }
    
    // Função para inicializar todos os modais da página
    function initAllModals() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }
        
        var modalElements = document.querySelectorAll('.modal');
        modalElements.forEach(function(modalElement) {
            initModal(modalElement);
        });
    }
    
    // Função para lidar com cliques em botões de modal
    function handleModalTrigger(trigger, e) {
        var targetId = trigger.getAttribute('data-bs-target') || trigger.getAttribute('data-modal-target');
        if (!targetId) {
            return false;
        }
        
        var modalElement = document.querySelector(targetId);
        if (!modalElement) {
            console.warn('Modal não encontrado:', targetId);
            return false;
        }
        
        var modal = initModal(modalElement);
        if (modal) {
            modal.show();
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            return true;
        }
        
        return false;
    }
    
    // Interceptar inicialização automática do Bootstrap
    function interceptBootstrapModals() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }
        
        // Sobrescrever getOrCreateInstance para adicionar tratamento de erro
        if (bootstrap.Modal.getOrCreateInstance && !originalGetOrCreateInstance) {
            originalGetOrCreateInstance = bootstrap.Modal.getOrCreateInstance;
            bootstrap.Modal.getOrCreateInstance = function(element, config) {
                try {
                    return originalGetOrCreateInstance.call(this, element, config || {
                        backdrop: true,
                        keyboard: true,
                        focus: true
                    });
                } catch (error) {
                    console.error('Erro no getOrCreateInstance:', error);
                    // Tentar criar diretamente
                    try {
                        return new bootstrap.Modal(element, config || {
                            backdrop: true,
                            keyboard: true,
                            focus: true
                        });
                    } catch (err) {
                        console.error('Erro ao criar modal diretamente:', err);
                        return null;
                    }
                }
            };
        }
    }
    
    // Interceptar cliques ANTES do Bootstrap processá-los
    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('[data-bs-toggle="modal"]');
        if (trigger) {
            var targetId = trigger.getAttribute('data-bs-target');
            if (targetId) {
                var modalElement = document.querySelector(targetId);
                if (!modalElement) {
                    // Modal não existe, prevenir erro
                    e.preventDefault();
                    e.stopPropagation();
                    console.warn('Modal não encontrado:', targetId);
                    return false;
                }
                
                // Garantir que o modal está inicializado antes do Bootstrap tentar
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    var existingModal = bootstrap.Modal.getInstance(modalElement);
                    if (!existingModal) {
                        try {
                            var modal = new bootstrap.Modal(modalElement, {
                                backdrop: true,
                                keyboard: true,
                                focus: true
                            });
                        } catch (err) {
                            console.error('Erro ao criar modal:', err);
                            e.preventDefault();
                            e.stopPropagation();
                            return false;
                        }
                    }
                }
            }
        }
    }, true); // Usar capture phase para interceptar antes
    
    // Inicializar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Aguardar Bootstrap carregar
            var checkBootstrap = setInterval(function() {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    clearInterval(checkBootstrap);
                    interceptBootstrapModals();
                    initAllModals();
                }
            }, 50);
            
            // Timeout de segurança
            setTimeout(function() {
                clearInterval(checkBootstrap);
            }, 5000);
        });
    } else {
        // DOM já está pronto
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            interceptBootstrapModals();
            initAllModals();
        }
    }
    
    // Exportar função globalmente para uso manual
    window.initModalSafely = initModal;
    window.initAllModalsSafely = initAllModals;
    window.handleModalTrigger = handleModalTrigger;
})();

