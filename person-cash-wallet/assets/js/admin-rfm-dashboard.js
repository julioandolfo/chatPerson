/**
 * Script do Dashboard RFM
 * @since 1.4.0
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Recalcular RFM
		$('#pcw-recalculate-rfm').on('click', function(e) {
			e.preventDefault();

			var $button = $(this);
			var originalText = $button.text();

			$button.prop('disabled', true).text('Calculando...');

			$.post(pcwRFM.ajax_url, {
				action: 'pcw_recalculate_rfm',
				nonce: pcwRFM.nonce
			}, function(response) {
				if (response.success) {
					alert('Segmentos RFM calculados com sucesso!\n\nClientes processados: ' + response.data.processed);
					location.reload();
				} else {
					alert('Erro ao calcular segmentos: ' + response.data.message);
				}
			}).fail(function() {
				alert('Erro ao processar requisição');
			}).always(function() {
				$button.prop('disabled', false).text(originalText);
			});
		});
	});

})(jQuery);
