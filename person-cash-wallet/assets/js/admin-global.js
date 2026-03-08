/**
 * Growly Digital - Admin Global JavaScript
 * 
 * @package GrowlyDigital
 * @since 1.3.0
 */

(function($) {
	'use strict';

	const PCWAdminGlobal = {
		/**
		 * Inicializar
		 */
		init: function() {
			this.initTabs();
			this.initModals();
			this.initTooltips();
			this.initConfirm();
		},

		/**
		 * Gerenciar tabs
		 */
		initTabs: function() {
			// Tabs via links
			$(document).on('click', '.pcw-tab-link', function(e) {
				e.preventDefault();
				const $link = $(this);
				const tabId = $link.data('tab');

				// Atualizar navegação
				$link.closest('.pcw-tabs-nav').find('.pcw-tab-link').removeClass('active');
				$link.addClass('active');

				// Mostrar painel correspondente
				$('.pcw-tab-panel').removeClass('active');
				$('#' + tabId).addClass('active');

				// Atualizar URL hash sem scroll
				if (history.pushState) {
					history.pushState(null, null, '#' + tabId);
				} else {
					location.hash = '#' + tabId;
				}
			});

			// Verificar hash na URL ao carregar
			if (window.location.hash) {
				const hash = window.location.hash.substring(1);
				const $tab = $('.pcw-tab-link[data-tab="' + hash + '"]');
				if ($tab.length) {
					$tab.trigger('click');
				}
			}
		},

		/**
		 * Gerenciar modais
		 */
		initModals: function() {
			// Fechar modal ao clicar no overlay
			$(document).on('click', '.pcw-modal-overlay', function(e) {
				if ($(e.target).hasClass('pcw-modal-overlay')) {
					$(this).fadeOut(200, function() {
						$(this).remove();
					});
				}
			});

			// Fechar modal com botão close
			$(document).on('click', '.pcw-modal-close', function() {
				$(this).closest('.pcw-modal-overlay').fadeOut(200, function() {
					$(this).remove();
				});
			});

			// Fechar com ESC
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && $('.pcw-modal-overlay').length) {
					$('.pcw-modal-overlay').fadeOut(200, function() {
						$(this).remove();
					});
				}
			});
		},

		/**
		 * Tooltips simples
		 */
		initTooltips: function() {
			$('.pcw-tooltip').hover(
				function() {
					$(this).attr('data-tooltip-visible', 'true');
				},
				function() {
					$(this).removeAttr('data-tooltip-visible');
				}
			);
		},

		/**
		 * Confirmação de ações
		 */
		initConfirm: function() {
			$(document).on('click', '[data-pcw-confirm]', function(e) {
				const message = $(this).data('pcw-confirm');
				if (!confirm(message)) {
					e.preventDefault();
					return false;
				}
			});
		}
	};

	// Inicializar quando o DOM estiver pronto
	$(document).ready(function() {
		PCWAdminGlobal.init();
	});

	// Expor globalmente
	window.PCWAdminGlobal = PCWAdminGlobal;

})(jQuery);
