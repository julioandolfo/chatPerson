/**
 * Growly Digital - Automation Reports
 */
(function($) {
    'use strict';

    var PCWAutomationReports = {
        charts: {},
        currentFilters: {},

        init: function() {
            this.bindEvents();
            this.loadInitialData();
        },

        bindEvents: function() {
            $('#pcw-period-preset').on('change', this.handlePeriodPresetChange.bind(this));
            $('#pcw-apply-filters').on('click', this.applyFilters.bind(this));
            $('#pcw-reset-filters').on('click', this.resetFilters.bind(this));
            $('#pcw-export-csv').on('click', this.exportCSV.bind(this));
            $('.pcw-tab-button').on('click', this.handleTabChange.bind(this));
            $(document).on('click', '.pcw-events-pagination a', this.handleEventsPage.bind(this));
            $(document).on('click', '.pcw-queue-pagination a', this.handleQueuePage.bind(this));
            $(document).on('click', '.pcw-queue-filter-btn', this.handleQueueFilter.bind(this));
            $('#pcw-refresh-queue').on('click', this.loadQueue.bind(this, 1));
            $('#pcw-run-automation-now').on('click', this.runAutomationNow.bind(this));
        },

        handlePeriodPresetChange: function(e) {
            var preset = $(e.currentTarget).val();
            var dates = this.calculatePresetDates(preset);

            if (preset === 'custom') {
                $('.pcw-custom-dates').show();
            } else {
                $('.pcw-custom-dates').hide();
                $('#pcw-start-date').val(dates.start);
                $('#pcw-end-date').val(dates.end);
            }
        },

        calculatePresetDates: function(preset) {
            var today = new Date();
            var start, end;

            end = this.formatDate(today);

            switch(preset) {
                case 'today':
                    start = end;
                    break;
                case 'yesterday':
                    var yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    start = end = this.formatDate(yesterday);
                    break;
                case 'last_7_days':
                    var week = new Date(today);
                    week.setDate(week.getDate() - 7);
                    start = this.formatDate(week);
                    break;
                case 'last_30_days':
                    var month = new Date(today);
                    month.setDate(month.getDate() - 30);
                    start = this.formatDate(month);
                    break;
                case 'last_90_days':
                    var quarter = new Date(today);
                    quarter.setDate(quarter.getDate() - 90);
                    start = this.formatDate(quarter);
                    break;
                case 'this_month':
                    start = this.formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
                    break;
                case 'last_month':
                    var lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    start = this.formatDate(lastMonth);
                    var lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
                    end = this.formatDate(lastDay);
                    break;
                default:
                    start = end;
            }

            return { start: start, end: end };
        },

        formatDate: function(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        },

        applyFilters: function() {
            var automation_id = $('.pcw-automation-report-page').data('automation-id');

            this.currentFilters = {
                automation_id: automation_id,
                start_date: $('#pcw-start-date').val(),
                end_date: $('#pcw-end-date').val(),
                event_type: $('#pcw-event-type').val(),
                email: $('#pcw-filter-email').val()
            };

            this.loadMetrics();
            this.loadEvents(1);
        },

        resetFilters: function() {
            $('#pcw-period-preset').val('last_30_days').trigger('change');
            $('#pcw-event-type').val('');
            $('#pcw-filter-email').val('');
            $('.pcw-custom-dates').hide();
            this.applyFilters();
        },

        loadInitialData: function() {
            this.applyFilters();
        },

        loadMetrics: function() {
            var self = this;

            $.ajax({
                url: pcwReports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_get_automation_metrics',
                    nonce: pcwReports.nonce,
                    ...self.currentFilters
                },
                success: function(response) {
                    if (response.success) {
                        self.updateMetricsUI(response.data.metrics);
                        self.renderTimelineChart(response.data.timeline);
                        self.renderTopLinks(response.data.top_links);
                        self.renderDevicesChart(response.data.devices);
                        self.renderEmailClientsChart(response.data.email_clients);
                    }
                },
                error: function() {
                    alert(pcwReports.i18n.error);
                }
            });
        },

        updateMetricsUI: function(metrics) {
            var self = this;

            var metricMap = {
                'executions':       { value: metrics.executions },
                'whatsapp_pending': { value: (metrics.whatsapp_pending || 0) },
                'whatsapp_sent':    { value: (metrics.whatsapp_sent || 0) },
                'emails_sent':      { value: metrics.emails_sent, sub: metrics.delivery_rate + '%' },
                'emails_opened':    { value: metrics.emails_opened, sub: metrics.open_rate + '%' },
                'conversions':      { value: metrics.conversions, sub: metrics.conversion_rate + '%' },
            };

            $.each(metricMap, function(key, data) {
                var $card = $('.pcw-metric-card[data-metric="' + key + '"]');
                if ($card.length) {
                    $card.find('.pcw-metric-value').text(self.formatNumber(data.value));
                    if (data.sub !== undefined) {
                        $card.find('.pcw-metric-subtext').text(data.sub);
                    }
                }
            });
        },

        renderTimelineChart: function(data) {
            var ctx = document.getElementById('pcw-timeline-chart');
            if (!ctx) return;

            // Destruir gráfico anterior se existir
            if (this.charts.timeline) {
                this.charts.timeline.destroy();
            }

            var labels = data.map(function(item) { return item.date_label; });
            var values = data.map(function(item) { return item.total; });

            this.charts.timeline = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Eventos',
                        data: values,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        },

        renderTopLinks: function(links) {
            var $container = $('#pcw-top-links-container');

            if (!links || links.length === 0) {
                $container.html('<p class="pcw-no-data">' + pcwReports.i18n.noData + '</p>');
                return;
            }

            var html = '<table class="pcw-data-table">';
            html += '<thead><tr>';
            html += '<th>Link</th>';
            html += '<th>Texto</th>';
            html += '<th style="text-align: center;">Cliques</th>';
            html += '<th style="text-align: center;">Únicos</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            links.forEach(function(link) {
                html += '<tr>';
                html += '<td><a href="' + link.link_url + '" target="_blank" class="pcw-link-truncate">' + link.link_url + '</a></td>';
                html += '<td>' + (link.link_text || '-') + '</td>';
                html += '<td style="text-align: center;"><strong>' + link.clicked_count + '</strong></td>';
                html += '<td style="text-align: center;">' + link.unique_clicks + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            $container.html(html);
        },

        renderDevicesChart: function(data) {
            var ctx = document.getElementById('pcw-devices-chart');
            if (!ctx || !data || data.length === 0) return;

            if (this.charts.devices) {
                this.charts.devices.destroy();
            }

            var labels = data.map(function(item) { return item.device_type || 'Unknown'; });
            var values = data.map(function(item) { return parseFloat(item.count); });

            this.charts.devices = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: [
                            '#667eea',
                            '#f59e0b',
                            '#10b981'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },

        renderEmailClientsChart: function(data) {
            var ctx = document.getElementById('pcw-email-clients-chart');
            if (!ctx || !data || data.length === 0) return;

            if (this.charts.emailClients) {
                this.charts.emailClients.destroy();
            }

            var labels = data.map(function(item) { return item.email_client || 'Unknown'; });
            var values = data.map(function(item) { return parseFloat(item.count); });

            this.charts.emailClients = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: [
                            '#3b82f6',
                            '#22c55e',
                            '#f59e0b',
                            '#ef4444',
                            '#8b5cf6',
                            '#ec4899'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },

        loadEvents: function(page) {
            var self = this;
            var $container = $('#pcw-events-table-container');

            $container.html('<p class="pcw-loading">' + pcwReports.i18n.loading + '</p>');

            $.ajax({
                url: pcwReports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_get_automation_events',
                    nonce: pcwReports.nonce,
                    page: page,
                    per_page: 20,
                    ...self.currentFilters
                },
                success: function(response) {
                    if (response.success) {
                        self.renderEventsTable(response.data);
                    }
                },
                error: function() {
                    $container.html('<p class="pcw-error">' + pcwReports.i18n.error + '</p>');
                }
            });
        },

        renderEventsTable: function(data) {
            var $container = $('#pcw-events-table-container');

            if (!data.events || data.events.length === 0) {
                $container.html('<p class="pcw-no-data">' + pcwReports.i18n.noData + '</p>');
                return;
            }

            var eventLabels = {
                'email_sent': '📧 Email Enviado',
                'email_opened': '👁️ Email Aberto',
                'email_clicked': '🖱️ Email Clicado',
                'whatsapp_queued': '📱 WhatsApp Na Fila',
                'whatsapp_sent': '✅ WhatsApp Enviado',
                'whatsapp_failed': '❌ WhatsApp Falhou',
                'conversion': '💰 Conversão',
                'order_completed': '✅ Pedido Completado'
            };

            var html = '<table class="pcw-data-table">';
            html += '<thead><tr>';
            html += '<th>Data/Hora</th>';
            html += '<th>Evento</th>';
            html += '<th>Cliente</th>';
            html += '<th>Email</th>';
            html += '<th>Detalhes</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            data.events.forEach(function(event) {
                var eventLabel = eventLabels[event.event_type] || event.event_type;
                var details = '';

                if (event.metadata) {
                    if (event.metadata.order_total) {
                        details = 'R$ ' + parseFloat(event.metadata.order_total).toFixed(2).replace('.', ',');
                    } else if (event.metadata.link_text) {
                        details = event.metadata.link_text;
                    } else if (event.metadata.device_type) {
                        details = event.metadata.device_type + ' - ' + event.metadata.email_client;
                    }
                }

                html += '<tr>';
                html += '<td>' + event.created_at + '</td>';
                html += '<td>' + eventLabel + '</td>';
                html += '<td>' + (event.user_name || '-') + '</td>';
                html += '<td>' + (event.email || '-') + '</td>';
                html += '<td>' + details + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';

            // Paginação
            if (data.total_pages > 1) {
                html += '<div class="pcw-events-pagination">';
                html += this.renderPagination(data.page, data.total_pages);
                html += '</div>';
            }

            $container.html(html);
        },

        renderPagination: function(currentPage, totalPages) {
            var html = '';
            var maxButtons = 7;
            var startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
            var endPage = Math.min(totalPages, startPage + maxButtons - 1);

            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }

            // Anterior
            if (currentPage > 1) {
                html += '<a href="#" data-page="' + (currentPage - 1) + '">← Anterior</a>';
            }

            // Números
            for (var i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += '<span class="current">' + i + '</span>';
                } else {
                    html += '<a href="#" data-page="' + i + '">' + i + '</a>';
                }
            }

            // Próximo
            if (currentPage < totalPages) {
                html += '<a href="#" data-page="' + (currentPage + 1) + '">Próximo →</a>';
            }

            return html;
        },

        handleEventsPage: function(e) {
            e.preventDefault();
            var page = $(e.currentTarget).data('page');
            this.loadEvents(page);
            // Scroll to top of events
            $('#tab-events').get(0).scrollIntoView({ behavior: 'smooth' });
        },

        currentQueueFilter: '',

        loadQueue: function(page) {
            var self = this;
            var $container = $('#pcw-queue-table-container');
            var automation_id = $('.pcw-automation-report-page').data('automation-id');

            $container.html('<p class="pcw-loading">' + pcwReports.i18n.loading + '</p>');

            $.ajax({
                url: pcwReports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_get_automation_queue',
                    nonce: pcwReports.nonce,
                    automation_id: automation_id,
                    page: page || 1,
                    queue_status: self.currentQueueFilter
                },
                success: function(response) {
                    if (response.success) {
                        self.renderQueueTable(response.data);
                        self.updateQueueStats(response.data.stats);
                    }
                },
                error: function() {
                    $container.html('<p class="pcw-error">' + pcwReports.i18n.error + '</p>');
                }
            });
        },

        renderQueueTable: function(data) {
            var self = this;
            var $container = $('#pcw-queue-table-container');

            var statusLabels = {
                'pending': 'Pendente',
                'scheduled': 'Agendada',
                'processing': 'Processando',
                'sent': 'Enviada',
                'failed': 'Falhou'
            };

            var html = '<div class="pcw-queue-filter-bar">';
            html += '<button class="pcw-queue-filter-btn' + (self.currentQueueFilter === '' ? ' active' : '') + '" data-status="">Todos (' + data.total + ')</button>';
            if (data.stats) {
                if (data.stats.pending > 0) html += '<button class="pcw-queue-filter-btn' + (self.currentQueueFilter === 'pending' ? ' active' : '') + '" data-status="pending">⏳ Pendentes (' + data.stats.pending + ')</button>';
                if (data.stats.scheduled > 0) html += '<button class="pcw-queue-filter-btn' + (self.currentQueueFilter === 'scheduled' ? ' active' : '') + '" data-status="scheduled">📅 Agendadas (' + data.stats.scheduled + ')</button>';
                if (data.stats.sent > 0) html += '<button class="pcw-queue-filter-btn' + (self.currentQueueFilter === 'sent' ? ' active' : '') + '" data-status="sent">✅ Enviadas (' + data.stats.sent + ')</button>';
                if (data.stats.failed > 0) html += '<button class="pcw-queue-filter-btn' + (self.currentQueueFilter === 'failed' ? ' active' : '') + '" data-status="failed">❌ Falharam (' + data.stats.failed + ')</button>';
            }
            html += '</div>';

            if (!data.messages || data.messages.length === 0) {
                html += '<p class="pcw-no-data">' + pcwReports.i18n.noData + '</p>';
                $container.html(html);
                return;
            }

            html += '<table class="pcw-data-table">';
            html += '<thead><tr>';
            html += '<th>Destinatário</th>';
            html += '<th>Telefone</th>';
            html += '<th>De</th>';
            html += '<th>Status</th>';
            html += '<th>Agendado para</th>';
            html += '<th>Enviado em</th>';
            html += '<th>Erro</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            data.messages.forEach(function(msg) {
                var statusClass = 'pcw-queue-status-' + msg.status;
                var statusText = statusLabels[msg.status] || msg.status;

                html += '<tr>';
                html += '<td><strong>' + (msg.name || '-') + '</strong></td>';
                html += '<td>' + (msg.phone || '-') + '</td>';
                html += '<td style="font-size: 12px;">' + (msg.from_number || 'Auto') + '</td>';
                html += '<td><span class="pcw-queue-status ' + statusClass + '">' + statusText + '</span></td>';
                html += '<td>' + msg.scheduled_at + '</td>';
                html += '<td>' + msg.sent_at + '</td>';
                html += '<td style="max-width: 200px; font-size: 12px; color: #ef4444;">' + (msg.error || '') + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';

            if (data.total_pages > 1) {
                html += '<div class="pcw-queue-pagination pcw-events-pagination">';
                html += self.renderPagination(data.page, data.total_pages);
                html += '</div>';
            }

            $container.html(html);
        },

        updateQueueStats: function(stats) {
            if (!stats) return;
            $('.pcw-queue-stat-pending .pcw-queue-stat-value').text(this.formatNumber(stats.pending));
            $('.pcw-queue-stat-scheduled .pcw-queue-stat-value').text(this.formatNumber(stats.scheduled));
            $('.pcw-queue-stat-sent .pcw-queue-stat-value').text(this.formatNumber(stats.sent));
            $('.pcw-queue-stat-failed .pcw-queue-stat-value').text(this.formatNumber(stats.failed));
        },

        handleQueuePage: function(e) {
            e.preventDefault();
            var page = $(e.currentTarget).data('page');
            this.loadQueue(page);
            $('#tab-queue').get(0).scrollIntoView({ behavior: 'smooth' });
        },

        handleQueueFilter: function(e) {
            var $btn = $(e.currentTarget);
            this.currentQueueFilter = $btn.data('status');
            this.loadQueue(1);
        },

        handleTabChange: function(e) {
            var $btn = $(e.currentTarget);
            var tab = $btn.data('tab');

            $('.pcw-tab-button').removeClass('active');
            $btn.addClass('active');

            $('.pcw-tab-content').hide();
            $('#tab-' + tab).show();

            if (tab === 'events' && $('#pcw-events-table-container').html().indexOf('pcw-loading') !== -1) {
                this.loadEvents(1);
            }
            if (tab === 'queue' && $('#pcw-queue-table-container').html().indexOf('pcw-loading') !== -1) {
                this.loadQueue(1);
            }
        },

        runAutomationNow: function() {
            var self = this;
            var $btn = $('#pcw-run-automation-now');
            var automation_id = $btn.data('automation-id');

            if (!confirm('Executar o lote de hoje agora? Isso irá processar os clientes e adicionar mensagens à fila do WhatsApp.')) {
                return;
            }

            $btn.prop('disabled', true).text('⏳ Executando...');

            $.ajax({
                url: pcwReports.ajaxUrl,
                type: 'POST',
                timeout: 300000,
                data: {
                    action: 'pcw_run_automation_cron',
                    nonce: pcwReports.nonce,
                    automation_id: automation_id
                },
                success: function(response) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Executar agora');
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                        // Recarregar a página para atualizar os contadores
                        location.reload();
                    } else {
                        alert('❌ Erro: ' + response.data.message);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Executar agora');
                    alert('❌ Erro de conexão ao executar a automação.');
                }
            });
        },

        exportCSV: function() {
            var automation_id = $('.pcw-automation-report-page').data('automation-id');

            // Create form and submit
            var form = $('<form>', {
                method: 'POST',
                action: pcwReports.ajaxUrl
            });

            form.append($('<input>', { type: 'hidden', name: 'action', value: 'pcw_export_automation_report' }));
            form.append($('<input>', { type: 'hidden', name: 'nonce', value: pcwReports.nonce }));
            form.append($('<input>', { type: 'hidden', name: 'automation_id', value: automation_id }));
            form.append($('<input>', { type: 'hidden', name: 'start_date', value: $('#pcw-start-date').val() }));
            form.append($('<input>', { type: 'hidden', name: 'end_date', value: $('#pcw-end-date').val() }));
            form.append($('<input>', { type: 'hidden', name: 'event_type', value: $('#pcw-event-type').val() }));
            form.append($('<input>', { type: 'hidden', name: 'email', value: $('#pcw-filter-email').val() }));

            $('body').append(form);
            form.submit();
            form.remove();
        },

        formatNumber: function(num) {
            return new Intl.NumberFormat('pt-BR').format(num);
        }
    };

    $(document).ready(function() {
        if ($('.pcw-automation-report-page').length) {
            PCWAutomationReports.init();
        }
    });

})(jQuery);
