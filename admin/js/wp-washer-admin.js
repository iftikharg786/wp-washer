/**
 * WP Washer Admin JavaScript
 * Handles: settings save, bulk actions, progress polling, scan & cleanup,
 *          undo restore, log export/clear.
 * Owner: Yellowhoster SMC Limited
 */
/* global wpWasher, jQuery */
(function ($) {
    'use strict';

    var ajaxUrl = wpWasher.ajaxUrl;
    var nonce   = wpWasher.nonce;
    var strings = wpWasher.strings;

    // ─── Settings Page ─────────────────────────────────────────────────────────
    $('#wpw-settings-form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#wpw-save-settings').prop('disabled', true);

        var data = { action: 'wpwasher_save_settings', nonce: wpWasher.settingsNonce };

        // Collect all inputs including unchecked checkboxes.
        $(this).find('input, select').each(function () {
            var name = $(this).attr('name');
            if (!name) return;
            if (this.type === 'checkbox') {
                data[name] = this.checked ? '1' : '0';
            } else {
                data[name] = $(this).val();
            }
        });

        $.post(ajaxUrl, data, function (res) {
            $btn.prop('disabled', false);
            if (res.success) {
                $('#wpw-settings-saved').slideDown(200).delay(2500).slideUp(300);
            } else {
                alert(res.data && res.data.message ? res.data.message : strings.error);
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            alert(strings.error);
        });
    });

    // ─── Bulk Cleaner — Media Selection ────────────────────────────────────────
    var selectedIds = [];

    function updateSelectionUI() {
        var count = selectedIds.length;
        $('#wpw-selection-count').text(count);
        // Keep the panel visible always, but highlight when items selected.
        $('#wpw-bulk-panel').toggleClass('wpw-panel-active', count > 0);
    }

    function isSelected(id) {
        return selectedIds.indexOf(id) !== -1;
    }

    function selectItem($el) {
        var id = parseInt($el.data('id'), 10);
        if (!isSelected(id)) {
            selectedIds.push(id);
            $el.addClass('wpw-selected');
        }
    }

    function deselectItem($el) {
        var id = parseInt($el.data('id'), 10);
        selectedIds = selectedIds.filter(function (i) { return i !== id; });
        $el.removeClass('wpw-selected');
    }

    function toggleItem($el) {
        if ($el.hasClass('wpw-selected')) { deselectItem($el); } else { selectItem($el); }
    }

    var $items = $('#wpw-media-items');
    var lastClickedIndex = -1;

    $items.on('click', '.wpw-media-item', function (e) {
        var $el  = $(this);
        var allItems = $items.find('.wpw-media-item');
        var idx  = allItems.index($el);

        if (e.shiftKey && lastClickedIndex !== -1) {
            // Range select.
            var start = Math.min(lastClickedIndex, idx);
            var end   = Math.max(lastClickedIndex, idx);
            allItems.slice(start, end + 1).each(function () { selectItem($(this)); });
        } else if (e.ctrlKey || e.metaKey) {
            // Toggle individual.
            toggleItem($el);
        } else {
            // Normal click — check if already only selected item.
            var id = parseInt($el.data('id'), 10);
            if (selectedIds.length === 1 && isSelected(id)) {
                deselectItem($el);
            } else {
                toggleItem($el);
            }
        }

        lastClickedIndex = idx;
        updateSelectionUI();
    });

    // Ctrl+A — select all in current view.
    $(document).on('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
            var $grid = $('#wpw-media-items');
            if (!$grid.length) return;
            e.preventDefault();
            $grid.find('.wpw-media-item').each(function () { selectItem($(this)); });
            updateSelectionUI();
        }
    });

    // Select All in View.
    $('#wpw-select-all-view').on('click', function () {
        $items.find('.wpw-media-item').each(function () { selectItem($(this)); });
        updateSelectionUI();
    });

    // Clear selection.
    $('#wpw-deselect-all').on('click', function () {
        selectedIds = [];
        $items.find('.wpw-media-item').removeClass('wpw-selected');
        updateSelectionUI();
    });

    // Select Entire Library (AJAX).
    $('#wpw-select-all-library').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Loading…');
        $.post(ajaxUrl, { action: 'wpwasher_get_all_attachment_ids', nonce: nonce }, function (res) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-portfolio"></span> Select Entire Library');
            if (res.success && res.data.ids) {
                selectedIds = res.data.ids.map(Number);
                // Mark visible items.
                $items.find('.wpw-media-item').each(function () {
                    var id = parseInt($(this).data('id'), 10);
                    if (isSelected(id)) { $(this).addClass('wpw-selected'); }
                });
                updateSelectionUI();
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            alert(strings.error);
        });
    });

    // ─── Drag-to-select ────────────────────────────────────────────────────────
    var drag = { active: false, startX: 0, startY: 0 };
    var $overlay = $('#wpw-drag-overlay');

    $items.on('mousedown', function (e) {
        // Only start drag if clicking on the background (not on an item).
        if ($(e.target).closest('.wpw-media-item').length) return;
        drag.active = true;
        drag.startX = e.pageX - $items.offset().left;
        drag.startY = e.pageY - $items.offset().top;
        $overlay.css({ left: drag.startX, top: drag.startY, width: 0, height: 0 }).show();
        e.preventDefault();
    });

    $(document).on('mousemove', function (e) {
        if (!drag.active) return;
        var offset = $items.offset();
        var curX   = e.pageX - offset.left;
        var curY   = e.pageY - offset.top;
        var x      = Math.min(drag.startX, curX);
        var y      = Math.min(drag.startY, curY);
        var w      = Math.abs(curX - drag.startX);
        var h      = Math.abs(curY - drag.startY);

        $overlay.css({ left: x, top: y, width: w, height: h });

        // Determine which items intersect.
        var rectA = { left: x, top: y, right: x + w, bottom: y + h };
        $items.find('.wpw-media-item').each(function () {
            var itemOff = $(this).offset();
            var itemPOff = { left: itemOff.left - offset.left, top: itemOff.top - offset.top };
            var rectB = { left: itemPOff.left, top: itemPOff.top, right: itemPOff.left + $(this).outerWidth(), bottom: itemPOff.top + $(this).outerHeight() };
            if (rectA.left < rectB.right && rectA.right > rectB.left && rectA.top < rectB.bottom && rectA.bottom > rectB.top) {
                selectItem($(this));
            }
        });
        updateSelectionUI();
    });

    $(document).on('mouseup', function () {
        if (!drag.active) return;
        drag.active = false;
        $overlay.hide();
    });

    // ─── Run Bulk Action ───────────────────────────────────────────────────────
    var actionLabels = {
        delete_meta:       'Delete Metadata Only',
        delete_file:       '⚠ Delete Metadata + File (Permanent)',
        strip_exif:        'Remove EXIF Data',
        remove_thumbs:     'Remove Unused Thumbnails',
        clean_orphan_meta: 'Clean Orphaned Post Meta'
    };

    $('#wpw-run-bulk').on('click', function () {
        var action = $('#wpw-action-select').val();
        if (!action)          { alert(strings.select_action); return; }
        if (!selectedIds.length) { alert(strings.no_selection); return; }

        // Populate modal.
        $('#wpw-modal-count').text(selectedIds.length);
        $('#wpw-modal-action-label').text(actionLabels[action] || action);
        $('#wpw-modal-danger-warning').toggle(action === 'delete_file');

        // Show modal in confirmation state.
        showModal('confirm');
    });

    function showModal(state) {
        $('#wpw-confirm-section').toggle(state === 'confirm');
        $('#wpw-progress-section').toggle(state === 'progress');
        $('#wpw-result-section').toggle(state === 'result');
        $('#wpw-modal-overlay').show();
    }

    $('#wpw-modal-cancel, #wpw-modal-close').on('click', function () {
        $('#wpw-modal-overlay').hide();
    });

    $('#wpw-modal-overlay').on('click', function (e) {
        if ($(e.target).is('#wpw-modal-overlay')) { $('#wpw-modal-overlay').hide(); }
    });

    $('#wpw-modal-confirm').on('click', function () {
        var action = $('#wpw-action-select').val();
        showModal('progress');
        startBatch(action, selectedIds);
    });

    function startBatch(actionType, ids) {
        $('#wpw-total-count').text(ids.length);
        $('#wpw-processed-count').text(0);
        $('#wpw-error-count').text('0 errors');
        $('#wpw-progress-bar').css('width', '0%');

        $.post(ajaxUrl, {
            action:      'wpwasher_bulk_action',
            nonce:       nonce,
            action_type: actionType,
            ids:         ids
        }, function (res) {
            if (res.success) {
                pollProgress(res.data.job_id, res.data.total);
            } else {
                showModal('result');
                $('#wpw-result-message').text(res.data && res.data.message ? res.data.message : strings.error);
                $('.wpw-result-icon').removeClass('dashicons-yes-alt').addClass('dashicons-dismiss');
            }
        }).fail(function () {
            showModal('result');
            $('#wpw-result-message').text(strings.error);
        });
    }

    function pollProgress(jobId, total) {
        $.post(ajaxUrl, { action: 'wpwasher_get_progress', nonce: nonce, job_id: jobId }, function (res) {
            if (res.success) {
                var p = res.data;
                var pct = total > 0 ? Math.round((p.processed / total) * 100) : 0;
                $('#wpw-progress-bar').css('width', pct + '%');
                $('#wpw-processed-count').text(p.processed);
                $('#wpw-total-count').text(p.total || total);
                var errCount = p.errors ? p.errors.length : 0;
                $('#wpw-error-count').text(errCount + ' errors');

                if (p.done) {
                    showModal('result');
                    $('#wpw-result-message').text(
                        strings.done + ' ' + p.success + ' succeeded, ' +
                        p.skipped + ' skipped, ' + errCount + ' errors.'
                    );
                    // Clear selection after done.
                    selectedIds = [];
                    $items.find('.wpw-media-item').removeClass('wpw-selected');
                    updateSelectionUI();
                } else {
                    // Continue polling.
                    setTimeout(function () { pollProgress(jobId, total); }, 300);
                }
            } else {
                showModal('result');
                $('#wpw-result-message').text(strings.error);
            }
        }).fail(function () {
            // Retry on failure (server might be processing).
            setTimeout(function () { pollProgress(jobId, total); }, 1000);
        });
    }

    // ─── Reports Page — Scan & Cleanup ─────────────────────────────────────────
    var scanData = {};

    $('#wpw-scan-btn').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        $('#wpw-scan-label').text('Scanning…');
        $('#wpw-scan-icon').addClass('wpw-spin');

        $.post(ajaxUrl, { action: 'wpwasher_scan_orphans', nonce: nonce }, function (res) {
            $btn.prop('disabled', false);
            $('#wpw-scan-label').text('Scan Now');
            $('#wpw-scan-icon').removeClass('wpw-spin');

            if (res.success) {
                scanData = res.data;
                $('#wpw-orphan-att-count').text(res.data.orphaned_attachments.length);
                $('#wpw-orphan-meta-count').text(res.data.orphaned_postmeta.length);
                $('#wpw-space-saved').text(res.data.estimated_size_human);
                $('#wpw-scan-results').slideDown(200);
            } else {
                alert(strings.error);
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            alert(strings.error);
        });
    });

    $('#wpw-cleanup-btn').on('click', function () {
        if (!confirm(strings.confirm_cleanup)) return;
        var $btn = $(this).prop('disabled', true);

        $.post(ajaxUrl, {
            action:             'wpwasher_confirm_cleanup',
            nonce:              nonce,
            delete_attachments: $('#wpw-delete-att-files').is(':checked') ? 1 : 0,
            meta_ids:           scanData.orphaned_postmeta || [],
            attachment_ids:     scanData.orphaned_attachments || []
        }, function (res) {
            $btn.prop('disabled', false);
            if (res.success) {
                $('#wpw-cleanup-result').html(
                    '<div class="wpw-notice wpw-notice-success"><span class="dashicons dashicons-yes-alt"></span>' +
                    escHtml(res.data.message) + '</div>'
                ).show();
                $('#wpw-scan-results').hide();
            } else {
                $('#wpw-cleanup-result').html(
                    '<div class="wpw-notice wpw-notice-error">' + escHtml(res.data && res.data.message ? res.data.message : strings.error) + '</div>'
                ).show();
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            alert(strings.error);
        });
    });

    // ─── Restore last ──────────────────────────────────────────────────────────
    $('#wpw-restore-last').on('click', function () {
        if (!confirm(strings.restore_confirm)) return;
        var $btn = $(this).prop('disabled', true);

        $.post(ajaxUrl, { action: 'wpwasher_restore_last', nonce: nonce }, function (res) {
            $btn.prop('disabled', false);
            if (res.success) {
                $('#wpw-restore-result').html(
                    '<div class="wpw-notice wpw-notice-success"><span class="dashicons dashicons-yes-alt"></span> Restored metadata for ' +
                    res.data.restored + ' attachment(s).</div>'
                ).show();
            } else {
                $('#wpw-restore-result').html('<div class="wpw-notice wpw-notice-error">' + strings.error + '</div>').show();
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            alert(strings.error);
        });
    });

    // ─── Logs Page ────────────────────────────────────────────────────────────
    $('#wpw-export-log').on('click', function () {
        var form = $('<form method="post" action="' + ajaxUrl + '">' +
            '<input name="action" value="wpwasher_export_log">' +
            '<input name="nonce" value="' + nonce + '">' +
            '</form>');
        $('body').append(form);
        form.submit();
        form.remove();
    });

    $('#wpw-clear-log').on('click', function () {
        if (!confirm('Clear all log entries? This cannot be undone.')) return;
        $.post(ajaxUrl, { action: 'wpwasher_clear_log', nonce: nonce }, function (res) {
            if (res.success) { location.reload(); }
        });
    });

    // ─── Utility ──────────────────────────────────────────────────────────────
    function escHtml(str) {
        return $('<div>').text(String(str)).html();
    }

}(jQuery));
