/**
 * Admin Dashboard Scripts
 * 
 * @package PersonCashWallet
 * @since 1.0.0
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Verificar se Chart.js esta carregado
		if (typeof Chart === 'undefined') {
			console.error('Chart.js not loaded');
			return;
		}

		// Verificar se os dados estao disponiveis
		if (typeof pcwDashboardData === 'undefined') {
			console.error('Dashboard data not available');
			return;
		}

		// Configurações globais do Chart.js
		Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
		Chart.defaults.plugins.legend.position = 'bottom';
		Chart.defaults.plugins.legend.labels.usePointStyle = true;
		Chart.defaults.plugins.legend.labels.padding = 15;

		// Gráfico de Cashback (Line Chart)
		var cashbackCtx = document.getElementById('pcwCashbackChart');
		if (cashbackCtx) {
			new Chart(cashbackCtx, {
				type: 'line',
				data: {
					labels: pcwDashboardData.cashbackChart.labels,
					datasets: [
						{
							label: 'Cashback Ganho',
							data: pcwDashboardData.cashbackChart.earned,
							borderColor: '#667eea',
							backgroundColor: 'rgba(102, 126, 234, 0.1)',
							borderWidth: 3,
							fill: true,
							tension: 0.4,
							pointRadius: 4,
							pointHoverRadius: 6,
							pointBackgroundColor: '#667eea',
							pointBorderColor: '#fff',
							pointBorderWidth: 2
						},
						{
							label: 'Cashback Utilizado',
							data: pcwDashboardData.cashbackChart.used,
							borderColor: '#f5576c',
							backgroundColor: 'rgba(245, 87, 108, 0.1)',
							borderWidth: 3,
							fill: true,
							tension: 0.4,
							pointRadius: 4,
							pointHoverRadius: 6,
							pointBackgroundColor: '#f5576c',
							pointBorderColor: '#fff',
							pointBorderWidth: 2
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					interaction: {
						mode: 'index',
						intersect: false
					},
					plugins: {
						legend: {
							display: true
						},
						tooltip: {
							backgroundColor: 'rgba(0, 0, 0, 0.8)',
							padding: 12,
							cornerRadius: 8,
							titleFont: {
								size: 14,
								weight: 'bold'
							},
							bodyFont: {
								size: 13
							},
							callbacks: {
								label: function(context) {
									var label = context.dataset.label || '';
									if (label) {
										label += ': ';
									}
									label += 'R$ ' + context.parsed.y.toFixed(2).replace('.', ',');
									return label;
								}
							}
						}
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								callback: function(value) {
									return 'R$ ' + value.toFixed(2).replace('.', ',');
								}
							},
							grid: {
								color: 'rgba(0, 0, 0, 0.05)'
							}
						},
						x: {
							grid: {
								display: false
							}
						}
					}
				}
			});
		}

		// Gráfico de Níveis (Doughnut Chart)
		var levelsCtx = document.getElementById('pcwLevelsChart');
		if (levelsCtx) {
			new Chart(levelsCtx, {
				type: 'doughnut',
				data: {
					labels: pcwDashboardData.levelsChart.labels,
					datasets: [{
						data: pcwDashboardData.levelsChart.data,
						backgroundColor: pcwDashboardData.levelsChart.colors.length > 0 
							? pcwDashboardData.levelsChart.colors 
							: ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe'],
						borderWidth: 3,
						borderColor: '#fff',
						hoverOffset: 10
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: {
							display: true,
							position: 'right'
						},
						tooltip: {
							backgroundColor: 'rgba(0, 0, 0, 0.8)',
							padding: 12,
							cornerRadius: 8,
							titleFont: {
								size: 14,
								weight: 'bold'
							},
							bodyFont: {
								size: 13
							},
							callbacks: {
								label: function(context) {
									var label = context.label || '';
									if (label) {
										label += ': ';
									}
									label += context.parsed + ' usuário' + (context.parsed !== 1 ? 's' : '');
									return label;
								}
							}
						}
					}
				}
			});
		}

		// Gráfico de Wallet (Bar Chart)
		var walletCtx = document.getElementById('pcwWalletChart');
		if (walletCtx) {
			new Chart(walletCtx, {
				type: 'bar',
				data: {
					labels: pcwDashboardData.walletChart.labels,
					datasets: [
						{
							label: 'Créditos',
							data: pcwDashboardData.walletChart.credits,
							backgroundColor: 'rgba(0, 163, 42, 0.8)',
							borderColor: '#00a32a',
							borderWidth: 2,
							borderRadius: 6,
							hoverBackgroundColor: '#00a32a'
						},
						{
							label: 'Débitos',
							data: pcwDashboardData.walletChart.debits,
							backgroundColor: 'rgba(214, 54, 56, 0.8)',
							borderColor: '#d63638',
							borderWidth: 2,
							borderRadius: 6,
							hoverBackgroundColor: '#d63638'
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					interaction: {
						mode: 'index',
						intersect: false
					},
					plugins: {
						legend: {
							display: true
						},
						tooltip: {
							backgroundColor: 'rgba(0, 0, 0, 0.8)',
							padding: 12,
							cornerRadius: 8,
							titleFont: {
								size: 14,
								weight: 'bold'
							},
							bodyFont: {
								size: 13
							},
							callbacks: {
								label: function(context) {
									var label = context.dataset.label || '';
									if (label) {
										label += ': ';
									}
									label += 'R$ ' + context.parsed.y.toFixed(2).replace('.', ',');
									return label;
								}
							}
						}
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								callback: function(value) {
									return 'R$ ' + value.toFixed(2).replace('.', ',');
								}
							},
							grid: {
								color: 'rgba(0, 0, 0, 0.05)'
							}
						},
						x: {
							grid: {
								display: false
							}
						}
					}
				}
			});
		}
		// ============================================
		// Live Activity Dashboard
		// ============================================
		
		var LiveDashboard = {
			refreshInterval: null,
			
			init: function() {
				this.loadStats();
				this.loadActivities();
				// Limpar cache na primeira carga para evitar dados antigos
				this.loadGA4Stats(true);
				this.bindEvents();
				this.startAutoRefresh();
			},
			
			bindEvents: function() {
				$('#pcw-live-period').on('change', function() {
					LiveDashboard.loadStats();
					LiveDashboard.loadGA4Stats();
				});
			},
			
			loadStats: function() {
				var period = $('#pcw-live-period').val() || '7days';
				
				$.ajax({
					url: pcwDashboardData.ajaxUrl,
					type: 'POST',
					data: {
						action: 'pcw_get_dashboard_stats',
						nonce: pcwDashboardData.nonce,
						period: period
					},
					success: function(response) {
						if (response.success) {
							var data = response.data;
							
							// Estatisticas principais
							$('#stat-visitors').text(LiveDashboard.formatNumber(data.visitors));
							$('#stat-new-visitors').text(LiveDashboard.formatNumber(data.new_visitors || 0));
							$('#stat-returning-visitors').text(LiveDashboard.formatNumber(data.returning_visitors || 0));
							$('#stat-views').text(LiveDashboard.formatNumber(data.product_views));
							$('#stat-cart').text(LiveDashboard.formatNumber(data.add_to_cart));
							$('#stat-orders').text(LiveDashboard.formatNumber(data.orders));
							$('#stat-conversion').text((data.conversion_rate || 0) + '%');
							
							// Detalhes de visitantes
							$('#stat-logged').text(LiveDashboard.formatNumber(data.visitors_logged || 0));
							$('#stat-anonymous').text(LiveDashboard.formatNumber(data.visitors_anonymous || 0));
							$('#stat-pageviews').text(LiveDashboard.formatNumber(data.pageviews || 0));
							$('#stat-pages-per-visit').text(data.pages_per_visit || '0');
							
							// Detalhe sob o numero principal
							var detailText = '';
							if (data.visitors_logged > 0 || data.visitors_anonymous > 0) {
								detailText = data.visitors_logged + ' clientes, ' + data.visitors_anonymous + ' visitantes';
							}
							$('#stat-visitors-detail').text(detailText);
						}
					}
				});
			},
			
			loadActivities: function() {
				$.ajax({
					url: pcwDashboardData.ajaxUrl,
					type: 'POST',
					data: {
						action: 'pcw_get_live_activities',
						nonce: pcwDashboardData.nonce,
						limit: 10
					},
					success: function(response) {
						if (response.success && response.data.activities) {
							LiveDashboard.renderActivities(response.data.activities);
						} else {
							$('#pcw-live-activities').html('<p class="pcw-no-activity">Nenhuma atividade recente</p>');
						}
					},
					error: function() {
						$('#pcw-live-activities').html('<p class="pcw-no-activity">Erro ao carregar</p>');
					}
				});
			},

			loadGA4Stats: function(clearCache) {
				// Verificar se a seção GA4 existe
				if ($('#pcw-ga4-comparison').length === 0) {
					return;
				}

				var period = $('#pcw-live-period').val() || '7days';

				// Mostrar loading
				$('#pcw-ga4-comparison').find('.pcw-comparison-values strong').text('-');

				$.ajax({
					url: pcwDashboardData.ajaxUrl,
					type: 'POST',
					timeout: 30000, // 30 segundos de timeout
					data: {
						action: 'pcw_get_ga4_stats',
						nonce: pcwDashboardData.nonce,
						period: period,
						clear_cache: clearCache ? '1' : ''
					},
					success: function(response) {
						console.log('GA4 AJAX response:', response);
						if (response.success) {
							LiveDashboard.renderGA4Stats(response.data);
						} else if (response.data && response.data.not_configured) {
							// GA4 não configurado - ignorar silenciosamente
							console.log('GA4 not configured');
						} else {
							var errorMsg = response.data ? response.data.message : 'Erro ao carregar';
							console.error('GA4 error:', response.data);
							LiveDashboard.showGA4Error(errorMsg, true);
						}
					},
					error: function(xhr, status, error) {
						console.error('GA4 AJAX error:', status, error, xhr.responseText);
						var errorMsg = 'Erro de conexão';
						if (status === 'timeout') {
							errorMsg = 'Tempo limite excedido. Tente novamente.';
						} else if (xhr.responseText) {
							try {
								var resp = JSON.parse(xhr.responseText);
								if (resp.data && resp.data.message) {
									errorMsg = resp.data.message;
								}
							} catch(e) {
								errorMsg = 'Erro: ' + xhr.status + ' - ' + error;
							}
						}
						LiveDashboard.showGA4Error(errorMsg, true);
					}
				});
			},

			renderGA4Stats: function(data) {
				// Usuários em tempo real
				if (data.realtime_users !== undefined) {
					$('#ga4-realtime-count').text(data.realtime_users);
					$('#ga4-realtime-badge').show();
				}

				// Comparação
				if (data.comparison) {
					var cmp = data.comparison;
					
					// Visitantes
					$('#cmp-visitors-internal').text(this.formatNumber(cmp.visitors.internal));
					$('#cmp-visitors-ga').text(this.formatNumber(cmp.visitors.ga));
					$('#cmp-visitors-diff').html(this.formatDiff(cmp.visitors.diff_pct));

					// Pageviews
					$('#cmp-pageviews-internal').text(this.formatNumber(cmp.pageviews.internal));
					$('#cmp-pageviews-ga').text(this.formatNumber(cmp.pageviews.ga));
					$('#cmp-pageviews-diff').html(this.formatDiff(cmp.pageviews.diff_pct));

					// Add to Cart
					$('#cmp-cart-internal').text(this.formatNumber(cmp.add_to_cart.internal));
					$('#cmp-cart-ga').text(this.formatNumber(cmp.add_to_cart.ga));
					$('#cmp-cart-diff').html(this.formatDiff(cmp.add_to_cart.diff_pct));

					// Pedidos
					$('#cmp-orders-internal').text(this.formatNumber(cmp.orders.internal));
					$('#cmp-orders-ga').text(this.formatNumber(cmp.orders.ga));
					$('#cmp-orders-diff').html(this.formatDiff(cmp.orders.diff_pct));
				}

				// Métricas de engajamento
				if (data.ga_stats) {
					var ga = data.ga_stats;
					$('#ga4-bounce-rate').text(ga.bounce_rate + '%');
					$('#ga4-engagement-rate').text(ga.engagement_rate + '%');
					$('#ga4-avg-duration').text(this.formatDuration(ga.avg_session_duration));
					$('#ga4-pages-session').text(ga.pages_per_session);

					// Fontes de tráfego
					if (ga.traffic_sources) {
						this.renderTrafficSources(ga.traffic_sources);
					}

					// Dispositivos
					if (ga.devices) {
						this.renderDevices(ga.devices);
					}

					// Países
					if (ga.countries) {
						this.renderCountries(ga.countries);
					}

					// Top páginas
					if (ga.top_pages) {
						this.renderTopPages(ga.top_pages);
					}
				}
			},

			formatDiff: function(diffPct) {
				if (diffPct === 0) {
					return '<span class="diff-neutral">=</span>';
				} else if (diffPct > 0) {
					return '<span class="diff-positive">+' + diffPct + '%</span>';
				} else {
					return '<span class="diff-negative">' + diffPct + '%</span>';
				}
			},

			formatDuration: function(seconds) {
				if (seconds < 60) {
					return seconds + 's';
				}
				var minutes = Math.floor(seconds / 60);
				var secs = seconds % 60;
				if (minutes < 60) {
					return minutes + 'm ' + secs + 's';
				}
				var hours = Math.floor(minutes / 60);
				var mins = minutes % 60;
				return hours + 'h ' + mins + 'm';
			},

			renderTrafficSources: function(sources) {
				var html = '';
				var total = sources.reduce(function(sum, s) { return sum + s.sessions; }, 0);
				
				sources.slice(0, 5).forEach(function(source) {
					var pct = total > 0 ? Math.round((source.sessions / total) * 100) : 0;
					html += '<div class="pcw-ga4-bar-item">';
					html += '<span class="label">' + source.name + '</span>';
					html += '<div class="bar-container"><div class="bar" style="width: ' + pct + '%"></div></div>';
					html += '<span class="value">' + pct + '%</span>';
					html += '</div>';
				});

				$('#ga4-traffic-sources').html(html || '<p class="pcw-no-data">Sem dados</p>');
			},

			renderDevices: function(devices) {
				var html = '';
				var total = devices.reduce(function(sum, d) { return sum + d.sessions; }, 0);
				var icons = {
					'desktop': '🖥️',
					'mobile': '📱',
					'tablet': '📱'
				};

				devices.forEach(function(device) {
					var pct = total > 0 ? Math.round((device.sessions / total) * 100) : 0;
					var icon = icons[device.name.toLowerCase()] || '📱';
					html += '<div class="pcw-ga4-device-item">';
					html += '<span class="icon">' + icon + '</span>';
					html += '<span class="label">' + device.name + '</span>';
					html += '<span class="value">' + pct + '%</span>';
					html += '</div>';
				});

				$('#ga4-devices').html(html || '<p class="pcw-no-data">Sem dados</p>');
			},

			renderCountries: function(countries) {
				var html = '';
				
				countries.slice(0, 5).forEach(function(country) {
					html += '<div class="pcw-ga4-list-item">';
					html += '<span class="label">' + country.name + '</span>';
					html += '<span class="value">' + LiveDashboard.formatNumber(country.sessions) + '</span>';
					html += '</div>';
				});

				$('#ga4-countries').html(html || '<p class="pcw-no-data">Sem dados</p>');
			},

			renderTopPages: function(pages) {
				var html = '<table class="pcw-ga4-pages-table">';
				html += '<thead><tr><th>Página</th><th>Views</th><th>Usuários</th></tr></thead>';
				html += '<tbody>';

				pages.slice(0, 10).forEach(function(page) {
					var displayName = page.name.length > 50 ? page.name.substring(0, 47) + '...' : page.name;
					html += '<tr>';
					html += '<td title="' + page.name + '">' + displayName + '</td>';
					html += '<td>' + LiveDashboard.formatNumber(page.sessions) + '</td>';
					html += '<td>' + LiveDashboard.formatNumber(page.users) + '</td>';
					html += '</tr>';
				});

				html += '</tbody></table>';
				$('#ga4-top-pages').html(html);
			},

			showGA4Error: function(message, showRetry) {
				var html = '<div class="pcw-notice pcw-notice-error" style="display: flex; align-items: center; gap: 15px;">' +
					'<span class="dashicons dashicons-warning"></span>' +
					'<span>' + message + '</span>';
				
				if (showRetry) {
					html += '<button type="button" class="button button-small" id="pcw-ga4-retry">' +
						'<span class="dashicons dashicons-update"></span> Tentar Novamente' +
						'</button>';
				}
				
				html += '</div>';
				$('#pcw-ga4-comparison').html(html);

				// Bind retry button
				$('#pcw-ga4-retry').on('click', function() {
					$(this).prop('disabled', true).find('.dashicons').addClass('spin');
					LiveDashboard.loadGA4Stats(true); // true = limpar cache
				});
			},
			
			renderActivities: function(activities) {
				if (activities.length === 0) {
					$('#pcw-live-activities').html('<p class="pcw-no-activity">Nenhuma atividade recente</p>');
					return;
				}
				
				var html = '';
				activities.forEach(function(activity) {
					var icon = LiveDashboard.getActivityIcon(activity.type);
					var userInfo = LiveDashboard.getUserInfo(activity);
					
					// Determinar nome a exibir
					var displayName = activity.object_name || activity.page_name || '';
					var typeLabel = LiveDashboard.getTypeLabel(activity.object_type);
					var url = activity.page_url || '';
					
					html += '<div class="pcw-activity-item">';
					
					// Imagem do produto ou icone
					if (activity.object_image && activity.type === 'product_view') {
						html += '<div class="pcw-activity-image"><img src="' + activity.object_image + '" alt=""></div>';
					} else {
						html += '<div class="pcw-activity-icon">' + icon + '</div>';
					}
					
					html += '<div class="pcw-activity-content">';
					
					// Titulo com badge de tipo e nome
					html += '<div class="pcw-activity-type">';
					html += '<span class="pcw-type-badge pcw-type-' + activity.object_type + '">' + typeLabel + '</span> ';
					if (displayName) {
						html += '<strong>' + displayName + '</strong>';
					}
					html += '</div>';
					
					// URL como link
					if (url) {
						html += '<div class="pcw-activity-details">';
						html += '<a href="' + url + '" target="_blank" class="pcw-activity-link">' + LiveDashboard.truncateUrl(url) + '</a>';
						html += '</div>';
					}
					
					// Meta: usuario e tempo
					html += '<div class="pcw-activity-meta">';
					html += userInfo;
					html += '<span class="pcw-activity-time">' + activity.time_ago + '</span>';
					html += '</div>';
					
					html += '</div>';
					html += '</div>';
				});
				
				$('#pcw-live-activities').html(html);
			},
			
			getTypeLabel: function(type) {
				var labels = {
					'home': 'Inicio',
					'shop': 'Loja',
					'category': 'Categoria',
					'product': 'Produto',
					'cart': 'Carrinho',
					'checkout': 'Checkout',
					'page': 'Pagina',
					'post': 'Artigo'
				};
				return labels[type] || type || 'Pagina';
			},
			
			getActivityIcon: function(type) {
				var icons = {
					'page_view': '<span class="dashicons dashicons-visibility"></span>',
					'product_view': '<span class="dashicons dashicons-cart"></span>',
					'add_to_cart': '<span class="dashicons dashicons-cart"></span>',
					'checkout_start': '<span class="dashicons dashicons-money-alt"></span>',
					'order_placed': '<span class="dashicons dashicons-yes-alt"></span>'
				};
				return icons[type] || '<span class="dashicons dashicons-marker"></span>';
			},
			
			getUserInfo: function(activity) {
				var html = '';
				
				if (activity.user_type === 'cliente' && activity.user_id) {
					html += '<span class="pcw-user-badge pcw-user-customer">';
					html += '<span class="dashicons dashicons-admin-users"></span> ';
					html += '<strong>' + activity.user_name + '</strong>';
					if (activity.user_email) {
						html += ' <span class="pcw-user-email">(' + activity.user_email + ')</span>';
					}
					html += '</span>';
				} else {
					html += '<span class="pcw-user-badge pcw-user-visitor">';
					html += '<span class="dashicons dashicons-groups"></span> Visitante';
					html += '</span>';
				}
				
				return html;
			},
			
			getActivityDescription: function(activity) {
				var pageName = activity.object_name || activity.page_name || activity.object_type || 'Pagina';
				var pageType = activity.object_type || '';
				
				// Traduzir tipos de pagina
				var typeLabels = {
					'home': 'Pagina Inicial',
					'shop': 'Loja',
					'category': 'Categoria',
					'cart': 'Carrinho',
					'checkout': 'Checkout',
					'page': 'Pagina',
					'post': 'Artigo'
				};
				
				var typeLabel = typeLabels[pageType] || pageType;
				
				var descriptions = {
					'page_view': { 
						title: '<span class="pcw-type-badge">' + typeLabel + '</span> ' + (activity.object_name ? '<strong>' + activity.object_name + '</strong>' : pageName), 
						details: activity.page_url ? '<a href="' + activity.page_url + '" target="_blank" class="pcw-activity-link">' + LiveDashboard.truncateUrl(activity.page_url) + '</a>' : ''
					},
					'product_view': { 
						title: '<span class="pcw-type-badge pcw-type-product">Produto</span> <strong>' + (activity.object_name || 'Produto #' + activity.object_id) + '</strong>', 
						details: activity.object_price ? activity.object_price : ''
					},
					'add_to_cart': { 
						title: '<span class="pcw-type-badge pcw-type-cart">Carrinho</span> Adicionou produto', 
						details: activity.object_name ? '<strong>' + activity.object_name + '</strong>' : ''
					},
					'checkout_start': { 
						title: '<span class="pcw-type-badge pcw-type-checkout">Checkout</span> Iniciou compra', 
						details: ''
					},
					'order_placed': { 
						title: '<span class="pcw-type-badge pcw-type-order">Pedido</span> Novo pedido', 
						details: activity.object_id ? '<strong>#' + activity.object_id + '</strong>' : ''
					}
				};
				
				return descriptions[activity.type] || { title: activity.type, details: pageName };
			},
			
			truncateUrl: function(url) {
				if (!url) return '';
				try {
					var parsed = new URL(url);
					var path = parsed.pathname;
					if (path.length > 40) {
						path = path.substring(0, 37) + '...';
					}
					return path || '/';
				} catch(e) {
					return url.length > 40 ? url.substring(0, 37) + '...' : url;
				}
			},
			
			formatNumber: function(num) {
				if (num >= 1000000) {
					return (num / 1000000).toFixed(1) + 'M';
				}
				if (num >= 1000) {
					return (num / 1000).toFixed(1) + 'K';
				}
				return num.toString();
			},
			
			startAutoRefresh: function() {
				this.refreshInterval = setInterval(function() {
					LiveDashboard.loadActivities();
				}, 30000); // Refresh every 30 seconds
			}
		};
		
		// Initialize Live Dashboard if elements exist
		if ($('#pcw-live-activities').length) {
			LiveDashboard.init();
		}
	});

})(jQuery);
