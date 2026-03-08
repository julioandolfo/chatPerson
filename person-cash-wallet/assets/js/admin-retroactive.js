/**
 * Admin - Cashback Retroativo
 *
 * @package PersonCashWallet
 */

(function($) {
	'use strict';

	var PCW_Retroactive = {
		init: function() {
			console.log('PCW_Retroactive init, pcwRetroactive:', typeof pcwRetroactive !== 'undefined');
			this.initSelect2();
			this.bindEvents();
			this.handleConditionalFields();
		},

		initSelect2: function() {
			// Select2 para status
			$('#status').select2({
				placeholder: 'Selecione os status',
				width: '100%'
			});

			// Select2 com AJAX para clientes
			$('#customers').select2({
				ajax: {
					url: pcwRetroactive.ajaxurl,
					dataType: 'json',
					delay: 250,
					data: function(params) {
						return {
							action: 'pcw_search_customers',
							nonce: pcwRetroactive.nonce,
							q: params.term
						};
					},
					processResults: function(data) {
						return {
							results: data.results
						};
					}
				},
				placeholder: 'Buscar clientes...',
				minimumInputLength: 2,
				width: '100%'
			});

			// Select2 com AJAX para produtos
			$('#products').select2({
				ajax: {
					url: pcwRetroactive.ajaxurl,
					dataType: 'json',
					delay: 250,
					data: function(params) {
						return {
							action: 'pcw_search_products',
							nonce: pcwRetroactive.nonce,
							q: params.term
						};
					},
					processResults: function(data) {
						return {
							results: data.results
						};
					}
				},
				placeholder: 'Buscar produtos...',
				minimumInputLength: 2,
				width: '100%'
			});

			// Select2 simples
			$('.pcw-select').not('#status, #customers, #products').select2({
				width: '100%'
			});
		},

		bindEvents: function() {
			var self = this;

			// Buscar pedidos
			$('#pcw-search-orders').on('click', function() {
				self.searchOrders();
			});

			// Processar cashback
			$(document).on('click', '#pcw-process-cashback', function() {
				self.confirmProcess();
			});

			// Reverter batch
			$(document).on('click', '.pcw-revert-batch', function() {
				var batchId = $(this).data('batch-id');
				self.confirmRevert(batchId);
			});

			// Cancelar batch
			$(document).on('click', '.pcw-cancel-batch', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var batchId = $(this).data('batch-id');
				console.log('Cancel clicked, batch:', batchId);
				if (batchId) {
					self.confirmCancel(batchId);
				} else {
					alert('Batch ID não encontrado no botão');
				}
			});

			// Novo processamento
			$(document).on('click', '#pcw-new-process', function() {
				window.location.reload();
			});

			// Download CSV
			$(document).on('click', '#pcw-download-csv', function() {
				self.downloadCSV();
			});

			// Campos condicionais
			$('#cashback_rule').on('change', function() {
				self.handleConditionalFields();
			});
		},

		handleConditionalFields: function() {
			var selectedValue = $('#cashback_rule').val();

			$('.pcw-conditional').hide();

			$('.pcw-conditional[data-show-value="' + selectedValue + '"]').show();
		},

		searchOrders: function() {
			var self = this;
			var $button = $('#pcw-search-orders');
			var originalText = $button.html();

			// Validar
			var filters = this.getFilters();

			$button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Buscando...');

			// Esconder preview anterior
			$('#pcw-preview-container').hide();

			var ajaxData = {
				action: 'pcw_retroactive_preview',
				nonce: pcwRetroactive.nonce
			};
			
			// Merge filters into ajaxData
			for (var key in filters) {
				if (filters.hasOwnProperty(key)) {
					ajaxData[key] = filters[key];
				}
			}

			$.ajax({
				url: pcwRetroactive.ajaxurl,
				type: 'POST',
				data: ajaxData,
				success: function(response) {
					if (response.success) {
						self.renderPreview(response.data);
					} else {
						alert(response.data.message || pcwRetroactive.i18n.error);
					}
				},
				error: function() {
					alert(pcwRetroactive.i18n.error);
				},
				complete: function() {
					$button.prop('disabled', false).html(originalText);
				}
			});
		},

		renderPreview: function(data) {
			// Renderizar resumo
			var summaryHTML = '<div class="pcw-summary-item">' +
				'<div class="pcw-summary-icon pcw-summary-icon-orders">' +
					'<span class="dashicons dashicons-cart"></span>' +
				'</div>' +
				'<div class="pcw-summary-content">' +
					'<div class="pcw-summary-label">Pedidos Encontrados</div>' +
					'<div class="pcw-summary-value">' + data.total_orders + '</div>' +
				'</div>' +
			'</div>' +
			'<div class="pcw-summary-item">' +
				'<div class="pcw-summary-icon pcw-summary-icon-cashback">' +
					'<span class="dashicons dashicons-money-alt"></span>' +
				'</div>' +
				'<div class="pcw-summary-content">' +
					'<div class="pcw-summary-label">Cashback a Gerar</div>' +
					'<div class="pcw-summary-value">' + this.formatMoney(data.total_cashback) + '</div>' +
				'</div>' +
			'</div>' +
			'<div class="pcw-summary-item">' +
				'<div class="pcw-summary-icon pcw-summary-icon-customers">' +
					'<span class="dashicons dashicons-groups"></span>' +
				'</div>' +
				'<div class="pcw-summary-content">' +
					'<div class="pcw-summary-label">Clientes Beneficiados</div>' +
					'<div class="pcw-summary-value">' + data.customers_count + '</div>' +
				'</div>' +
			'</div>' +
			'<div class="pcw-summary-item">' +
				'<div class="pcw-summary-icon pcw-summary-icon-total">' +
					'<span class="dashicons dashicons-chart-line"></span>' +
				'</div>' +
				'<div class="pcw-summary-content">' +
					'<div class="pcw-summary-label">Valor Total Pedidos</div>' +
					'<div class="pcw-summary-value">' + this.formatMoney(data.total_amount) + '</div>' +
				'</div>' +
			'</div>';

			$('#pcw-preview-summary').html(summaryHTML);

			// Renderizar tabela
			if (data.orders.length === 0) {
				$('#pcw-preview-table').html(
					'<div class="pcw-empty-state">' +
						'<span class="dashicons dashicons-info"></span>' +
						'<h3>Nenhum pedido encontrado</h3>' +
						'<p>Tente ajustar os filtros e buscar novamente.</p>' +
					'</div>'
				);
				$('#pcw-process-cashback').hide();
			} else {
				var tableHTML = '<div class="pcw-table-header">' +
					'<h3>Preview dos Primeiros ' + data.orders.length + ' Pedidos</h3>' +
				'</div>' +
				'<table class="wp-list-table widefat fixed striped">' +
					'<thead>' +
						'<tr>' +
							'<th>Pedido</th>' +
							'<th>Cliente</th>' +
							'<th>Data</th>' +
							'<th>Valor</th>' +
							'<th>Cashback</th>' +
							'<th>Status Atual</th>' +
						'</tr>' +
					'</thead>' +
					'<tbody>';

				data.orders.forEach(function(order) {
					var statusBadge = order.has_cashback 
						? '<span class="pcw-badge pcw-badge-warning">Já tem cashback</span>'
						: '<span class="pcw-badge pcw-badge-pending">Sem cashback</span>';

					tableHTML += '<tr>' +
						'<td>#' + order.number + '</td>' +
						'<td>' + order.customer + '</td>' +
						'<td>' + order.date + '</td>' +
						'<td>' + PCW_Retroactive.formatMoney(order.total) + '</td>' +
						'<td><strong>' + PCW_Retroactive.formatMoney(order.cashback) + '</strong></td>' +
						'<td>' + statusBadge + '</td>' +
					'</tr>';
				});

				tableHTML += '</tbody></table>';

				if (data.total_orders > data.orders.length) {
					tableHTML += '<p class="description" style="margin-top: 10px;">Mostrando ' + data.orders.length + ' de ' + data.total_orders + ' pedidos. Todos os pedidos serão processados.</p>';
				}

				$('#pcw-preview-table').html(tableHTML);
				$('#pcw-process-cashback').show();
			}

			// Mostrar card de preview
			$('#pcw-preview-container').slideDown();

			// Scroll suave para o preview
			$('html, body').animate({
				scrollTop: $('#pcw-preview-container').offset().top - 100
			}, 500);
		},

		confirmProcess: function() {
			if (!confirm(pcwRetroactive.i18n.confirm_process)) {
				return;
			}

			this.processRetroactive();
		},

		processRetroactive: function() {
			var self = this;
			var filters = this.getFilters();

			// Esconder preview e mostrar processando
			$('#pcw-preview-container').hide();
			$('#pcw-processing-container').show();

			// Scroll para processando
			$('html, body').animate({
				scrollTop: $('#pcw-processing-container').offset().top - 100
			}, 500);

			// Dados de controle
			var batchId = null;
			var offset = 0;
			var totalProcessed = 0;
			var totalSuccess = 0;
			var totalErrors = 0;
			var totalAmount = 0;

			function processBatch() {
				var requestData = {
					action: 'pcw_retroactive_process',
					nonce: pcwRetroactive.nonce,
					offset: offset
				};

				// Merge filters
				for (var key in filters) {
					if (filters.hasOwnProperty(key)) {
						requestData[key] = filters[key];
					}
				}

				if (batchId) {
					requestData.batch_id = batchId;
				}

				$.ajax({
					url: pcwRetroactive.ajaxurl,
					type: 'POST',
					data: requestData,
					success: function(response) {
						if (response.success) {
							var data = response.data;

							// Armazenar batch_id
							if (!batchId) {
								batchId = data.batch_id;
							}

							// Atualizar totais
							totalProcessed = data.processed;
							totalSuccess += data.success;
							totalErrors += data.errors;
							totalAmount += data.amount;

							// Atualizar progresso
							var percentage = Math.round((totalProcessed / data.total) * 100);
							$('#pcw-progress-fill').css('width', percentage + '%');

							// Atualizar info
							var infoHTML = '<div class="pcw-processing-stats">' +
								'<div class="pcw-stat">' +
									'<span class="dashicons dashicons-yes-alt"></span>' +
									'<strong>' + totalSuccess + '</strong> cashbacks gerados' +
								'</div>' +
								'<div class="pcw-stat pcw-stat-error">' +
									'<span class="dashicons dashicons-dismiss"></span>' +
									'<strong>' + totalErrors + '</strong> erros' +
								'</div>' +
								'<div class="pcw-stat">' +
									'<span class="dashicons dashicons-chart-line"></span>' +
									'<strong>' + percentage + '%</strong> (' + totalProcessed + '/' + data.total + ' pedidos)' +
								'</div>' +
							'</div>';
							$('#pcw-processing-info').html(infoHTML);

							// Adicionar logs
							if (data.logs && data.logs.length > 0) {
								data.logs.forEach(function(log) {
									$('#pcw-processing-logs').append('<div class="pcw-log-entry">' + log + '</div>');
								});

								// Auto-scroll logs
								var logsContainer = $('#pcw-processing-logs')[0];
								logsContainer.scrollTop = logsContainer.scrollHeight;
							}

							// Se não concluiu, processar próximo lote
							if (!data.completed) {
								offset = data.offset;
								processBatch();
							} else {
								// Concluído!
								self.showResult({
									total: data.total,
									success: totalSuccess,
									errors: totalErrors,
									amount: totalAmount,
									batchId: batchId
								});
							}
						} else {
							alert(response.data.message || pcwRetroactive.i18n.error);
							$('#pcw-processing-container').hide();
							$('#pcw-preview-container').show();
						}
					},
					error: function() {
						alert(pcwRetroactive.i18n.error);
						$('#pcw-processing-container').hide();
						$('#pcw-preview-container').show();
					}
				});
			}

			// Iniciar processamento
			processBatch();
		},

		showResult: function(data) {
			// Esconder processando
			$('#pcw-processing-container').hide();

			// Mostrar resultado
			var resultHTML = '<div class="pcw-result-success">' +
				'<span class="dashicons dashicons-yes-alt"></span>' +
				'<h3>Processamento Concluído com Sucesso!</h3>' +
			'</div>' +
			'<div class="pcw-summary-grid">' +
				'<div class="pcw-summary-item">' +
					'<div class="pcw-summary-icon pcw-summary-icon-orders">' +
						'<span class="dashicons dashicons-cart"></span>' +
					'</div>' +
					'<div class="pcw-summary-content">' +
						'<div class="pcw-summary-label">Total Processado</div>' +
						'<div class="pcw-summary-value">' + data.total + ' pedidos</div>' +
					'</div>' +
				'</div>' +
				'<div class="pcw-summary-item">' +
					'<div class="pcw-summary-icon pcw-summary-icon-success">' +
						'<span class="dashicons dashicons-yes"></span>' +
					'</div>' +
					'<div class="pcw-summary-content">' +
						'<div class="pcw-summary-label">Cashbacks Gerados</div>' +
						'<div class="pcw-summary-value">' + data.success + '</div>' +
					'</div>' +
				'</div>' +
				'<div class="pcw-summary-item">' +
					'<div class="pcw-summary-icon pcw-summary-icon-error">' +
						'<span class="dashicons dashicons-dismiss"></span>' +
					'</div>' +
					'<div class="pcw-summary-content">' +
						'<div class="pcw-summary-label">Erros</div>' +
						'<div class="pcw-summary-value">' + data.errors + '</div>' +
					'</div>' +
				'</div>' +
				'<div class="pcw-summary-item">' +
					'<div class="pcw-summary-icon pcw-summary-icon-cashback">' +
						'<span class="dashicons dashicons-money-alt"></span>' +
					'</div>' +
					'<div class="pcw-summary-content">' +
						'<div class="pcw-summary-label">Valor Total em Cashback</div>' +
						'<div class="pcw-summary-value">' + this.formatMoney(data.amount) + '</div>' +
					'</div>' +
				'</div>' +
			'</div>';

			$('#pcw-result-summary').html(resultHTML);
			$('#pcw-result-container').show();

			// Armazenar batch_id para CSV
			$('#pcw-download-csv').data('batch-id', data.batchId);

			// Scroll para resultado
			$('html, body').animate({
				scrollTop: $('#pcw-result-container').offset().top - 100
			}, 500);
		},

		confirmRevert: function(batchId) {
			if (!confirm(pcwRetroactive.i18n.confirm_revert)) {
				return;
			}

			var $button = $('.pcw-revert-batch[data-batch-id="' + batchId + '"]');
			var originalText = $button.html();

			$button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');

			$.ajax({
				url: pcwRetroactive.ajaxurl,
				type: 'POST',
				data: {
					action: 'pcw_retroactive_revert',
					nonce: pcwRetroactive.nonce,
					batch_id: batchId
				},
				success: function(response) {
					if (response.success) {
						alert('Reversão concluída!\n\n✓ ' + response.data.reverted + ' cashbacks revertidos\n✗ ' + response.data.failed + ' não puderam ser revertidos');
						window.location.reload();
					} else {
						alert(response.data.message || pcwRetroactive.i18n.error);
					}
				},
				error: function() {
					alert(pcwRetroactive.i18n.error);
				},
				complete: function() {
					$button.prop('disabled', false).html(originalText);
				}
			});
		},

		confirmCancel: function(batchId) {
			console.log('confirmCancel called with batchId:', batchId);
			
			if (typeof pcwRetroactive === 'undefined') {
				alert('Erro: pcwRetroactive não está definido. Recarregue a página.');
				return;
			}

			var confirmMsg = pcwRetroactive.i18n && pcwRetroactive.i18n.confirm_cancel 
				? pcwRetroactive.i18n.confirm_cancel 
				: 'Tem certeza que deseja cancelar este lote?';
			
			if (!confirm(confirmMsg)) {
				return;
			}

			var $button = $('.pcw-cancel-batch[data-batch-id="' + batchId + '"]');
			var originalText = $button.html();

			$button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Cancelando...');

			console.log('Sending AJAX to:', pcwRetroactive.ajaxurl);

			$.ajax({
				url: pcwRetroactive.ajaxurl,
				type: 'POST',
				data: {
					action: 'pcw_retroactive_cancel',
					nonce: pcwRetroactive.nonce,
					batch_id: batchId
				},
				success: function(response) {
					console.log('AJAX response:', response);
					if (response.success) {
						alert(response.data.message || 'Lote cancelado com sucesso!');
						window.location.reload();
					} else {
						alert(response.data ? response.data.message : 'Erro ao cancelar');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error:', status, error);
					alert('Erro ao processar: ' + error);
				},
				complete: function() {
					$button.prop('disabled', false).html(originalText);
				}
			});
		},

		downloadCSV: function() {
			var batchId = $('#pcw-download-csv').data('batch-id');

			if (!batchId) {
				alert('Batch ID não encontrado.');
				return;
			}

			// Gerar CSV a partir dos logs
			var logs = $('#pcw-processing-logs .pcw-log-entry').map(function() {
				return $(this).text();
			}).get();

			var csv = 'Pedido,Status,Detalhes\n';
			logs.forEach(function(log) {
				csv += '"' + log.replace(/"/g, '""') + '"\n';
			});

			// Download
			var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
			var link = document.createElement('a');
			var url = URL.createObjectURL(blob);

			link.setAttribute('href', url);
			link.setAttribute('download', 'cashback-retroativo-' + batchId + '.csv');
			link.style.visibility = 'hidden';

			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
		},

		getFilters: function() {
			return {
				date_from: $('#date_from').val(),
				date_to: $('#date_to').val(),
				status: $('#status').val() || ['wc-completed'],
				min_amount: $('#min_amount').val(),
				max_amount: $('#max_amount').val(),
				customers: $('#customers').val() || [],
				products: $('#products').val() || [],
				cashback_rule: $('#cashback_rule').val(),
				rule_id: $('#rule_id').val(),
				fixed_amount: $('#fixed_amount').val(),
				percentage_value: $('#percentage_value').val(),
				ignore_existing: $('#ignore_existing').is(':checked') ? 1 : 0,
				send_email: $('#send_email').is(':checked') ? 1 : 0
			};
		},

		formatMoney: function(value) {
			return 'R$ ' + parseFloat(value).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
		}
	};

	$(document).ready(function() {
		PCW_Retroactive.init();
	});

})(jQuery);
