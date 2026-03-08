/**
 * Editor Visual de Email - Growly Digital
 * Editor personalizado em Português
 * 
 * @package GrowlyDigital
 * @since 1.2.0
 */

(function($) {
	'use strict';

	window.PCWEmailEditor = {
		modal: null,
		targetField: null,
		selectedElement: null,
		options: {},

		/**
		 * Blocos disponíveis
		 */
		blocks: {
			basico: {
				label: 'Basico',
				icon: 'dashicons-editor-textcolor',
				items: {
					'text': {
						label: 'Texto',
						icon: 'dashicons-editor-paragraph',
						html: '<p style="font-family: Arial, sans-serif; font-size: 14px; color: #333333; line-height: 1.6; margin: 0 0 15px 0; padding: 10px;">Clique para editar este texto.</p>'
					},
					'heading1': {
						label: 'Titulo H1',
						icon: 'dashicons-heading',
						html: '<h1 style="font-family: Arial, sans-serif; font-size: 32px; font-weight: bold; color: #333333; margin: 0 0 20px 0; padding: 10px;">Titulo Principal</h1>'
					},
					'heading2': {
						label: 'Titulo H2',
						icon: 'dashicons-heading',
						html: '<h2 style="font-family: Arial, sans-serif; font-size: 24px; font-weight: bold; color: #333333; margin: 0 0 15px 0; padding: 10px;">Subtitulo</h2>'
					},
					'heading3': {
						label: 'Titulo H3',
						icon: 'dashicons-heading',
						html: '<h3 style="font-family: Arial, sans-serif; font-size: 18px; font-weight: bold; color: #333333; margin: 0 0 10px 0; padding: 10px;">Titulo da Secao</h3>'
					},
					'image': {
						label: 'Imagem',
						icon: 'dashicons-format-image',
						html: '<div style="text-align: center; padding: 10px;"><img src="https://via.placeholder.com/500x200/e0e0e0/666666?text=Clique+para+editar" alt="Imagem" style="max-width: 100%; height: auto;"></div>'
					},
					'button': {
						label: 'Botao',
						icon: 'dashicons-button',
						html: '<div style="text-align: center; padding: 20px;"><a href="#" style="display: inline-block; background: #667eea; color: #ffffff; padding: 14px 35px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; font-family: Arial, sans-serif;">Clique Aqui</a></div>'
					},
					'divider': {
						label: 'Divisor',
						icon: 'dashicons-minus',
						html: '<div style="padding: 15px 0;"><hr style="border: none; border-top: 1px solid #e0e0e0; margin: 0;"></div>'
					},
					'spacer': {
						label: 'Espaco',
						icon: 'dashicons-arrow-down-alt2',
						html: '<div style="height: 30px;"></div>'
					}
				}
			},
			colunas: {
				label: 'Colunas',
				icon: 'dashicons-columns',
				items: {
					'col1': {
						label: '1 Coluna',
						icon: 'dashicons-align-center',
						html: '<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff;"><tr><td style="padding: 20px;"><p style="font-family: Arial, sans-serif; font-size: 14px; color: #333333; margin: 0;">Conteudo aqui...</p></td></tr></table>'
					},
					'col2': {
						label: '2 Colunas',
						icon: 'dashicons-columns',
						html: '<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff;"><tr><td width="50%" valign="top" style="padding: 20px;"><h4 style="font-family: Arial, sans-serif; margin: 0 0 10px 0;">Coluna 1</h4><p style="font-family: Arial, sans-serif; font-size: 14px; color: #666; margin: 0;">Texto da esquerda.</p></td><td width="50%" valign="top" style="padding: 20px;"><h4 style="font-family: Arial, sans-serif; margin: 0 0 10px 0;">Coluna 2</h4><p style="font-family: Arial, sans-serif; font-size: 14px; color: #666; margin: 0;">Texto da direita.</p></td></tr></table>'
					},
					'col3': {
						label: '3 Colunas',
						icon: 'dashicons-columns',
						html: '<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff;"><tr><td width="33%" valign="top" style="padding: 15px; text-align: center;"><h4 style="font-family: Arial, sans-serif; margin: 0 0 8px 0;">Item 1</h4><p style="font-family: Arial, sans-serif; font-size: 13px; color: #666; margin: 0;">Descricao.</p></td><td width="33%" valign="top" style="padding: 15px; text-align: center;"><h4 style="font-family: Arial, sans-serif; margin: 0 0 8px 0;">Item 2</h4><p style="font-family: Arial, sans-serif; font-size: 13px; color: #666; margin: 0;">Descricao.</p></td><td width="33%" valign="top" style="padding: 15px; text-align: center;"><h4 style="font-family: Arial, sans-serif; margin: 0 0 8px 0;">Item 3</h4><p style="font-family: Arial, sans-serif; font-size: 13px; color: #666; margin: 0;">Descricao.</p></td></tr></table>'
					},
					'col2img': {
						label: '2 Colunas com Imagem',
						icon: 'dashicons-align-left',
						html: '<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff;"><tr><td width="40%" valign="top" style="padding: 20px;"><img src="https://via.placeholder.com/200x150/e0e0e0/666666?text=Imagem" alt="" style="max-width: 100%;"></td><td width="60%" valign="top" style="padding: 20px;"><h3 style="font-family: Arial, sans-serif; margin: 0 0 10px 0;">Titulo</h3><p style="font-family: Arial, sans-serif; font-size: 14px; color: #666; margin: 0;">Descricao do conteudo ao lado da imagem.</p></td></tr></table>'
					}
				}
			},
			ecommerce: {
				label: 'E-commerce',
				icon: 'dashicons-cart',
				items: {
					'header': {
						label: 'Cabecalho',
						icon: 'dashicons-admin-site-alt3',
						html: '<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff;"><tr><td align="center" style="padding: 30px 20px;"><img src="https://via.placeholder.com/200x60/667eea/ffffff?text=SUA+LOGO" alt="Logo" style="max-width: 200px;"></td></tr></table>'
					},
					'hero': {
						label: 'Banner Principal',
						icon: 'dashicons-cover-image',
						html: '<table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"><tr><td align="center" style="padding: 50px 30px;"><h1 style="color: #ffffff; font-size: 32px; font-weight: bold; margin: 0 0 15px 0; font-family: Arial, sans-serif;">Titulo do Banner</h1><p style="color: rgba(255,255,255,0.9); font-size: 16px; margin: 0 0 20px 0; font-family: Arial, sans-serif;">Subtitulo ou descricao promocional</p><a href="#" style="display: inline-block; background: #ffffff; color: #667eea; padding: 12px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; font-family: Arial, sans-serif;">Ver Mais</a></td></tr></table>'
					},
					'product': {
						label: 'Card Produto',
						icon: 'dashicons-products',
						html: '<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border: 1px solid #e5e5e5; border-radius: 8px;"><tr><td align="center" style="padding: 15px;"><img src="https://via.placeholder.com/200x200/f5f5f5/999999?text=PRODUTO" alt="Produto" style="max-width: 200px;"><h3 style="color: #333; font-size: 16px; margin: 15px 0 5px 0; font-family: Arial, sans-serif;">Nome do Produto</h3><p style="color: #999; font-size: 13px; text-decoration: line-through; margin: 0; font-family: Arial, sans-serif;">R$ 199,90</p><p style="color: #e74c3c; font-size: 20px; font-weight: bold; margin: 5px 0 15px 0; font-family: Arial, sans-serif;">R$ 149,90</p><a href="#" style="display: inline-block; background: #27ae60; color: #ffffff; padding: 10px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; font-family: Arial, sans-serif; font-size: 14px;">Comprar</a></td></tr></table>'
					},
					'products2': {
						label: '2 Produtos',
						icon: 'dashicons-grid-view',
						html: '<table width="100%" cellpadding="10" cellspacing="0"><tr><td width="50%" valign="top"><table width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border: 1px solid #e5e5e5; border-radius: 8px;"><tr><td align="center" style="padding: 15px;"><img src="https://via.placeholder.com/120x120/f5f5f5/999999?text=1" alt="" style="max-width: 120px;"><h4 style="color: #333; font-size: 14px; margin: 10px 0 5px 0; font-family: Arial, sans-serif;">Produto 1</h4><p style="color: #e74c3c; font-size: 16px; font-weight: bold; margin: 0; font-family: Arial, sans-serif;">R$ 99,90</p></td></tr></table></td><td width="50%" valign="top"><table width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border: 1px solid #e5e5e5; border-radius: 8px;"><tr><td align="center" style="padding: 15px;"><img src="https://via.placeholder.com/120x120/f5f5f5/999999?text=2" alt="" style="max-width: 120px;"><h4 style="color: #333; font-size: 14px; margin: 10px 0 5px 0; font-family: Arial, sans-serif;">Produto 2</h4><p style="color: #e74c3c; font-size: 16px; font-weight: bold; margin: 0; font-family: Arial, sans-serif;">R$ 89,90</p></td></tr></table></td></tr></table>'
					},
					'cashback': {
						label: 'Cashback',
						icon: 'dashicons-money-alt',
						html: '<table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border-radius: 10px;"><tr><td align="center" style="padding: 30px;"><h2 style="color: #ffffff; font-size: 24px; font-weight: bold; margin: 0 0 10px 0; font-family: Arial, sans-serif;">Voce tem R$ {{cashback_balance}} de Cashback!</h2><p style="color: rgba(255,255,255,0.9); font-size: 14px; margin: 0 0 15px 0; font-family: Arial, sans-serif;">Use seu saldo na proxima compra</p><a href="#" style="display: inline-block; background: #ffffff; color: #11998e; padding: 12px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; font-family: Arial, sans-serif;">Usar Cashback</a></td></tr></table>'
					},
					'vip': {
						label: 'Nivel VIP',
						icon: 'dashicons-star-filled',
						html: '<table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 10px;"><tr><td align="center" style="padding: 30px;"><p style="color: rgba(255,255,255,0.8); font-size: 12px; margin: 0; font-family: Arial, sans-serif; text-transform: uppercase; letter-spacing: 2px;">Seu nivel</p><h2 style="color: #ffffff; font-size: 28px; font-weight: bold; margin: 8px 0; font-family: Arial, sans-serif;">{{user_level}}</h2><p style="color: rgba(255,255,255,0.9); font-size: 14px; margin: 0; font-family: Arial, sans-serif;">{{cashback_percent}}% de cashback</p></td></tr></table>'
					},
					'coupon': {
						label: 'Cupom',
						icon: 'dashicons-tickets-alt',
						html: '<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fff3cd; border: 2px dashed #ffc107; border-radius: 10px;"><tr><td align="center" style="padding: 25px;"><p style="color: #856404; font-size: 14px; margin: 0 0 8px 0; font-family: Arial, sans-serif;">Use o cupom:</p><h2 style="color: #856404; font-size: 28px; font-weight: bold; margin: 0 0 8px 0; font-family: Arial, sans-serif; letter-spacing: 3px;">DESCONTO20</h2><p style="color: #856404; font-size: 14px; margin: 0; font-family: Arial, sans-serif;">e ganhe 20% OFF!</p></td></tr></table>'
					},
					'cart': {
						label: 'Carrinho Abandonado',
						icon: 'dashicons-cart',
						html: '<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff;"><tr><td align="center" style="padding: 30px;"><h2 style="color: #333; font-size: 22px; margin: 0 0 10px 0; font-family: Arial, sans-serif;">Ola {{customer_name}}!</h2><p style="color: #666; font-size: 14px; margin: 0 0 20px 0; font-family: Arial, sans-serif;">Voce deixou itens no carrinho. Complete sua compra!</p><a href="{{cart_url}}" style="display: inline-block; background: #e74c3c; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-family: Arial, sans-serif;">Finalizar Compra</a></td></tr></table>'
					},
					'footer': {
						label: 'Rodape',
						icon: 'dashicons-align-full-width',
						html: '<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #2c3e50;"><tr><td align="center" style="padding: 30px;"><p style="color: #95a5a6; font-size: 13px; margin: 0 0 10px 0; font-family: Arial, sans-serif;">Siga nas redes:</p><table cellpadding="5" cellspacing="0"><tr><td><a href="#" style="display: inline-block; width: 35px; height: 35px; background: #3b5998; border-radius: 50%; text-align: center; line-height: 35px; color: #fff; text-decoration: none; font-family: Arial;">f</a></td><td><a href="#" style="display: inline-block; width: 35px; height: 35px; background: #e4405f; border-radius: 50%; text-align: center; line-height: 35px; color: #fff; text-decoration: none; font-family: Arial;">I</a></td><td><a href="#" style="display: inline-block; width: 35px; height: 35px; background: #25d366; border-radius: 50%; text-align: center; line-height: 35px; color: #fff; text-decoration: none; font-family: Arial;">W</a></td></tr></table><p style="color: #7f8c8d; font-size: 11px; margin: 15px 0 0 0; font-family: Arial, sans-serif;"><a href="{{unsubscribe_url}}" style="color: #95a5a6;">Cancelar inscricao</a></p></td></tr></table>'
					}
				}
			}
		},

		/**
		 * Inicializar
		 */
		init: function() {
			var self = this;
			this.createModal();

			$(document).on('click', '.pcw-open-email-editor', function(e) {
				e.preventDefault();
				var targetId = $(this).data('target');
				self.open(targetId);
			});
		},

		/**
		 * Criar modal
		 */
		createModal: function() {
			var self = this;

			// Gerar HTML dos blocos
			var blocksHtml = '';
			$.each(this.blocks, function(catKey, category) {
				blocksHtml += '<div class="pcw-block-category">';
				blocksHtml += '<div class="pcw-block-category-title"><span class="dashicons ' + category.icon + '"></span> ' + category.label + '</div>';
				blocksHtml += '<div class="pcw-block-items">';
				$.each(category.items, function(blockKey, block) {
					blocksHtml += '<div class="pcw-block-item" data-block="' + catKey + '.' + blockKey + '">';
					blocksHtml += '<span class="dashicons ' + block.icon + '"></span>';
					blocksHtml += '<span class="pcw-block-label">' + block.label + '</span>';
					blocksHtml += '</div>';
				});
				blocksHtml += '</div></div>';
			});

			var modalHtml = '\
				<div id="pcw-email-editor-modal" class="pcw-email-editor-modal">\
					<div class="pcw-email-editor-container">\
						<div class="pcw-email-editor-header">\
							<h2><span class="dashicons dashicons-email-alt"></span> Editor de Email</h2>\
							<div class="pcw-email-editor-actions">\
								<button type="button" class="button" id="pcw-editor-undo"><span class="dashicons dashicons-undo"></span> Desfazer</button>\
								<button type="button" class="button" id="pcw-editor-preview"><span class="dashicons dashicons-visibility"></span> Visualizar</button>\
								<button type="button" class="button button-primary" id="pcw-editor-save"><span class="dashicons dashicons-saved"></span> Aplicar</button>\
								<button type="button" class="button" id="pcw-editor-close"><span class="dashicons dashicons-no"></span></button>\
							</div>\
						</div>\
						<div class="pcw-email-editor-body">\
							<div class="pcw-email-editor-sidebar">\
								<div class="pcw-sidebar-section">\
									<h3>Blocos</h3>\
									<p class="pcw-sidebar-hint">Clique para adicionar ao email</p>\
									<div class="pcw-blocks-list">' + blocksHtml + '</div>\
								</div>\
								<div class="pcw-sidebar-section pcw-variables-section">\
									<h3>Variaveis</h3>\
									<div class="pcw-variables-list">\
										<code>{{customer_name}}</code> Nome<br>\
										<code>{{customer_first_name}}</code> Primeiro nome<br>\
										<code>{{customer_email}}</code> Email<br>\
										<code>{{cashback_balance}}</code> Saldo cashback<br>\
										<code>{{user_level}}</code> Nivel VIP<br>\
										<code>{{cashback_percent}}</code> % cashback<br>\
										<code>{{site_name}}</code> Nome loja<br>\
										<code>{{site_url}}</code> URL loja<br>\
										<code>{{cart_url}}</code> URL carrinho<br>\
										<code>{{unsubscribe_url}}</code> Descadastro\
									</div>\
								</div>\
							</div>\
							<div class="pcw-email-editor-canvas">\
								<div class="pcw-canvas-toolbar">\
									<button type="button" class="pcw-tool-btn" id="pcw-tool-move-up" title="Mover para cima"><span class="dashicons dashicons-arrow-up-alt"></span></button>\
									<button type="button" class="pcw-tool-btn" id="pcw-tool-move-down" title="Mover para baixo"><span class="dashicons dashicons-arrow-down-alt"></span></button>\
									<button type="button" class="pcw-tool-btn" id="pcw-tool-duplicate" title="Duplicar"><span class="dashicons dashicons-admin-page"></span></button>\
									<button type="button" class="pcw-tool-btn pcw-tool-delete" id="pcw-tool-delete" title="Excluir"><span class="dashicons dashicons-trash"></span></button>\
								</div>\
								<div class="pcw-canvas-wrapper">\
									<div class="pcw-canvas-content" id="pcw-canvas-content">\
										<div class="pcw-canvas-empty">Clique em um bloco para adicionar</div>\
									</div>\
								</div>\
							</div>\
							<div class="pcw-email-editor-properties">\
								<h3>Propriedades</h3>\
								<div id="pcw-properties-panel">\
									<p class="pcw-properties-hint">Selecione um elemento para editar</p>\
								</div>\
							</div>\
						</div>\
					</div>\
				</div>\
			';

			$('body').append(modalHtml);
			this.modal = $('#pcw-email-editor-modal');
			this.bindEvents();
		},

		/**
		 * Vincular eventos
		 */
		bindEvents: function() {
			var self = this;

			// Fechar
			$('#pcw-editor-close').on('click', function() { self.close(); });
			
			// Salvar
			$('#pcw-editor-save').on('click', function() { self.save(); });
			
			// Preview
			$('#pcw-editor-preview').on('click', function() { self.preview(); });

			// Desfazer
			$('#pcw-editor-undo').on('click', function() { self.undo(); });

			// Adicionar bloco
			$(document).on('click', '.pcw-block-item', function() {
				var blockId = $(this).data('block');
				self.addBlock(blockId);
			});

			// Selecionar elemento
			$(document).on('click', '#pcw-canvas-content .pcw-element', function(e) {
				e.stopPropagation();
				self.selectElement($(this));
			});

			// Editar texto inline
			$(document).on('dblclick', '#pcw-canvas-content .pcw-element', function(e) {
				e.stopPropagation();
				self.editElement($(this));
			});

			// Deselecionar
			$(document).on('click', '#pcw-canvas-content', function(e) {
				if ($(e.target).is('#pcw-canvas-content')) {
					self.deselectElement();
				}
			});

			// Toolbar
			$('#pcw-tool-move-up').on('click', function() { self.moveElement('up'); });
			$('#pcw-tool-move-down').on('click', function() { self.moveElement('down'); });
			$('#pcw-tool-duplicate').on('click', function() { self.duplicateElement(); });
			$('#pcw-tool-delete').on('click', function() { self.deleteElement(); });

			// ESC para fechar
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && self.modal.is(':visible')) {
					self.close();
				}
			});
		},

		/**
		 * Abrir editor
		 * @param {string} targetId - ID do campo de texto alvo
		 * @param {object} options - Opções (title, onSave)
		 */
		open: function(targetId, options) {
			var self = this;
			
			// Suportar tanto ID (string) quanto elemento jQuery
			if (typeof targetId === 'object' && targetId.jquery) {
				// É um elemento jQuery, pegar o ID
				this.targetField = targetId;
			} else if (typeof targetId === 'string') {
				// É uma string (ID), buscar o elemento
				this.targetField = $('#' + targetId);
			} else {
				console.error('PCWEmailEditor.open: targetId inválido', targetId);
				return;
			}
			
			// Guardar opções
			this.options = options || {};
			
			var content = this.targetField.val();

			this.modal.fadeIn(200);
			$('body').addClass('pcw-editor-open');

			// Carregar conteúdo existente ou template padrão
			if (content && content.trim() !== '') {
				this.loadContent(content);
			} else {
				this.loadDefaultTemplate();
			}

			this.history = [];
			this.saveHistory();
		},

		/**
		 * Carregar conteúdo existente
		 */
		loadContent: function(html) {
			var $canvas = $('#pcw-canvas-content');
			$canvas.empty();

			// Envolver cada elemento de primeiro nível
			var $temp = $('<div>').html(html);
			$temp.children().each(function() {
				var $wrapper = $('<div class="pcw-element">').append($(this).clone());
				$canvas.append($wrapper);
			});

			if ($canvas.children().length === 0) {
				$canvas.html('<div class="pcw-canvas-empty">Clique em um bloco para adicionar</div>');
			}
		},

		/**
		 * Carregar template padrão
		 */
		loadDefaultTemplate: function() {
			var $canvas = $('#pcw-canvas-content');
			$canvas.empty();
			$canvas.html('<div class="pcw-canvas-empty">Clique em um bloco para adicionar</div>');
		},

		/**
		 * Adicionar bloco
		 */
		addBlock: function(blockId) {
			var parts = blockId.split('.');
			var category = parts[0];
			var block = parts[1];

			if (!this.blocks[category] || !this.blocks[category].items[block]) {
				return;
			}

			var html = this.blocks[category].items[block].html;
			var $element = $('<div class="pcw-element">').html(html);

			var $canvas = $('#pcw-canvas-content');
			$canvas.find('.pcw-canvas-empty').remove();

			// Adicionar após o elemento selecionado ou no final
			if (this.selectedElement && this.selectedElement.length) {
				this.selectedElement.after($element);
			} else {
				$canvas.append($element);
			}

			this.selectElement($element);
			this.saveHistory();
		},

		/**
		 * Selecionar elemento
		 */
		selectElement: function($el) {
			$('#pcw-canvas-content .pcw-element').removeClass('pcw-selected');
			$el.addClass('pcw-selected');
			this.selectedElement = $el;
			this.showProperties($el);
		},

		/**
		 * Deselecionar
		 */
		deselectElement: function() {
			$('#pcw-canvas-content .pcw-element').removeClass('pcw-selected');
			this.selectedElement = null;
			$('#pcw-properties-panel').html('<p class="pcw-properties-hint">Selecione um elemento para editar</p>');
		},

		/**
		 * Editar elemento inline
		 */
		editElement: function($el) {
			var $editable = $el.find('h1, h2, h3, h4, p, a, span').first();
			if ($editable.length) {
				$editable.attr('contenteditable', 'true').focus();
				
				// Selecionar todo o texto
				var range = document.createRange();
				range.selectNodeContents($editable[0]);
				var sel = window.getSelection();
				sel.removeAllRanges();
				sel.addRange(range);

				$editable.on('blur', function() {
					$(this).removeAttr('contenteditable');
				});
			}
		},

		/**
		 * Mostrar propriedades (versão avançada)
		 */
		showProperties: function($el) {
			var self = this;
			var $panel = $('#pcw-properties-panel');
			var $firstChild = $el.children().first();
			var $textElement = $el.find('h1, h2, h3, h4, p, span, a').first();
			
			// Ler estilos atuais
			var currentBgColor = self.rgbToHex($firstChild.css('background-color')) || '#ffffff';
			var currentTextAlign = $firstChild.css('text-align') || 'left';
			var currentBorderRadius = parseInt($firstChild.css('border-radius')) || 0;
			var currentBorderWidth = parseInt($firstChild.css('border-width')) || 0;
			var currentBorderColor = self.rgbToHex($firstChild.css('border-color')) || '#e0e0e0';
			
			// Estilos de texto
			var currentTextColor = $textElement.length ? (self.rgbToHex($textElement.css('color')) || '#333333') : '#333333';
			var currentFontSize = $textElement.length ? parseInt($textElement.css('font-size')) : 14;
			var currentFontWeight = $textElement.length ? $textElement.css('font-weight') : '400';
			var currentLineHeight = $textElement.length ? parseFloat($textElement.css('line-height')) / parseFloat($textElement.css('font-size')) : 1.5;
			var currentTextTransform = $textElement.length ? $textElement.css('text-transform') : 'none';
			var currentTextDecoration = $textElement.length ? $textElement.css('text-decoration').split(' ')[0] : 'none';

			var html = '<div class="pcw-properties-tabs">';
			html += '<button type="button" class="pcw-prop-tab active" data-tab="layout">Layout</button>';
			html += '<button type="button" class="pcw-prop-tab" data-tab="colors">Cores</button>';
			html += '<button type="button" class="pcw-prop-tab" data-tab="text">Texto</button>';
			html += '<button type="button" class="pcw-prop-tab" data-tab="advanced">Avançado</button>';
			html += '</div>';

			// Aba Layout
			html += '<div class="pcw-prop-content" data-content="layout">';
			
			html += '<div class="pcw-property-group">';
			html += '<label>Espaçamento Interno</label>';
			html += '<div class="pcw-spacing-controls">';
			html += '<input type="number" id="prop-padding" placeholder="Geral" min="0" max="100" style="width: 100%; margin-bottom: 5px;">';
			html += '<div style="display: flex; gap: 5px;">';
			html += '<input type="number" id="prop-padding-top" placeholder="↑" min="0" max="100" style="width: 25%;">';
			html += '<input type="number" id="prop-padding-right" placeholder="→" min="0" max="100" style="width: 25%;">';
			html += '<input type="number" id="prop-padding-bottom" placeholder="↓" min="0" max="100" style="width: 25%;">';
			html += '<input type="number" id="prop-padding-left" placeholder="←" min="0" max="100" style="width: 25%;">';
			html += '</div>';
			html += '</div></div>';

			html += '<div class="pcw-property-group">';
			html += '<label>Margem Externa</label>';
			html += '<div class="pcw-spacing-controls">';
			html += '<input type="number" id="prop-margin" placeholder="Inferior" min="0" max="100" style="width: 100%;">';
			html += '</div></div>';

			html += '<div class="pcw-property-group">';
			html += '<label>Alinhamento</label>';
			html += '<select id="prop-text-align" style="width: 100%;">';
			html += '<option value="left"' + (currentTextAlign === 'left' ? ' selected' : '') + '>Esquerda</option>';
			html += '<option value="center"' + (currentTextAlign === 'center' ? ' selected' : '') + '>Centro</option>';
			html += '<option value="right"' + (currentTextAlign === 'right' ? ' selected' : '') + '>Direita</option>';
			html += '<option value="justify"' + (currentTextAlign === 'justify' ? ' selected' : '') + '>Justificado</option>';
			html += '</select></div>';

			html += '<div class="pcw-property-group">';
			html += '<label>Largura Máxima</label>';
			html += '<input type="number" id="prop-max-width" placeholder="px (vazio = 100%)" min="0" max="1000"></div>';

			html += '</div>';

			// Aba Cores
			html += '<div class="pcw-prop-content" data-content="colors" style="display: none;">';
			
			html += '<div class="pcw-property-group">';
			html += '<label>Cor de Fundo</label>';
			html += '<div style="display: flex; gap: 5px;">';
			html += '<input type="color" id="prop-bg-color" value="' + currentBgColor + '" style="width: 60px;">';
			html += '<input type="text" id="prop-bg-color-hex" value="' + currentBgColor + '" placeholder="#ffffff" style="flex: 1;">';
			html += '</div></div>';

			html += '<div class="pcw-property-group">';
			html += '<label>Gradiente de Fundo</label>';
			html += '<input type="text" id="prop-bg-gradient" placeholder="Ex: linear-gradient(135deg, #667eea 0%, #764ba2 100%)" style="width: 100%;">';
			html += '<p class="description">Deixe vazio para usar cor sólida</p></div>';

			html += '<div class="pcw-property-group">';
			html += '<label>Cor da Borda</label>';
			html += '<div style="display: flex; gap: 5px;">';
			html += '<input type="color" id="prop-border-color" value="' + currentBorderColor + '" style="width: 60px;">';
			html += '<input type="text" id="prop-border-color-hex" value="' + currentBorderColor + '" style="flex: 1;">';
			html += '</div></div>';

			html += '<div class="pcw-property-group">';
			html += '<label>Espessura da Borda (px)</label>';
			html += '<input type="number" id="prop-border-width" value="' + currentBorderWidth + '" min="0" max="20"></div>';

			html += '<div class="pcw-property-group">';
			html += '<label>Arredondamento (px)</label>';
			html += '<input type="number" id="prop-border-radius" value="' + currentBorderRadius + '" min="0" max="50"></div>';

			html += '</div>';

			// Aba Texto
			html += '<div class="pcw-prop-content" data-content="text" style="display: none;">';
			
			var $textElement = $el.find('h1, h2, h3, h4, p, span, a').first();
			if ($textElement.length) {
				html += '<div class="pcw-property-group">';
				html += '<label>Cor do Texto</label>';
				html += '<div style="display: flex; gap: 5px;">';
				html += '<input type="color" id="prop-text-color" value="' + currentTextColor + '" style="width: 60px;">';
				html += '<input type="text" id="prop-text-color-hex" value="' + currentTextColor + '" style="flex: 1;">';
				html += '</div></div>';

				html += '<div class="pcw-property-group">';
				html += '<label>Tamanho da Fonte (px)</label>';
				html += '<input type="number" id="prop-font-size" value="' + currentFontSize + '" min="8" max="72"></div>';

				html += '<div class="pcw-property-group">';
				html += '<label>Peso da Fonte</label>';
				html += '<select id="prop-font-weight" style="width: 100%;">';
				html += '<option value="300"' + (currentFontWeight == '300' ? ' selected' : '') + '>Fino</option>';
				html += '<option value="400"' + (currentFontWeight == '400' || currentFontWeight == 'normal' ? ' selected' : '') + '>Normal</option>';
				html += '<option value="600"' + (currentFontWeight == '600' ? ' selected' : '') + '>Semi-negrito</option>';
				html += '<option value="700"' + (currentFontWeight == '700' || currentFontWeight == 'bold' ? ' selected' : '') + '>Negrito</option>';
				html += '<option value="900"' + (currentFontWeight == '900' ? ' selected' : '') + '>Extra-negrito</option>';
				html += '</select></div>';

				html += '<div class="pcw-property-group">';
				html += '<label>Altura da Linha</label>';
				html += '<input type="number" id="prop-line-height" value="' + currentLineHeight.toFixed(1) + '" min="0.8" max="3" step="0.1"></div>';

				html += '<div class="pcw-property-group">';
				html += '<label>Espaçamento de Letras (px)</label>';
				html += '<input type="number" id="prop-letter-spacing" min="-5" max="10" step="0.5"></div>';

				html += '<div class="pcw-property-group">';
				html += '<label>Transformação</label>';
				html += '<select id="prop-text-transform" style="width: 100%;">';
				html += '<option value="none"' + (currentTextTransform === 'none' ? ' selected' : '') + '>Nenhuma</option>';
				html += '<option value="uppercase"' + (currentTextTransform === 'uppercase' ? ' selected' : '') + '>MAIÚSCULAS</option>';
				html += '<option value="lowercase"' + (currentTextTransform === 'lowercase' ? ' selected' : '') + '>minúsculas</option>';
				html += '<option value="capitalize"' + (currentTextTransform === 'capitalize' ? ' selected' : '') + '>Capitalize</option>';
				html += '</select></div>';

				html += '<div class="pcw-property-group">';
				html += '<label>Decoração</label>';
				html += '<select id="prop-text-decoration" style="width: 100%;">';
				html += '<option value="none"' + (currentTextDecoration === 'none' ? ' selected' : '') + '>Nenhuma</option>';
				html += '<option value="underline"' + (currentTextDecoration === 'underline' ? ' selected' : '') + '>Sublinhado</option>';
				html += '<option value="line-through"' + (currentTextDecoration === 'line-through' ? ' selected' : '') + '>Riscado</option>';
				html += '</select></div>';
			}
			html += '</div>';

			// Aba Avançado
			html += '<div class="pcw-prop-content" data-content="advanced" style="display: none;">';
			
			// Se tiver imagem
			if ($el.find('img').length) {
				var $img = $el.find('img').first();
				var imgSrc = $img.attr('src');
				var imgAlt = $img.attr('alt');
				
				html += '<div class="pcw-property-group">';
				html += '<label>URL da Imagem</label>';
				html += '<input type="text" id="prop-img-src" value="' + imgSrc + '" style="width: 100%; margin-bottom: 5px;">';
				html += '<button type="button" class="button button-small" id="prop-img-upload" style="width: 100%;">📁 Escolher da Biblioteca</button>';
				html += '</div>';

				html += '<div class="pcw-property-group">';
				html += '<label>Texto Alternativo</label>';
				html += '<input type="text" id="prop-img-alt" value="' + imgAlt + '" placeholder="Descrição da imagem"></div>';

				html += '<div class="pcw-property-group">';
				html += '<label>Largura da Imagem (px)</label>';
				html += '<input type="number" id="prop-img-width" placeholder="Auto" min="50" max="800"></div>';

				html += '<div class="pcw-property-group">';
				html += '<label>Altura da Imagem (px)</label>';
				html += '<input type="number" id="prop-img-height" placeholder="Auto" min="50" max="800"></div>';
			}

			// Se tiver link/botão
			if ($el.find('a').length) {
				var $link = $el.find('a').first();
				var linkHref = $link.attr('href') || '#';
				var linkTarget = $link.attr('target') || '_self';
				
				html += '<div class="pcw-property-group">';
				html += '<label>URL do Link</label>';
				html += '<input type="text" id="prop-link-href" value="' + linkHref + '" style="width: 100%;"></div>';

				html += '<div class="pcw-property-group">';
				html += '<label>Abrir em</label>';
				html += '<select id="prop-link-target" style="width: 100%;">';
				html += '<option value="_self"' + (linkTarget === '_self' ? ' selected' : '') + '>Mesma aba</option>';
				html += '<option value="_blank"' + (linkTarget === '_blank' ? ' selected' : '') + '>Nova aba</option>';
				html += '</select></div>';

				// Se for um botão
				if ($link.css('display') === 'inline-block' || $link.css('display') === 'block') {
					html += '<div class="pcw-property-group">';
					html += '<label>Cor do Botão</label>';
					html += '<div style="display: flex; gap: 5px;">';
					html += '<input type="color" id="prop-btn-bg" style="width: 60px;">';
					html += '<input type="text" id="prop-btn-bg-hex" style="flex: 1;">';
					html += '</div></div>';

					html += '<div class="pcw-property-group">';
					html += '<label>Cor do Texto do Botão</label>';
					html += '<div style="display: flex; gap: 5px;">';
					html += '<input type="color" id="prop-btn-color" style="width: 60px;">';
					html += '<input type="text" id="prop-btn-color-hex" style="flex: 1;">';
					html += '</div></div>';

					html += '<div class="pcw-property-group">';
					html += '<label>Padding do Botão</label>';
					html += '<input type="text" id="prop-btn-padding" placeholder="14px 35px" style="width: 100%;"></div>';

					html += '<div class="pcw-property-group">';
					html += '<label>Arredondamento do Botão (px)</label>';
					html += '<input type="number" id="prop-btn-radius" min="0" max="50"></div>';
				}
			}

			// Se for card de produto - adicionar botões para escolher produtos
			if ($el.find('img').length && ($el.text().includes('Produto') || $el.text().includes('Comprar'))) {
				// Detectar se há múltiplos produtos (múltiplas colunas)
				var $productColumns = $el.find('> table > tbody > tr > td').filter(function() {
					return $(this).find('img').length > 0 && $(this).find('h3, h4').length > 0;
				});
				
				if ($productColumns.length === 0) {
					// Fallback: pode ser um único produto
					$productColumns = $el.find('td').filter(function() {
						return $(this).find('img').length > 0;
					}).first();
					if ($productColumns.length === 0) {
						$productColumns = $el; // Elemento inteiro
					}
				}
				
				html += '<hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">';
				
				// Se houver múltiplos produtos, criar um botão para cada
				if ($productColumns.length > 1) {
					html += '<h4 style="margin: 0 0 15px 0; color: #667eea; font-size: 14px;">🛒 Produtos do WooCommerce</h4>';
					
					$productColumns.each(function(index) {
						html += '<div class="pcw-property-group" style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 12px; border-left: 3px solid #667eea;" data-product-index="' + index + '">';
						html += '<label style="display: block; margin-bottom: 8px;"><strong>Produto ' + (index + 1) + '</strong></label>';
						html += '<button type="button" class="button button-primary pcw-choose-product-btn" data-column-index="' + index + '" style="width: 100%; margin-bottom: 8px;"><span class="dashicons dashicons-search" style="margin-top: 3px;"></span> Escolher Produto</button>';
						html += '<div class="prop-selected-product" data-column-index="' + index + '" style="margin-top: 8px;"></div>';
						html += '</div>';
					});
				} else {
					// Produto único
					html += '<div class="pcw-property-group">';
					html += '<label style="display: block; margin-bottom: 8px;"><strong>🛒 Produto do WooCommerce</strong></label>';
					html += '<button type="button" class="button button-primary pcw-choose-product-btn" data-column-index="0" style="width: 100%;"><span class="dashicons dashicons-search" style="margin-top: 3px;"></span> Escolher Produto</button>';
					html += '<div class="prop-selected-product" data-column-index="0" style="margin-top: 10px;"></div>';
					html += '</div>';
				}
			}

			html += '<div class="pcw-property-group">';
			html += '<label>CSS Personalizado</label>';
			html += '<textarea id="prop-custom-css" rows="4" placeholder="color: red; font-size: 16px;" style="width: 100%; font-family: monospace;"></textarea>';
			html += '<p class="description">Estilos inline personalizados</p></div>';

			html += '</div>';

			$panel.html(html);

			// Eventos das abas
			$('.pcw-prop-tab').on('click', function() {
				$('.pcw-prop-tab').removeClass('active');
				$('.pcw-prop-content').hide();
				$(this).addClass('active');
				$('.pcw-prop-content[data-content="' + $(this).data('tab') + '"]').show();
			});

			// Vincular eventos (continua na próxima parte...)
			this.bindPropertyEvents($el, $firstChild, $textElement);
		},

		/**
		 * Vincular eventos de propriedades
		 */
		bindPropertyEvents: function($el, $firstChild, $textElement) {
			var self = this;

			// Layout
			$('#prop-padding').on('change', function() {
				if ($(this).val()) {
					$firstChild.css('padding', $(this).val() + 'px');
					self.saveHistory();
				}
			});

			$('#prop-padding-top, #prop-padding-right, #prop-padding-bottom, #prop-padding-left').on('change', function() {
				var top = $('#prop-padding-top').val() || '';
				var right = $('#prop-padding-right').val() || '';
				var bottom = $('#prop-padding-bottom').val() || '';
				var left = $('#prop-padding-left').val() || '';
				if (top || right || bottom || left) {
					var padding = (top || 0) + 'px ' + (right || 0) + 'px ' + (bottom || 0) + 'px ' + (left || 0) + 'px';
					$firstChild.css('padding', padding);
					self.saveHistory();
				}
			});

			$('#prop-margin').on('change', function() {
				if ($(this).val()) {
					$el.css('margin-bottom', $(this).val() + 'px');
					self.saveHistory();
				}
			});

			$('#prop-text-align').on('change', function() {
				$firstChild.css('text-align', $(this).val());
				self.saveHistory();
			});

			$('#prop-max-width').on('change', function() {
				if ($(this).val()) {
					$firstChild.css({'max-width': $(this).val() + 'px', 'margin-left': 'auto', 'margin-right': 'auto'});
				} else {
					$firstChild.css({'max-width': '', 'margin-left': '', 'margin-right': ''});
				}
				self.saveHistory();
			});

			// Cores
			$('#prop-bg-color, #prop-bg-color-hex').on('change', function() {
				var color = $(this).val();
				$('#prop-bg-color').val(color);
				$('#prop-bg-color-hex').val(color);
				$firstChild.css('background-color', color);
				$firstChild.css('background', ''); // Remove gradient se houver
				self.saveHistory();
			});

			$('#prop-bg-gradient').on('change', function() {
				if ($(this).val()) {
					$firstChild.css('background', $(this).val());
				} else {
					$firstChild.css('background', '');
					$firstChild.css('background-color', $('#prop-bg-color').val());
				}
				self.saveHistory();
			});

			$('#prop-border-color, #prop-border-color-hex').on('change', function() {
				var color = $(this).val();
				$('#prop-border-color').val(color);
				$('#prop-border-color-hex').val(color);
				$firstChild.css('border-color', color);
				self.saveHistory();
			});

			$('#prop-border-width').on('change', function() {
				$firstChild.css('border-width', $(this).val() + 'px');
				$firstChild.css('border-style', 'solid');
				self.saveHistory();
			});

			$('#prop-border-radius').on('change', function() {
				$firstChild.css('border-radius', $(this).val() + 'px');
				self.saveHistory();
			});

			// Texto
			if ($textElement && $textElement.length) {
				$('#prop-text-color, #prop-text-color-hex').on('change', function() {
					var color = $(this).val();
					$('#prop-text-color').val(color);
					$('#prop-text-color-hex').val(color);
					$textElement.css('color', color);
					self.saveHistory();
				});

				$('#prop-font-size').on('change', function() {
					if ($(this).val()) {
						$textElement.css('font-size', $(this).val() + 'px');
						self.saveHistory();
					}
				});

				$('#prop-font-weight').on('change', function() {
					$textElement.css('font-weight', $(this).val());
					self.saveHistory();
				});

				$('#prop-line-height').on('change', function() {
					if ($(this).val()) {
						$textElement.css('line-height', $(this).val());
						self.saveHistory();
					}
				});

				$('#prop-letter-spacing').on('change', function() {
					if ($(this).val()) {
						$textElement.css('letter-spacing', $(this).val() + 'px');
						self.saveHistory();
					}
				});

				$('#prop-text-transform').on('change', function() {
					$textElement.css('text-transform', $(this).val());
					self.saveHistory();
				});

				$('#prop-text-decoration').on('change', function() {
					$textElement.css('text-decoration', $(this).val());
					self.saveHistory();
				});
			}

			// Imagem
			$('#prop-img-src').on('change', function() {
				$el.find('img').first().attr('src', $(this).val());
				self.saveHistory();
			});

			$('#prop-img-alt').on('change', function() {
				$el.find('img').first().attr('alt', $(this).val());
				self.saveHistory();
			});

			$('#prop-img-width').on('change', function() {
				if ($(this).val()) {
					$el.find('img').first().css('width', $(this).val() + 'px');
				} else {
					$el.find('img').first().css('width', '');
				}
				self.saveHistory();
			});

			$('#prop-img-height').on('change', function() {
				if ($(this).val()) {
					$el.find('img').first().css('height', $(this).val() + 'px');
				} else {
					$el.find('img').first().css('height', '');
				}
				self.saveHistory();
			});

			// Upload de imagem
			$('#prop-img-upload').on('click', function() {
				if (typeof wp !== 'undefined' && wp.media) {
					var frame = wp.media({
						title: 'Selecionar Imagem',
						button: { text: 'Usar esta imagem' },
						multiple: false
					});
					frame.on('select', function() {
						var attachment = frame.state().get('selection').first().toJSON();
						$('#prop-img-src').val(attachment.url).trigger('change');
					});
					frame.open();
				}
			});

			// Link
			$('#prop-link-href').on('change', function() {
				$el.find('a').first().attr('href', $(this).val());
				self.saveHistory();
			});

			$('#prop-link-target').on('change', function() {
				$el.find('a').first().attr('target', $(this).val());
				self.saveHistory();
			});

			// Botão
			$('#prop-btn-bg, #prop-btn-bg-hex').on('change', function() {
				var color = $(this).val();
				$('#prop-btn-bg').val(color);
				$('#prop-btn-bg-hex').val(color);
				$el.find('a').first().css('background', color);
				self.saveHistory();
			});

			$('#prop-btn-color, #prop-btn-color-hex').on('change', function() {
				var color = $(this).val();
				$('#prop-btn-color').val(color);
				$('#prop-btn-color-hex').val(color);
				$el.find('a').first().css('color', color);
				self.saveHistory();
			});

			$('#prop-btn-padding').on('change', function() {
				if ($(this).val()) {
					$el.find('a').first().css('padding', $(this).val());
					self.saveHistory();
				}
			});

			$('#prop-btn-radius').on('change', function() {
				if ($(this).val()) {
					$el.find('a').first().css('border-radius', $(this).val() + 'px');
					self.saveHistory();
				}
			});

			// Abrir modal de escolha de produto
			$(document).off('click', '.pcw-choose-product-btn').on('click', '.pcw-choose-product-btn', function() {
				var columnIndex = parseInt($(this).data('column-index'));
				self.openProductModal($el, columnIndex);
			});

			// CSS personalizado
			$('#prop-custom-css').on('change', function() {
				if ($(this).val()) {
					var styles = $(this).val().split(';').map(function(s) { return s.trim(); }).filter(function(s) { return s; });
					styles.forEach(function(style) {
						var parts = style.split(':');
						if (parts.length === 2) {
							$firstChild.css(parts[0].trim(), parts[1].trim());
						}
					});
					self.saveHistory();
				}
			});
		},

		/**
		 * Abrir modal de escolha de produto
		 */
		openProductModal: function($el, columnIndex) {
			var self = this;
			
			// Criar modal se não existir
			if ($('#pcw-product-modal').length === 0) {
				var modalHtml = '\
					<div id="pcw-product-modal" class="pcw-product-modal-overlay">\
						<div class="pcw-product-modal">\
							<div class="pcw-product-modal-header">\
								<h3><span class="dashicons dashicons-products"></span> Escolher Produto do WooCommerce</h3>\
								<button type="button" class="pcw-product-modal-close"><span class="dashicons dashicons-no"></span></button>\
							</div>\
							<div class="pcw-product-modal-body">\
								<div class="pcw-product-search-box">\
									<input type="text" id="pcw-product-modal-search" placeholder="Digite o nome do produto para buscar..." />\
									<span class="dashicons dashicons-search"></span>\
								</div>\
								<div id="pcw-product-modal-results" class="pcw-product-modal-results">\
									<p class="pcw-product-modal-hint">Digite acima para buscar produtos...</p>\
								</div>\
							</div>\
						</div>\
					</div>';
				$('body').append(modalHtml);
				
				// Evento de fechar modal
				$('.pcw-product-modal-close, .pcw-product-modal-overlay').on('click', function(e) {
					if (e.target === this) {
						$('#pcw-product-modal').fadeOut(200);
					}
				});
				
				// ESC para fechar
				$(document).on('keydown', function(e) {
					if (e.key === 'Escape' && $('#pcw-product-modal').is(':visible')) {
						$('#pcw-product-modal').fadeOut(200);
					}
				});
			}
			
			// Armazenar contexto do elemento e coluna
			$('#pcw-product-modal').data('target-element', $el).data('column-index', columnIndex);
			
			// Limpar busca anterior
			$('#pcw-product-modal-search').val('');
			$('#pcw-product-modal-results').html('<p class="pcw-product-modal-hint">Digite acima para buscar produtos...</p>');
			
			// Abrir modal
			$('#pcw-product-modal').fadeIn(200);
			$('#pcw-product-modal-search').focus();
			
			// Evento de busca no modal
			var searchTimer;
			$('#pcw-product-modal-search').off('input').on('input', function() {
				clearTimeout(searchTimer);
				var query = $(this).val();
				
				if (query.length < 2) {
					$('#pcw-product-modal-results').html('<p class="pcw-product-modal-hint">Digite pelo menos 2 caracteres...</p>');
					return;
				}
				
				$('#pcw-product-modal-results').html('<p class="pcw-product-modal-loading"><span class="dashicons dashicons-update spin"></span> Buscando produtos...</p>');
				
				searchTimer = setTimeout(function() {
					self.searchProductsInModal(query);
				}, 300);
			});
		},
		
		/**
		 * Buscar produtos no modal
		 */
		searchProductsInModal: function(query) {
			var self = this;
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'pcw_search_products',
					nonce: typeof pcwCampaigns !== 'undefined' ? pcwCampaigns.nonce : '',
					search: query,
					page: 1
				},
				success: function(response) {
					if (response.success && response.data.products.length > 0) {
						var html = '<div class="pcw-product-modal-grid">';
						response.data.products.forEach(function(product) {
							html += '<div class="pcw-product-modal-card" data-product-id="' + product.id + '">';
							html += '<div class="pcw-product-modal-img"><img src="' + product.image + '" alt="' + product.name + '"></div>';
							html += '<div class="pcw-product-modal-info">';
							html += '<h4>' + product.name + '</h4>';
							html += '<p class="pcw-product-modal-price">' + product.price + '</p>';
							html += '<button type="button" class="button button-primary pcw-select-product-btn">Selecionar</button>';
							html += '</div>';
							html += '</div>';
						});
						html += '</div>';
						$('#pcw-product-modal-results').html(html);
						
						// Evento de selecionar produto
						$('.pcw-select-product-btn').on('click', function() {
							var $card = $(this).closest('.pcw-product-modal-card');
							var productId = $card.data('product-id');
							var selectedProduct = response.data.products.find(function(p) { return p.id == productId; });
							
							if (selectedProduct) {
								var $targetEl = $('#pcw-product-modal').data('target-element');
								var columnIndex = $('#pcw-product-modal').data('column-index');
								self.applyProduct(selectedProduct, $targetEl, columnIndex);
								$('#pcw-product-modal').fadeOut(200);
							}
						});
					} else {
						$('#pcw-product-modal-results').html('<p class="pcw-product-modal-empty"><span class="dashicons dashicons-info"></span> Nenhum produto encontrado.</p>');
					}
				},
				error: function() {
					$('#pcw-product-modal-results').html('<p class="pcw-product-modal-error"><span class="dashicons dashicons-warning"></span> Erro ao buscar produtos. Tente novamente.</p>');
				}
			});
		},

		/**
		 * Buscar produtos WooCommerce (método antigo - mantido para compatibilidade)
		 */
		searchProducts: function(query, $el, columnIndex) {
			var self = this;
			columnIndex = columnIndex || 0;
			var $results = $('.prop-product-results[data-column-index="' + columnIndex + '"]');
			
			$results.html('<p style="padding: 10px;">Buscando...</p>').show();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'pcw_search_products',
					nonce: typeof pcwCampaigns !== 'undefined' ? pcwCampaigns.nonce : '',
					search: query,
					page: 1
				},
				success: function(response) {
					if (response.success && response.data.products.length > 0) {
						var html = '<div style="padding: 5px;">';
						response.data.products.forEach(function(product) {
							html += '<div class="pcw-product-item" data-product="' + product.id + '" data-column-index="' + columnIndex + '" style="display: flex; gap: 10px; padding: 8px; border-bottom: 1px solid #eee; cursor: pointer;">';
							html += '<img src="' + product.image + '" alt="" style="width: 40px; height: 40px; object-fit: cover;">';
							html += '<div style="flex: 1;">';
							html += '<div style="font-weight: 600; font-size: 13px;">' + product.name + '</div>';
							html += '<div style="font-size: 12px; color: #666;">' + product.price + '</div>';
							html += '</div>';
							html += '</div>';
						});
						html += '</div>';
						$results.html(html);

						// Evento de clique para selecionar produto
						$results.find('.pcw-product-item').on('click', function() {
							var productId = $(this).data('product');
							var colIndex = $(this).data('column-index');
							var selectedProduct = response.data.products.find(function(p) { return p.id == productId; });
							if (selectedProduct) {
								self.applyProduct(selectedProduct, $el, colIndex);
								$results.hide();
								$('.prop-product-search[data-column-index="' + colIndex + '"]').val('');
							}
						});
					} else {
						$results.html('<p style="padding: 10px; color: #999;">Nenhum produto encontrado.</p>');
					}
				},
				error: function() {
					$results.html('<p style="padding: 10px; color: #dc2626;">Erro ao buscar produtos.</p>');
				}
			});
		},

		/**
		 * Aplicar produto selecionado ao elemento
		 */
		applyProduct: function(product, $el, columnIndex) {
			columnIndex = columnIndex || 0;
			
			// Detectar se há múltiplas colunas de produtos
			var $productColumns = $el.find('> table > tbody > tr > td').filter(function() {
				return $(this).find('img').length > 0 && $(this).find('h3, h4').length > 0;
			});
			
			if ($productColumns.length === 0) {
				$productColumns = $el.find('td').filter(function() {
					return $(this).find('img').length > 0;
				});
				if ($productColumns.length === 0) {
					$productColumns = $el; // Elemento inteiro
				}
			}
			
			// Selecionar a coluna específica
			var $targetColumn = $productColumns.eq(columnIndex);
			if ($targetColumn.length === 0) {
				$targetColumn = $el; // Fallback
			}
			
			// Atualizar imagem
			var $img = $targetColumn.find('img').first();
			if ($img.length) {
				$img.attr('src', product.image);
				$img.attr('alt', product.name);
			}

			// Atualizar título
			var $title = $targetColumn.find('h3, h4').first();
			if ($title.length) {
				$title.text(product.name);
			}

			// Atualizar preços
			var $prices = $targetColumn.find('p');
			if ($prices.length >= 2 && product.on_sale) {
				$prices.eq(0).html(product.regular_price).css('text-decoration', 'line-through');
				$prices.eq(1).html(product.sale_price);
			} else if ($prices.length >= 1) {
				$prices.first().html(product.price);
			}

			// Atualizar link
			var $link = $targetColumn.find('a').first();
			if ($link.length) {
				$link.attr('href', product.permalink);
			}

			// Mostrar confirmação
			$('.prop-selected-product[data-column-index="' + columnIndex + '"]').html('<div style="padding: 10px; background: #d1fae5; border-radius: 4px; color: #065f46; font-size: 12px;"><strong>✓ Produto aplicado:</strong> ' + product.name + '</div>');

			this.saveHistory();
		},

		/**
		 * Mover elemento
		 */
		moveElement: function(direction) {
			if (!this.selectedElement) return;

			if (direction === 'up') {
				this.selectedElement.prev('.pcw-element').before(this.selectedElement);
			} else {
				this.selectedElement.next('.pcw-element').after(this.selectedElement);
			}
			this.saveHistory();
		},

		/**
		 * Duplicar elemento
		 */
		duplicateElement: function() {
			if (!this.selectedElement) return;

			var $clone = this.selectedElement.clone();
			this.selectedElement.after($clone);
			this.selectElement($clone);
			this.saveHistory();
		},

		/**
		 * Excluir elemento
		 */
		deleteElement: function() {
			if (!this.selectedElement) return;

			this.selectedElement.remove();
			this.selectedElement = null;
			this.deselectElement();

			if ($('#pcw-canvas-content').children().length === 0) {
				$('#pcw-canvas-content').html('<div class="pcw-canvas-empty">Clique em um bloco para adicionar</div>');
			}
			this.saveHistory();
		},

		/**
		 * Histórico
		 */
		history: [],
		saveHistory: function() {
			this.history.push($('#pcw-canvas-content').html());
			if (this.history.length > 20) this.history.shift();
		},
		undo: function() {
			if (this.history.length > 1) {
				this.history.pop();
				$('#pcw-canvas-content').html(this.history[this.history.length - 1]);
			}
		},

		/**
		 * Obter HTML limpo
		 */
		getCleanHtml: function() {
			var $clone = $('#pcw-canvas-content').clone();
			$clone.find('.pcw-canvas-empty').remove();
			$clone.find('.pcw-element').removeClass('pcw-element pcw-selected').removeAttr('contenteditable');
			
			// Remover wrappers
			var html = '';
			$clone.find('> div').each(function() {
				html += $(this).html();
			});

			return html || $clone.html();
		},

		/**
		 * Salvar
		 */
		save: function() {
			var html = this.getCleanHtml();
			this.targetField.val(html);
			
			// Atualizar preview se existir
			var $preview = this.targetField.closest('.pcw-form-group').find('.pcw-email-preview-frame');
			if ($preview.length) {
				$preview.attr('srcdoc', html);
			}

			// Chamar callback onSave se definido nas opções
			if (this.options && typeof this.options.onSave === 'function') {
				this.options.onSave(html);
			}

			this.close();
		},

		/**
		 * Preview
		 */
		preview: function() {
			var html = this.getCleanHtml();
			var fullHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Preview</title></head><body style="margin: 0; padding: 20px; background: #f5f5f5;">' + html + '</body></html>';
			
			var win = window.open('', '_blank', 'width=700,height=600');
			win.document.write(fullHtml);
			win.document.close();
		},

		/**
		 * Fechar
		 */
		close: function() {
			this.modal.fadeOut(200);
			$('body').removeClass('pcw-editor-open');
			this.deselectElement();
		},

		/**
		 * Converter RGB para HEX
		 */
		rgbToHex: function(rgb) {
			if (!rgb || rgb === 'transparent' || rgb === 'rgba(0, 0, 0, 0)') {
				return '#ffffff';
			}
			
			// Se já for HEX, retornar
			if (rgb.indexOf('#') === 0) {
				return rgb;
			}
			
			// Extrair valores RGB
			var rgbMatch = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
			if (!rgbMatch) {
				rgbMatch = rgb.match(/^rgba\((\d+),\s*(\d+),\s*(\d+),\s*[\d.]+\)$/);
			}
			
			if (!rgbMatch) {
				return '#ffffff';
			}
			
			var r = parseInt(rgbMatch[1]);
			var g = parseInt(rgbMatch[2]);
			var b = parseInt(rgbMatch[3]);
			
			return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
		}
	};

	// Inicializar
	$(document).ready(function() {
		PCWEmailEditor.init();
	});

})(jQuery);
