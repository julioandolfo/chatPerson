/**
 * Script do formulário de inscrição
 * @since 1.4.0
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		$('.pcw-subscribe-form__form').on('submit', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $button = $form.find('.pcw-subscribe-form__submit');
			var $message = $form.find('.pcw-subscribe-form__message');

			// Desabilitar botão
			$button.prop('disabled', true).text('Enviando...');
			$message.hide().removeClass('pcw-subscribe-form__message--success pcw-subscribe-form__message--error');

			// Coletar dados
			var data = {
				action: 'pcw_subscribe',
				pcw_subscribe_nonce: $form.find('[name="pcw_subscribe_nonce"]').val(),
				name: $form.find('[name="name"]').val(),
				email: $form.find('[name="email"]').val(),
				phone: $form.find('[name="phone"]').val(),
				list_id: $form.data('list-id'),
				automation_id: $form.data('automation-id')
			};

			// Enviar via AJAX
			$.post(pcwSubscribeForm.ajax_url, data, function(response) {
				if (response.success) {
					$message
						.addClass('pcw-subscribe-form__message--success')
						.html(response.data.message)
						.fadeIn();

					// Limpar formulário
					$form[0].reset();

					// Redirecionar se configurado
					var redirectUrl = $form.data('redirect');
					if (redirectUrl) {
						setTimeout(function() {
							window.location.href = redirectUrl;
						}, 2000);
					}
				} else {
					$message
						.addClass('pcw-subscribe-form__message--error')
						.html(response.data.message)
						.fadeIn();
				}
			}).fail(function() {
				$message
					.addClass('pcw-subscribe-form__message--error')
					.html(pcwSubscribeForm.messages.error)
					.fadeIn();
			}).always(function() {
				// Reabilitar botão
				$button.prop('disabled', false).text($button.data('original-text') || 'Inscrever');
			});
		});

		// Salvar texto original do botão
		$('.pcw-subscribe-form__submit').each(function() {
			$(this).data('original-text', $(this).text());
		});
	});

})(jQuery);
