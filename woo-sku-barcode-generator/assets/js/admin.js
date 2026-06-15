/* global jQuery, wsbgAdmin, ajaxurl */
(function ($) {
    'use strict';

    // Add to print queue from product list / metabox
    $(document).on('click', '.wsbg-add-to-queue', function (e) {
        e.preventDefault();
        var $btn  = $(this);
        var id    = $btn.data('id');
        var nonce = $btn.data('nonce');
        var qty   = parseInt( $btn.data('qty') || 1, 10 );

        $.post(ajaxurl, {
            action: 'wsbg_queue_add',
            nonce:  nonce,
            id:     id,
            qty:    qty
        }, function (res) {
            if (res.success) {
                $btn.text('✓ ' + wsbgAdmin.addedText).prop('disabled', true);
            }
        });
    });

    // Dynamic variation: update barcode preview when variation changes
    $('body').on('found_variation', function (e, variation) {
        if (!variation.sku) return;
        // Could add AJAX call here to get variation barcode SVG if needed
    });

}(jQuery));
