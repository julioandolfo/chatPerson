/**
 * Growly Digital - Admin Notifications
 * Handles email preview functionality
 */
(function($) {
    'use strict';

    var PCWNotifications = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Preview button click
            $(document).on('click', '.pcw-preview-email', this.handlePreviewClick.bind(this));
            
            // Close modal
            $(document).on('click', '.pcw-email-preview-close', this.closeModal.bind(this));
            $(document).on('click', '.pcw-email-preview-modal', this.handleModalClick.bind(this));
            
            // ESC key to close
            $(document).on('keyup', this.handleKeyUp.bind(this));
        },

        handlePreviewClick: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var type = $button.data('type');
            var subjectField = $button.data('subject-field');
            var bodyEditor = $button.data('body-editor');
            
            // Get subject value
            var subject = $('#' + subjectField).val();
            
            // Get body value from wp_editor (TinyMCE or textarea)
            var body = '';
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get(bodyEditor)) {
                body = tinyMCE.get(bodyEditor).getContent();
            } else {
                body = $('#' + bodyEditor).val();
            }
            
            // Show loading state
            $button.prop('disabled', true).text('Carregando...');
            
            // Make AJAX request
            $.ajax({
                url: pcwNotifications.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcw_preview_email',
                    nonce: pcwNotifications.nonce,
                    type: type,
                    subject: subject,
                    body: body
                },
                success: function(response) {
                    if (response.success) {
                        PCWNotifications.showPreview(response.data.subject, response.data.body);
                    } else {
                        alert('Erro ao gerar preview: ' + (response.data.message || 'Erro desconhecido'));
                    }
                },
                error: function() {
                    alert('Erro de conexão ao gerar preview');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-visibility"></span> Visualizar Email');
                }
            });
        },

        showPreview: function(subject, body) {
            var $modal = $('#pcw-email-preview-modal');
            var $subjectSpan = $('#pcw-preview-subject');
            var $iframe = $('#pcw-preview-iframe');
            
            // Set subject
            $subjectSpan.text(subject);
            
            // Set body in iframe
            var iframeDoc = $iframe[0].contentDocument || $iframe[0].contentWindow.document;
            iframeDoc.open();
            iframeDoc.write(body);
            iframeDoc.close();
            
            // Show modal
            $modal.addClass('active');
            $('body').css('overflow', 'hidden');
        },

        closeModal: function() {
            $('#pcw-email-preview-modal').removeClass('active');
            $('body').css('overflow', '');
        },

        handleModalClick: function(e) {
            if ($(e.target).hasClass('pcw-email-preview-modal')) {
                this.closeModal();
            }
        },

        handleKeyUp: function(e) {
            if (e.keyCode === 27) { // ESC
                this.closeModal();
            }
        }
    };

    $(document).ready(function() {
        PCWNotifications.init();
    });

})(jQuery);
