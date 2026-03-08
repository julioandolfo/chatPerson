/**
 * Script para painel admin de indicações
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

(function($) {
    'use strict';

    var PCW_Admin_Referrals = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Excluir indicação
            $(document).on('click', '.pcw-delete-referral', this.deleteReferral);
        },

        deleteReferral: function(e) {
            e.preventDefault();

            if (!confirm(pcwAdminReferrals.i18n.confirmDelete)) {
                return;
            }

            var $btn = $(this);
            var referralId = $btn.data('id');
            var $row = $btn.closest('tr');

            $btn.prop('disabled', true);

            $.ajax({
                url: pcwAdminReferrals.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_admin_referral_action',
                    nonce: pcwAdminReferrals.nonce,
                    referral_action: 'delete',
                    referral_id: referralId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || pcwAdminReferrals.i18n.error);
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(pcwAdminReferrals.i18n.error);
                    $btn.prop('disabled', false);
                }
            });
        }
    };

    $(document).ready(function() {
        PCW_Admin_Referrals.init();
    });

})(jQuery);
