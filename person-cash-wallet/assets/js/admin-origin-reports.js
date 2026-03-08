/**
 * Growly Digital - Admin Origin Reports
 * 
 * @package PersonCashWallet
 * @since 1.4.0
 */
(function($) {
    'use strict';

    var PCWOriginReports = {
        
        /**
         * Inicializar
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Refresh data
            $(document).on('click', '.pcw-refresh-data', this.refreshData.bind(this));
            
            // Export CSV (futuro)
            $(document).on('click', '.pcw-export-csv', this.exportCSV.bind(this));

            // Sincronizar atribuições WooCommerce
            $(document).on('click', '#pcw-sync-wc-attributions', this.syncWCAttributions.bind(this));
        },

        /**
         * Refresh data via AJAX
         */
        refreshData: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var period = $('select[name="period"]').val() || '30days';
            
            $btn.prop('disabled', true).text('Carregando...');
            
            $.ajax({
                url: pcwOriginReports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_get_origin_report',
                    nonce: pcwOriginReports.nonce,
                    period: period
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to show new data
                        location.reload();
                    } else {
                        alert(response.data.message || 'Erro ao carregar dados');
                    }
                },
                error: function() {
                    alert('Erro de conexão');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Atualizar');
                }
            });
        },

        /**
         * Export to CSV
         */
        exportCSV: function(e) {
            e.preventDefault();
            
            var period = $('select[name="period"]').val() || '30days';
            var reportType = $(e.currentTarget).data('report') || 'channels';
            
            // TODO: Implementar exportação CSV
            alert('Funcionalidade de exportação em desenvolvimento');
        },

        /**
         * Sincronizar atribuições do WooCommerce
         */
        syncWCAttributions: function(e) {
            e.preventDefault();
            console.log('PCW: syncWCAttributions called');

            var $btn = $(e.currentTarget);
            var $result = $('#pcw-sync-result');
            var originalText = $btn.html();

            // Verificar se temos os dados de AJAX
            if (typeof pcwOriginReports === 'undefined') {
                console.error('PCW: pcwOriginReports not defined');
                alert('Erro: Dados de configuração não encontrados. Recarregue a página.');
                return;
            }

            console.log('PCW: AJAX URL:', pcwOriginReports.ajaxUrl);

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="margin-top: 4px;"></span> Sincronizando...');
            $result.html('').css('color', '');

            $.ajax({
                url: pcwOriginReports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_sync_wc_attributions',
                    nonce: pcwOriginReports.nonce
                },
                success: function(response) {
                    console.log('PCW: AJAX response:', response);
                    if (response.success) {
                        $result.html('✓ ' + response.data.message).css('color', '#00a32a');
                        // Recarregar página após 2 segundos
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.html('✗ ' + (response.data.message || 'Erro ao sincronizar')).css('color', '#d63638');
                        $btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('PCW: AJAX error:', status, error);
                    $result.html('✗ Erro de conexão: ' + error).css('color', '#d63638');
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    };

    // CSS para animação de spin
    $('<style>.dashicons.spin { animation: pcw-spin 1s linear infinite; } @keyframes pcw-spin { 100% { transform: rotate(360deg); } }</style>').appendTo('head');

    $(document).ready(function() {
        console.log('PCW Origin Reports: Initializing...');
        console.log('PCW: Button found:', $('#pcw-sync-wc-attributions').length);
        PCWOriginReports.init();
        console.log('PCW Origin Reports: Initialized');
    });

})(jQuery);
