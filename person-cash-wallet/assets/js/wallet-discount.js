/**
 * Wallet Discount - Checkout Script
 *
 * @package PersonCashWallet
 */

(function($) {
	'use strict';

	var PCW_WalletDiscount = {
		/**
		 * Inicializar
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Vincular eventos
		 */
		bindEvents: function() {
			var self = this;

			// Aplicar desconto
			$(document).on('click', '.pcw-apply-wallet-discount', function(e) {
				e.preventDefault();
				self.applyDiscount($(this));
			});

			// Remover desconto
			$(document).on('click', '.pcw-remove-wallet-discount', function(e) {
				e.preventDefault();
				self.removeDiscount($(this));
			});

			// Botões de valor rápido
			$(document).on('click', '.pcw-quick-amount', function(e) {
				e.preventDefault();
				var amount = $(this).data('amount');
				$('#pcw_wallet_discount_amount').val(amount);
			});

			// Validar input
			$(document).on('input', '#pcw_wallet_discount_amount', function() {
				var max = parseFloat($(this).attr('max')) || 0;
				var value = parseFloat($(this).val()) || 0;

				if (value > max) {
					$(this).val(max);
				}

				if (value < 0) {
					$(this).val(0);
				}
			});

			// Atualizar após checkout ser atualizado
			$(document.body).on('updated_checkout', function() {
				// Re-vincular eventos se necessário
			});
		},

		/**
		 * Aplicar desconto
		 *
		 * @param {jQuery} $button Botão clicado
		 */
		applyDiscount: function($button) {
			var self = this;
			var $section = $('#pcw-wallet-discount-section');
			var amount = parseFloat($('#pcw_wallet_discount_amount').val()) || 0;
			var orderId = parseInt($('#pcw_wallet_order_id').val()) || 0;

			if (amount <= 0) {
				alert(pcw_wallet_discount.i18n.error);
				return;
			}

			$section.addClass('pcw-loading');
			$button.prop('disabled', true).text(pcw_wallet_discount.i18n.applying);

			$.ajax({
				url: pcw_wallet_discount.ajax_url,
				type: 'POST',
				data: {
					action: 'pcw_apply_wallet_discount',
					nonce: pcw_wallet_discount.nonce,
					amount: amount,
					order_id: orderId
				},
				success: function(response) {
					if (response.success) {
						// Atualizar checkout
						if (orderId > 0) {
							// Pay-order: recarregar página
							location.reload();
						} else {
							// Checkout: atualizar via WooCommerce
							$(document.body).trigger('update_checkout');
							
							// Recarregar seção após um pequeno delay
							setTimeout(function() {
								location.reload();
							}, 500);
						}
					} else {
						alert(response.data.message || pcw_wallet_discount.i18n.error);
						$section.removeClass('pcw-loading');
						$button.prop('disabled', false).text('Aplicar Desconto');
					}
				},
				error: function() {
					alert(pcw_wallet_discount.i18n.error);
					$section.removeClass('pcw-loading');
					$button.prop('disabled', false).text('Aplicar Desconto');
				}
			});
		},

		/**
		 * Remover desconto
		 *
		 * @param {jQuery} $button Botão clicado
		 */
		removeDiscount: function($button) {
			var $section = $('#pcw-wallet-discount-section');
			var orderId = parseInt($('#pcw_wallet_order_id').val()) || 0;

			$section.addClass('pcw-loading');
			$button.prop('disabled', true).text(pcw_wallet_discount.i18n.removing);

			$.ajax({
				url: pcw_wallet_discount.ajax_url,
				type: 'POST',
				data: {
					action: 'pcw_remove_wallet_discount',
					nonce: pcw_wallet_discount.nonce,
					order_id: orderId
				},
				success: function(response) {
					if (response.success) {
						// Atualizar checkout
						if (orderId > 0) {
							// Pay-order: recarregar página
							location.reload();
						} else {
							// Checkout: atualizar via WooCommerce
							$(document.body).trigger('update_checkout');
							
							// Recarregar seção após um pequeno delay
							setTimeout(function() {
								location.reload();
							}, 500);
						}
					} else {
						alert(response.data.message || pcw_wallet_discount.i18n.error);
						$section.removeClass('pcw-loading');
						$button.prop('disabled', false).text('Remover');
					}
				},
				error: function() {
					alert(pcw_wallet_discount.i18n.error);
					$section.removeClass('pcw-loading');
					$button.prop('disabled', false).text('Remover');
				}
			});
		}
	};

	// Inicializar quando documento estiver pronto
	$(document).ready(function() {
		PCW_WalletDiscount.init();
	});

})(jQuery);
