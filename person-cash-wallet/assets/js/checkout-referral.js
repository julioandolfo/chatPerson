/**
 * Script para campo de código de indicação no checkout
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

(function($) {
    'use strict';

    var PCW_Referral_Checkout = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Validar código ao clicar no botão
            $(document).on('click', '#pcw-validate-code-btn', this.validateCode);

            // Validar ao sair do campo
            $(document).on('blur', '#pcw_referral_code, #pcw_referral_code_pay', this.validateCode);

            // Converter para uppercase
            $(document).on('input', '#pcw_referral_code, #pcw_referral_code_pay', function() {
                $(this).val($(this).val().toUpperCase());
            });
        },

        validateCode: function(e) {
            if (e) {
                e.preventDefault();
            }

            var $input = $('#pcw_referral_code').length ? $('#pcw_referral_code') : $('#pcw_referral_code_pay');
            var code = $input.val() ? $input.val().trim() : '';

            // Se campo vazio, limpar feedback e sair
            if (!code) {
                PCW_Referral_Checkout.showFeedback('', '');
                $('#pcw_referral_code_validated').val('0');
                return;
            }

            // Verificar se variável pcwReferral existe
            if (typeof pcwReferral === 'undefined') {
                console.error('PCW Referral: Script não carregado corretamente');
                return;
            }

            var $btn = $('#pcw-validate-code-btn');

            $btn.prop('disabled', true).text(pcwReferral.validating);

            $.ajax({
                url: pcwReferral.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_validate_referral_code',
                    nonce: pcwReferral.nonce,
                    code: code
                },
                success: function(response) {
                    $btn.prop('disabled', false).text(pcwReferral.validate);

                    if (response && response.success) {
                        PCW_Referral_Checkout.showFeedback(response.data.message, 'valid');
                        $('#pcw_referral_code_validated').val('1');
                    } else if (response && response.data) {
                        PCW_Referral_Checkout.showFeedback(response.data.message, 'invalid');
                        $('#pcw_referral_code_validated').val('0');
                    } else {
                        PCW_Referral_Checkout.showFeedback('Código inválido.', 'invalid');
                        $('#pcw_referral_code_validated').val('0');
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).text(pcwReferral.validate);
                    console.error('PCW Referral AJAX Error:', status, error);
                    PCW_Referral_Checkout.showFeedback('Erro ao validar. Tente novamente.', 'invalid');
                    $('#pcw_referral_code_validated').val('0');
                }
            });
        },

        showFeedback: function(message, type) {
            var $feedback = $('#pcw-referral-feedback');

            if (!message) {
                $feedback.hide();
                return;
            }

            var icon = type === 'valid' ? '✓' : '✗';
            var className = 'pcw-' + type;

            $feedback.html('<span class="' + className + '">' + icon + ' ' + message + '</span>').show();
        }
    };

    $(document).ready(function() {
        PCW_Referral_Checkout.init();
    });

})(jQuery);
