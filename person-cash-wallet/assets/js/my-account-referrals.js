/**
 * Script para área Minha Conta - Indicações
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

(function($) {
    'use strict';

    var PCW_My_Referrals = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Copiar código/link
            $(document).on('click', '.pcw-copy-btn', this.copyToClipboard);

            // Toggle QR Code
            $(document).on('click', '#pcw-qr-toggle', this.toggleQRCode);

            // Submit formulário de indicação
            $(document).on('submit', '#pcw-add-referral-form', this.submitReferral);

            // Máscara de telefone
            $('#pcw_referred_phone').on('input', this.maskPhone);
        },

        copyToClipboard: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var textToCopy = $btn.data('copy');

            // Criar elemento temporário
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(textToCopy).select();
            document.execCommand('copy');
            $temp.remove();

            // Feedback visual
            var originalHtml = $btn.html();
            $btn.html('<span style="color: #10b981;">✓</span>');

            setTimeout(function() {
                $btn.html(originalHtml);
            }, 2000);

            // Toast
            PCW_My_Referrals.showToast(pcwMyReferrals.copied);
        },

        toggleQRCode: function(e) {
            e.preventDefault();

            var $qrCode = $('#pcw-qr-code');
            var $btn = $(this);

            if ($qrCode.is(':visible')) {
                $qrCode.slideUp();
                $btn.text($btn.data('show') || 'Mostrar QR Code');
            } else {
                $qrCode.slideDown();
                $btn.text($btn.data('hide') || 'Esconder QR Code');
            }
        },

        submitReferral: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $form.find('.pcw-submit-btn');
            var $message = $('#pcw-form-message');

            $btn.prop('disabled', true).text('Enviando...');
            $message.removeClass('success error').text('');

            $.ajax({
                url: pcwMyReferrals.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=pcw_add_referral',
                success: function(response) {
                    $btn.prop('disabled', false).text('Adicionar Indicação');

                    if (response.success) {
                        $message.addClass('success').text(response.data.message);
                        $form[0].reset();

                        // Recarregar página após 2 segundos
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $message.addClass('error').text(response.data.message);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Adicionar Indicação');
                    $message.addClass('error').text('Erro ao enviar. Tente novamente.');
                }
            });
        },

        maskPhone: function() {
            var value = $(this).val().replace(/\D/g, '');

            if (value.length > 11) {
                value = value.substring(0, 11);
            }

            if (value.length > 2) {
                value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
            }
            if (value.length > 10) {
                value = value.substring(0, 10) + '-' + value.substring(10);
            }

            $(this).val(value);
        },

        showToast: function(message) {
            var $toast = $('<div class="pcw-toast">' + message + '</div>');
            $('body').append($toast);

            $toast.css({
                position: 'fixed',
                bottom: '20px',
                left: '50%',
                transform: 'translateX(-50%)',
                background: '#333',
                color: '#fff',
                padding: '12px 24px',
                borderRadius: '6px',
                zIndex: 9999,
                opacity: 0
            }).animate({ opacity: 1 }, 200);

            setTimeout(function() {
                $toast.animate({ opacity: 0 }, 200, function() {
                    $toast.remove();
                });
            }, 2000);
        }
    };

    $(document).ready(function() {
        PCW_My_Referrals.init();
    });

})(jQuery);
