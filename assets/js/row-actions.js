/**
 * Row Actions - AJAX JavaScript
 *
 * Handles AJAX row actions in WordPress admin tables.
 *
 * @package SugarCommerce\Columns
 */

(function ($) {
    'use strict';

    // Every plugin bundling a prefixed copy of the library loads this file
    // under its own handle. One click handler serves all of them -- each link
    // names its own configuration -- so a second copy binding again would
    // send every action twice.
    if (window.SugarCommerceColumnsRowActions) {
        return;
    }

    /**
     * The configuration a link's copy of the library printed.
     *
     * @param {jQuery} $link The link.
     *
     * @return {Object}
     */
    const configFor = ($link) => (window.SugarCommerceColumns || {})[$link.data('config')] || {};

    /**
     * Row Action Handler
     */
    const RowActionHandler = {

        /**
         * Initialize the handler
         */
        init() {
            this.bindEvents();
        },

        /**
         * Bind click events to AJAX action links
         */
        bindEvents() {
            $(document).on('click', '.row-action-ajax', (e) => {
                e.preventDefault();
                this.handleAction($(e.currentTarget));
            });
        },

        /**
         * Handle an action click
         *
         * @param {jQuery} $link The clicked link element
         */
        async handleAction($link) {
            const config = configFor($link);
            const strings = config.strings || {};

            // Get confirmation if needed
            const confirmMessage = $link.data('confirm');
            if (confirmMessage && !window.confirm(confirmMessage)) {
                return;
            }

            // Get action data
            const data = {
                // From the link, not rebuilt here: the action carries this
                // build's prefix, which the browser has no way to derive, and
                // a shared config object would be owned by whichever copy
                // localised last.
                action: $link.data('ajax-action'),
                action_key: $link.data('action-key'),
                object_id: $link.data('object-id'),
                _wpnonce: $link.data('nonce'),
                options: {}
            };

            // Visual feedback - store original text and show spinner
            const originalHtml = $link.html();
            const icons = this.icons(config);
            const $icon = $link.find('.sugarcommerce-icon, .dashicons').first();

            if ($icon.length) {
                $icon.replaceWith(icons.processing);
            } else {
                $link.html(icons.processing + ' ' + (strings.processing || 'Processing...'));
            }

            // Disable the link
            $link.css('pointer-events', 'none').css('opacity', '0.6');

            try {
                const response = await $.ajax({
                    url: config.ajaxUrl || window.ajaxurl,
                    type: 'POST',
                    data: data
                });

                if (response.success) {
                    // Handle success
                    this.handleSuccess($link, response.data, originalHtml, config);
                } else {
                    // Handle error
                    this.handleError($link, response.data, originalHtml, strings, config);
                }

            } catch (error) {
                // Handle AJAX error
                this.handleError($link, {message: error.statusText || 'AJAX request failed'}, originalHtml, strings, config);
            }
        },

        /**
         * The icons for each state, drawn by PHP.
         *
         * Pre-rendered markup from the link's configuration; the Dashicons
         * these were before if the configuration does not send them.
         *
         * @param {Object} config The link's configuration.
         *
         * @return {{processing: string, success: string, error: string}}
         */
        icons(config) {
            const icons = (config || {}).icons || {};

            return {
                processing: icons.processing || '<span class="dashicons dashicons-update-alt sugarcommerce-icon row-action-spin"></span>',
                success: icons.success || '<span class="dashicons dashicons-yes sugarcommerce-icon row-action-success"></span>',
                error: icons.error || '<span class="dashicons dashicons-no-alt sugarcommerce-icon row-action-error"></span>'
            };
        },

        /**
         * Handle successful response
         *
         * @param {jQuery} $link The link element
         * @param {Object} data Response data
         * @param {string} originalHtml Original link HTML
         * @param {Object} config The link's configuration
         */
        handleSuccess($link, data, originalHtml, config) {
            const strings = (config || {}).strings || {};

            // Show success message if provided
            if (data.message) {
                this.showNotice(data.message, 'success');
            }

            // Default to reload unless explicitly set to false
            const shouldReload = data.reload !== false;

            if (shouldReload) {
                // Brief visual feedback before reload
                $link.html(this.icons(config).success + ' ' + (strings.success || 'Success'));
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                // No reload - show success icon briefly then restore
                $link.html(this.icons(config).success);
                setTimeout(() => {
                    $link.html(originalHtml);
                    $link.css('pointer-events', '').css('opacity', '');
                }, 1000);
            }
        },

        /**
         * Handle error response
         *
         * @param {jQuery} $link The link element
         * @param {Object} data Response data
         * @param {string} originalHtml Original link HTML
         * @param {Object} strings Localized strings
         * @param {Object} config The link's configuration
         */
        handleError($link, data, originalHtml, strings, config) {
            // Show error message
            // A refusal is sent as a bare string (wp_send_json_error( 'Invalid action' )),
            // a callback's own answer as an object with a message.
            const message = (typeof data === 'string' ? data : data?.message) || (strings.error || 'Error') + ': Unknown error';
            this.showNotice(message, 'error');

            // Show error icon briefly
            $link.html(this.icons(config).error);

            // Restore original state after showing error
            setTimeout(() => {
                $link.html(originalHtml);
                $link.css('pointer-events', '').css('opacity', '');
            }, 2000);
        },

        /**
         * Show admin notice
         *
         * @param {string} message The message to display
         * @param {string} type The notice type (success, error, warning, info)
         */
        showNotice(message, type = 'success') {
            const noticeClass = type === 'error' ? 'notice-error' :
                type === 'warning' ? 'notice-warning' :
                    type === 'info' ? 'notice-info' :
                        'notice-success';

            // The message as text: it comes from a plugin's callback and can
            // carry a title or a name somebody typed.
            const $notice = $(`<div class="notice ${noticeClass} is-dismissible"><p></p></div>`);
            $notice.find('p').text(String(message));

            // Find the best place to insert the notice
            const $target = $('.wrap > h1, .wrap > h2').first();
            if ($target.length) {
                $target.after($notice);
            } else {
                $('.wrap').prepend($notice);
            }

            // Make dismissible
            $notice.on('click', '.notice-dismiss', function () {
                $notice.fadeOut(() => $notice.remove());
            });

            // Auto-remove success notices
            if (type === 'success') {
                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, 5000);
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(() => {
        RowActionHandler.init();
    });

    // Exposed, and the flag the guard above reads.
    window.SugarCommerceColumnsRowActions = RowActionHandler;

})(jQuery);