jQuery(document).ready(function($) {
	
	'use strict';

	// Modal de Número
	const modal = $('#number-modal');
	const form = $('#number-form');

	// Abrir modal para adicionar
	$(document).on('click', '#add-new-number, #add-first-number', function(e) {
		e.preventDefault();
		e.stopPropagation();
		resetForm();
		$('#modal-title').text('Adicionar Número WhatsApp');
		modal.fadeIn(200);
	});

	// Abrir modal para editar
	$(document).on('click', '.pcw-edit-number', function() {
		const card = $(this).closest('.pcw-number-card');
		const id = card.data('id');
		
		// Buscar dados do card (simplificado - em produção, buscar via AJAX)
		$('#number_id').val(id);
		$('#modal-title').text('Editar Número WhatsApp');
		
		// TODO: Carregar dados via AJAX
		modal.fadeIn(200);
	});

	// Fechar modal
	$('.pcw-modal-close, #cancel-number').on('click', function() {
		modal.fadeOut(200);
	});

	// Fechar modal ao clicar fora
	modal.on('click', function(e) {
		if ($(e.target).is('.pcw-modal')) {
			modal.fadeOut(200);
		}
	});

	// Resetar formulário
	function resetForm() {
		form[0].reset();
		$('#number_id').val('0');
		$('#number_distribution_enabled').prop('checked', true);
	}

	// Salvar número
	form.on('submit', function(e) {
		e.preventDefault();

		const submitBtn = $('#save-number');
		const originalText = submitBtn.html();
		
		submitBtn.prop('disabled', true)
			.html('<span class="dashicons dashicons-update spin"></span> Salvando...');

		const formData = {
			action: 'pcw_save_whatsapp_number',
			nonce: pcwQueue.nonce,
			id: $('#number_id').val(),
			phone_number: $('#number_phone').val(),
			name: $('#number_name').val(),
			rate_limit_hour: $('#number_rate_limit').val(),
			distribution_weight: $('#number_weight').val(),
			status: $('#number_status').val(),
			distribution_enabled: $('#number_distribution_enabled').is(':checked') ? 1 : 0
		};

		$.post(pcwQueue.ajaxUrl, formData, function(response) {
			if (response.success) {
				showNotice(response.data.message || pcwQueue.i18n.saveSuccess, 'success');
				setTimeout(function() {
					location.reload();
				}, 1000);
			} else {
				showNotice(response.data.message || pcwQueue.i18n.error, 'error');
				submitBtn.prop('disabled', false).html(originalText);
			}
		}).fail(function() {
			showNotice(pcwQueue.i18n.error, 'error');
			submitBtn.prop('disabled', false).html(originalText);
		});
	});

	// Deletar número
	$(document).on('click', '.pcw-delete-number', function() {
		if (!confirm(pcwQueue.i18n.confirmDelete)) {
			return;
		}

		const card = $(this).closest('.pcw-number-card');
		const id = card.data('id');
		const btn = $(this);

		btn.prop('disabled', true);

		$.post(pcwQueue.ajaxUrl, {
			action: 'pcw_delete_whatsapp_number',
			nonce: pcwQueue.nonce,
			id: id
		}, function(response) {
			if (response.success) {
				card.fadeOut(300, function() {
					$(this).remove();
					checkEmptyState();
				});
				showNotice(response.data.message || pcwQueue.i18n.deleteSuccess, 'success');
			} else {
				showNotice(response.data.message || pcwQueue.i18n.error, 'error');
				btn.prop('disabled', false);
			}
		}).fail(function() {
			showNotice(pcwQueue.i18n.error, 'error');
			btn.prop('disabled', false);
		});
	});

	// Atualizar estatísticas
	$('#refresh-stats').on('click', function() {
		const btn = $(this);
		const originalText = btn.html();

		btn.prop('disabled', true)
			.html('<span class="dashicons dashicons-update spin"></span> Atualizando...');

		$.post(pcwQueue.ajaxUrl, {
			action: 'pcw_get_queue_stats',
			nonce: pcwQueue.nonce
		}, function(response) {
			if (response.success) {
				updateStatsDisplay(response.data.stats);
				showNotice('Estatísticas atualizadas!', 'success');
			}
			btn.prop('disabled', false).html(originalText);
		}).fail(function() {
			btn.prop('disabled', false).html(originalText);
		});
	});

	// Limpar falhadas
	$('#clear-failed').on('click', function() {
		if (!confirm(pcwQueue.i18n.confirmClear)) {
			return;
		}

		const btn = $(this);
		btn.prop('disabled', true);

		$.post(pcwQueue.ajaxUrl, {
			action: 'pcw_clear_failed_queue',
			nonce: pcwQueue.nonce
		}, function(response) {
			if (response.success) {
				showNotice(response.data.message, 'success');
				setTimeout(function() {
					location.reload();
				}, 1000);
			} else {
				showNotice(response.data.message || pcwQueue.i18n.error, 'error');
				btn.prop('disabled', false);
			}
		});
	});

	// Reprocessar falhadas
	$('#retry-failed').on('click', function() {
		if (!confirm(pcwQueue.i18n.confirmRetry)) {
			return;
		}

		const btn = $(this);
		btn.prop('disabled', true);

		$.post(pcwQueue.ajaxUrl, {
			action: 'pcw_retry_failed_queue',
			nonce: pcwQueue.nonce
		}, function(response) {
			if (response.success) {
				showNotice(response.data.message, 'success');
				setTimeout(function() {
					location.reload();
				}, 1000);
			} else {
				showNotice(response.data.message || pcwQueue.i18n.error, 'error');
				btn.prop('disabled', false);
			}
		});
	});

	// Salvar estratégia de distribuição
	$('#distribution-form').on('submit', function(e) {
		e.preventDefault();

		const strategy = $('input[name="distribution_strategy"]:checked').val();
		const submitBtn = $(this).find('button[type="submit"]');
		const originalText = submitBtn.html();

		submitBtn.prop('disabled', true)
			.html('<span class="dashicons dashicons-update spin"></span> Salvando...');

		$.post(pcwQueue.ajaxUrl, {
			action: 'pcw_save_distribution_strategy',
			nonce: pcwQueue.nonce,
			strategy: strategy
		}, function(response) {
			if (response.success) {
				showNotice(response.data.message || pcwQueue.i18n.saveSuccess, 'success');
			} else {
				showNotice(response.data.message || pcwQueue.i18n.error, 'error');
			}
			submitBtn.prop('disabled', false).html(originalText);
		}).fail(function() {
			showNotice(pcwQueue.i18n.error, 'error');
			submitBtn.prop('disabled', false).html(originalText);
		});
	});

	// Toggle estratégia ativa
	$('.pcw-strategy-option input[type="radio"]').on('change', function() {
		$('.pcw-strategy-option').removeClass('active');
		$(this).closest('.pcw-strategy-option').addClass('active');
	});

	// Atualizar display de estatísticas
	function updateStatsDisplay(stats) {
		$('.pcw-stat-card').each(function() {
			const type = $(this).find('.pcw-stat-icon').attr('class').split(' ')[1].replace('pcw-stat-', '');
			if (stats[type] !== undefined) {
				$(this).find('.pcw-stat-value').text(stats[type].toLocaleString());
			}
		});
	}

	// Verificar estado vazio
	function checkEmptyState() {
		if ($('.pcw-number-card').length === 0) {
			location.reload();
		}
	}

	// Mostrar notificação
	function showNotice(message, type) {
		const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
		$('.pcw-page-header').after(notice);
		
		setTimeout(function() {
			notice.fadeOut(300, function() {
				$(this).remove();
			});
		}, 3000);
	}

	// Auto-refresh de estatísticas a cada 30 segundos (apenas na aba de filas)
	if ($('.pcw-queue-section').length > 0) {
		setInterval(function() {
			$('#refresh-stats').trigger('click');
		}, 30000);
	}

	// Pausar/retomar fila
	$(document).on('click', '#pcw-toggle-queue-pause', function(e) {
		e.preventDefault();
		
		var $btn = $(this);
		var originalText = $btn.html();
		var isPaused = $btn.data('paused') || $btn.closest('#pcw-queue-paused-banner').length > 0;
		
		var confirmMsg = isPaused 
			? 'Deseja retomar os disparos da fila?' 
			: 'Deseja PAUSAR todos os disparos da fila? Nenhuma mensagem será enviada enquanto estiver pausada.';
		
		if (!confirm(confirmMsg)) {
			return;
		}
		
		$btn.prop('disabled', true)
			.html('<span class="dashicons dashicons-update spin"></span> Aguarde...');
		
		$.post(pcwQueue.ajaxUrl, {
			action: 'pcw_toggle_queue_pause',
			nonce: pcwQueue.nonce
		}, function(response) {
			if (response.success) {
				showNotice(response.data.message, 'success');
				setTimeout(function() {
					location.reload();
				}, 1000);
			} else {
				showNotice(response.data.message || 'Erro', 'error');
				$btn.prop('disabled', false).html(originalText);
			}
		}).fail(function() {
			showNotice('Erro de conexão', 'error');
			$btn.prop('disabled', false).html(originalText);
		});
	});

	// Processar fila manualmente
	$(document).on('click', '#pcw-process-queue-now', function(e) {
		e.preventDefault();
		
		var $btn = $(this);
		var originalText = $btn.html();
		
		$btn.prop('disabled', true)
			.html('<span class="dashicons dashicons-update spin"></span> Processando...');
		
		$.post(pcwQueue.ajaxUrl, {
			action: 'pcw_process_queue_now',
			nonce: pcwQueue.nonce
		}, function(response) {
			if (response.success) {
				showNotice(response.data.message, 'success');
				// Atualizar estatísticas
				setTimeout(function() {
					location.reload();
				}, 1500);
			} else {
				showNotice(response.data.message || 'Erro ao processar fila', 'error');
			}
		}).fail(function() {
			showNotice('Erro ao processar fila', 'error');
		}).always(function() {
			$btn.prop('disabled', false).html(originalText);
		});
	});
});
