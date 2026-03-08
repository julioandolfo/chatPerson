/**
 * Growly Digital - Frontend Activity Tracker
 */
(function($) {
    'use strict';

    var PCWActivityTracker = {
        init: function() {
            this.trackCartEvents();
            this.trackCheckoutEvents();
        },

        track: function(activityType, objectType, objectId, objectName, objectPrice) {
            $.ajax({
                url: pcwTracker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_track_activity',
                    nonce: pcwTracker.nonce,
                    activity_type: activityType,
                    object_type: objectType || '',
                    object_id: objectId || 0,
                    object_name: objectName || '',
                    object_price: objectPrice || 0,
                    page_url: window.location.href
                }
            });
        },

        trackCartEvents: function() {
            // Track add to cart
            $(document.body).on('added_to_cart', function(e, fragments, cart_hash, $button) {
                var $product = $button.closest('.product');
                var productId = $button.data('product_id') || 0;
                var productName = $product.find('.woocommerce-loop-product__title, .product_title').text().trim();
                var productPrice = $product.find('.price .woocommerce-Price-amount').first().text().replace(/[^\d.,]/g, '');

                PCWActivityTracker.track('add_to_cart', 'product', productId, productName, parseFloat(productPrice.replace(',', '.')));
            });

            // Track single product add to cart
            $('form.cart').on('submit', function() {
                var productId = $(this).find('input[name="product_id"], button[name="add-to-cart"]').val();
                var productName = $('.product_title').text().trim();
                var productPrice = $('.price .woocommerce-Price-amount').first().text().replace(/[^\d.,]/g, '');

                PCWActivityTracker.track('add_to_cart', 'product', productId, productName, parseFloat(productPrice.replace(',', '.')));
            });
        },

        trackCheckoutEvents: function() {
            // Track checkout initiation
            if ($('body').hasClass('woocommerce-checkout')) {
                this.track('checkout_start', 'checkout', 0, '', 0);
            }

            // Track order placed
            if ($('body').hasClass('woocommerce-order-received')) {
                var orderId = $('.woocommerce-order-overview__order strong').text();
                this.track('order_placed', 'order', parseInt(orderId) || 0, '', 0);
            }
        }
    };

    $(document).ready(function() {
        PCWActivityTracker.init();
    });

})(jQuery);
