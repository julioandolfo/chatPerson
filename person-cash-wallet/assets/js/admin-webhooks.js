jQuery(document).ready(function($) {
    'use strict';

    console.log('[PCW Webhooks] Initializing...');
    console.log('[PCW Webhooks] pcwWebhooks object:', typeof pcwWebhooks !== 'undefined' ? 'exists' : 'missing');
    
    // Verificar se pcwWebhooks existe
    if (typeof pcwWebhooks === 'undefined') {
        console.error('[PCW Webhooks] pcwWebhooks not defined! Script may not be localized.');
        window.pcwWebhooks = {
            ajaxUrl: ajaxurl,
            nonce: '',
            i18n: {}
        };
    }

    const PCWWebhooks = {
        init: function() {
            this.bindEvents();
            console.log('[PCW Webhooks] Events bound');
        },

        bindEvents: function() {
            // Toggle webhook type config
            $('input[name="type"]').on('change', this.handleTypeChange.bind(this));

            // Submit form
            $('#pcw-webhook-form').on('submit', this.handleSubmit.bind(this));

            // Delete webhook
            $(document).on('click', '.pcw-delete-webhook', this.handleDelete.bind(this));

            // Test webhook - usar event delegation para garantir que funcione
            $(document).on('click', '#pcw-test-webhook', this.handleTest.bind(this));
            
            console.log('[PCW Webhooks] Test button bound:', $('#pcw-test-webhook').length > 0);
        },

        handleTypeChange: function(e) {
            const type = $('input[name="type"]:checked').val();
            
            console.log('Webhook type changed to:', type);
            
            // Update active visual state
            $('.pcw-webhook-type-option').removeClass('active');
            $('input[name="type"]:checked').closest('.pcw-webhook-type-option').addClass('active');
            
            // Show/hide configs
            if (type === 'personizi_whatsapp') {
                $('#personizi-config').slideDown(300);
                $('#custom-config').slideUp(300);
                $('#test-personizi').slideDown(300);
            } else {
                $('#personizi-config').slideUp(300);
                $('#custom-config').slideDown(300);
                $('#test-personizi').slideUp(300);
            }
        },

        handleSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const $btn = $form.find('button[type="submit"]');
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Salvando...');
            
            $.ajax({
                url: pcwWebhooks.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'pcw_save_webhook',
                    nonce: pcwWebhooks.nonce,
                    ...$form.serializeArray().reduce((obj, item) => {
                        obj[item.name] = item.value;
                        return obj;
                    }, {})
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        // Mostrar erro detalhado
                        let errorMsg = response.data && response.data.message ? response.data.message : 'Erro ao salvar webhook';
                        
                        // Se tiver detalhes do erro, adicionar
                        if (response.data && response.data.details) {
                            errorMsg += '\n\nDetalhes: ' + response.data.details;
                        }
                        
                        // Se tiver query SQL (para debug), adicionar
                        if (response.data && response.data.query) {
                            console.error('SQL Query:', response.data.query);
                        }
                        
                        console.error('Save webhook error:', response);
                        alert('❌ ' + errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', {xhr, status, error});
                    alert('❌ Erro ao conectar com o servidor\n\nDetalhes: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        handleDelete: function(e) {
            e.preventDefault();
            
            if (!confirm(pcwWebhooks.i18n.confirmDelete)) {
                return;
            }
            
            const $btn = $(e.currentTarget);
            const webhookId = $btn.data('id');
            const $card = $btn.closest('.pcw-webhook-card');
            
            $btn.prop('disabled', true);
            
            $.ajax({
                url: pcwWebhooks.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'pcw_delete_webhook',
                    nonce: pcwWebhooks.nonce,
                    webhook_id: webhookId
                },
                success: function(response) {
                    if (response.success) {
                        $card.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if no more webhooks
                            if ($('.pcw-webhook-card').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        alert('❌ ' + (response.data.message || 'Erro ao deletar webhook'));
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('❌ Erro ao conectar com o servidor');
                    $btn.prop('disabled', false);
                }
            });
        },

        handleTest: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('[PCW Webhooks] Test button clicked');
            
            const $btn = $(e.currentTarget);
            const $result = $('#test-result');
            const originalText = $btn.html();
            const type = $('input[name="type"]:checked').val();
            
            console.log('[PCW Webhooks] Webhook type:', type);
            
            if (!type) {
                $result.html('<div class="notice notice-error inline"><p>⚠️ Selecione um tipo de webhook primeiro</p></div>');
                return;
            }
            
            if (type === 'personizi_whatsapp') {
                const testPhone = $('#test_phone').val();
                const testMessage = $('#test_message').val();
                
                if (!testPhone || !testMessage) {
                    $result.html('<div class="notice notice-error inline"><p>⚠️ Preencha o número e a mensagem de teste</p></div>');
                    return;
                }
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Enviando...');
                $result.html('<p>📤 Enviando mensagem WhatsApp...</p>');
                
                $.ajax({
                    url: pcwWebhooks.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'pcw_test_webhook_send',
                        nonce: pcwWebhooks.nonce,
                        type: type,
                        test_phone: testPhone,
                        test_message: testMessage
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<div class="notice notice-success inline">';
                            html += '<p><strong>✅ ' + response.data.message + '</strong></p>';
                            if (response.data.data) {
                                html += '<p style="font-size: 12px; color: #666; margin-top: 8px;">';
                                html += 'ID da Mensagem: ' + (response.data.data.data?.message_id || 'N/A') + '<br>';
                                html += 'ID da Conversa: ' + (response.data.data.data?.conversation_id || 'N/A');
                                html += '</p>';
                            }
                            html += '</div>';
                            $result.html(html);
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>❌ ' + (response.data.message || 'Erro ao enviar') + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error inline"><p>❌ Erro ao conectar com o servidor</p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            } else {
                // Teste de webhook customizado
                const url = $('#url').val();
                const event = $('select[name="event"]').val();
                
                if (!url) {
                    $result.html('<div class="notice notice-error inline"><p>⚠️ Preencha a URL do webhook antes de testar</p></div>');
                    return;
                }
                
                if (!event) {
                    $result.html('<div class="notice notice-error inline"><p>⚠️ Selecione um evento antes de testar</p></div>');
                    return;
                }
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Testando...');
                $result.html('<p>🔄 Enviando requisição de teste para ' + url + '...</p>');
                
                $.ajax({
                    url: pcwWebhooks.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'pcw_test_webhook_send',
                        nonce: pcwWebhooks.nonce,
                        type: 'custom',
                        url: url,
                        event: event,
                        method: $('select[name="method"]').val() || 'POST',
                        headers: $('#headers').val() || ''
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<div class="notice notice-success inline">';
                            html += '<p><strong>✅ ' + response.data.message + '</strong></p>';
                            if (response.data.response_code) {
                                html += '<p style="font-size: 12px; color: #666; margin-top: 8px;">';
                                html += '<strong>Código HTTP:</strong> ' + response.data.response_code + '<br>';
                                if (response.data.response_body) {
                                    html += '<strong>Resposta:</strong><br>';
                                    html += '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 200px;">' + response.data.response_body + '</pre>';
                                }
                                html += '</p>';
                            }
                            html += '</div>';
                            $result.html(html);
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>❌ ' + (response.data.message || 'Erro ao testar webhook') + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error inline"><p>❌ Erro ao conectar com o servidor</p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            }
        }
    };

    PCWWebhooks.init();
});
