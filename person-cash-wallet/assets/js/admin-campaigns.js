/**
 * Growly Digital - Admin Campaigns
 */
(function($) {
    'use strict';

    var PCWAdminCampaigns = {
        init: function() {
            console.log('PCWAdminCampaigns init');
            this.bindEvents();
            this.initAudienceToggle();
        },

        bindEvents: function() {
            console.log('PCWAdminCampaigns bindEvents');
            
            // Form submit
            $('#pcw-campaign-form').on('submit', this.handleSubmit.bind(this));

            // Send campaign
            $('#send-campaign').on('click', this.handleSend.bind(this));

            // Delete campaign
            $(document).on('click', '.pcw-delete-campaign', this.handleDelete.bind(this));

            // Preview recipients
            $('#preview-recipients').on('click', this.previewRecipients.bind(this));

            // Audience type toggle
            $('#audience-type').on('change', this.toggleAudience.bind(this));

            // Custom list selection
            $('#custom-list-select').on('change', this.previewListMembers.bind(this));
            
            console.log('Event bindings complete');
        },

        initAudienceToggle: function() {
            console.log('initAudienceToggle');
            var $audienceType = $('#audience-type');
            console.log('Audience type element found:', $audienceType.length);
            
            if ($audienceType.length > 0) {
                // Mostrar a seção correta ao carregar
                this.toggleAudience();
            }
        },

        toggleAudience: function(e) {
            var type = $('#audience-type').val();
            console.log('toggleAudience - type:', type);
            
            // Remove active de todas as seções
            $('.audience-section').removeClass('active').css('display', 'none');
            console.log('Hidden all sections');
            
            // Adiciona active na seção selecionada
            if (type === 'filtered') {
                console.log('Showing filtered audience');
                $('#filtered-audience').addClass('active').css('display', 'block');
            } else if (type === 'custom_list') {
                console.log('Showing custom list audience');
                $('#custom-list-audience').addClass('active').css('display', 'block');
            }
        },

        handleSubmit: function(e) {
            e.preventDefault();

            var $form = $(e.currentTarget);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.html();

            var formData = new FormData($form[0]);
            formData.append('action', 'pcw_save_campaign');
            formData.append('nonce', pcwCampaigns.nonce);

            // Get wp_editor content
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('campaign_content')) {
                formData.set('content', tinyMCE.get('campaign_content').getContent());
            }

            $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Salvando...');

            $.ajax({
                url: pcwCampaigns.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $submitBtn.html('<span class="dashicons dashicons-yes"></span> Salvo!');
                        
                        // Update hidden field with campaign ID
                        if (response.data.campaign_id) {
                            $('input[name="campaign_id"]').val(response.data.campaign_id);
                        }

                        setTimeout(function() {
                            $submitBtn.prop('disabled', false).html(originalText);
                        }, 1500);
                    } else {
                        alert(response.data.message || 'Erro ao salvar');
                        $submitBtn.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    alert('Erro ao salvar');
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            });
        },

        handleSend: function(e) {
            e.preventDefault();

            if (!confirm(pcwCampaigns.i18n.confirmSend)) {
                return;
            }

            var $btn = $(e.currentTarget);
            var originalText = $btn.html();
            var campaignId = $('input[name="campaign_id"]').val();

            if (!campaignId || campaignId === '0') {
                alert('Salve a campanha primeiro');
                return;
            }

            $btn.prop('disabled', true).html(pcwCampaigns.i18n.sending);

            $.ajax({
                url: pcwCampaigns.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_send_campaign',
                    nonce: pcwCampaigns.nonce,
                    campaign_id: campaignId
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        alert(response.data.message || 'Erro ao enviar');
                        $btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    alert('Erro ao enviar');
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        handleDelete: function(e) {
            e.preventDefault();

            if (!confirm(pcwCampaigns.i18n.confirmDelete)) {
                return;
            }

            var $btn = $(e.currentTarget);
            var id = $btn.data('id');

            $.ajax({
                url: pcwCampaigns.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_delete_campaign',
                    nonce: pcwCampaigns.nonce,
                    id: id
                },
                success: function() {
                    $btn.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                    });
                }
            });
        },

        previewRecipients: function(e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);
            var $preview = $('#recipients-preview');
            
            var conditions = {};
            $('select[name^="recipient_conditions"], input[name^="recipient_conditions"]').each(function() {
                var name = $(this).attr('name').replace('recipient_conditions[', '').replace(']', '');
                conditions[name] = $(this).val();
            });

            $btn.prop('disabled', true);
            $preview.html('<p>Carregando...</p>');

            $.ajax({
                url: pcwCampaigns.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_preview_recipients',
                    nonce: pcwCampaigns.nonce,
                    conditions: conditions
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<p><strong>' + response.data.count + ' destinatários</strong></p>';
                        if (response.data.preview.length > 0) {
                            html += '<ul style="margin: 10px 0; font-size: 12px;">';
                            response.data.preview.forEach(function(user) {
                                html += '<li>' + user.display_name + ' (' + user.user_email + ')</li>';
                            });
                            if (response.data.count > 5) {
                                html += '<li>...</li>';
                            }
                            html += '</ul>';
                        }
                        $preview.html(html);
                    } else {
                        $preview.html('<p style="color: #dc2626;">Erro ao carregar</p>');
                    }
                },
                error: function() {
                    $preview.html('<p style="color: #dc2626;">Erro ao carregar</p>');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        previewListMembers: function(e) {
            var listId = $(e.currentTarget).val();
            var $preview = $('#list-members-preview');

            if (!listId) {
                $preview.empty();
                return;
            }

            $preview.html('<p><span class="dashicons dashicons-update spin"></span> Carregando membros...</p>');

            $.ajax({
                url: pcwCampaigns.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_preview_list_members',
                    nonce: pcwCampaigns.nonce,
                    list_id: listId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var html = '<div class="pcw-notice pcw-notice-success">';
                        html += '<strong>Total de membros:</strong> ' + response.data.count;
                        html += '</div>';
                        
                        if (response.data.preview && response.data.preview.length > 0) {
                            html += '<p style="margin: 10px 0;"><strong>Primeiros membros:</strong></p>';
                            html += '<ul style="list-style: disc; padding-left: 20px;">';
                            response.data.preview.forEach(function(member) {
                                var name = member.name ? member.name + ' - ' : '';
                                html += '<li>' + name + member.email + '</li>';
                            });
                            if (response.data.count > 5) {
                                html += '<li>...</li>';
                            }
                            html += '</ul>';
                        }
                        $preview.html(html);
                    } else {
                        $preview.html('<p style="color: #dc2626;">Erro ao carregar membros</p>');
                    }
                },
                error: function() {
                    $preview.html('<p style="color: #dc2626;">Erro ao carregar membros</p>');
                }
            });
        }
    };

    $(document).ready(function() {
        PCWAdminCampaigns.init();
    });

})(jQuery);
