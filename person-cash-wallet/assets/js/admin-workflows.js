/**
 * Growly Digital - Admin Workflows
 */
(function($) {
    'use strict';

    var PCWWorkflows = {
        currentTrigger: '',
        currentVariables: {},
        conditionIndex: 0,
        actionIndex: 0,

        init: function() {
            this.bindEvents();
            this.initializeForm();
        },

        bindEvents: function() {
            // Trigger change
            $('#trigger_type').on('change', this.handleTriggerChange.bind(this));

            // Add condition
            $('#add_condition').on('click', this.addCondition.bind(this));

            // Add action
            $('#add_action').on('click', this.addAction.bind(this));

            // Remove condition
            $(document).on('click', '.pcw-remove-condition', this.removeCondition.bind(this));

            // Remove action
            $(document).on('click', '.pcw-remove-action', this.removeAction.bind(this));

            // Action type change
            $(document).on('change', '.pcw-action-type-select select', this.handleActionTypeChange.bind(this));

            // Add payload field
            $(document).on('click', '.pcw-add-payload-field', this.addPayloadField.bind(this));

            // Remove payload field
            $(document).on('click', '.pcw-remove-payload-field', this.removePayloadField.bind(this));

            // Add header field
            $(document).on('click', '.pcw-add-header-field', this.addHeaderField.bind(this));

            // Remove header field
            $(document).on('click', '.pcw-remove-header-field', this.removeHeaderField.bind(this));

            // Variable dropdown
            $(document).on('click', '.pcw-variable-dropdown', this.toggleVariablePopup.bind(this));
            $(document).on('click', '.pcw-variable-item', this.insertVariable.bind(this));

            // Close popups on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.pcw-field-value').length) {
                    $('.pcw-variables-popup').removeClass('active');
                }
            });

            // Form submit
            $('#pcw-workflow-form').on('submit', this.handleSubmit.bind(this));

            // Delete workflow
            $(document).on('click', '.pcw-delete-workflow', this.handleDelete.bind(this));

            // Toggle workflow status
            $(document).on('change', '.pcw-workflow-toggle', this.handleToggle.bind(this));

            // Test webhook
            $(document).on('click', '.pcw-test-webhook-btn', this.handleTestWebhook.bind(this));

            // Clicar em variável para inserir
            $(document).on('click', '.pcw-var-click', this.insertVariableFromClick.bind(this));

            // Gerar mensagem com IA
            $(document).on('click', '.pcw-generate-ai-message', this.handleGenerateAIMessage.bind(this));

            // Mostrar mais variáveis
            $(document).on('click', '.pcw-show-more-vars', this.showMoreVariables.bind(this));

            // Executar teste do workflow
            $(document).on('click', '#pcw-test-workflow', this.openTestModal.bind(this));
            $(document).on('click', '.pcw-close-test-modal, .pcw-modal-overlay', this.closeTestModal.bind(this));
            $(document).on('click', '#pcw-run-test', this.runWorkflowTest.bind(this));

            // Make actions sortable
            this.initSortable();
        },

        initializeForm: function() {
            // Initialize trigger if already selected
            var selectedTrigger = $('#trigger_type').val();
            if (selectedTrigger) {
                this.handleTriggerChange();
            }

            // Load initial conditions
            if (typeof pcwInitialConditions !== 'undefined' && pcwInitialConditions.length) {
                for (var i = 0; i < pcwInitialConditions.length; i++) {
                    this.addCondition(null, pcwInitialConditions[i]);
                }
            }

            // Load initial actions
            if (typeof pcwInitialActions !== 'undefined' && pcwInitialActions.length) {
                for (var j = 0; j < pcwInitialActions.length; j++) {
                    this.addAction(null, pcwInitialActions[j]);
                }
            }
        },

        initSortable: function() {
            if ($('#actions_container').length) {
                $('#actions_container').sortable({
                    handle: '.pcw-action-header',
                    placeholder: 'ui-sortable-placeholder',
                    tolerance: 'pointer'
                });
            }
        },

        handleTriggerChange: function() {
            var triggerType = $('#trigger_type').val();
            this.currentTrigger = triggerType;

            if (!triggerType) {
                $('#trigger_description').text('');
                $('#trigger_config_container').hide();
                return;
            }

            var trigger = pcwWorkflows.triggersList[triggerType];
            if (!trigger) return;

            // Update description
            $('#trigger_description').text(trigger.description || '');

            // Store variables
            this.currentVariables = trigger.variables || {};

            // Show config fields if any
            if (trigger.config_fields && Object.keys(trigger.config_fields).length > 0) {
                this.renderTriggerConfigFields(trigger.config_fields);
                $('#trigger_config_container').show();
            } else {
                $('#trigger_config_container').hide();
            }
        },

        renderTriggerConfigFields: function(fields) {
            var html = '';
            var existingConfig = {};
            
            // Try to get existing values from workflow
            if (typeof pcwWorkflowData !== 'undefined' && pcwWorkflowData.trigger_config) {
                existingConfig = pcwWorkflowData.trigger_config;
            }

            for (var fieldId in fields) {
                var field = fields[fieldId];
                var existingValue = existingConfig[fieldId] !== undefined ? existingConfig[fieldId] : (field.default || '');
                
                html += '<div class="pcw-form-group">';
                html += '<label>' + field.label + '</label>';

                if (field.type === 'select') {
                    html += '<select name="trigger_config[' + fieldId + ']" id="trigger_config_' + fieldId + '">';
                    html += '<option value="">' + (pcwWorkflows.i18n.selectOption || '-- Selecione --') + '</option>';
                    
                    // Get options
                    var options = {};
                    if (field.options === 'order_statuses') {
                        options = pcwWorkflows.orderStatuses;
                    } else if (field.options === 'levels') {
                        options = pcwWorkflows.levels;
                    } else if (typeof field.options === 'object') {
                        options = field.options;
                    }

                    for (var optVal in options) {
                        var selected = optVal === existingValue ? ' selected' : '';
                        html += '<option value="' + optVal + '"' + selected + '>' + options[optVal] + '</option>';
                    }

                    html += '</select>';
                } else if (field.type === 'number') {
                    html += '<input type="number" name="trigger_config[' + fieldId + ']" id="trigger_config_' + fieldId + '" value="' + existingValue + '" min="0" style="width: 100px;">';
                } else if (field.type === 'checkbox') {
                    var checked = existingValue ? ' checked' : '';
                    html += '<label class="pcw-checkbox-label" style="font-weight: normal;">';
                    html += '<input type="checkbox" name="trigger_config[' + fieldId + ']" id="trigger_config_' + fieldId + '" value="1"' + checked + '>';
                    html += '</label>';
                } else {
                    html += '<input type="text" name="trigger_config[' + fieldId + ']" id="trigger_config_' + fieldId + '" value="' + existingValue + '">';
                }

                if (field.description) {
                    html += '<p class="description">' + field.description + '</p>';
                }

                html += '</div>';
            }

            $('#trigger_config_fields').html(html);
        },

        addCondition: function(e, data) {
            if (e) e.preventDefault();

            var index = this.conditionIndex++;
            var conditions = pcwWorkflows.conditions;

            var html = '<div class="pcw-condition-row" data-index="' + index + '">';
            html += '<select class="pcw-condition-type" name="conditions[rules][' + index + '][type]">';
            html += '<option value="">' + pcwWorkflows.i18n.selectCondition + '</option>';
            
            for (var condId in conditions) {
                var selected = data && data.type === condId ? ' selected' : '';
                html += '<option value="' + condId + '"' + selected + '>' + conditions[condId].name + '</option>';
            }
            html += '</select>';

            html += '<select class="pcw-condition-operator" name="conditions[rules][' + index + '][operator]">';
            for (var op in pcwWorkflows.operators) {
                var opSelected = data && data.operator === op ? ' selected' : '';
                html += '<option value="' + op + '"' + opSelected + '>' + pcwWorkflows.operators[op] + '</option>';
            }
            html += '</select>';

            var value = data && data.value ? data.value : '';
            html += '<input type="text" class="pcw-condition-value" name="conditions[rules][' + index + '][value]" value="' + value + '" placeholder="Valor">';

            html += '<button type="button" class="pcw-remove-btn pcw-remove-condition"><span class="dashicons dashicons-no-alt"></span></button>';
            html += '</div>';

            $('#conditions_container').append(html);
        },

        removeCondition: function(e) {
            $(e.currentTarget).closest('.pcw-condition-row').remove();
        },

        addAction: function(e, data) {
            if (e) e.preventDefault();

            var index = this.actionIndex++;
            var actions = pcwWorkflows.actions;

            var html = '<div class="pcw-action-item" data-index="' + index + '">';
            html += '<div class="pcw-action-header">';
            html += '<div class="pcw-action-title">';
            html += '<span class="dashicons dashicons-menu"></span>';
            html += '<span class="action-name">' + (data && data.type ? actions[data.type].name : pcwWorkflows.i18n.selectAction) + '</span>';
            html += '</div>';
            html += '<button type="button" class="pcw-remove-btn pcw-remove-action"><span class="dashicons dashicons-no-alt"></span></button>';
            html += '</div>';

            html += '<div class="pcw-action-body">';
            html += '<div class="pcw-action-type-select">';
            html += '<label>Tipo de Ação</label>';
            html += '<select name="actions[' + index + '][type]">';
            html += '<option value="">-- Selecione --</option>';
            
            for (var actId in actions) {
                var actSelected = data && data.type === actId ? ' selected' : '';
                html += '<option value="' + actId + '"' + actSelected + '>' + actions[actId].name + '</option>';
            }
            html += '</select>';
            html += '</div>';

            html += '<div class="pcw-action-config"></div>';
            html += '</div>';
            html += '</div>';

            $('#actions_container').append(html);

            // If data provided, render config
            if (data && data.type) {
                var $item = $('#actions_container .pcw-action-item').last();
                this.renderActionConfig($item, data.type, data.config || {});
            }

            this.initSortable();
        },

        removeAction: function(e) {
            $(e.currentTarget).closest('.pcw-action-item').remove();
        },

        handleActionTypeChange: function(e) {
            var $select = $(e.currentTarget);
            var actionType = $select.val();
            var $item = $select.closest('.pcw-action-item');
            var action = pcwWorkflows.actions[actionType];

            // Update header title
            $item.find('.action-name').text(action ? action.name : pcwWorkflows.i18n.selectAction);

            // Render config fields
            if (actionType) {
                this.renderActionConfig($item, actionType, {});
            } else {
                $item.find('.pcw-action-config').empty();
            }
        },

        renderActionConfig: function($item, actionType, configData) {
            var action = pcwWorkflows.actions[actionType];
            if (!action || !action.fields) return;

            var index = $item.data('index');
            var html = '';

            for (var fieldId in action.fields) {
                var field = action.fields[fieldId];
                var value = configData[fieldId] || field.default || '';

                html += '<div class="pcw-form-group">';
                html += '<label>' + field.label + (field.required ? ' *' : '') + '</label>';

                if (field.type === 'select') {
                    html += '<select name="actions[' + index + '][config][' + fieldId + ']">';
                    for (var optVal in field.options) {
                        var sel = value === optVal ? ' selected' : '';
                        html += '<option value="' + optVal + '"' + sel + '>' + field.options[optVal] + '</option>';
                    }
                    html += '</select>';
                } else if (field.type === 'textarea') {
                    // Para campo de mensagem, adicionar variáveis e botão de IA
                    if (fieldId === 'message' && actionType === 'send_whatsapp') {
                        html += this.renderMessageFieldWithVariables(index, fieldId, value, field);
                    } else {
                        html += '<textarea name="actions[' + index + '][config][' + fieldId + ']" rows="4">' + value + '</textarea>';
                    }
                } else if (field.type === 'checkbox') {
                    var checked = value ? ' checked' : '';
                    html += '<label class="pcw-checkbox-label">';
                    html += '<input type="checkbox" name="actions[' + index + '][config][' + fieldId + ']" value="1"' + checked + '>';
                    html += ' ' + field.label;
                    html += '</label>';
                } else if (field.type === 'key_value') {
                    // Headers builder
                    html += this.renderHeadersBuilder(index, fieldId, value || []);
                } else if (field.type === 'payload_builder') {
                    // Payload builder
                    html += this.renderPayloadBuilder(index, fieldId, value || []);
                } else if (field.type === 'email_editor') {
                    // Email editor visual
                    html += this.renderEmailEditor(index, fieldId, value || '');
                } else if (field.type === 'personizi_accounts') {
                    // Select de contas Personizi
                    html += '<select name="actions[' + index + '][config][' + fieldId + ']">';
                    html += '<option value="">' + (pcwWorkflows.i18n.useDefault || 'Usar padrão configurado') + '</option>';
                    if (typeof pcwWorkflows.personiziAccounts !== 'undefined') {
                        for (var accPhone in pcwWorkflows.personiziAccounts) {
                            if (accPhone === '') continue;
                            var sel = value === accPhone ? ' selected' : '';
                            html += '<option value="' + accPhone + '"' + sel + '>' + pcwWorkflows.personiziAccounts[accPhone] + '</option>';
                        }
                    }
                    html += '</select>';
                } else {
                    html += '<input type="' + (field.type || 'text') + '" name="actions[' + index + '][config][' + fieldId + ']" value="' + value + '" placeholder="' + (field.placeholder || '') + '">';
                }

                if (field.description) {
                    html += '<p class="description">' + field.description + '</p>';
                }

                html += '</div>';
            }

            // Add test button for webhook
            if (actionType === 'webhook') {
                html += '<div class="pcw-form-group">';
                html += '<button type="button" class="button pcw-test-webhook-btn" data-action-index="' + index + '">';
                html += '<span class="dashicons dashicons-admin-links"></span> Testar Webhook';
                html += '</button>';
                html += '<div class="pcw-test-result"></div>';
                html += '</div>';
            }

            $item.find('.pcw-action-config').html(html);
        },

        renderHeadersBuilder: function(actionIndex, fieldId, headers) {
            var html = '<div class="pcw-headers-builder">';
            html += '<div class="pcw-payload-header">Headers HTTP</div>';
            html += '<div class="pcw-payload-fields" data-field="headers">';

            // Default headers
            if (!headers || headers.length === 0) {
                headers = [
                    { key: 'Authorization', value: 'Bearer SEU_TOKEN' },
                    { key: 'Content-Type', value: 'application/json' }
                ];
            }

            for (var i = 0; i < headers.length; i++) {
                html += this.renderHeaderFieldHTML(actionIndex, fieldId, i, headers[i]);
            }

            html += '</div>';
            html += '<div style="padding: 12px;">';
            html += '<button type="button" class="button pcw-add-header-field" data-action-index="' + actionIndex + '" data-field-id="' + fieldId + '">';
            html += '<span class="dashicons dashicons-plus-alt2"></span> Adicionar Header';
            html += '</button>';
            html += '</div>';
            html += '</div>';

            return html;
        },

        renderHeaderFieldHTML: function(actionIndex, fieldId, index, data) {
            var key = data && data.key ? data.key : '';
            var value = data && data.value ? data.value : '';

            var html = '<div class="pcw-header-field" data-index="' + index + '">';
            html += '<input type="text" name="actions[' + actionIndex + '][config][' + fieldId + '][' + index + '][key]" value="' + key + '" placeholder="Header Name">';
            html += '<input type="text" name="actions[' + actionIndex + '][config][' + fieldId + '][' + index + '][value]" value="' + value + '" placeholder="Valor">';
            html += '<button type="button" class="pcw-remove-btn pcw-remove-header-field"><span class="dashicons dashicons-no-alt"></span></button>';
            html += '</div>';

            return html;
        },

        renderPayloadBuilder: function(actionIndex, fieldId, fields) {
            var html = '<div class="pcw-payload-builder">';
            html += '<div class="pcw-payload-header">Payload JSON</div>';
            html += '<div class="pcw-payload-fields" data-field="body">';

            // Default fields for WhatsApp
            if (!fields || fields.length === 0) {
                fields = [
                    { key: 'to', value: '{customer_phone}' },
                    { key: 'from', value: '' },
                    { key: 'message', value: '' },
                    { key: 'contact_name', value: '{customer_name}' }
                ];
            }

            for (var i = 0; i < fields.length; i++) {
                html += this.renderPayloadFieldHTML(actionIndex, fieldId, i, fields[i]);
            }

            html += '</div>';
            html += '<div style="padding: 12px;">';
            html += '<button type="button" class="button pcw-add-payload-field" data-action-index="' + actionIndex + '" data-field-id="' + fieldId + '">';
            html += '<span class="dashicons dashicons-plus-alt2"></span> ' + pcwWorkflows.i18n.addField;
            html += '</button>';
            html += '</div>';
            html += '</div>';

            return html;
        },

        renderPayloadFieldHTML: function(actionIndex, fieldId, index, data) {
            var key = data && data.key ? data.key : '';
            var value = data && data.value ? data.value : '';

            var html = '<div class="pcw-payload-field" data-index="' + index + '">';
            html += '<input type="text" class="pcw-field-key" name="actions[' + actionIndex + '][config][' + fieldId + '][' + index + '][key]" value="' + key + '" placeholder="Campo">';
            html += '<div class="pcw-field-value">';
            html += '<input type="text" name="actions[' + actionIndex + '][config][' + fieldId + '][' + index + '][value]" value="' + value + '" placeholder="Valor ou {variavel}">';
            html += '<button type="button" class="pcw-variable-dropdown">{x}</button>';
            html += this.renderVariablesPopup();
            html += '</div>';
            html += '<button type="button" class="pcw-remove-btn pcw-remove-payload-field"><span class="dashicons dashicons-no-alt"></span></button>';
            html += '</div>';

            return html;
        },

        renderMessageFieldWithVariables: function(actionIndex, fieldId, value, field) {
            var html = '<div class="pcw-message-field-wrapper">';
            
            // Header com botão de IA
            html += '<div style="display: flex; justify-content: flex-end; margin-bottom: 8px;">';
            if (typeof pcwWorkflows.aiConfigured !== 'undefined' && pcwWorkflows.aiConfigured) {
                html += '<button type="button" class="button button-small pcw-generate-ai-message" data-action-index="' + actionIndex + '" style="height: 28px;">';
                html += '<span class="dashicons dashicons-superhero" style="font-size: 16px; margin-top: 3px;"></span> ';
                html += (pcwWorkflows.i18n.generateWithAI || 'Gerar com IA');
                html += '</button>';
            } else {
                html += '<a href="' + (pcwWorkflows.aiSettingsUrl || '#') + '" class="button button-small" style="height: 28px; color: #64748b;" target="_blank">';
                html += '<span class="dashicons dashicons-admin-generic" style="font-size: 16px; margin-top: 3px;"></span> ';
                html += (pcwWorkflows.i18n.configureAI || 'Configurar IA');
                html += '</a>';
            }
            html += '</div>';
            
            // Textarea
            html += '<textarea name="actions[' + actionIndex + '][config][' + fieldId + ']" id="message_' + actionIndex + '" rows="5" ';
            html += 'placeholder="' + (field.placeholder || '') + '">' + (value || '') + '</textarea>';
            
            // Variáveis disponíveis
            html += '<div class="pcw-variables-section" style="margin-top: 12px; padding: 12px; background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 6px;">';
            html += '<p style="margin: 0 0 10px; font-weight: 600; font-size: 13px;">' + (pcwWorkflows.i18n.availableVariables || 'Variáveis disponíveis:') + '</p>';
            html += '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; font-size: 12px;">';
            
            // Coluna 1 - Cliente
            html += '<div>';
            html += '<strong>' + (pcwWorkflows.i18n.client || 'Cliente') + ':</strong><br>';
            html += '<code class="pcw-var-click" data-var="{customer_name}" style="cursor: pointer;">{customer_name}</code><br>';
            html += '<code class="pcw-var-click" data-var="{customer_first_name}" style="cursor: pointer;">{customer_first_name}</code><br>';
            html += '<code class="pcw-var-click" data-var="{customer_email}" style="cursor: pointer;">{customer_email}</code><br>';
            html += '<code class="pcw-var-click" data-var="{customer_phone}" style="cursor: pointer;">{customer_phone}</code>';
            html += '</div>';
            
            // Coluna 2 - Pedido/Cashback (dinâmico baseado no trigger)
            html += '<div>';
            html += '<strong>' + (pcwWorkflows.i18n.orderCashback || 'Pedido/Cashback') + ':</strong><br>';
            html += '<code class="pcw-var-click" data-var="{order_number}" style="cursor: pointer;">{order_number}</code><br>';
            html += '<code class="pcw-var-click" data-var="{order_total}" style="cursor: pointer;">{order_total}</code><br>';
            html += '<code class="pcw-var-click" data-var="{cashback_amount}" style="cursor: pointer;">{cashback_amount}</code><br>';
            html += '<code class="pcw-var-click" data-var="{wallet_balance}" style="cursor: pointer;">{wallet_balance}</code>';
            html += '</div>';
            
            // Coluna 3 - Datas/Links
            html += '<div>';
            html += '<strong>' + (pcwWorkflows.i18n.datesLinks || 'Datas/Links') + ':</strong><br>';
            html += '<code class="pcw-var-click" data-var="{expiration_date}" style="cursor: pointer;">{expiration_date}</code><br>';
            html += '<code class="pcw-var-click" data-var="{days_remaining}" style="cursor: pointer;">{days_remaining}</code><br>';
            html += '<code class="pcw-var-click" data-var="{payment_link}" style="cursor: pointer;">{payment_link}</code><br>';
            html += '<code class="pcw-var-click" data-var="{site_name}" style="cursor: pointer;">{site_name}</code>';
            html += '</div>';
            
            html += '</div>';
            
            // Link para ver mais variáveis
            html += '<p style="margin: 10px 0 0; font-size: 12px;">';
            html += '<a href="#" class="pcw-show-more-vars" data-action-index="' + actionIndex + '" style="color: #0073aa;">📋 Ver todas as variáveis do gatilho selecionado</a>';
            html += '</p>';
            
            // Container para variáveis extras (oculto inicialmente)
            html += '<div class="pcw-extra-vars" data-action-index="' + actionIndex + '" style="display: none; margin-top: 10px;"></div>';
            
            html += '</div>';
            html += '</div>';
            
            return html;
        },

        renderEmailEditor: function(actionIndex, fieldId, value) {
            var html = '<div class="pcw-email-editor-wrapper" data-action-index="' + actionIndex + '" data-field-id="' + fieldId + '">';
            
            // Botão para abrir o editor visual
            html += '<div class="pcw-email-editor-actions">';
            html += '<button type="button" class="button button-primary pcw-open-email-editor" data-target="email_body_' + actionIndex + '">';
            html += '<span class="dashicons dashicons-edit-page"></span> Editor Visual (Drag & Drop)';
            html += '</button>';
            html += '</div>';
            
            // Campo hidden para armazenar o HTML
            html += '<textarea id="email_body_' + actionIndex + '" name="actions[' + actionIndex + '][config][' + fieldId + ']" style="display: none;">' + this.escapeHtml(value) + '</textarea>';
            
            // Preview do email atual
            if (value) {
                html += '<div class="pcw-email-preview" style="margin-top: 15px;">';
                html += '<p><strong>Preview atual:</strong></p>';
                html += '<iframe class="pcw-email-preview-frame" style="width: 100%; height: 300px; border: 1px solid #ddd; border-radius: 4px;" srcdoc="' + this.escapeHtml(value) + '"></iframe>';
                html += '</div>';
            }
            
            // Variáveis disponíveis
            html += '<div class="pcw-email-variables" style="margin-top: 15px; padding: 12px; background: #f0f6fc; border-radius: 4px;">';
            html += '<strong>Variáveis disponíveis:</strong><br>';
            html += '<code>{customer_name}</code> - Nome completo<br>';
            html += '<code>{customer_first_name}</code> - Primeiro nome<br>';
            html += '<code>{customer_email}</code> - Email<br>';
            html += '<code>{order_id}</code> - ID do pedido<br>';
            html += '<code>{order_total}</code> - Total do pedido<br>';
            html += '<code>{cashback_amount}</code> - Valor do cashback<br>';
            html += '<code>{wallet_balance}</code> - Saldo da carteira<br>';
            html += '<code>{site_name}</code> - Nome da loja';
            html += '</div>';
            
            html += '</div>';
            return html;
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/"/g, '&quot;');
        },

        renderVariablesPopup: function() {
            var html = '<div class="pcw-variables-popup">';
            html += '<div class="pcw-variables-popup-header">Variáveis Disponíveis</div>';

            for (var varId in this.currentVariables) {
                html += '<div class="pcw-variable-item" data-variable="{' + varId + '}">';
                html += '<code>{' + varId + '}</code>';
                html += '<span>' + this.currentVariables[varId] + '</span>';
                html += '</div>';
            }

            if (Object.keys(this.currentVariables).length === 0) {
                html += '<div style="padding: 12px; color: #64748b; font-size: 12px;">Selecione um gatilho para ver as variáveis disponíveis</div>';
            }

            html += '</div>';
            return html;
        },

        addPayloadField: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var actionIndex = $btn.data('action-index');
            var fieldId = $btn.data('field-id');
            var $container = $btn.closest('.pcw-payload-builder').find('.pcw-payload-fields');
            var index = $container.find('.pcw-payload-field').length;

            var html = this.renderPayloadFieldHTML(actionIndex, fieldId, index, {});
            $container.append(html);
        },

        removePayloadField: function(e) {
            $(e.currentTarget).closest('.pcw-payload-field').remove();
        },

        addHeaderField: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var actionIndex = $btn.data('action-index');
            var fieldId = $btn.data('field-id');
            var $container = $btn.closest('.pcw-headers-builder').find('.pcw-payload-fields');
            var index = $container.find('.pcw-header-field').length;

            var html = this.renderHeaderFieldHTML(actionIndex, fieldId, index, {});
            $container.append(html);
        },

        removeHeaderField: function(e) {
            $(e.currentTarget).closest('.pcw-header-field').remove();
        },

        toggleVariablePopup: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $popup = $(e.currentTarget).siblings('.pcw-variables-popup');
            
            // Close others
            $('.pcw-variables-popup').not($popup).removeClass('active');
            
            // Toggle this one
            $popup.toggleClass('active');

            // Refresh popup content with current variables
            var popupHtml = '';
            popupHtml += '<div class="pcw-variables-popup-header">Variáveis Disponíveis</div>';

            for (var varId in this.currentVariables) {
                popupHtml += '<div class="pcw-variable-item" data-variable="{' + varId + '}">';
                popupHtml += '<code>{' + varId + '}</code>';
                popupHtml += '<span>' + this.currentVariables[varId] + '</span>';
                popupHtml += '</div>';
            }

            if (Object.keys(this.currentVariables).length === 0) {
                popupHtml += '<div style="padding: 12px; color: #64748b; font-size: 12px;">Selecione um gatilho para ver as variáveis disponíveis</div>';
            }

            $popup.html(popupHtml);
        },

        insertVariable: function(e) {
            e.preventDefault();
            var variable = $(e.currentTarget).data('variable');
            var $input = $(e.currentTarget).closest('.pcw-field-value').find('input');
            
            // Insert at cursor position or append
            var currentVal = $input.val();
            $input.val(currentVal + variable);
            
            // Close popup
            $(e.currentTarget).closest('.pcw-variables-popup').removeClass('active');
        },

        handleSubmit: function(e) {
            e.preventDefault();

            var $form = $(e.currentTarget);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.html();

            $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + pcwWorkflows.i18n.saving);

            // Collect form data
            var formData = {
                action: 'pcw_save_workflow',
                nonce: pcwWorkflows.nonce,
                workflow_id: $form.find('input[name="workflow_id"]').val(),
                name: $form.find('input[name="name"]').val(),
                description: $form.find('textarea[name="description"]').val(),
                trigger_type: $form.find('select[name="trigger_type"]').val(),
                trigger_config: {},
                conditions: {
                    logic: $form.find('select[name="conditions_logic"]').val(),
                    rules: []
                },
                actions: []
            };

            // Collect trigger config
            $form.find('[name^="trigger_config"]').each(function() {
                var name = $(this).attr('name').match(/\[([^\]]+)\]/)[1];
                formData.trigger_config[name] = $(this).val();
            });

            // Collect conditions
            $form.find('.pcw-condition-row').each(function() {
                var rule = {
                    type: $(this).find('.pcw-condition-type').val(),
                    operator: $(this).find('.pcw-condition-operator').val(),
                    value: $(this).find('.pcw-condition-value').val()
                };
                if (rule.type) {
                    formData.conditions.rules.push(rule);
                }
            });

            // Collect actions
            $form.find('.pcw-action-item').each(function() {
                var $item = $(this);
                var actionType = $item.find('.pcw-action-type-select select').val();
                
                if (actionType) {
                    var actionData = {
                        type: actionType,
                        config: {}
                    };

                    // Collect config fields
                    $item.find('.pcw-action-config').find('input, select, textarea').each(function() {
                        var name = $(this).attr('name');
                        if (name) {
                            var match = name.match(/\[config\]\[([^\]]+)\](?:\[(\d+)\]\[([^\]]+)\])?/);
                            if (match) {
                                var fieldId = match[1];
                                if (match[2] !== undefined && match[3]) {
                                    // Array field (headers or payload)
                                    if (!actionData.config[fieldId]) {
                                        actionData.config[fieldId] = [];
                                    }
                                    var idx = parseInt(match[2]);
                                    var subField = match[3];
                                    if (!actionData.config[fieldId][idx]) {
                                        actionData.config[fieldId][idx] = {};
                                    }
                                    actionData.config[fieldId][idx][subField] = $(this).val();
                                } else {
                                    // Simple field
                                    if ($(this).attr('type') === 'checkbox') {
                                        actionData.config[fieldId] = $(this).is(':checked');
                                    } else {
                                        actionData.config[fieldId] = $(this).val();
                                    }
                                }
                            }
                        }
                    });

                    formData.actions.push(actionData);
                }
            });

            $.ajax({
                url: pcwWorkflows.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $submitBtn.html('<span class="dashicons dashicons-yes"></span> ' + pcwWorkflows.i18n.saved);
                        setTimeout(function() {
                            if (response.data.redirect) {
                                window.location.href = response.data.redirect;
                            }
                        }, 500);
                    } else {
                        alert(response.data.message || pcwWorkflows.i18n.error);
                        $submitBtn.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    alert(pcwWorkflows.i18n.error);
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            });
        },

        handleDelete: function(e) {
            e.preventDefault();

            if (!confirm(pcwWorkflows.i18n.confirmDelete)) {
                return;
            }

            var $btn = $(e.currentTarget);
            var workflowId = $btn.data('id');
            var $row = $btn.closest('tr');

            $.ajax({
                url: pcwWorkflows.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_delete_workflow',
                    nonce: pcwWorkflows.nonce,
                    workflow_id: workflowId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || pcwWorkflows.i18n.error);
                    }
                }
            });
        },

        handleToggle: function(e) {
            var $checkbox = $(e.currentTarget);
            var workflowId = $checkbox.data('id');
            var status = $checkbox.is(':checked') ? 'active' : 'inactive';

            $.ajax({
                url: pcwWorkflows.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_toggle_workflow',
                    nonce: pcwWorkflows.nonce,
                    workflow_id: workflowId,
                    status: status
                }
            });
        },

        handleTestWebhook: function(e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);
            var $item = $btn.closest('.pcw-action-item');
            var $resultDiv = $btn.siblings('.pcw-test-result');
            var originalText = $btn.html();

            // Collect webhook config
            var config = {
                url: $item.find('input[name*="[url]"]').val(),
                method: $item.find('select[name*="[method]"]').val(),
                body_type: $item.find('select[name*="[body_type]"]').val(),
                headers: [],
                body: []
            };

            // Collect headers
            $item.find('.pcw-headers-builder .pcw-header-field').each(function() {
                var key = $(this).find('input:eq(0)').val();
                var value = $(this).find('input:eq(1)').val();
                if (key) {
                    config.headers.push({ key: key, value: value });
                }
            });

            // Collect body fields
            $item.find('.pcw-payload-builder .pcw-payload-field').each(function() {
                var key = $(this).find('.pcw-field-key').val();
                var value = $(this).find('.pcw-field-value input').val();
                if (key) {
                    config.body.push({ key: key, value: value });
                }
            });

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + pcwWorkflows.i18n.testing);

            $.ajax({
                url: pcwWorkflows.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_test_webhook',
                    nonce: pcwWorkflows.nonce,
                    config: config
                },
                success: function(response) {
                    var resultHtml = '';
                    if (response.success) {
                        resultHtml = '<div class="pcw-response-preview success">';
                        resultHtml += 'HTTP ' + response.data.response_code + '\n\n';
                        resultHtml += response.data.response_body;
                        resultHtml += '</div>';
                    } else {
                        resultHtml = '<div class="pcw-response-preview error">';
                        resultHtml += 'Erro: ' + (response.data.message || 'Erro desconhecido');
                        resultHtml += '</div>';
                    }
                    $resultDiv.html(resultHtml);
                },
                error: function() {
                    $resultDiv.html('<div class="pcw-response-preview error">Erro de conexão</div>');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        // Inserir variável ao clicar
        insertVariableFromClick: function(e) {
            e.preventDefault();
            var $code = $(e.currentTarget);
            var variable = $code.data('var');
            var $wrapper = $code.closest('.pcw-message-field-wrapper');
            var $textarea = $wrapper.find('textarea');
            
            if ($textarea.length && variable) {
                var cursorPos = $textarea[0].selectionStart || $textarea.val().length;
                var textBefore = $textarea.val().substring(0, cursorPos);
                var textAfter = $textarea.val().substring(cursorPos);
                $textarea.val(textBefore + variable + textAfter);
                $textarea.focus();
                
                // Highlight brevemente
                $code.css('background', '#d4edda');
                setTimeout(function() {
                    $code.css('background', '');
                }, 300);
            }
        },

        // Gerar mensagem com IA
        handleGenerateAIMessage: function(e) {
            e.preventDefault();
            var self = this;
            var $btn = $(e.currentTarget);
            var actionIndex = $btn.data('action-index');
            var $wrapper = $btn.closest('.pcw-message-field-wrapper');
            var $textarea = $wrapper.find('textarea');
            var triggerType = $('#trigger_type').val();
            var workflowName = $('input[name="name"]').val();
            
            if (!triggerType) {
                alert(pcwWorkflows.i18n.selectTrigger || 'Selecione um gatilho primeiro');
                return;
            }
            
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + (pcwWorkflows.i18n.generating || 'Gerando...'));
            
            $.ajax({
                url: pcwWorkflows.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_generate_workflow_message',
                    nonce: pcwWorkflows.nonce,
                    trigger_type: triggerType,
                    workflow_name: workflowName
                },
                success: function(response) {
                    if (response.success && response.data.message) {
                        $textarea.val(response.data.message);
                        $textarea.css('background', '#d4edda');
                        setTimeout(function() {
                            $textarea.css('background', '');
                        }, 1000);
                    } else {
                        alert(response.data.message || 'Erro ao gerar mensagem');
                    }
                },
                error: function() {
                    alert('Erro de conexão');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        // Mostrar mais variáveis do gatilho
        showMoreVariables: function(e) {
            e.preventDefault();
            var $link = $(e.currentTarget);
            var actionIndex = $link.data('action-index');
            var $container = $('.pcw-extra-vars[data-action-index="' + actionIndex + '"]');
            
            if ($container.is(':visible')) {
                $container.slideUp(200);
                $link.text('📋 Ver todas as variáveis do gatilho selecionado');
                return;
            }
            
            var triggerType = $('#trigger_type').val();
            if (!triggerType || !pcwWorkflows.triggersList[triggerType]) {
                alert(pcwWorkflows.i18n.selectTrigger || 'Selecione um gatilho primeiro');
                return;
            }
            
            var trigger = pcwWorkflows.triggersList[triggerType];
            var variables = trigger.variables || {};
            
            var html = '<div style="padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">';
            html += '<strong>' + (pcwWorkflows.i18n.allVariables || 'Todas as variáveis para: ') + trigger.name + '</strong><br><br>';
            html += '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">';
            
            for (var varKey in variables) {
                html += '<div>';
                html += '<code class="pcw-var-click" data-var="{' + varKey + '}" style="cursor: pointer;">{' + varKey + '}</code>';
                html += ' - <small>' + variables[varKey] + '</small>';
                html += '</div>';
            }
            
            html += '</div></div>';
            
            $container.html(html).slideDown(200);
            $link.text('📋 Ocultar variáveis');
        },

        // Abrir modal de teste
        openTestModal: function(e) {
            e.preventDefault();
            $('#pcw-test-result').hide().html('');
            $('#pcw-test-workflow-modal').fadeIn(200);
        },

        // Fechar modal de teste
        closeTestModal: function(e) {
            if (e) e.preventDefault();
            $('#pcw-test-workflow-modal').fadeOut(200);
        },

        // Executar teste do workflow
        runWorkflowTest: function(e) {
            e.preventDefault();
            var self = this;
            var $btn = $(e.currentTarget);
            var $resultDiv = $('#pcw-test-result');
            var originalText = $btn.html();

            var workflowId = $('input[name="workflow_id"]').val();
            var testMode = $('input[name="test_mode"]:checked').val();
            var overrideEmail = $('#test_override_email').val();
            var overridePhone = $('#test_override_phone').val();

            if (!workflowId || workflowId === '0') {
                alert('Salve o workflow primeiro antes de testar.');
                return;
            }

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="margin-top: 4px;"></span> Executando...');
            $resultDiv.html('<div style="padding: 15px; background: #f0f6fc; border-radius: 4px;"><span class="dashicons dashicons-update spin"></span> Executando ações do workflow...</div>').show();

            $.ajax({
                url: pcwWorkflows.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_test_run_workflow',
                    nonce: pcwWorkflows.nonce,
                    workflow_id: workflowId,
                    test_mode: testMode,
                    override_email: overrideEmail,
                    override_phone: overridePhone
                },
                success: function(response) {
                    var html = '';
                    if (response.success) {
                        html += '<div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 15px;">';
                        html += '<strong>✅ ' + response.data.message + '</strong>';
                        html += '</div>';

                        // Contexto usado
                        if (response.data.context_used) {
                            html += '<div style="padding: 10px; background: #f8f9fa; border-radius: 4px; margin-bottom: 15px; font-size: 12px;">';
                            html += '<strong>Dados usados:</strong><br>';
                            html += '• Nome: ' + (response.data.context_used.customer_name || '-') + '<br>';
                            html += '• Email: ' + (response.data.context_used.customer_email || '-') + '<br>';
                            html += '• Telefone: ' + (response.data.context_used.customer_phone || '-');
                            html += '</div>';
                        }

                        // Resultados das ações
                        if (response.data.results && response.data.results.length) {
                            html += '<div style="border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">';
                            html += '<div style="padding: 10px; background: #f0f0f1; font-weight: 600;">Detalhes das ações:</div>';
                            for (var i = 0; i < response.data.results.length; i++) {
                                var result = response.data.results[i];
                                var bgColor = result.success ? '#d4edda' : '#f8d7da';
                                var icon = result.success ? '✅' : '❌';
                                html += '<div style="padding: 10px; border-top: 1px solid #ddd; background: ' + bgColor + ';">';
                                html += icon + ' <strong>' + result.action + '</strong>: ' + result.message;
                                html += '</div>';
                            }
                            html += '</div>';
                        }
                    } else {
                        html += '<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">';
                        html += '<strong>❌ Erro:</strong> ' + (response.data.message || 'Erro desconhecido');
                        html += '</div>';
                    }
                    $resultDiv.html(html);
                },
                error: function() {
                    $resultDiv.html('<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;"><strong>❌ Erro de conexão</strong></div>');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    };

    // CSS para animação de spin
    $('<style>.dashicons.spin { animation: pcw-spin 1s linear infinite; } @keyframes pcw-spin { 100% { transform: rotate(360deg); } }</style>').appendTo('head');

    $(document).ready(function() {
        PCWWorkflows.init();
    });

})(jQuery);
