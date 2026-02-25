/**
 * WP Washer Media Library JS
 * Extends the native WordPress Media Library grid view with:
 *   - drag-to-select
 *   - Shift+Click range select
 *   - Ctrl+Click individual toggle
 *   - Ctrl+A select all in view
 *
 * NOTE: For the Bulk Cleaner page the plugin renders its own grid,
 *       so this file provides the wp.media frame integration for
 *       future embedding inside the native media modal if needed.
 *
 * Owner: Yellowhoster SMC Limited
 */
/* global wp, wpWasher, jQuery */
(function ($) {
    'use strict';

    // Only run if wp.media is loaded.
    if (typeof wp === 'undefined' || !wp.media) { return; }

    /**
     * Patch the wp.media.view.Attachments to wire additional selection events.
     */
    var OriginalAttachments = wp.media.view.Attachments;

    wp.media.view.Attachments = OriginalAttachments.extend({

        events: $.extend({}, OriginalAttachments.prototype.events, {
            'click .attachment': 'onWpwAttachmentClick'
        }),

        initialize: function () {
            OriginalAttachments.prototype.initialize.apply(this, arguments);
            this._wpwLastIdx = -1;
        },

        onWpwAttachmentClick: function (e) {
            var $clicked = $(e.currentTarget);
            var allItems = this.$('.attachment');
            var clickedIdx = allItems.index($clicked);
            var selection = this.model.get('selection');
            var collection = this.collection;

            if (e.shiftKey && this._wpwLastIdx !== -1 && clickedIdx !== -1) {
                e.stopImmediatePropagation();
                var start = Math.min(this._wpwLastIdx, clickedIdx);
                var end = Math.max(this._wpwLastIdx, clickedIdx);
                collection.slice(start, end + 1).forEach(function (model) {
                    selection.add(model);
                });
                this._wpwLastIdx = clickedIdx;
                return;
            }

            if (e.ctrlKey || e.metaKey) {
                e.stopImmediatePropagation();
                var model = collection.at(clickedIdx);
                if (model) {
                    if (selection.has(model)) {
                        selection.remove(model);
                    } else {
                        selection.add(model);
                    }
                }
                this._wpwLastIdx = clickedIdx;
                return;
            }

            this._wpwLastIdx = clickedIdx;
            // Let original handler run for normal click.
        }
    });

    // Ctrl+A inside media modal.
    $(document).on('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
            var frame = wp.media.frame;
            if (!frame) return;
            var state = frame.state && frame.state();
            if (!state) return;
            var selection = state.get('selection');
            var collection = state.get('library');
            if (!selection || !collection) return;
            e.preventDefault();
            collection.each(function (model) { selection.add(model); });
        }
    });

}(jQuery));
