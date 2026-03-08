/**
 * Integração Growly Digital com Easy Product Creator
 * 
 * Este script garante que o cashback seja exibido corretamente
 * em produtos criados pelo Easy Product Creator
 */

(function($) {
    'use strict';

    // Aguardar carregamento completo do DOM e EPC
    $(document).ready(function() {
        // Log para debug
        if (window.console && console.log) {
            console.log('PCW EPC Integration: Initialized');
        }

        // Adicionar classe de identificação ao body
        $('body').addClass('pcw-epc-integration-active');
    });

})(jQuery);
