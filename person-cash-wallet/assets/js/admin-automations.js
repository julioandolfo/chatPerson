/**
 * Growly Digital - Admin Automations
 */
(function($) {
    'use strict';

    var PCWAdminAutomations = {
        init: function() {
            this.bindEvents();
        },

        openDialog: function($content, options) {
            var dialogOptions = $.extend({
                modal: true,
                width: 800,
                draggable: false,
                resizable: false,
                dialogClass: 'pcw-ui-dialog',
                closeOnEscape: true,
                create: function() {
                    $('.ui-widget-overlay').addClass('pcw-ui-overlay');
                },
                close: function() {
                    $content.dialog('destroy');
                    $content.remove();
                }
            }, options || {});

            $content.dialog(dialogOptions);
        },

        loadAISubjectSuggestions: function($modal, type, context) {
            var $modalContent = $modal.find('.pcw-ai-suggestions');
            var $modalLoading = $modal.find('.pcw-ai-modal-loading');
            $modalContent.hide();
            $modalLoading.show();

            var promises = [];
            for (var i = 0; i < 3; i++) {
                promises.push($.ajax({
                    url: pcwAutomations.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pcw_generate_ai_subject',
                        nonce: pcwAutomations.nonce,
                        type: type,
                        context: context
                    }
                }));
            }

            $.when.apply($, promises).done(function() {
                var suggestions = [];

                for (var i = 0; i < arguments.length; i++) {
                    var response = arguments[i];
                    if (Array.isArray(response)) {
                        response = response[0];
                    }
                    if (response.success && response.data.subject) {
                        suggestions.push(response.data.subject);
                    }
                }

                if (suggestions.length > 0) {
                    var $list = $modalContent.find('.pcw-suggestions-list').empty();
                    $.each(suggestions, function(index, subject) {
                        var $item = $('<div class="pcw-suggestion-item">')
                            .html('<span class="pcw-suggestion-text">' + subject + '</span>' +
                                  '<button type="button" class="button button-small pcw-use-suggestion">Usar</button>')
                            .data('subject', subject);
                        $list.append($item);
                    });

                    $modalLoading.hide();
                    $modalContent.show();
                } else {
                    alert(pcwAutomations.i18n.aiError);
                    $modal.dialog('close');
                }
            }).fail(function() {
                alert(pcwAutomations.i18n.aiError);
                $modal.dialog('close');
            });
        },

        bindEvents: function() {
            // Form submit
            $('#pcw-automation-form').on('submit', this.handleSubmit.bind(this));

            // Toggle automation
            $(document).on('click', '.pcw-toggle-automation', this.handleToggle.bind(this));

            // Delete automation
            $(document).on('click', '.pcw-delete-automation', this.handleDelete.bind(this));

            // Add workflow step
            $('#add-workflow-step').on('click', this.addWorkflowStep.bind(this));

            // Remove workflow step
            $(document).on('click', '.pcw-remove-step', this.removeWorkflowStep.bind(this));

            // Toggle step config
            $(document).on('click', '.pcw-toggle-step', this.toggleStepConfig.bind(this));
            $(document).on('click', '.pcw-step-header', this.toggleStepConfigHeader.bind(this));

            // Open step email editor
            $(document).on('click', '.pcw-open-step-email-editor', this.openStepEmailEditor.bind(this));

            // Character counter for SMS
            $(document).on('input', 'textarea[maxlength="160"]', this.updateCharCounter.bind(this));

            // Media selector for WhatsApp
            $(document).on('click', '.pcw-select-media', this.selectMedia.bind(this));

            // WhatsApp - Personizi toggle
            $(document).on('change', '.pcw-use-personizi', this.handlePersoniziToggle.bind(this));

            // WhatsApp - Specific number toggle
            $(document).on('change', '.pcw-use-specific-number', this.handleSpecificNumberToggle.bind(this));

            // WhatsApp - Template toggle and account change
            $(document).on('change', '.pcw-use-template-toggle', this.handleTemplateToggle.bind(this));
            $(document).on('change', '.pcw-template-account', this.handleTemplateAccountChange.bind(this));
            $(document).on('change', '.pcw-template-select', this.handleTemplateSelection.bind(this));

            // WhatsApp - Generate message with AI
            $(document).on('click', '.pcw-generate-whatsapp-message', this.generateWhatsAppAI.bind(this));

            // Webhook - Preset selection
            $(document).on('change', '.pcw-webhook-preset', this.handleWebhookPreset.bind(this));

            // Webhook - Auth type change
            $(document).on('change', '.pcw-webhook-auth', this.handleWebhookAuthChange.bind(this));

            // Webhook - Method change
            $(document).on('change', '.pcw-webhook-method', this.handleWebhookMethodChange.bind(this));

            // Webhook - Add/Remove headers
            $(document).on('click', '.pcw-add-header', this.addWebhookHeader.bind(this));
            $(document).on('click', '.pcw-remove-header', this.removeWebhookHeader.bind(this));

            // Webhook - Test connection
            $(document).on('click', '.pcw-test-webhook', this.testWebhook.bind(this));

            // Condition - Type change
            $(document).on('change', '.pcw-condition-type', this.handleConditionTypeChange.bind(this));

            // Condition - Cart operator change
            $(document).on('change', '.pcw-cart-operator', this.handleCartOperatorChange.bind(this));

            // Generate AI subject
            $('#generate-ai-subject').on('click', this.generateAISubject.bind(this));

            // Generate AI content
            $('#generate-ai-content').on('click', this.generateAIContent.bind(this));

            // Generate AI complete (subject + content)
            $('#generate-ai-complete').on('click', this.generateAIComplete.bind(this));

            // Generate AI for workflow steps
            $(document).on('click', '.pcw-generate-step-subject', this.generateStepSubject.bind(this));
            $(document).on('click', '.pcw-generate-step-content', this.generateStepContent.bind(this));
            $(document).on('click', '.pcw-generate-step-complete', this.generateStepComplete.bind(this));
        },

        handleSubmit: function(e) {
            e.preventDefault();

            var $form = $(e.currentTarget);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.html();

            // Collect form data
            var formData = new FormData($form[0]);
            formData.append('action', 'pcw_save_automation');
            formData.append('nonce', pcwAutomations.nonce);

            // Get wp_editor content
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email_template')) {
                formData.set('email_template', tinyMCE.get('email_template').getContent());
            }

            $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Salvando...');

            $.ajax({
                url: pcwAutomations.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $submitBtn.html('<span class="dashicons dashicons-yes"></span> Salvo!');
                        setTimeout(function() {
                            if (response.data.redirect) {
                                window.location.href = response.data.redirect;
                            }
                        }, 500);
                    } else {
                        alert(response.data.message || pcwAutomations.i18n.error);
                        $submitBtn.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    alert(pcwAutomations.i18n.error);
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            });
        },

        handleToggle: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var id = $btn.data('id');
            var status = $btn.data('status');

            $.ajax({
                url: pcwAutomations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_toggle_automation',
                    nonce: pcwAutomations.nonce,
                    id: id,
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        },

        handleDelete: function(e) {
            e.preventDefault();

            if (!confirm(pcwAutomations.i18n.confirmDelete)) {
                return;
            }

            var $btn = $(e.currentTarget);
            var id = $btn.data('id');

            $.ajax({
                url: pcwAutomations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_delete_automation',
                    nonce: pcwAutomations.nonce,
                    id: id
                },
                success: function() {
                    $btn.closest('.pcw-automation-card').fadeOut(300, function() {
                        $(this).remove();
                    });
                }
            });
        },

        addWorkflowStep: function(e) {
            e.preventDefault();
            this.showStepSelector();
        },

        showStepSelector: function() {
            var self = this;
            
            // Criar modal de seleção
            var modalHtml = '<div id="pcw-step-selector-modal">';
            modalHtml += '<p class="description" style="margin-bottom: 20px;">Escolha o tipo de ação que deseja adicionar ao workflow:</p>';
            modalHtml += '<div class="pcw-step-types-grid">';
            
            // Tipos de etapas disponíveis
            var stepTypes = [
                {
                    type: 'delay',
                    icon: 'dashicons-clock',
                    title: 'Aguardar',
                    description: 'Aguardar um período de tempo antes da próxima ação',
                    color: '#667eea'
                },
                {
                    type: 'send_email',
                    icon: 'dashicons-email',
                    title: 'Enviar Email',
                    description: 'Enviar um email personalizado para o cliente',
                    color: '#3b82f6'
                },
                {
                    type: 'send_sms',
                    icon: 'dashicons-smartphone',
                    title: 'Enviar SMS',
                    description: 'Enviar uma mensagem de texto (SMS)',
                    color: '#f59e0b'
                },
                {
                    type: 'send_whatsapp',
                    icon: 'dashicons-admin-comments',
                    title: 'Enviar WhatsApp',
                    description: 'Enviar uma mensagem via WhatsApp',
                    color: '#22c55e'
                },
                {
                    type: 'add_tag',
                    icon: 'dashicons-tag',
                    title: 'Adicionar Tag',
                    description: 'Adicionar uma tag ao cliente',
                    color: '#f59e0b'
                },
                {
                    type: 'condition',
                    icon: 'dashicons-randomize',
                    title: 'Condição',
                    description: 'Criar uma bifurcação baseada em condições',
                    color: '#8b5cf6'
                },
                {
                    type: 'webhook',
                    icon: 'dashicons-admin-plugins',
                    title: 'Webhook',
                    description: 'Chamar uma URL externa',
                    color: '#ec4899'
                }
            ];
            
            stepTypes.forEach(function(step) {
                modalHtml += '<div class="pcw-step-type-card" data-step-type="' + step.type + '" style="border-left: 4px solid ' + step.color + ';">';
                modalHtml += '<span class="dashicons ' + step.icon + '" style="color: ' + step.color + '; font-size: 32px; width: 32px; height: 32px;"></span>';
                modalHtml += '<div>';
                modalHtml += '<h3>' + step.title + '</h3>';
                modalHtml += '<p>' + step.description + '</p>';
                modalHtml += '</div>';
                modalHtml += '</div>';
            });
            
            modalHtml += '</div>'; // fecha grid
            modalHtml += '</div>'; // fecha modal principal
            
            // Adicionar modal ao body
            var $modal = $(modalHtml);
            $('body').append($modal);
            
            // Evento de clique nos tipos
            $modal.find('.pcw-step-type-card').on('click', function() {
                var stepType = $(this).data('step-type');
                $modal.dialog('close');
                self.createStep(stepType);
            });
            
            // Mostrar modal via jQuery UI Dialog
            this.openDialog($modal, {
                title: 'Adicionar Etapa no Fluxo',
                width: 760
            });
        },

        createStep: function(stepType) {
            var $container = $('#workflow-steps-container');
            var index = $container.find('.pcw-workflow-step').length;
            var html = '';
            
            switch(stepType) {
                case 'delay':
                    html = this.createDelayStep(index);
                    break;
                case 'send_email':
                    html = this.createEmailStep(index);
                    break;
                case 'send_sms':
                    html = this.createSMSStep(index);
                    break;
                case 'send_whatsapp':
                    html = this.createWhatsAppStep(index);
                    break;
                case 'add_tag':
                    html = this.createTagStep(index);
                    break;
                case 'condition':
                    html = this.createConditionStep(index);
                    break;
                case 'webhook':
                    html = this.createWebhookStep(index);
                    break;
            }
            
            $container.append(html);
            
            // Auto-abrir a configuração da etapa recém-criada
            var $newStep = $container.find('.pcw-workflow-step').last();
            $newStep.find('.pcw-step-config').slideDown(300, function() {
                $newStep.addClass('expanded');
            });
            $newStep.find('.pcw-toggle-step .dashicons')
                .removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        },

        createDelayStep: function(index) {
            var html = '<div class="pcw-workflow-step pcw-step-delay" data-index="' + index + '">';
            html += '<div class="pcw-step-header">';
            html += '<div class="pcw-step-icon"><span class="dashicons dashicons-clock"></span></div>';
            html += '<div class="pcw-step-summary">';
            html += '<h4>Aguardar</h4>';
            html += '<p class="description">Clique para configurar</p>';
            html += '</div>';
            html += '<div class="pcw-step-actions">';
            html += '<button type="button" class="button button-small pcw-toggle-step"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
            html += '<button type="button" class="button button-small pcw-remove-step"><span class="dashicons dashicons-trash"></span></button>';
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-step-config" style="display: none;">';
            html += '<input type="hidden" name="steps[' + index + '][type]" value="delay">';
            html += '<div class="pcw-form-group">';
            html += '<label>Tempo de Espera</label>';
            html += '<div class="pcw-inline-form">';
            html += '<input type="number" name="steps[' + index + '][config][value]" value="1" min="1" style="width: 80px;">';
            html += '<select name="steps[' + index + '][config][unit]" style="width: 120px;">';
            html += '<option value="minutes">minutos</option>';
            html += '<option value="hours">horas</option>';
            html += '<option value="days" selected>dias</option>';
            html += '</select>';
            html += '</div>';
            html += '<p class="description">Para não sobrecarregar o cliente com muitas mensagens</p>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-workflow-connector"></div>';
            return html;
        },

        createEmailStep: function(index) {
            var html = '<div class="pcw-workflow-step pcw-step-send_email" data-index="' + index + '">';
            html += '<div class="pcw-step-header">';
            html += '<div class="pcw-step-icon"><span class="dashicons dashicons-email"></span></div>';
            html += '<div class="pcw-step-summary">';
            html += '<h4>Enviar Email</h4>';
            html += '<p class="description">Clique para configurar</p>';
            html += '</div>';
            html += '<div class="pcw-step-actions">';
            html += '<button type="button" class="button button-small pcw-toggle-step"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
            html += '<button type="button" class="button button-small pcw-remove-step"><span class="dashicons dashicons-trash"></span></button>';
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-step-config" style="display: none;">';
            html += '<input type="hidden" name="steps[' + index + '][type]" value="send_email">';
            
            // Campo de Assunto com botão IA
            html += '<div class="pcw-form-group">';
            html += '<label>Assunto do Email ';
            html += '<button type="button" class="button button-small pcw-ai-btn pcw-generate-step-subject" data-step="' + index + '" title="Gerar sugestões de assunto com IA">';
            html += '<span class="dashicons dashicons-admin-site-alt3"></span> IA';
            html += '</button>';
            html += '</label>';
            html += '<input type="text" name="steps[' + index + '][config][subject]" id="step-email-subject-' + index + '" class="widefat" placeholder="Ex: Olá {{customer_first_name}}, temos novidades!">';
            html += '</div>';
            
            // Campo de Conteúdo com botões IA
            html += '<div class="pcw-form-group">';
            html += '<label>Conteúdo do Email</label>';
            html += '<div class="pcw-email-editor-actions">';
            html += '<button type="button" class="button button-primary pcw-open-step-email-editor" data-step="' + index + '">';
            html += '<span class="dashicons dashicons-edit-page"></span> Editor Visual (Drag & Drop)';
            html += '</button>';
            html += '<button type="button" class="button button-hero pcw-ai-btn-hero pcw-generate-step-complete" data-step="' + index + '" title="Gerar assunto e conteúdo com IA">';
            html += '<span class="dashicons dashicons-admin-site-alt3"></span> Gerar Email Completo com IA';
            html += '</button>';
            html += '<button type="button" class="button pcw-ai-btn pcw-generate-step-content" data-step="' + index + '" title="Gerar apenas conteúdo com IA">';
            html += '<span class="dashicons dashicons-admin-site-alt3"></span> Gerar Conteúdo';
            html += '</button>';
            html += '</div>';
            html += '<textarea name="steps[' + index + '][config][content]" id="step-email-content-' + index + '" class="widefat" rows="6" style="display: none;"></textarea>';
            html += '<p class="description" style="margin-top: 15px;"><strong>Variáveis disponíveis:</strong><br>';
            html += '<code>{{customer_name}}</code>, <code>{{customer_first_name}}</code>, <code>{{customer_email}}</code>, ';
            html += '<code>{{product_name}}</code>, <code>{{product_image}}</code>, <code>{{product_price}}</code>, ';
            html += '<code>{{cashback_balance}}</code>, <code>{{user_level}}</code>, <code>{{site_name}}</code>, <code>{{site_url}}</code>';
            html += '</p>';
            html += '</div>';
            
            // Checkbox de IA automática
            html += '<div class="pcw-form-group">';
            html += '<label class="pcw-checkbox-inline">';
            html += '<input type="checkbox" name="steps[' + index + '][config][use_ai]" value="1">';
            html += ' Usar IA para personalizar conteúdo automaticamente em cada envio';
            html += '</label>';
            html += '<p class="description" style="margin-left: 24px;">A IA vai adaptar o conteúdo para cada cliente baseado no contexto (nome, produtos, etc)</p>';
            html += '</div>';
            
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-workflow-connector"></div>';
            return html;
        },

        createWhatsAppStep: function(index) {
            var self = this;
            var html = '<div class="pcw-workflow-step pcw-step-send_whatsapp" data-index="' + index + '">';
            html += '<div class="pcw-step-header">';
            html += '<div class="pcw-step-icon"><span class="dashicons dashicons-admin-comments"></span></div>';
            html += '<div class="pcw-step-summary">';
            html += '<h4>Enviar WhatsApp</h4>';
            html += '<p class="description">Clique para configurar</p>';
            html += '</div>';
            html += '<div class="pcw-step-actions">';
            html += '<button type="button" class="button button-small pcw-toggle-step"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
            html += '<button type="button" class="button button-small pcw-remove-step"><span class="dashicons dashicons-trash"></span></button>';
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-step-config" style="display: none;">';
            html += '<input type="hidden" name="steps[' + index + '][type]" value="send_whatsapp">';
            
            // Personizi Integration (será preenchido via AJAX)
            html += '<div class="pcw-personizi-section-' + index + '">';
            html += '<div class="notice notice-info inline" style="margin: 0 0 15px;">';
            html += '<p><span class="dashicons dashicons-update spin"></span> Carregando configuração do Personizi...</p>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="pcw-form-group">';
            html += '<label>';
            html += 'Mensagem WhatsApp ';
            html += '<button type="button" class="button button-small pcw-ai-btn pcw-generate-whatsapp-message" data-step="' + index + '" title="Gerar mensagem com IA">';
            html += '<span class="dashicons dashicons-admin-site-alt3"></span> Gerar com IA';
            html += '</button>';
            html += '</label>';
            html += '<div style="background: #fefce8; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 12px; margin-bottom: 10px;">';
            html += '<label class="pcw-checkbox-inline" style="align-items: flex-start; gap: 8px;">';
            html += '<input type="checkbox" name="steps[' + index + '][config][ai_unique_message]" value="1" class="pcw-ai-unique-toggle" data-step="' + index + '" style="margin-top: 2px;">';
            html += '<div>';
            html += '<strong>✨ Mensagem única por IA para cada envio</strong><br>';
            html += '<span style="font-size: 12px; color: #92400e;">A IA gera uma nova variação da mensagem para cada destinatário — reduz risco de bloqueio por conteúdo repetitivo. A mensagem abaixo é usada como contexto/base.</span>';
            html += '</div>';
            html += '</label>';
            html += '</div>';
            html += '<textarea name="steps[' + index + '][config][message]" id="step-whatsapp-message-' + index + '" class="widefat" rows="6" placeholder="Ex: Olá {{customer_first_name}}! 🎉"></textarea>';
            html += '<p class="description">Emojis são bem-vindos! 😊 Variáveis: {{customer_first_name}}, {{customer_name}}, {{order_number}}, {{order_total}}</p>';
            html += '</div>';
            
            html += '<div class="pcw-form-group pcw-whatsapp-media">';
            html += '<label>Imagem/Arquivo (opcional)</label>';
            html += '<div class="pcw-media-uploader">';
            html += '<input type="text" name="steps[' + index + '][config][media_url]" class="widefat pcw-media-input" placeholder="URL da imagem ou arquivo">';
            html += '<button type="button" class="button pcw-select-media"><span class="dashicons dashicons-admin-media"></span> Selecionar</button>';
            html += '</div>';
            html += '<p class="description">Disponível apenas para outras integrações WhatsApp</p>';
            html += '</div>';
            
            html += '<div class="pcw-form-group">';
            html += '<label>Botões de Ação (opcional)</label>';
            html += '<input type="text" name="steps[' + index + '][config][button_text]" class="widefat" placeholder="Ex: Ver Produtos" style="margin-bottom: 8px;">';
            html += '<input type="url" name="steps[' + index + '][config][button_url]" class="widefat" placeholder="URL do botão">';
            html += '<p class="description">Adicione um botão de ação à mensagem (ex: link para produtos, pedidos, cashback)</p>';
            html += '</div>';
            
            html += '<div class="pcw-step-variables">';
            html += '<p class="description"><strong>Variáveis disponíveis:</strong><br>';
            html += '<code>{{customer_name}}</code>, <code>{{customer_first_name}}</code>, <code>{{customer_email}}</code>, ';
            html += '<code>{{customer_phone}}</code>, <code>{{order_number}}</code>, <code>{{order_total}}</code>, <code>{{site_name}}</code>';
            html += '</p>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-workflow-connector"></div>';
            
            // Carregar configuração do Personizi via AJAX
            setTimeout(function() {
                self.loadPersoniziConfig(index);
            }, 100);
            
            return html;
        },

        createSMSStep: function(index) {
            var html = '<div class="pcw-workflow-step pcw-step-send_sms" data-index="' + index + '">';
            html += '<div class="pcw-step-header">';
            html += '<div class="pcw-step-icon"><span class="dashicons dashicons-smartphone"></span></div>';
            html += '<div class="pcw-step-summary">';
            html += '<h4>Enviar SMS</h4>';
            html += '<p class="description">Clique para configurar</p>';
            html += '</div>';
            html += '<div class="pcw-step-actions">';
            html += '<button type="button" class="button button-small pcw-toggle-step"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
            html += '<button type="button" class="button button-small pcw-remove-step"><span class="dashicons dashicons-trash"></span></button>';
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-step-config" style="display: none;">';
            html += '<input type="hidden" name="steps[' + index + '][type]" value="send_sms">';
            html += '<div class="pcw-form-group">';
            html += '<label>Mensagem SMS</label>';
            html += '<textarea name="steps[' + index + '][config][message]" class="widefat" rows="4" maxlength="160" placeholder="Ex: Olá {{customer_first_name}}, confira nossas ofertas!"></textarea>';
            html += '<p class="description">Máximo 160 caracteres <span class="pcw-char-count">0/160</span></p>';
            html += '</div>';
            html += '<div class="pcw-form-group">';
            html += '<label class="pcw-checkbox-inline">';
            html += '<input type="checkbox" name="steps[' + index + '][config][use_short_url]" value="1">';
            html += ' Encurtar URLs automaticamente';
            html += '</label>';
            html += '</div>';
            html += '<div class="pcw-step-variables">';
            html += '<p class="description"><strong>Variáveis disponíveis:</strong><br>';
            html += '<code>{{customer_name}}</code>, <code>{{customer_first_name}}</code>, <code>{{site_name}}</code>';
            html += '</p>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-workflow-connector"></div>';
            return html;
        },

        createTagStep: function(index) {
            var html = '<div class="pcw-workflow-step pcw-step-tag" data-index="' + index + '">';
            html += '<div class="pcw-step-icon"><span class="dashicons dashicons-tag"></span></div>';
            html += '<div class="pcw-step-content">';
            html += '<h4>Adicionar Tag</h4>';
            html += '<input type="text" name="steps[' + index + '][config][tag_name]" placeholder="Nome da tag">';
            html += '<input type="hidden" name="steps[' + index + '][type]" value="add_tag">';
            html += '</div>';
            html += '<button type="button" class="pcw-remove-step"><span class="dashicons dashicons-no-alt"></span></button>';
            html += '</div>';
            html += '<div class="pcw-workflow-connector"></div>';
            return html;
        },

        createConditionStep: function(index) {
            var html = '<div class="pcw-workflow-step pcw-step-condition" data-index="' + index + '">';
            html += '<div class="pcw-step-header">';
            html += '<div class="pcw-step-icon"><span class="dashicons dashicons-randomize"></span></div>';
            html += '<div class="pcw-step-summary">';
            html += '<h4>Condição</h4>';
            html += '<p class="description">Clique para configurar</p>';
            html += '</div>';
            html += '<div class="pcw-step-actions">';
            html += '<button type="button" class="button button-small pcw-toggle-step"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
            html += '<button type="button" class="button button-small pcw-remove-step"><span class="dashicons dashicons-trash"></span></button>';
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-step-config" style="display: none;">';
            html += '<input type="hidden" name="steps[' + index + '][type]" value="condition">';
            
            html += '<div class="pcw-form-group">';
            html += '<label>Tipo de Condição</label>';
            html += '<select name="steps[' + index + '][config][condition_type]" class="widefat pcw-condition-type" data-step="' + index + '">';
            html += '<option value="opened_email">Abriu o email anterior</option>';
            html += '<option value="clicked_email">Clicou no email anterior</option>';
            html += '<option value="made_purchase">Cliente fez uma compra</option>';
            html += '<option value="not_purchased">Cliente NÃO fez compra</option>';
            html += '<option value="cart_value">Valor do carrinho</option>';
            html += '<option value="order_count">Quantidade de pedidos</option>';
            html += '<option value="cashback_balance">Saldo de cashback</option>';
            html += '<option value="user_level">Nível VIP do cliente</option>';
            html += '<option value="product_category">Comprou de categoria específica</option>';
            html += '</select>';
            html += '</div>';
            
            // Opened Email
            html += '<div class="pcw-condition-config pcw-condition-opened_email">';
            html += '<div class="pcw-form-group">';
            html += '<label>Período de Verificação</label>';
            html += '<div class="pcw-inline-form">';
            html += '<span>Nos últimos</span> ';
            html += '<input type="number" name="steps[' + index + '][config][email_period_value]" value="24" min="1" style="width: 80px;"> ';
            html += '<select name="steps[' + index + '][config][email_period_unit]" style="width: 100px;">';
            html += '<option value="hours" selected>horas</option>';
            html += '<option value="days">dias</option>';
            html += '</select>';
            html += '</div>';
            html += '<p class="description">Verifica se o cliente abriu o email enviado na etapa anterior</p>';
            html += '</div>';
            html += '</div>';
            
            // Clicked Email
            html += '<div class="pcw-condition-config pcw-condition-clicked_email" style="display: none;">';
            html += '<div class="pcw-form-group">';
            html += '<label>Período de Verificação</label>';
            html += '<div class="pcw-inline-form">';
            html += '<span>Nos últimos</span> ';
            html += '<input type="number" name="steps[' + index + '][config][click_period_value]" value="24" min="1" style="width: 80px;"> ';
            html += '<select name="steps[' + index + '][config][click_period_unit]" style="width: 100px;">';
            html += '<option value="hours" selected>horas</option>';
            html += '<option value="days">dias</option>';
            html += '</select>';
            html += '</div>';
            html += '<p class="description">Verifica se o cliente clicou em algum link do email anterior</p>';
            html += '</div>';
            html += '</div>';
            
            // Made Purchase
            html += '<div class="pcw-condition-config pcw-condition-made_purchase" style="display: none;">';
            html += '<div class="pcw-form-group">';
            html += '<label>Período de Verificação</label>';
            html += '<div class="pcw-inline-form">';
            html += '<span>Nos últimos</span> ';
            html += '<input type="number" name="steps[' + index + '][config][purchase_period_value]" value="7" min="1" style="width: 80px;"> ';
            html += '<select name="steps[' + index + '][config][purchase_period_unit]" style="width: 100px;">';
            html += '<option value="hours">horas</option>';
            html += '<option value="days" selected>dias</option>';
            html += '</select>';
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-form-group">';
            html += '<label>Valor Mínimo (opcional)</label>';
            html += '<input type="number" name="steps[' + index + '][config][min_order_value]" min="0" step="0.01" placeholder="0.00" style="width: 150px;">';
            html += '<p class="description">Deixe vazio para considerar qualquer valor</p>';
            html += '</div>';
            html += '</div>';
            
            // Not Purchased
            html += '<div class="pcw-condition-config pcw-condition-not_purchased" style="display: none;">';
            html += '<div class="pcw-form-group">';
            html += '<label>Período de Verificação</label>';
            html += '<div class="pcw-inline-form">';
            html += '<span>Nos últimos</span> ';
            html += '<input type="number" name="steps[' + index + '][config][no_purchase_period_value]" value="30" min="1" style="width: 80px;"> ';
            html += '<select name="steps[' + index + '][config][no_purchase_period_unit]" style="width: 100px;">';
            html += '<option value="days" selected>dias</option>';
            html += '<option value="months">meses</option>';
            html += '</select>';
            html += '</div>';
            html += '<p class="description">Verifica se o cliente NÃO fez nenhuma compra neste período</p>';
            html += '</div>';
            html += '</div>';
            
            // Cart Value
            html += '<div class="pcw-condition-config pcw-condition-cart_value" style="display: none;">';
            html += '<div class="pcw-form-group">';
            html += '<label>Operador</label>';
            html += '<select name="steps[' + index + '][config][cart_operator]" class="pcw-cart-operator" data-step="' + index + '" style="width: 150px;">';
            html += '<option value="greater_than">Maior que</option>';
            html += '<option value="less_than">Menor que</option>';
            html += '<option value="equals">Igual a</option>';
            html += '<option value="between">Entre</option>';
            html += '</select>';
            html += '</div>';
            html += '<div class="pcw-form-group">';
            html += '<label>Valor (R$)</label>';
            html += '<input type="number" name="steps[' + index + '][config][cart_value]" min="0" step="0.01" placeholder="100.00" style="width: 150px;">';
            html += '</div>';
            html += '<div class="pcw-form-group pcw-cart-value-max-' + index + '" style="display: none;">';
            html += '<label>Valor Máximo (R$)</label>';
            html += '<input type="number" name="steps[' + index + '][config][cart_value_max]" min="0" step="0.01" placeholder="500.00" style="width: 150px;">';
            html += '</div>';
            html += '</div>';
            
            // Order Count
            html += '<div class="pcw-condition-config pcw-condition-order_count" style="display: none;">';
            html += '<div class="pcw-form-group">';
            html += '<label>Operador</label>';
            html += '<select name="steps[' + index + '][config][order_count_operator]" style="width: 150px;">';
            html += '<option value="greater_than">Maior que</option>';
            html += '<option value="less_than">Menor que</option>';
            html += '<option value="equals">Igual a</option>';
            html += '</select>';
            html += '</div>';
            html += '<div class="pcw-form-group">';
            html += '<label>Quantidade de Pedidos</label>';
            html += '<input type="number" name="steps[' + index + '][config][order_count_value]" value="1" min="0" style="width: 100px;">';
            html += '</div>';
            html += '<div class="pcw-form-group">';
            html += '<label>Período de Verificação (opcional)</label>';
            html += '<div class="pcw-inline-form">';
            html += '<span>Nos últimos</span> ';
            html += '<input type="number" name="steps[' + index + '][config][order_count_period_value]" placeholder="30" min="1" style="width: 80px;"> ';
            html += '<select name="steps[' + index + '][config][order_count_period_unit]" style="width: 100px;">';
            html += '<option value="days">dias</option>';
            html += '<option value="months">meses</option>';
            html += '</select>';
            html += '</div>';
            html += '<p class="description">Deixe vazio para contar todos os pedidos (desde sempre)</p>';
            html += '</div>';
            html += '</div>';
            
            // Cashback Balance
            html += '<div class="pcw-condition-config pcw-condition-cashback_balance" style="display: none;">';
            html += '<div class="pcw-form-group">';
            html += '<label>Operador</label>';
            html += '<select name="steps[' + index + '][config][cashback_operator]" style="width: 150px;">';
            html += '<option value="greater_than">Maior que</option>';
            html += '<option value="less_than">Menor que</option>';
            html += '<option value="equals">Igual a</option>';
            html += '</select>';
            html += '</div>';
            html += '<div class="pcw-form-group">';
            html += '<label>Valor (R$)</label>';
            html += '<input type="number" name="steps[' + index + '][config][cashback_value]" value="0" min="0" step="0.01" placeholder="50.00" style="width: 150px;">';
            html += '</div>';
            html += '</div>';
            
            // Info box
            html += '<div class="pcw-notice pcw-notice-info" style="margin-top: 16px;">';
            html += '<p><strong>Como funciona:</strong></p>';
            html += '<p>Se a condição for VERDADEIRA → continua para próxima etapa</p>';
            html += '<p>Se a condição for FALSA → pula as próximas etapas e encerra</p>';
            html += '</div>';
            
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-workflow-connector"></div>';
            return html;
        },

        createWebhookStep: function(index) {
            var html = '<div class="pcw-workflow-step pcw-step-webhook" data-index="' + index + '">';
            html += '<div class="pcw-step-header">';
            html += '<div class="pcw-step-icon"><span class="dashicons dashicons-admin-plugins"></span></div>';
            html += '<div class="pcw-step-summary">';
            html += '<h4>Webhook / API Externa</h4>';
            html += '<p class="description">Clique para configurar</p>';
            html += '</div>';
            html += '<div class="pcw-step-actions">';
            html += '<button type="button" class="button button-small pcw-toggle-step"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
            html += '<button type="button" class="button button-small pcw-remove-step"><span class="dashicons dashicons-trash"></span></button>';
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-step-config" style="display: none;">';
            html += '<input type="hidden" name="steps[' + index + '][type]" value="webhook">';
            
            // URL e Método
            html += '<div class="pcw-form-group">';
            html += '<label>URL do Webhook</label>';
            html += '<input type="url" name="steps[' + index + '][config][url]" class="widefat" placeholder="https://api.exemplo.com/endpoint" required>';
            html += '</div>';
            
            html += '<div class="pcw-form-group">';
            html += '<label>Método HTTP</label>';
            html += '<select name="steps[' + index + '][config][method]" class="widefat pcw-webhook-method" data-step="' + index + '">';
            html += '<option value="POST">POST</option>';
            html += '<option value="GET">GET</option>';
            html += '<option value="PUT">PUT</option>';
            html += '<option value="PATCH">PATCH</option>';
            html += '<option value="DELETE">DELETE</option>';
            html += '</select>';
            html += '</div>';
            
            // Autenticação
            html += '<div class="pcw-form-group">';
            html += '<label>Autenticação</label>';
            html += '<select name="steps[' + index + '][config][auth_type]" class="widefat pcw-webhook-auth" data-step="' + index + '">';
            html += '<option value="none">Nenhuma</option>';
            html += '<option value="bearer">Bearer Token</option>';
            html += '<option value="basic">Basic Auth</option>';
            html += '<option value="api_key">API Key (Header)</option>';
            html += '</select>';
            html += '</div>';
            
            // Campo de token (oculto por padrão)
            html += '<div class="pcw-webhook-auth-fields" id="auth-fields-' + index + '" style="display: none;">';
            
            // Bearer Token
            html += '<div class="pcw-auth-bearer" style="display: none;">';
            html += '<div class="pcw-form-group">';
            html += '<label>Bearer Token</label>';
            html += '<input type="text" name="steps[' + index + '][config][bearer_token]" class="widefat" placeholder="seu-token-aqui">';
            html += '</div>';
            html += '</div>';
            
            // Basic Auth
            html += '<div class="pcw-auth-basic" style="display: none;">';
            html += '<div class="pcw-form-group">';
            html += '<label>Usuário</label>';
            html += '<input type="text" name="steps[' + index + '][config][basic_username]" class="widefat" placeholder="username">';
            html += '</div>';
            html += '<div class="pcw-form-group">';
            html += '<label>Senha</label>';
            html += '<input type="password" name="steps[' + index + '][config][basic_password]" class="widefat" placeholder="password">';
            html += '</div>';
            html += '</div>';
            
            // API Key
            html += '<div class="pcw-auth-api_key" style="display: none;">';
            html += '<div class="pcw-form-group">';
            html += '<label>Nome do Header</label>';
            html += '<input type="text" name="steps[' + index + '][config][api_key_header]" class="widefat" placeholder="X-API-Key">';
            html += '</div>';
            html += '<div class="pcw-form-group">';
            html += '<label>Valor da API Key</label>';
            html += '<input type="text" name="steps[' + index + '][config][api_key_value]" class="widefat" placeholder="sua-api-key-aqui">';
            html += '</div>';
            html += '</div>';
            
            html += '</div>';
            
            // Headers customizados
            html += '<div class="pcw-form-group">';
            html += '<label>Headers Customizados <small>(opcional)</small></label>';
            html += '<div class="pcw-webhook-headers" id="webhook-headers-' + index + '">';
            html += '<div class="pcw-webhook-header-row">';
            html += '<input type="text" name="steps[' + index + '][config][headers][0][key]" placeholder="Content-Type" style="width: 45%;">';
            html += '<input type="text" name="steps[' + index + '][config][headers][0][value]" placeholder="application/json" style="width: 45%;">';
            html += '<button type="button" class="button button-small pcw-remove-header" style="display: none;"><span class="dashicons dashicons-trash"></span></button>';
            html += '</div>';
            html += '</div>';
            html += '<button type="button" class="button button-small pcw-add-header" data-step="' + index + '" style="margin-top: 8px;">';
            html += '<span class="dashicons dashicons-plus-alt"></span> Adicionar Header';
            html += '</button>';
            html += '</div>';
            
            // Body/Payload (apenas para POST, PUT, PATCH)
            html += '<div class="pcw-webhook-body-section" id="webhook-body-' + index + '">';
            html += '<div class="pcw-form-group">';
            html += '<label>Formato do Body</label>';
            html += '<select name="steps[' + index + '][config][body_format]" class="widefat">';
            html += '<option value="json">JSON</option>';
            html += '<option value="form">Form Data</option>';
            html += '<option value="raw">Raw (texto)</option>';
            html += '</select>';
            html += '</div>';
            
            html += '<div class="pcw-form-group">';
            html += '<label>Parâmetros / Body</label>';
            html += '<textarea name="steps[' + index + '][config][body]" class="widefat pcw-webhook-body" rows="8" placeholder=\'{\n  "customer_name": "{{customer_name}}",\n  "customer_email": "{{customer_email}}",\n  "event": "automation_triggered"\n}\'></textarea>';
            html += '<p class="description">Use variáveis como <code>{{customer_name}}</code>, <code>{{customer_email}}</code>, <code>{{product_name}}</code>, etc.</p>';
            html += '</div>';
            html += '</div>';
            
            // Botão de teste
            html += '<div class="pcw-form-group">';
            html += '<button type="button" class="button pcw-test-webhook" data-step="' + index + '">';
            html += '<span class="dashicons dashicons-cloud"></span> Testar Conexão';
            html += '</button>';
            html += '<div class="pcw-webhook-test-result" id="webhook-test-' + index + '" style="margin-top: 10px;"></div>';
            html += '</div>';
            
            // Variáveis
            html += '<div class="pcw-step-variables">';
            html += '<p class="description"><strong>Variáveis disponíveis:</strong><br>';
            html += '<code>{{customer_name}}</code>, <code>{{customer_first_name}}</code>, <code>{{customer_email}}</code>, ';
            html += '<code>{{customer_phone}}</code>, <code>{{order_id}}</code>, <code>{{order_total}}</code>, ';
            html += '<code>{{product_name}}</code>, <code>{{product_id}}</code>, <code>{{cashback_balance}}</code>, ';
            html += '<code>{{user_level}}</code>, <code>{{site_name}}</code>, <code>{{site_url}}</code>';
            html += '</p>';
            html += '</div>';
            
            html += '</div>';
            html += '</div>';
            html += '<div class="pcw-workflow-connector"></div>';
            return html;
        },

        removeWorkflowStep: function(e) {
            e.preventDefault();
            var $step = $(e.currentTarget).closest('.pcw-workflow-step');
            var $connector = $step.next('.pcw-workflow-connector');
            $step.remove();
            $connector.remove();
        },

        generateAISubject: function(e) {
            e.preventDefault();
            var type = $('#pcw-automation-form input[name="type"]').val() || new URLSearchParams(window.location.search).get('type') || '';
            var context = $('#automation_description').val();

            var $modal = this.createAISubjectModal();
            $modal.data('ai-type', type).data('ai-context', context);
            this.loadAISubjectSuggestions($modal, type, context);
        },

        createAISubjectModal: function() {
            var modalHtml = '<div class="pcw-ai-subject-modal">' +
                '<div class="pcw-ai-modal-loading" style="text-align:center; padding:40px;">' +
                    '<span class="spinner is-active" style="float:none; margin:0 auto;"></span>' +
                    '<p style="margin-top:20px;">Gerando 3 sugestões de assunto...</p>' +
                '</div>' +
                '<div class="pcw-ai-suggestions" style="display:none;">' +
                    '<p class="description" style="margin-bottom:15px;">Escolha uma das sugestões abaixo ou clique em "Gerar Mais" para novas opções:</p>' +
                    '<div class="pcw-suggestions-list"></div>' +
                    '<div style="text-align:center; margin-top:20px;">' +
                        '<button type="button" class="button pcw-generate-more-subjects">' +
                            '<span class="dashicons dashicons-update"></span> Gerar Mais Sugestões' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

            var $modal = $(modalHtml);
            $('body').append($modal);
            this.openDialog($modal, { title: 'Sugestões de Assunto com IA', width: 700 });

            $modal.on('click', '.pcw-use-suggestion', function() {
                var subject = $(this).closest('.pcw-suggestion-item').data('subject');
                var stepIndex = $modal.data('step-index');

                if (stepIndex !== undefined) {
                    $('#step-email-subject-' + stepIndex).val(subject).focus();
                } else {
                    $('#email_subject').val(subject).focus();
                }
                $modal.dialog('close');
            });

            $modal.on('click', '.pcw-generate-more-subjects', function() {
                var type = $modal.data('ai-type');
                var context = $modal.data('ai-context');
                if (type) {
                    $modal.find('.pcw-ai-suggestions').hide();
                    $modal.find('.pcw-ai-modal-loading').show();
                    PCWAdminAutomations.loadAISubjectSuggestions($modal, type, context || '');
                }
            });

            return $modal;
        },

        generateAIContent: function(e) {
            e.preventDefault();
            var type = $('#pcw-automation-form input[name="type"]').val() || new URLSearchParams(window.location.search).get('type') || '';
            var context = $('#automation_description').val();

            var $modal = this.createAIEmailModal();
            var $modalContent = $modal.find('.pcw-ai-modal-preview');
            var $modalLoading = $modal.find('.pcw-ai-modal-loading');
            var $modalActions = $modal.find('.pcw-ai-modal-actions');
            $modalContent.hide();
            $modalActions.hide();
            $modalLoading.show();

            // Gerar conteúdo
            $.ajax({
                url: pcwAutomations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_generate_ai_content',
                    nonce: pcwAutomations.nonce,
                    type: type,
                    context: context
                },
                success: function(response) {
                    if (response.success) {
                        // Armazenar conteúdo gerado
                        var content = response.data.content || '';
                        $modal.data('generated-content', content);
                        
                        // Mostrar preview - usar data URI para evitar problemas de encoding
                        var encodedContent = 'data:text/html;charset=utf-8,' + encodeURIComponent(content);
                        $modalContent.find('iframe').attr('src', encodedContent);
                        $modalLoading.hide();
                        $modalContent.show();
                        $modalActions.show();
                    } else {
                        alert(response.data.message || pcwAutomations.i18n.aiError);
                        $modal.dialog('close');
                    }
                },
                error: function() {
                    alert(pcwAutomations.i18n.aiError);
                    $modal.dialog('close');
                }
            });
        },

        createAIEmailModal: function() {
            var modalHtml = '<div class="pcw-ai-email-modal">' +
                '<div class="pcw-ai-modal-loading" style="text-align:center; padding:40px;">' +
                    '<span class="spinner is-active" style="float:none; margin:0 auto;"></span>' +
                    '<p style="margin-top:20px; font-size:16px; color:#64748b;">Gerando email personalizado com IA...</p>' +
                    '<p style="font-size:13px; color:#94a3b8;">Isso pode levar alguns segundos</p>' +
                '</div>' +
                '<div class="pcw-ai-modal-preview" style="display:none;">' +
                    '<div class="pcw-ai-success-badge" style="display:flex; align-items:center; gap:10px; padding:12px 16px; background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; margin-bottom:20px;">' +
                        '<span class="dashicons dashicons-yes-alt" style="color:#059669; font-size:20px;"></span>' +
                        '<span style="color:#065f46; font-weight:600;">Email gerado com sucesso!</span>' +
                    '</div>' +
                    '<p class="description" style="margin-bottom:15px; font-size:14px;">Preview do email gerado pela IA. Você pode usar este conteúdo, editar ou regenerar:</p>' +
                    '<div class="pcw-ai-preview-container">' +
                        '<iframe class="pcw-email-preview-frame"></iframe>' +
                    '</div>' +
                '</div>' +
                '<div class="pcw-ai-modal-actions" style="display:none; margin-top: 20px; text-align:center;">' +
                    '<button type="button" class="button button-primary button-large pcw-ai-use-content" style="margin-right:10px;">' +
                        '<span class="dashicons dashicons-yes"></span> Usar Este Email' +
                    '</button>' +
                    '<button type="button" class="button button-large pcw-ai-regenerate">' +
                        '<span class="dashicons dashicons-update"></span> Regenerar' +
                    '</button>' +
                '</div>' +
            '</div>';

            var $modal = $(modalHtml);
            $('body').append($modal);
            this.openDialog($modal, { title: 'Gerar Email com IA', width: 850 });

            $modal.on('click', '.pcw-ai-use-content', function() {
                var content = $modal.data('generated-content');
                var stepIndex = $modal.data('step-index');
                
                if (stepIndex !== undefined) {
                    // Inserir no campo da etapa
                    $('#step-email-content-' + stepIndex).val(content);
                    
                    // Atualizar ou criar preview
                    var $preview = $('#step-email-preview-' + stepIndex);
                    if ($preview.length === 0) {
                        $preview = $('<div class="pcw-email-preview-mini" id="step-email-preview-' + stepIndex + '">' +
                            '<p><strong>Preview:</strong></p>' +
                            '<iframe class="pcw-email-preview-frame" style="width: 100%; height: 200px; border: 1px solid #ddd; border-radius: 4px;"></iframe>' +
                            '</div>');
                        $('#step-email-content-' + stepIndex).after($preview);
                    }
                    var encodedContent = 'data:text/html;charset=utf-8,' + encodeURIComponent(content);
                    $preview.find('iframe').attr('src', encodedContent);
                } else {
                    // Inserir no template global (fallback)
                    if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email_template')) {
                        tinyMCE.get('email_template').setContent(content);
                    } else {
                        $('#email_template').val(content);
                        $('#email_template_visual').val(content);
                    }

                    // Atualizar preview se existir
                    if ($('.pcw-email-preview-frame').length) {
                        var encodedContent = 'data:text/html;charset=utf-8,' + encodeURIComponent(content);
                        $('.pcw-email-preview-frame').first().attr('src', encodedContent);
                    }
                }

                $modal.dialog('close');
            });

            $modal.on('click', '.pcw-ai-regenerate', function() {
                var stepIndex = $modal.data('step-index');
                $modal.dialog('close');
                if (stepIndex !== undefined) {
                    $('.pcw-generate-step-content[data-step="' + stepIndex + '"]').trigger('click');
                } else {
                    $('#generate-ai-content').trigger('click');
                }
            });

            return $modal;
        },

        generateAIComplete: function(e) {
            e.preventDefault();
            var type = $('#pcw-automation-form input[name="type"]').val() || new URLSearchParams(window.location.search).get('type') || '';
            var context = $('#automation_description').val();

            var $modal = this.createAICompleteModal();
            var $modalInput = $modal.find('.pcw-ai-input-section');
            var $modalContent = $modal.find('.pcw-ai-complete-preview');
            var $modalLoading = $modal.find('.pcw-ai-modal-loading');
            var $modalActions = $modal.find('.pcw-ai-modal-actions');
            var $generateBtn = $modal.find('.pcw-start-generation');

            $modalInput.show();
            $modalContent.hide();
            $modalLoading.hide();
            $modalActions.hide();

            $modal.find('#ai-context-input').val(context);

            // Quando clicar em gerar
            $generateBtn.off('click').on('click', function() {
                var customContext = $modal.find('#ai-context-input').val();
                
                $modalInput.hide();
                $modalLoading.show();

                // Gerar assunto e conteúdo em paralelo
                var subjectPromise = $.ajax({
                    url: pcwAutomations.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pcw_generate_ai_subject',
                        nonce: pcwAutomations.nonce,
                        type: type,
                        context: customContext
                    }
                });

                var contentPromise = $.ajax({
                    url: pcwAutomations.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pcw_generate_ai_content',
                        nonce: pcwAutomations.nonce,
                        type: type,
                        context: customContext
                    }
                });

                $.when(subjectPromise, contentPromise).done(function(subjectResponse, contentResponse) {
                    if (subjectResponse[0].success && contentResponse[0].success) {
                        var subject = subjectResponse[0].data.subject;
                        var content = contentResponse[0].data.content;

                        // Armazenar
                        $modal.data('generated-subject', subject);
                        $modal.data('generated-content', content);

                        // Mostrar preview
                        $modalContent.find('.pcw-subject-preview').text(subject);
                        var encodedContent = 'data:text/html;charset=utf-8,' + encodeURIComponent(content);
                        $modalContent.find('iframe').attr('src', encodedContent);
                        
                        $modalLoading.hide();
                        $modalContent.show();
                        $modalActions.show();
                    } else {
                        var errorMsg = (subjectResponse[0].data && subjectResponse[0].data.message) || 
                                     (contentResponse[0].data && contentResponse[0].data.message) || 
                                     pcwAutomations.i18n.aiError;
                        alert(errorMsg);
                        $modal.dialog('close');
                    }
                }).fail(function() {
                    alert(pcwAutomations.i18n.aiError);
                    $modal.dialog('close');
                });
            });
        },

        createAICompleteModal: function() {
            var modalHtml = '<div class="pcw-ai-complete-modal">' +
                '<div class="pcw-ai-input-section">' +
                    '<div style="background:#eff6ff; padding:20px; border-radius:8px; border:1px solid #bfdbfe; margin-bottom:20px;">' +
                        '<h3 style="margin:0 0 10px; font-size:16px; color:#1e40af;">' +
                            '<span class="dashicons dashicons-lightbulb" style="vertical-align:middle;"></span> ' +
                            'Instruções para a IA' +
                        '</h3>' +
                        '<p style="margin:0 0 15px; font-size:13px; color:#1e40af;">Descreva o que você quer no email (opcional). A IA usará o contexto da automação se deixar em branco.</p>' +
                        '<textarea id="ai-context-input" rows="4" placeholder="Ex: Incluir cupom de desconto de 10%, tom informal e amigável, destacar os benefícios do cashback..."></textarea>' +
                    '</div>' +
                    '<div style="text-align:center;">' +
                        '<button type="button" class="button button-primary button-hero pcw-start-generation" style="padding:15px 40px; font-size:16px;">' +
                            '<span class="dashicons dashicons-admin-site-alt3"></span> Gerar Email Agora' +
                        '</button>' +
                    '</div>' +
                '</div>' +
                '<div class="pcw-ai-modal-loading" style="display:none; text-align:center; padding:60px 40px;">' +
                    '<span class="spinner is-active" style="float:none; margin:0 auto; width:50px; height:50px;"></span>' +
                    '<p style="margin-top:30px; font-size:18px; color:#64748b; font-weight:600;">Gerando email completo com IA...</p>' +
                    '<p style="font-size:14px; color:#94a3b8; margin-top:10px;">Criando assunto persuasivo e conteúdo profissional</p>' +
                '</div>' +
                '<div class="pcw-ai-complete-preview" style="display:none;">' +
                    '<div class="pcw-ai-success-badge" style="display:flex; align-items:center; gap:10px; padding:15px 20px; background:#d1fae5; border:1px solid #6ee7b7; border-radius:8px; margin-bottom:25px;">' +
                        '<span class="dashicons dashicons-yes-alt" style="color:#059669; font-size:24px;"></span>' +
                        '<div>' +
                            '<div style="color:#065f46; font-weight:700; font-size:15px;">Email completo gerado!</div>' +
                            '<div style="color:#047857; font-size:13px; margin-top:3px;">Assunto e conteúdo criados pela IA</div>' +
                        '</div>' +
                    '</div>' +
                    '<div style="margin-bottom:25px;">' +
                        '<label style="display:block; margin-bottom:8px; font-weight:600; color:#374151;">📧 Assunto do Email:</label>' +
                        '<div class="pcw-subject-preview-box" style="padding:15px 20px; background:#fff; border:2px solid #e5e7eb; border-radius:6px; font-size:15px; color:#1f2937; font-weight:500;">' +
                            '<span class="pcw-subject-preview"></span>' +
                        '</div>' +
                    '</div>' +
                    '<div>' +
                        '<label style="display:block; margin-bottom:8px; font-weight:600; color:#374151;">📄 Preview do Conteúdo:</label>' +
                        '<div class="pcw-ai-preview-container">' +
                            '<iframe class="pcw-email-preview-frame"></iframe>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="pcw-ai-modal-actions" style="display:none; margin-top:20px; text-align:center;">' +
                    '<button type="button" class="button button-primary button-hero pcw-ai-use-complete" style="margin-right:10px;">' +
                        '<span class="dashicons dashicons-yes"></span> Usar Assunto + Conteúdo' +
                    '</button>' +
                    '<button type="button" class="button button-large pcw-ai-regenerate-complete">' +
                        '<span class="dashicons dashicons-update"></span> Regenerar Tudo' +
                    '</button>' +
                '</div>' +
            '</div>';

            var $modal = $(modalHtml);
            $('body').append($modal);
            this.openDialog($modal, { title: 'Gerar Email Completo com IA', width: 950 });

            $modal.on('click', '.pcw-ai-use-complete', function() {
                var subject = $modal.data('generated-subject');
                var content = $modal.data('generated-content');
                var stepIndex = $modal.data('step-index');
                
                if (stepIndex !== undefined) {
                    // Inserir na etapa do workflow
                    $('#step-email-subject-' + stepIndex).val(subject);
                    $('#step-email-content-' + stepIndex).val(content);
                    
                    // Atualizar ou criar preview
                    var $preview = $('#step-email-preview-' + stepIndex);
                    if ($preview.length === 0) {
                        $preview = $('<div class="pcw-email-preview-mini" id="step-email-preview-' + stepIndex + '">' +
                            '<p><strong>Preview:</strong></p>' +
                            '<iframe class="pcw-email-preview-frame" style="width: 100%; height: 200px; border: 1px solid #ddd; border-radius: 4px;"></iframe>' +
                            '</div>');
                        $('#step-email-content-' + stepIndex).after($preview);
                    }
                    var encodedContent = 'data:text/html;charset=utf-8,' + encodeURIComponent(content);
                    $preview.find('iframe').attr('src', encodedContent);
                } else {
                    // Inserir no template global (fallback)
                    $('#email_subject').val(subject);

                    if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email_template')) {
                        tinyMCE.get('email_template').setContent(content);
                    } else {
                        $('#email_template').val(content);
                        $('#email_template_visual').val(content);
                    }

                    if ($('.pcw-email-preview-frame').length) {
                        var encodedContent = 'data:text/html;charset=utf-8,' + encodeURIComponent(content);
                        $('.pcw-email-preview-frame').first().attr('src', encodedContent);
                    }
                }

                $modal.dialog('close');

                // Mostrar mensagem de sucesso
                var $successMsg = $('<div class="notice notice-success is-dismissible" style="margin:15px 0;">' +
                    '<p><strong>✅ Email gerado com IA aplicado com sucesso!</strong> Revise o conteúdo e salve a automação.</p>' +
                    '</div>');
                $('.pcw-automations-page h1').after($successMsg);
                setTimeout(function() {
                    $successMsg.fadeOut(function() { $(this).remove(); });
                }, 5000);
            });

            $modal.on('click', '.pcw-ai-regenerate-complete', function() {
                var stepIndex = $modal.data('step-index');
                $modal.dialog('close');
                if (stepIndex !== undefined) {
                    $('.pcw-generate-step-complete[data-step="' + stepIndex + '"]').trigger('click');
                } else {
                    $('#generate-ai-complete').trigger('click');
                }
            });

            return $modal;
        },

        // ========================================
        // AI para etapas do workflow
        // ========================================

        generateStepSubject: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var stepIndex = $btn.data('step');
            var type = $('#pcw-automation-form input[name="type"]').val() || new URLSearchParams(window.location.search).get('type') || '';
            var context = $('#automation_description').val();

            var $modal = this.createAISubjectModal();
            $modal.data('step-index', stepIndex);
            $modal.data('ai-type', type).data('ai-context', context);
            this.loadAISubjectSuggestions($modal, type, context);
        },

        generateStepContent: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var stepIndex = $btn.data('step');
            var type = $('#pcw-automation-form input[name="type"]').val() || new URLSearchParams(window.location.search).get('type') || '';
            var context = $('#automation_description').val();

            var $modal = this.createAIEmailModal();
            $modal.data('step-index', stepIndex);
            var $modalContent = $modal.find('.pcw-ai-modal-preview');
            var $modalLoading = $modal.find('.pcw-ai-modal-loading');
            var $modalActions = $modal.find('.pcw-ai-modal-actions');
            $modalContent.hide();
            $modalActions.hide();
            $modalLoading.show();

            $.ajax({
                url: pcwAutomations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_generate_ai_content',
                    nonce: pcwAutomations.nonce,
                    type: type,
                    context: context
                },
                success: function(response) {
                    if (response.success) {
                        var content = response.data.content || '';
                        $modal.data('generated-content', content);
                        var encodedContent = 'data:text/html;charset=utf-8,' + encodeURIComponent(content);
                        $modalContent.find('iframe').attr('src', encodedContent);
                        $modalLoading.hide();
                        $modalContent.show();
                        $modalActions.show();
                    } else {
                        alert(response.data.message || pcwAutomations.i18n.aiError);
                        $modal.dialog('close');
                    }
                },
                error: function() {
                    alert(pcwAutomations.i18n.aiError);
                    $modal.dialog('close');
                }
            });
        },

        generateStepComplete: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var stepIndex = $btn.data('step');
            var type = $('#pcw-automation-form input[name="type"]').val() || new URLSearchParams(window.location.search).get('type') || '';
            var context = $('#automation_description').val();

            var $modal = this.createAICompleteModal();
            $modal.data('step-index', stepIndex);
            var $modalInput = $modal.find('.pcw-ai-input-section');
            var $modalContent = $modal.find('.pcw-ai-complete-preview');
            var $modalLoading = $modal.find('.pcw-ai-modal-loading');
            var $modalActions = $modal.find('.pcw-ai-modal-actions');
            var $generateBtn = $modal.find('.pcw-start-generation');

            $modalInput.show();
            $modalContent.hide();
            $modalLoading.hide();
            $modalActions.hide();

            $modal.find('#ai-context-input').val(context);

            $generateBtn.off('click').on('click', function() {
                var customContext = $modal.find('#ai-context-input').val();
                
                $modalInput.hide();
                $modalLoading.show();

                var subjectPromise = $.ajax({
                    url: pcwAutomations.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pcw_generate_ai_subject',
                        nonce: pcwAutomations.nonce,
                        type: type,
                        context: customContext
                    }
                });

                var contentPromise = $.ajax({
                    url: pcwAutomations.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pcw_generate_ai_content',
                        nonce: pcwAutomations.nonce,
                        type: type,
                        context: customContext
                    }
                });

                $.when(subjectPromise, contentPromise).done(function(subjectResponse, contentResponse) {
                    if (subjectResponse[0].success && contentResponse[0].success) {
                        var subject = subjectResponse[0].data.subject;
                        var content = contentResponse[0].data.content;

                        $modal.data('generated-subject', subject);
                        $modal.data('generated-content', content);

                        $modalContent.find('.pcw-subject-preview').text(subject);
                        var encodedContent = 'data:text/html;charset=utf-8,' + encodeURIComponent(content);
                        $modalContent.find('iframe').attr('src', encodedContent);
                        
                        $modalLoading.hide();
                        $modalContent.show();
                        $modalActions.show();
                    } else {
                        var errorMsg = (subjectResponse[0].data && subjectResponse[0].data.message) || 
                                     (contentResponse[0].data && contentResponse[0].data.message) || 
                                     pcwAutomations.i18n.aiError;
                        alert(errorMsg);
                        $modal.dialog('close');
                    }
                }).fail(function() {
                    alert(pcwAutomations.i18n.aiError);
                    $modal.dialog('close');
                });
            });
        },

        toggleStepConfig: function(e) {
            e.stopPropagation();
            var $step = $(e.currentTarget).closest('.pcw-workflow-step');
            var $config = $step.find('.pcw-step-config');
            var $icon = $(e.currentTarget).find('.dashicons');
            
            $config.slideToggle(300, function() {
                $step.toggleClass('expanded', $config.is(':visible'));
            });
            $icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        },

        toggleStepConfigHeader: function(e) {
            // Não toggle se clicar em botões ou inputs
            if ($(e.target).closest('button, input, select, textarea, .pcw-step-actions').length) {
                return;
            }

            var $step = $(e.currentTarget).closest('.pcw-workflow-step');
            var $config = $step.find('.pcw-step-config');
            var $icon = $step.find('.pcw-toggle-step .dashicons');
            
            $config.slideToggle(300, function() {
                $step.toggleClass('expanded', $config.is(':visible'));
            });
            $icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        },

        openStepEmailEditor: function(e) {
            e.preventDefault();
            var stepIndex = $(e.currentTarget).data('step');
            var textareaId = 'step-email-content-' + stepIndex;
            var $textarea = $('#' + textareaId);
            
            // Criar um editor visual temporário para esta etapa
            if (typeof PCWEmailEditor !== 'undefined') {
                // A função open espera o ID do campo (string), não o elemento jQuery
                PCWEmailEditor.open(textareaId, {
                    title: 'Configurar Email - Etapa ' + (parseInt(stepIndex) + 1),
                    onSave: function(content) {
                        $textarea.val(content);
                        
                        // Atualizar preview
                        var $preview = $textarea.siblings('.pcw-email-preview-mini');
                        if ($preview.length === 0) {
                            $preview = $('<div class="pcw-email-preview-mini"><p><strong>Preview:</strong></p><iframe class="pcw-email-preview-frame"></iframe></div>');
                            $textarea.after($preview);
                        }
                        var encodedContent = 'data:text/html;charset=utf-8,' + encodeURIComponent(content);
                        $preview.find('.pcw-email-preview-frame').attr('src', encodedContent);
                        $preview.show();
                    }
                });
            } else {
                // Fallback: mostrar textarea
                $textarea.show().css('display', 'block');
                alert('Editor visual não disponível. Use o campo de texto abaixo.');
            }
        },

        updateCharCounter: function(e) {
            var $textarea = $(e.currentTarget);
            var current = $textarea.val().length;
            var max = $textarea.attr('maxlength');
            var $counter = $textarea.siblings('.description').find('.pcw-char-count');
            
            if ($counter.length) {
                $counter.text(current + '/' + max);
                
                if (current > max * 0.9) {
                    $counter.css('color', '#ef4444');
                } else if (current > max * 0.7) {
                    $counter.css('color', '#f59e0b');
                } else {
                    $counter.css('color', '#3b82f6');
                }
            }
        },

        selectMedia: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var $input = $btn.siblings('.pcw-media-input');
            
            // WordPress Media Library
            var mediaUploader = wp.media({
                title: 'Selecionar Mídia',
                button: {
                    text: 'Usar esta mídia'
                },
                multiple: false
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $input.val(attachment.url);
            });
            
            mediaUploader.open();
        },

        handlePersoniziToggle: function(e) {
            var $checkbox = $(e.currentTarget);
            var stepIndex = $checkbox.data('step');
            var $step = $checkbox.closest('.pcw-workflow-step');
            var isChecked = $checkbox.is(':checked');
            
            // Mostrar/esconder config do Personizi
            $('.pcw-personizi-config-' + stepIndex).toggle(isChecked);
            
            // Mostrar/esconder campos de mídia e botões (não suportados pelo Personizi)
            $step.find('.pcw-whatsapp-media, .pcw-whatsapp-buttons').toggle(!isChecked);
        },

        handleSpecificNumberToggle: function(e) {
            var $checkbox = $(e.currentTarget);
            var stepIndex = $checkbox.data('step');
            var isChecked = $checkbox.is(':checked');

            $('.pcw-personizi-specific-' + stepIndex).toggle(isChecked);
        },

        handleTemplateToggle: function(e) {
            var $checkbox = $(e.currentTarget);
            var stepIndex = $checkbox.data('step');
            var isChecked = $checkbox.is(':checked');

            $('.pcw-template-config-' + stepIndex).toggle(isChecked);
            $('.pcw-message-group-' + stepIndex).toggle(!isChecked);
        },

        handleTemplateAccountChange: function(e) {
            var $select = $(e.currentTarget);
            var stepIndex = $select.data('step');
            var phone = $select.val();
            var $tplSelect = $select.closest('.pcw-template-config').find('.pcw-template-select');

            $tplSelect.html('<option value="">Carregando templates...</option>');
            $('.pcw-template-params-' + stepIndex).hide();
            $('.pcw-template-preview-' + stepIndex).hide();

            if (!phone) {
                $tplSelect.html('<option value="">Selecione a conta primeiro</option>');
                return;
            }

            $.post(pcwAutomations.ajaxUrl || ajaxurl, {
                action: 'pcw_get_templates',
                nonce: pcwAutomations.queueNonce || '',
                phone: phone
            }, function(response) {
                if (response.success && response.data.templates && response.data.templates.length > 0) {
                    var html = '<option value="">Selecione o template</option>';
                    response.data.templates.forEach(function(t) {
                        var status = t.status === 'APPROVED' ? ' [Aprovado]' : ' [' + (t.status || '?') + ']';
                        html += '<option value="' + $('<span>').text(t.name).html() + '"';
                        html += ' data-body="' + $('<span>').text(t.body_text || t.body || '').html() + '"';
                        html += ' data-language="' + $('<span>').text(t.language || 'pt_BR').html() + '"';
                        html += ' data-category="' + $('<span>').text(t.category || '').html() + '"';
                        html += ' data-header="' + $('<span>').text(t.header_text || '').html() + '"';
                        html += ' data-footer="' + $('<span>').text(t.footer_text || '').html() + '"';
                        html += ' data-buttons="' + $('<span>').text(JSON.stringify(t.buttons || [])).html() + '"';
                        html += '>' + $('<span>').text(t.name + status).html() + '</option>';
                    });
                    $tplSelect.html(html);
                } else {
                    var errMsg = 'Nenhum template encontrado';
                    if (!response.success && response.data && response.data.message) {
                        errMsg = response.data.message;
                    }
                    $tplSelect.html('<option value="">' + $('<span>').text(errMsg).html() + '</option>');
                }
            }).fail(function(xhr) {
                var errText = 'Erro ao carregar templates';
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.data && r.data.message) errText = r.data.message;
                    else if (r.message) errText = r.message;
                } catch(e) {}
                $tplSelect.html('<option value="">' + $('<span>').text(errText).html() + '</option>');
            });
        },

        handleTemplateSelection: function(e) {
            var $select = $(e.currentTarget);
            var stepIndex = $select.data('step');
            var $option = $select.find('option:selected');
            var body = $option.data('body') || '';
            var language = $option.data('language') || 'pt_BR';
            var headerText = $option.data('header') || '';
            var footerText = $option.data('footer') || '';
            var buttonsRaw = $option.data('buttons') || '[]';
            var buttons = [];
            try { buttons = typeof buttonsRaw === 'string' ? JSON.parse(buttonsRaw) : buttonsRaw; } catch(ex) {}
            if (!Array.isArray(buttons)) buttons = [];
            var $config = $select.closest('.pcw-template-config');

            $config.find('.pcw-template-language').val(language);
            $config.find('.pcw-template-body-text').val(body);
            $config.find('.pcw-template-header-text').val(headerText);
            $config.find('.pcw-template-footer-text').val(footerText);
            $config.find('.pcw-template-buttons').val(JSON.stringify(buttons));

            var $paramsContainer = $('.pcw-template-params-' + stepIndex);
            var $preview = $('.pcw-template-preview-' + stepIndex);

            if (!body) {
                $paramsContainer.hide();
                $preview.hide();
                return;
            }

            var varMatches = body.match(/\{\{(\d+)\}\}/g) || [];
            var varCount = varMatches.length;

            if (varCount > 0) {
                var existingParams = [];
                $paramsContainer.find('.pcw-tpl-param').each(function() {
                    existingParams.push($(this).val());
                });

                var fieldsHtml = '';
                for (var i = 1; i <= varCount; i++) {
                    var savedVal = (existingParams.length >= i) ? existingParams[i - 1] : '';
                    fieldsHtml += '<div style="margin-bottom: 8px;">';
                    fieldsHtml += '<label style="font-size: 12px; font-weight: 500;">Variável {{' + i + '}}</label>';
                    fieldsHtml += '<input type="text" name="steps[' + stepIndex + '][config][template_params][]" ';
                    fieldsHtml += 'class="widefat pcw-tpl-param" data-step="' + stepIndex + '" ';
                    fieldsHtml += 'placeholder="Ex: {{customer_first_name}}" value="' + $('<span>').text(savedVal).html() + '">';
                    fieldsHtml += '</div>';
                }
                $paramsContainer.find('.pcw-template-params-fields').html(fieldsHtml);
                $paramsContainer.show();
            } else {
                $paramsContainer.hide();
            }

            // Preview completo com header/footer/buttons
            var previewHtml = '';
            if (headerText) previewHtml += '📋 <strong>' + $('<span>').text(headerText).html() + '</strong>\n\n';
            previewHtml += $('<span>').text(body).html();
            if (footerText) previewHtml += '\n\n<em style="color: #94a3b8;">' + $('<span>').text(footerText).html() + '</em>';
            if (buttons.length > 0) {
                previewHtml += '\n';
                buttons.forEach(function(btn) {
                    var txt = btn.text || btn.label || '';
                    if (txt) previewHtml += '\n🔘 ' + $('<span>').text(txt).html();
                });
            }
            $preview.find('div').html(previewHtml.replace(/\n/g, '<br>'));
            $preview.show();
        },

        generateWhatsAppAI: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var stepIndex = $btn.data('step');
            var $textarea = $('#step-whatsapp-message-' + stepIndex);
            var originalText = $btn.html();

            // Pegar trigger: primeiro tenta o input hidden no form da automação,
            // depois tenta via URL como fallback
            var trigger = $('#pcw-automation-form input[name="type"]').val();
            if (!trigger) {
                var urlParams = new URLSearchParams(window.location.search);
                trigger = urlParams.get('type') || '';
            }
            var automationName = $('#automation_name').val() || 'Nova Automação';

            if (!trigger) {
                alert('Selecione o trigger da automação primeiro.');
                return;
            }

            // Desabilitar botão
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Gerando...');

            $.ajax({
                url: pcwAutomations.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'pcw_generate_whatsapp_ai',
                    nonce: pcwAutomations.nonce,
                    trigger: trigger,
                    automation_name: automationName
                },
                success: function(response) {
                    if (response.success) {
                        // Inserir mensagem gerada
                        $textarea.val(response.data.message);
                        
                        // Feedback visual
                        $textarea.addClass('pcw-ai-success').focus();
                        setTimeout(function() {
                            $textarea.removeClass('pcw-ai-success');
                        }, 2000);

                        // Mostrar notificação de sucesso
                        var $notification = $('<div class="pcw-ai-notification">✨ Mensagem gerada com IA!</div>');
                        $btn.after($notification);
                        setTimeout(function() {
                            $notification.fadeOut(function() {
                                $(this).remove();
                            });
                        }, 3000);
                    } else {
                        alert('Erro ao gerar mensagem: ' + (response.data.message || 'Erro desconhecido'));
                    }
                },
                error: function() {
                    alert('Erro de conexão ao gerar mensagem');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        handlePersoniziStrategyChange: function(e) {
            var $select = $(e.currentTarget);
            var stepIndex = $select.data('step');
            var strategy = $select.val();
            
            // Mostrar/esconder seletor de conta específica
            $('.pcw-personizi-account-' + stepIndex).toggle(strategy === 'specific');
            
            // Mostrar/esconder info de fila
            $('.pcw-personizi-queue-' + stepIndex).toggle(strategy === 'queue');
        },

        handleWebhookPreset: function(e) {
            var $select = $(e.currentTarget);
            var stepIndex = $select.data('step');
            var preset = $select.val();
            var $step = $select.closest('.pcw-workflow-step');
            
            console.log('Webhook preset changed:', preset, stepIndex);
            
            if (preset === 'personizi_whatsapp') {
                // Preencher campos automaticamente para Personizi
                $step.find('.pcw-webhook-url').val('https://chat.personizi.com.br/api/v1/messages/send');
                $step.find('.pcw-webhook-method').val('POST').trigger('change');
                $step.find('.pcw-webhook-auth').val('bearer').trigger('change');
                
                // Aguardar o campo de token aparecer
                setTimeout(function() {
                    // Buscar token do Personizi das configurações
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'pcw_get_personizi_token',
                            nonce: pcwAutomations.nonce
                        },
                        success: function(response) {
                            if (response.success && response.data.token) {
                                $step.find('input[name*="[bearer_token]"]').val(response.data.token);
                                $step.find('input[name*="[bearer_token]"]').prop('readonly', true);
                            }
                        }
                    });
                }, 100);
                
                // Preencher body padrão
                var defaultBody = JSON.stringify({
                    "to": "{{customer_phone}}",
                    "from": "{{from_number}}",
                    "message": "{{message}}",
                    "contact_name": "{{customer_name}}"
                }, null, 2);
                
                $step.find('.pcw-webhook-body').val(defaultBody);
                $step.find('select[name*="[body_format]"]').val('json');
                
                // Mostrar campos de teste Personizi, esconder genérico
                $('#webhook-test-personizi-' + stepIndex).show();
                $('#webhook-test-generic-' + stepIndex).hide();
                
            } else {
                // Limpar campos
                if (preset === '') {
                    $step.find('.pcw-webhook-url').val('').prop('readonly', false);
                    $step.find('input[name*="[bearer_token]"]').val('').prop('readonly', false);
                    $step.find('.pcw-webhook-body').val('');
                }
                
                // Mostrar teste genérico, esconder Personizi
                $('#webhook-test-personizi-' + stepIndex).hide();
                $('#webhook-test-generic-' + stepIndex).show();
            }
        },

        handleWebhookAuthChange: function(e) {
            var $select = $(e.currentTarget);
            var stepIndex = $select.data('step');
            var authType = $select.val();
            var $authFields = $('#auth-fields-' + stepIndex);
            
            // Esconder todos os campos de auth
            $authFields.find('.pcw-auth-bearer, .pcw-auth-basic, .pcw-auth-api_key').hide();
            
            if (authType !== 'none') {
                $authFields.show();
                $authFields.find('.pcw-auth-' + authType).show();
            } else {
                $authFields.hide();
            }
        },

        handleWebhookMethodChange: function(e) {
            var $select = $(e.currentTarget);
            var stepIndex = $select.data('step');
            var method = $select.val();
            var $bodySection = $('#webhook-body-' + stepIndex);
            
            // Mostrar body apenas para métodos que suportam
            if (method === 'POST' || method === 'PUT' || method === 'PATCH') {
                $bodySection.show();
            } else {
                $bodySection.hide();
            }
        },

        addWebhookHeader: function(e) {
            e.preventDefault();
            var stepIndex = $(e.currentTarget).data('step');
            var $container = $('#webhook-headers-' + stepIndex);
            var headerCount = $container.find('.pcw-webhook-header-row').length;
            
            var html = '<div class="pcw-webhook-header-row">';
            html += '<input type="text" name="steps[' + stepIndex + '][config][headers][' + headerCount + '][key]" placeholder="Header-Name" style="width: 45%;">';
            html += '<input type="text" name="steps[' + stepIndex + '][config][headers][' + headerCount + '][value]" placeholder="Valor" style="width: 45%;">';
            html += '<button type="button" class="button button-small pcw-remove-header"><span class="dashicons dashicons-trash"></span></button>';
            html += '</div>';
            
            $container.append(html);
            
            // Mostrar botões de remover se houver mais de 1
            if (headerCount > 0) {
                $container.find('.pcw-remove-header').show();
            }
        },

        removeWebhookHeader: function(e) {
            e.preventDefault();
            var $row = $(e.currentTarget).closest('.pcw-webhook-header-row');
            var $container = $row.closest('.pcw-webhook-headers');
            
            $row.remove();
            
            // Reindexar os headers
            $container.find('.pcw-webhook-header-row').each(function(index) {
                var stepIndex = $container.attr('id').replace('webhook-headers-', '');
                $(this).find('input').first().attr('name', 'steps[' + stepIndex + '][config][headers][' + index + '][key]');
                $(this).find('input').last().attr('name', 'steps[' + stepIndex + '][config][headers][' + index + '][value]');
            });
            
            // Esconder botões de remover se houver apenas 1
            if ($container.find('.pcw-webhook-header-row').length === 1) {
                $container.find('.pcw-remove-header').hide();
            }
        },

        handleConditionTypeChange: function(e) {
            var $select = $(e.currentTarget);
            var stepIndex = $select.data('step');
            var conditionType = $select.val();
            var $step = $select.closest('.pcw-workflow-step');
            
            // Esconder todas as configurações de condição
            $step.find('.pcw-condition-config').hide();
            
            // Mostrar apenas a configuração do tipo selecionado
            $step.find('.pcw-condition-' + conditionType).show();
        },

        handleCartOperatorChange: function(e) {
            var $select = $(e.currentTarget);
            var stepIndex = $select.data('step');
            var operator = $select.val();
            var $maxField = $('.pcw-cart-value-max-' + stepIndex);
            
            // Mostrar campo de valor máximo apenas para operador "between"
            if (operator === 'between') {
                $maxField.show();
            } else {
                $maxField.hide();
            }
        },

        loadPersoniziConfig: function(stepIndex) {
            var $section = $('.pcw-personizi-section-' + stepIndex);
            
            $.ajax({
                url: pcwAutomations.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'pcw_get_personizi_accounts',
                    nonce: pcwAutomations.nonce
                },
                success: function(response) {
                    if (response.success && response.data.accounts && response.data.accounts.length > 0) {
                        var html = '<div class="pcw-form-group">';
                        html += '<label class="pcw-checkbox-inline">';
                        html += '<input type="checkbox" name="steps[' + stepIndex + '][config][use_personizi]" value="1" ';
                        html += 'class="pcw-use-personizi" data-step="' + stepIndex + '" checked>';
                        html += 'Enviar via Personizi WhatsApp ';
                        html += '<span style="color: #22c55e; font-weight: 600;">✓ Configurado</span>';
                        html += '</label>';
                        html += '<p class="description">Use sua integração Personizi para enviar mensagens WhatsApp automaticamente. ';
                        html += '<a href="' + pcwAutomations.settingsUrl + '" target="_blank">Ver configurações</a></p>';
                        html += '</div>';
                        
                        html += '<div class="pcw-personizi-config pcw-personizi-config-' + stepIndex + '">';
                        
                        // Info sobre fila automática
                        var activeAccounts = response.data.accounts.filter(function(acc) {
                            return acc.status === 'active';
                        });
                        
                        html += '<div class="notice notice-info inline" style="margin: 0 0 15px;">';
                        html += '<p><strong>🔄 Sistema de Fila Automático</strong><br>';
                        html += 'Os envios serão distribuídos automaticamente entre ' + activeAccounts.length + ' conta(s) WhatsApp ativa(s) usando Round-Robin.</p>';
                        html += '<p style="margin: 8px 0 0; font-size: 13px;"><strong>Contas configuradas:</strong>';
                        activeAccounts.forEach(function(account) {
                            html += '<br>• ' + account.name + ' (' + account.phone_number + ')';
                        });
                        html += '</p>';
                        html += '<p style="margin: 8px 0 0; font-size: 12px; color: #666;">';
                        html += '<span class="dashicons dashicons-admin-settings" style="font-size: 14px; vertical-align: middle;"></span> ';
                        html += 'Para configurar números, rate limiting e estratégia: ';
                        html += '<a href="admin.php?page=pcw-queue&tab=numbers" target="_blank">Gerenciar Filas</a>';
                        html += '</p>';
                        html += '</div>';
                        
                        // Opção de forçar número específico
                        html += '<div class="pcw-form-group">';
                        html += '<label>';
                        html += '<input type="checkbox" name="steps[' + stepIndex + '][config][use_specific_number]" value="1" class="pcw-use-specific-number" data-step="' + stepIndex + '">';
                        html += ' Forçar número específico (ignorar fila)';
                        html += '</label>';
                        html += '<p class="description" style="margin-left: 24px;">Use apenas se precisar que ESTA automação sempre use um número fixo.</p>';
                        html += '</div>';
                        
                        // Seletor de número específico (oculto por padrão)
                        html += '<div class="pcw-personizi-specific-number pcw-personizi-specific-' + stepIndex + '" style="display: none;">';
                        html += '<div class="pcw-form-group">';
                        html += '<label>Número Fixo</label>';
                        html += '<select name="steps[' + stepIndex + '][config][personizi_from]" class="widefat">';
                        html += '<option value="">Selecione um número</option>';
                        
                        response.data.accounts.forEach(function(account) {
                            html += '<option value="' + account.phone_number + '">';
                            html += account.name + ' (' + account.phone_number + ')';
                            if (account.status !== 'active') {
                                html += ' [' + account.status.toUpperCase() + ']';
                            }
                            html += '</option>';
                        });
                        
                        html += '</select>';
                        html += '<p class="description">Este número será usado sempre, ignorando o sistema de fila.</p>';
                        html += '</div>';
                        html += '</div>';
                        
                        html += '</div>';

                        // Template option (only if there are official API accounts)
                        var officialAccounts = response.data.accounts.filter(function(acc) {
                            return acc.provider === 'notificame' || acc.provider === 'meta_cloud' || acc.provider === 'meta_coex';
                        });
                        if (officialAccounts.length > 0) {
                            html += '<div class="pcw-form-group" style="margin-bottom: 15px;">';
                            html += '<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 14px;">';
                            html += '<label class="pcw-checkbox-inline" style="align-items: flex-start; gap: 8px;">';
                            html += '<input type="checkbox" name="steps[' + stepIndex + '][config][use_template]" value="1" class="pcw-use-template-toggle" data-step="' + stepIndex + '" style="margin-top: 2px;">';
                            html += '<div><strong>Usar Template Aprovado (API Oficial)</strong><br>';
                            html += '<span style="font-size: 12px; color: #1e40af;">Enviar um template pré-aprovado pelo Meta.</span></div>';
                            html += '</label></div></div>';

                            html += '<div class="pcw-template-config pcw-template-config-' + stepIndex + '" style="display: none;">';
                            html += '<div class="pcw-form-group"><label>Conta (API Oficial)</label>';
                            html += '<select name="steps[' + stepIndex + '][config][template_from]" class="widefat pcw-template-account" data-step="' + stepIndex + '">';
                            html += '<option value="">Selecione a conta</option>';
                            officialAccounts.forEach(function(acc) {
                                html += '<option value="' + acc.phone_number + '">' + acc.name + ' (' + acc.phone_number + ')</option>';
                            });
                            html += '</select></div>';

                            html += '<div class="pcw-form-group"><label>Template</label>';
                            html += '<select name="steps[' + stepIndex + '][config][template_name]" class="widefat pcw-template-select" data-step="' + stepIndex + '">';
                            html += '<option value="">Selecione a conta primeiro</option>';
                            html += '</select></div>';

                            html += '<input type="hidden" name="steps[' + stepIndex + '][config][template_language]" class="pcw-template-language" data-step="' + stepIndex + '" value="pt_BR">';
                            html += '<input type="hidden" name="steps[' + stepIndex + '][config][template_body_text]" class="pcw-template-body-text" data-step="' + stepIndex + '" value="">';

                            html += '<div class="pcw-template-params-container pcw-template-params-' + stepIndex + '" style="display: none;">';
                            html += '<label>Variáveis do Template</label>';
                            html += '<div class="pcw-template-params-fields"></div>';
                            html += '<p class="description" style="font-size: 11px;">Variáveis: {{customer_first_name}}, {{customer_name}}, {{order_number}}, {{order_total}}</p>';
                            html += '</div>';

                            html += '<div class="pcw-template-preview pcw-template-preview-' + stepIndex + '" style="display: none; margin-top: 10px;">';
                            html += '<label>Preview</label>';
                            html += '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; font-size: 13px; color: #475569; white-space: pre-wrap;"></div>';
                            html += '</div>';
                            html += '</div>';
                        }

                        $section.html(html);
                    } else {
                        var html = '<div class="notice notice-warning inline" style="margin: 0 0 15px;">';
                        html += '<p><span class="dashicons dashicons-warning"></span> ';
                        html += '<strong>Personizi não configurado.</strong> ';
                        html += '<a href="' + pcwAutomations.settingsUrl + '" target="_blank">Configurar agora</a>';
                        html += '</p>';
                        html += '</div>';
                        $section.html(html);
                    }
                },
                error: function() {
                    var html = '<div class="notice notice-error inline" style="margin: 0 0 15px;">';
                    html += '<p>Erro ao carregar configuração do Personizi.</p>';
                    html += '</div>';
                    $section.html(html);
                }
            });
        },

        testWebhook: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var stepIndex = $btn.data('step');
            var originalText = $btn.html();
            var $result = $('#webhook-test-' + stepIndex);
            
            // Coletar dados do webhook
            var $step = $btn.closest('.pcw-workflow-step');
            var preset = $step.find('select[name*="[webhook_preset]"]').val();
            var url = $step.find('input[name*="[url]"]').val();
            var method = $step.find('select[name*="[method]"]').val();
            var authType = $step.find('select[name*="[auth_type]"]').val();
            var body = $step.find('textarea[name*="[body]"]').val();
            var bodyFormat = $step.find('select[name*="[body_format]"]').val();
            
            // Se for preset Personizi, validar campos específicos
            if (preset === 'personizi_whatsapp') {
                var testPhone = $step.find('.pcw-test-phone').val();
                var testMessage = $step.find('.pcw-test-message').val();
                
                if (!testPhone) {
                    $result.html('<div class="notice notice-error inline"><p>⚠️ Por favor, informe o número de teste.</p></div>');
                    return;
                }
                
                if (!testMessage) {
                    $result.html('<div class="notice notice-error inline"><p>⚠️ Por favor, informe a mensagem de teste.</p></div>');
                    return;
                }
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Enviando via Personizi...');
                $result.html('<p>📤 Enviando mensagem WhatsApp...</p>');
                
                $.ajax({
                    url: pcwAutomations.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pcw_test_webhook',
                        nonce: pcwAutomations.nonce,
                        preset: preset,
                        test_phone: testPhone,
                        test_message: testMessage
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success inline">';
                            html += '<p><strong>✅ ' + response.data.message + '</strong></p>';
                            if (response.data.data) {
                                html += '<p style="font-size: 12px; color: #666;">';
                                html += 'ID da Mensagem: ' + (response.data.data.message_id || 'N/A') + '<br>';
                                html += 'ID da Conversa: ' + (response.data.data.conversation_id || 'N/A') + '<br>';
                                html += 'Status: ' + (response.data.data.status || 'N/A');
                                html += '</p>';
                            }
                            html += '</div>';
                            $result.html(html);
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>❌ ' + (response.data.message || 'Erro ao enviar mensagem') + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error inline"><p>❌ Erro ao conectar com o Personizi.</p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
                
                return; // Não continuar com teste genérico
            }
            
            // Teste genérico de webhook
            if (!url) {
                $result.html('<div class="notice notice-error inline"><p>Por favor, preencha a URL do webhook.</p></div>');
                return;
            }
            
            // Coletar headers
            var headers = [];
            $step.find('.pcw-webhook-header-row').each(function() {
                var key = $(this).find('input').first().val();
                var value = $(this).find('input').last().val();
                if (key && value) {
                    headers.push({key: key, value: value});
                }
            });
            
            // Coletar auth
            var authData = {};
            if (authType === 'bearer') {
                authData.bearer_token = $step.find('input[name*="[bearer_token]"]').val();
            } else if (authType === 'basic') {
                authData.basic_username = $step.find('input[name*="[basic_username]"]').val();
                authData.basic_password = $step.find('input[name*="[basic_password]"]').val();
            } else if (authType === 'api_key') {
                authData.api_key_header = $step.find('input[name*="[api_key_header]"]').val();
                authData.api_key_value = $step.find('input[name*="[api_key_value]"]').val();
            }
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Testando...');
            $result.html('<p>Conectando...</p>');
            
            $.ajax({
                url: pcwAutomations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_test_webhook',
                    nonce: pcwAutomations.nonce,
                    url: url,
                    method: method,
                    auth_type: authType,
                    auth_data: authData,
                    headers: headers,
                    body: body,
                    body_format: bodyFormat
                },
                success: function(response) {
                    if (response.success) {
                        var statusClass = response.data.status >= 200 && response.data.status < 300 ? 'notice-success' : 'notice-warning';
                        var html = '<div class="notice ' + statusClass + ' inline">';
                        html += '<p><strong>Status: ' + response.data.status + '</strong></p>';
                        if (response.data.body) {
                            html += '<p>Resposta: <code>' + response.data.body.substring(0, 200) + (response.data.body.length > 200 ? '...' : '') + '</code></p>';
                        }
                        html += '</div>';
                        $result.html(html);
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + (response.data.message || 'Erro ao testar webhook') + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error inline"><p>Erro ao conectar com o webhook.</p></div>');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    };

    $(document).ready(function() {
        PCWAdminAutomations.init();

        // Inicializar contadores de caracteres
        $('textarea[maxlength="160"]').each(function() {
            var current = $(this).val().length;
            var max = $(this).attr('maxlength');
            $(this).siblings('.description').find('.pcw-char-count').text(current + '/' + max);
        });

        // Auto-carregar templates para contas já selecionadas (edição de automação)
        $('.pcw-template-account').each(function() {
            var $select = $(this);
            var phone = $select.val();
            var stepIndex = $select.data('step');
            if (!phone) return;

            var savedTemplateName = $select.closest('.pcw-template-config').find('.pcw-template-select').val();
            var $tplSelect = $select.closest('.pcw-template-config').find('.pcw-template-select');

            $.post(pcwAutomations.ajaxUrl || ajaxurl, {
                action: 'pcw_get_templates',
                nonce: pcwAutomations.queueNonce || '',
                phone: phone
            }, function(response) {
                if (response.success && response.data.templates && response.data.templates.length > 0) {
                    var html = '<option value="">Selecione o template</option>';
                    response.data.templates.forEach(function(t) {
                        var status = t.status === 'APPROVED' ? ' [Aprovado]' : ' [' + (t.status || '?') + ']';
                        var isSelected = (savedTemplateName && t.name === savedTemplateName) ? ' selected' : '';
                        html += '<option value="' + $('<span>').text(t.name).html() + '"';
                        html += ' data-body="' + $('<span>').text(t.body_text || t.body || '').html() + '"';
                        html += ' data-language="' + $('<span>').text(t.language || 'pt_BR').html() + '"';
                        html += ' data-category="' + $('<span>').text(t.category || '').html() + '"';
                        html += ' data-header="' + $('<span>').text(t.header_text || '').html() + '"';
                        html += ' data-footer="' + $('<span>').text(t.footer_text || '').html() + '"';
                        html += ' data-buttons="' + $('<span>').text(JSON.stringify(t.buttons || [])).html() + '"';
                        html += isSelected;
                        html += '>' + $('<span>').text(t.name + status).html() + '</option>';
                    });
                    $tplSelect.html(html);
                }
            });
        });
    });

})(jQuery);
