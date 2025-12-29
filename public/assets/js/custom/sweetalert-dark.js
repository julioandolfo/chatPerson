/**
 * SweetAlert2 Dark Mode Auto-Configuration
 * Aplica automaticamente o tema dark em todos os SweetAlerts quando o sistema estiver em modo dark
 */

(function() {
    'use strict';
    
    // Aguardar o SweetAlert2 estar disponível
    if (typeof Swal === 'undefined') {
        console.warn('SweetAlert2 não está disponível. O tema dark não será aplicado automaticamente.');
        return;
    }
    
    /**
     * Verifica se o sistema está em modo dark
     */
    function isDarkMode() {
        return document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
               document.body.classList.contains('dark-mode') ||
               window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    
    /**
     * Aplica o tema dark ao popup do SweetAlert
     */
    function applyDarkTheme(popup) {
        if (!popup) return;
        
        // Adicionar classe dark ao popup
        popup.classList.add('swal2-dark');
        
        // Garantir que todos os textos sejam claros
        const title = popup.querySelector('.swal2-title');
        if (title) {
            title.style.color = '#ffffff';
        }
        
        const htmlContainer = popup.querySelector('.swal2-html-container');
        if (htmlContainer) {
            htmlContainer.style.color = '#e0e0e0';
        }
        
        const content = popup.querySelector('.swal2-content');
        if (content) {
            content.style.color = '#e0e0e0';
        }
        
        // Aplicar estilos em inputs, selects e textareas
        const inputs = popup.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.style.backgroundColor = '#2b2b3d';
            input.style.color = '#ffffff';
            input.style.borderColor = 'rgba(255, 255, 255, 0.1)';
        });
        
        // Aplicar estilos em labels
        const labels = popup.querySelectorAll('label, .form-label');
        labels.forEach(label => {
            label.style.color = '#e0e0e0';
        });
        
        // Aplicar estilos em textos gerais
        const texts = popup.querySelectorAll('p, div, span');
        texts.forEach(text => {
            // Não sobrescrever se já tiver cor definida explicitamente
            if (!text.style.color || text.style.color === 'rgb(0, 0, 0)' || text.style.color === 'black') {
                text.style.color = '#e0e0e0';
            }
        });
    }
    
    // Salvar referência original do Swal.fire
    const originalSwalFire = Swal.fire;
    
    // Sobrescrever Swal.fire para aplicar tema dark automaticamente
    Swal.fire = function(...args) {
        // Chamar método original
        const result = originalSwalFire.apply(this, args);
        
        // Aplicar tema dark se necessário
        if (isDarkMode()) {
            // Aguardar o popup ser criado
            setTimeout(() => {
                const popup = document.querySelector('.swal2-popup');
                if (popup) {
                    applyDarkTheme(popup);
                    
                    // Observar mudanças no DOM (para casos onde conteúdo é adicionado dinamicamente)
                    const observer = new MutationObserver(() => {
                        applyDarkTheme(popup);
                    });
                    
                    observer.observe(popup, {
                        childList: true,
                        subtree: true
                    });
                    
                    // Limpar observer quando o popup for fechado
                    const checkClosed = setInterval(() => {
                        if (!document.querySelector('.swal2-popup')) {
                            observer.disconnect();
                            clearInterval(checkClosed);
                        }
                    }, 100);
                }
            }, 10);
            
            // Também aplicar via customClass se não estiver definido
            if (args[0] && typeof args[0] === 'object') {
                const config = args[0];
                if (!config.customClass) {
                    config.customClass = {};
                }
                if (!config.customClass.popup) {
                    config.customClass.popup = 'swal2-dark';
                }
                if (!config.customClass.title) {
                    config.customClass.title = 'text-white';
                }
                if (!config.customClass.htmlContainer) {
                    config.customClass.htmlContainer = 'text-white';
                }
            }
        }
        
        return result;
    };
    
    // Também interceptar Swal.mixin para aplicar tema dark nas configurações padrão
    const originalSwalMixin = Swal.mixin;
    Swal.mixin = function(config) {
        if (isDarkMode() && config) {
            if (!config.customClass) {
                config.customClass = {};
            }
            if (!config.customClass.popup) {
                config.customClass.popup = 'swal2-dark';
            }
        }
        return originalSwalMixin.apply(this, arguments);
    };
    
    // Observar mudanças no tema do sistema
    const themeObserver = new MutationObserver(() => {
        // Reaplicar tema em popups abertos quando o tema mudar
        const popup = document.querySelector('.swal2-popup');
        if (popup) {
            if (isDarkMode()) {
                applyDarkTheme(popup);
            } else {
                popup.classList.remove('swal2-dark');
            }
        }
    });
    
    // Observar mudanças no atributo data-bs-theme
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme']
    });
    
    // Observar mudanças na classe dark-mode do body
    themeObserver.observe(document.body, {
        attributes: true,
        attributeFilter: ['class']
    });
    
    console.log('SweetAlert2 Dark Mode Auto-Configuration carregado');
})();

