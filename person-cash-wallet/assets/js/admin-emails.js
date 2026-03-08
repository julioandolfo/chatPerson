/**
 * JavaScript para admin de emails
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

(function($) {
	'use strict';

	var PCWAdminEmails = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			// Visualizar email
			$(document).on('click', '.pcw-view-email', this.viewEmail.bind(this));

			// Reenviar email
			$(document).on('click', '.pcw-resend-email', this.resendEmail.bind(this));

			// Excluir email
			$(document).on('click', '.pcw-delete-email', this.deleteEmail.bind(this));

			// Fechar modal
			$(document).on('click', '.pcw-modal-close, .pcw-modal-overlay', this.closeModal.bind(this));

			// ESC para fechar modal
			$(document).on('keyup', function(e) {
				if (e.key === 'Escape') {
					this.closeModal();
				}
			}.bind(this));
		},

		viewEmail: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var id = $btn.data('id');

			$btn.prop('disabled', true);

			$.ajax({
				url: pcwAdminEmails.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pcw_view_email',
					nonce: pcwAdminEmails.nonce,
					id: id
				},
				success: function(response) {
					$btn.prop('disabled', false);

					if (response.success) {
						this.showEmailModal(response.data);
					} else {
						alert(response.data.message || pcwAdminEmails.i18n.error);
					}
				}.bind(this),
				error: function() {
					$btn.prop('disabled', false);
					alert(pcwAdminEmails.i18n.error);
				}
			});
		},

		showEmailModal: function(email) {
			var $modal = $('#pcw-email-modal');
			var $preview = $('#pcw-email-preview');

			var metaHtml = '<div class="pcw-email-meta">';
			metaHtml += '<div class="pcw-email-meta-item"><span class="pcw-email-meta-label">Destinatário:</span><span class="pcw-email-meta-value">' + this.escapeHtml(email.recipient) + '</span></div>';
			metaHtml += '<div class="pcw-email-meta-item"><span class="pcw-email-meta-label">Assunto:</span><span class="pcw-email-meta-value">' + this.escapeHtml(email.subject) + '</span></div>';
			metaHtml += '<div class="pcw-email-meta-item"><span class="pcw-email-meta-label">Tipo:</span><span class="pcw-email-meta-value">' + this.escapeHtml(email.email_type) + '</span></div>';
			metaHtml += '<div class="pcw-email-meta-item"><span class="pcw-email-meta-label">Status:</span><span class="pcw-email-meta-value"><span class="pcw-badge pcw-badge-' + email.status + '">' + (email.status === 'sent' ? 'Enviado' : 'Falha') + '</span></span></div>';
			metaHtml += '<div class="pcw-email-meta-item"><span class="pcw-email-meta-label">Data:</span><span class="pcw-email-meta-value">' + this.escapeHtml(email.created_at) + '</span></div>';
			metaHtml += '</div>';

			// Criar iframe para exibir o conteúdo HTML do email
			var iframeHtml = '<iframe id="email-content-frame" sandbox="allow-same-origin"></iframe>';

			$preview.html(metaHtml + iframeHtml);

			// Aguardar o iframe ser inserido no DOM e então inserir o conteúdo
			setTimeout(function() {
				var iframe = document.getElementById('email-content-frame');
				if (iframe) {
					var doc = iframe.contentDocument || iframe.contentWindow.document;
					doc.open();
					doc.write(email.content);
					doc.close();
				}
			}, 100);

			$modal.show();
		},

		closeModal: function() {
			$('#pcw-email-modal').hide();
			$('#pcw-email-preview').html('');
		},

		resendEmail: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var id = $btn.data('id');

			if (!confirm(pcwAdminEmails.i18n.confirmResend)) {
				return;
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: pcwAdminEmails.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pcw_resend_email',
					nonce: pcwAdminEmails.nonce,
					id: id
				},
				success: function(response) {
					$btn.prop('disabled', false);

					if (response.success) {
						alert(pcwAdminEmails.i18n.resent);
					} else {
						alert(response.data.message || pcwAdminEmails.i18n.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					alert(pcwAdminEmails.i18n.error);
				}
			});
		},

		deleteEmail: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var $row = $btn.closest('tr');
			var id = $btn.data('id');

			if (!confirm(pcwAdminEmails.i18n.confirmDelete)) {
				return;
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: pcwAdminEmails.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pcw_delete_email_log',
					nonce: pcwAdminEmails.nonce,
					id: id
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(300, function() {
							$(this).remove();
						});
					} else {
						$btn.prop('disabled', false);
						alert(response.data.message || pcwAdminEmails.i18n.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					alert(pcwAdminEmails.i18n.error);
				}
			});
		},

		escapeHtml: function(text) {
			if (!text) return '';
			var div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}
	};

	$(document).ready(function() {
		PCWAdminEmails.init();
	});

})(jQuery);
