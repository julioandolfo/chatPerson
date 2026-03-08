/**
 * Growly Digital - Integração com Pedidos Manuais
 * 
 * @package PersonCashWallet
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
	'use strict';
	
	var PCW_ManualOrders = {
		card: null,
		lastEmail: '',
		debounceTimer: null,
		nonce: pcwManualOrdersData.nonce || '',
		ajaxUrl: pcwManualOrdersData.ajaxUrl || ajaxurl,
		
		init: function() {
			this.monitorEmailField();
			this.setupReInitOnModalLoad();
			this.setupAjaxHooks();
		},
		
		getEmailSelectors: function() {
			return [
				'#_billing_email',
				'input[name="_billing_email"]',
				'input[name="billing_email"]',
				'#billing_email',
				'input[id*="billing_email"]',
				'input[name*="billing_email"]',
				'#customer_user',
				'select[name="customer_user"]',
				'select[name="_customer_user"]',
				'.wc-customer-search',
				'input.customer-email',
				'.customer_email',
				'[name="customer_email"]',
				'[name="customer[email]"]',
				'input[type="email"]'
			];
		},
		
		isEmailLike: function(value) {
			if (!value || typeof value !== 'string') {
				return false;
			}
			return value.indexOf('@') > 0 && value.indexOf('.') > value.indexOf('@');
		},
		
		extractEmailFromField: function($field) {
			if (!$field || !$field.length) {
				return '';
			}
			
			var email = '';
			
			if ($field.is('select')) {
				var selectedOption = $field.find('option:selected');
				var text = selectedOption.text();
				var emailMatch = text.match(/([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+)/);
				if (emailMatch) {
					email = emailMatch[1];
				}
			} else {
				email = $field.val();
			}
			
			return email;
		},
		
		setupAjaxHooks: function() {
			var self = this;
			
			// Interceptar respostas AJAX do plugin de manual orders
			$(document).ajaxComplete(function(event, xhr, settings) {
				if (!xhr || !xhr.responseText) {
					return;
				}
				
				// Verificar se é a action do plugin de manual orders
				var isUserSearchAction = false;
				
				if (settings && settings.data) {
					var dataString = typeof settings.data === 'string' ? settings.data : '';
					if (typeof settings.data === 'object') {
						try {
							dataString = $.param(settings.data);
						} catch (e) {
							dataString = JSON.stringify(settings.data);
						}
					}
					
					// Detectar action de busca de usuário
					if (dataString.indexOf('advanced_manual_orders_find_user') !== -1 ||
					    dataString.indexOf('find_user') !== -1) {
						isUserSearchAction = true;
					}
				}
				
				// Se é busca de usuário, processar resposta
				if (isUserSearchAction) {
					try {
						var json = JSON.parse(xhr.responseText);
						
						if (json && json.success && json.data) {
							var userData = json.data;
							var userId = userData.id;
							var userEmail = userData.email;
							
							if (userId && parseInt(userId) > 0) {
								self.fetchCashbackByUserId(userId);
							} else if (self.isEmailLike(userEmail)) {
								self.fetchCashback(userEmail);
							}
						}
					} catch (e) {
						// Ignorar erros de parsing
					}
				}
			});
		},
		
		createCard: function() {
			if (this.card && this.card.length && this.card.parent().length) {
				return;
			}
			
			this.card = $('<div>', {
				id: 'pcw-customer-cashback-card',
				'class': 'item-box pcw-cashback-box',
				html: '<div class="pcw-cashback-card-inner"><div class="pcw-cashback-loading"><span class="pcw-spinner"></span> Carregando...</div></div>'
			});
			
			var inserted = false;
			
			// 1. WC Advanced Manual Orders - inserir após o primeiro item-box (dados pessoais)
			var personalDataBox = $('form#new_manual_order .column-1 .item-box').first();
			if (personalDataBox.length) {
				personalDataBox.after(this.card);
				inserted = true;
			}
			
			// 2. Fallback: inserir no início da column-1
			if (!inserted) {
				var column1 = $('form#new_manual_order .column-1').first();
				if (column1.length) {
					column1.prepend(this.card);
					inserted = true;
				}
			}
			
			// 3. Fallback: inserir dentro de .new-order-container
			if (!inserted) {
				var orderContainer = $('.new-order-container').first();
				if (orderContainer.length) {
					orderContainer.prepend(this.card);
					inserted = true;
				}
			}
			
			// 4. Fallback: inserir após o headerbar
			if (!inserted) {
				var headerbar = $('.manual-orders-dashboard .headerbar').first();
				if (headerbar.length) {
					headerbar.after(this.card);
					inserted = true;
				}
			}
			
			// 5. Fallback: inserir no início do formulário
			if (!inserted) {
				var form = $('form#new_manual_order, form.manual-orders-dashboard').first();
				if (form.length) {
					form.prepend(this.card);
					inserted = true;
				}
			}
			
			// 6. Fallback: inserir em #wpcontent
			if (!inserted) {
				var wpcontent = $('#wpcontent').first();
				if (wpcontent.length) {
					wpcontent.prepend(this.card);
				}
			}
		},
		
		fetchCashback: function(email) {
			var self = this;
			
			if (!email || email === this.lastEmail) {
				return;
			}
			
			this.lastEmail = email;
			this.createCard();
			
			if (this.card) {
				this.card.addClass('pcw-show');
				this.card.find('.pcw-cashback-card-inner').html(
					'<div class="pcw-cashback-loading"><span class="pcw-spinner"></span> Buscando saldo do cliente...</div>'
				);
			}
			
			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pcw_get_customer_cashback',
					email: email,
					nonce: this.nonce
				},
				success: function(response) {
					if (response.success && response.data) {
						self.displayCashbackInfo(response.data);
					} else {
						var message = response.data && response.data.message ? response.data.message : 'Erro ao buscar dados';
						self.displayError(message);
					}
				},
				error: function() {
					self.displayError('Erro ao buscar saldo do cliente');
				}
			});
		},
		
		fetchCashbackByUserId: function(userId) {
			var self = this;
			
			if (!userId) {
				return;
			}
			
			this.createCard();
			
			if (this.card) {
				this.card.addClass('pcw-show');
				this.card.find('.pcw-cashback-card-inner').html(
					'<div class="pcw-cashback-loading"><span class="pcw-spinner"></span> Buscando saldo do cliente...</div>'
				);
			}
			
			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pcw_get_customer_cashback',
					user_id: userId,
					nonce: this.nonce
				},
				success: function(response) {
					if (response.success && response.data) {
						self.displayCashbackInfo(response.data);
					} else {
						var message = response.data && response.data.message ? response.data.message : 'Erro ao buscar dados';
						self.displayError(message);
					}
				},
				error: function() {
					self.displayError('Erro ao buscar saldo do cliente');
				}
			});
		},
		
		displayCashbackInfo: function(data) {
			if (!this.card) {
				return;
			}
			
			var html = '<div class="title">' +
				'<h3>💳 Saldo de Cashback - ' + data.customer_name + '</h3>' +
			'</div>' +
			'<div class="inner">' +
				'<div class="pcw-cashback-body">' +
					'<div class="pcw-cashback-stat">' +
						'<span class="pcw-cashback-stat-label">💰 Disponível</span>' +
						'<span class="pcw-cashback-stat-value">' + data.balance + '</span>' +
					'</div>' +
					'<div class="pcw-cashback-stat">' +
						'<span class="pcw-cashback-stat-label">⏳ Pendente</span>' +
						'<span class="pcw-cashback-stat-value">' + data.pending + '</span>' +
					'</div>' +
					'<div class="pcw-cashback-stat">' +
						'<span class="pcw-cashback-stat-label">📊 Total</span>' +
						'<span class="pcw-cashback-stat-value">' + data.total_earned + '</span>' +
					'</div>' +
				'</div>' +
			'</div>';
			
			this.card.find('.pcw-cashback-card-inner').html(html);
		},
		
		displayError: function(message) {
			var self = this;
			
			if (!this.card) {
				return;
			}
			
			this.card.find('.pcw-cashback-card-inner').html(
				'<div class="pcw-cashback-error">⚠️ ' + message + '</div>'
			);
			
			setTimeout(function() {
				if (self.card) {
					self.card.removeClass('pcw-show');
				}
				self.lastEmail = '';
			}, 3000);
		},
		
		monitorEmailField: function() {
			var self = this;
			var emailSelectors = this.getEmailSelectors();
			
			// Event delegation para campos dinâmicos
			$(document).on('change keyup blur input', emailSelectors.join(','), function() {
				clearTimeout(self.debounceTimer);
				
				var $field = $(this);
				var email = self.extractEmailFromField($field);
				
				if (self.isEmailLike(email)) {
					self.debounceTimer = setTimeout(function() {
						self.fetchCashback(email);
					}, 800);
				}
			});
			
			// Monitorar inputs de email genericamente
			$(document).on('change keyup blur input', 'input[type="email"]', function() {
				var email = $(this).val();
				
				if (self.isEmailLike(email)) {
					clearTimeout(self.debounceTimer);
					self.debounceTimer = setTimeout(function() {
						self.fetchCashback(email);
					}, 800);
				}
			});
			
			// Monitorar select2
			$(document).on('select2:select', emailSelectors.join(','), function() {
				$(this).trigger('change');
			});
		},
		
		setupReInitOnModalLoad: function() {
			var self = this;
			
			$(document).on('wc_backbone_modal_loaded', function() {
				setTimeout(function() {
					self.monitorEmailField();
				}, 500);
			});
			
			$(document).ajaxComplete(function() {
				setTimeout(function() {
					if (!self.card || !self.card.length) {
						self.card = null;
						self.monitorEmailField();
					}
				}, 1000);
			});
		}
	};
	
	PCW_ManualOrders.init();
	window.PCW_ManualOrders = PCW_ManualOrders;
});
