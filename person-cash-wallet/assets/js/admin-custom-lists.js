/**
 * Admin - Listas Personalizadas
 *
 * @package PersonCashWallet
 */

(function($) {
	'use strict';

	var PCWCustomLists = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			// Salvar lista
			$('#pcw-list-form').on('submit', this.handleSave.bind(this));

			// Upload de arquivo
			$('#pcw-upload-form').on('submit', this.handleUpload.bind(this));

			// Adicionar membro
			$('#pcw-add-member-form').on('submit', this.handleAddMember.bind(this));

			// Remover membro
			$(document).on('click', '.pcw-remove-member', this.handleRemoveMember.bind(this));

			// Deletar lista
			$(document).on('click', '.pcw-delete-list', this.handleDeleteList.bind(this));
		},

		handleSave: function(e) {
			e.preventDefault();

			var $form = $(e.currentTarget);
			var $submitBtn = $form.find('button[type="submit"]');
			var originalText = $submitBtn.html();

			var formData = new FormData($form[0]);
			formData.append('action', 'pcw_save_custom_list');
			formData.append('nonce', pcwCustomLists.nonce);

			$submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Salvando...');

			$.ajax({
				url: pcwCustomLists.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						window.location.href = response.data.redirect;
					} else {
						alert(response.data.message || 'Erro ao salvar');
					}
				},
				error: function() {
					alert('Erro ao processar requisição');
				},
				complete: function() {
					$submitBtn.prop('disabled', false).html(originalText);
				}
			});
		},

		handleUpload: function(e) {
			e.preventDefault();

			var $form = $(e.currentTarget);
			var $submitBtn = $form.find('button[type="submit"]');
			var originalText = $submitBtn.html();
			var $result = $('#upload-result');

			var formData = new FormData($form[0]);
			formData.append('action', 'pcw_upload_list_file');
			formData.append('nonce', pcwCustomLists.nonce);

			$submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Enviando...');
			$result.html('<p>Processando arquivo...</p>');

			$.ajax({
				url: pcwCustomLists.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						$result.html('<div class="pcw-notice pcw-notice-success">' + response.data.message + '</div>');
						$form[0].reset();
						// Recarregar após 2 segundos
						setTimeout(function() {
							window.location.reload();
						}, 2000);
					} else {
						$result.html('<div class="pcw-notice pcw-notice-error">' + response.data.message + '</div>');
					}
				},
				error: function() {
					$result.html('<div class="pcw-notice pcw-notice-error">Erro ao processar arquivo</div>');
				},
				complete: function() {
					$submitBtn.prop('disabled', false).html(originalText);
				}
			});
		},

		handleAddMember: function(e) {
			e.preventDefault();

			var $form = $(e.currentTarget);
			var $submitBtn = $form.find('button[type="submit"]');
			var originalText = $submitBtn.html();

			var formData = $form.serialize();
			formData += '&action=pcw_add_list_member&nonce=' + pcwCustomLists.nonce;

			$submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');

			$.ajax({
				url: pcwCustomLists.ajaxUrl,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						window.location.reload();
					} else {
						alert(response.data.message || 'Erro ao adicionar');
					}
				},
				error: function() {
					alert('Erro ao processar requisição');
				},
				complete: function() {
					$submitBtn.prop('disabled', false).html(originalText);
				}
			});
		},

		handleRemoveMember: function(e) {
			e.preventDefault();

			if (!confirm(pcwCustomLists.i18n.confirm_remove)) {
				return;
			}

			var $btn = $(e.currentTarget);
			var memberId = $btn.data('member-id');
			var listId = $('input[name="list_id"]').val();
			var originalText = $btn.html();

			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');

			$.ajax({
				url: pcwCustomLists.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pcw_remove_list_member',
					nonce: pcwCustomLists.nonce,
					list_id: listId,
					member_id: memberId
				},
				success: function(response) {
					if (response.success) {
						$btn.closest('tr').fadeOut(300, function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message || 'Erro ao remover');
						$btn.prop('disabled', false).html(originalText);
					}
				},
				error: function() {
					alert('Erro ao processar requisição');
					$btn.prop('disabled', false).html(originalText);
				}
			});
		},

		handleDeleteList: function(e) {
			e.preventDefault();

			if (!confirm(pcwCustomLists.i18n.confirm_delete)) {
				return;
			}

			var $btn = $(e.currentTarget);
			var listId = $btn.data('list-id');
			var originalText = $btn.html();

			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');

			$.ajax({
				url: pcwCustomLists.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pcw_delete_custom_list',
					nonce: pcwCustomLists.nonce,
					list_id: listId
				},
				success: function(response) {
					if (response.success) {
						$btn.closest('tr').fadeOut(300, function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message || 'Erro ao deletar');
						$btn.prop('disabled', false).html(originalText);
					}
				},
				error: function() {
					alert('Erro ao processar requisição');
					$btn.prop('disabled', false).html(originalText);
				}
			});
		}
	};

	$(document).ready(function() {
		PCWCustomLists.init();
	});

})(jQuery);
