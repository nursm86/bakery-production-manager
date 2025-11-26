jQuery(document).ready(function ($) {
    // Init Select2
    $('.bpm-select2').select2({
        width: '100%'
    });

    // Product Search for Production
    $('#bpm-quick-production-product').select2({
        ajax: {
            url: bpmDashboard.ajaxUrl,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    action: 'bpm_search_products',
                    term: params.term,
                    nonce: bpmDashboard.nonce
                };
            },
            processResults: function (data) {
                return {
                    results: data.success ? data.data.results : []
                };
            },
            cache: true
        },
        minimumInputLength: 3,
        placeholder: bpmDashboard.labels.searchPlaceholder
    });

    // Quick Production Submit
    $('#bpm-quick-production-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');

        const data = {
            product_id: $('#bpm-quick-production-product').val(),
            quantity_produced: $('#bpm-quick-production-qty').val(),
            quantity_wasted: 0, // Default to 0 for quick add
            production_type: $('#bpm-quick-production-type').val(),
            production_date: new Date().toISOString().slice(0, 10), // Today
            unit_type: 'piece' // Default
        };

        if (!data.product_id || !data.quantity_produced) {
            alert(bpmDashboard.messages.validation);
            return;
        }

        $btn.prop('disabled', true);

        $.post(bpmDashboard.ajaxUrl, {
            action: 'bpm_save_production_entries',
            nonce: bpmDashboard.nonce,
            entries: JSON.stringify([data]),
            production_date: data.production_date
        }, function (response) {
            $btn.prop('disabled', false);
            if (response.success) {
                alert(bpmDashboard.messages.saved);
                $form[0].reset();
                $('#bpm-quick-production-product').val(null).trigger('change');
                // Reload page to update stats (simple approach for now)
                location.reload();
            } else {
                alert(response.data.message || bpmDashboard.messages.error);
            }
        });
    });

    // Cook from Cold Storage
    $('.bpm-cook-cold-storage-btn').on('click', function () {
        const productId = $(this).data('product-id');
        const maxQty = $(this).data('max-qty');
        const qty = prompt(bpmDashboard.messages.enterCookQty + ' (Max: ' + maxQty + ')');

        if (qty === null) return; // Cancelled

        const cookQty = parseFloat(qty);
        if (isNaN(cookQty) || cookQty <= 0 || cookQty > maxQty) {
            alert(bpmDashboard.messages.invalidQty);
            return;
        }

        const localDate = new Date();
        localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
        const productionDate = localDate.toISOString().slice(0, 16);

        $.post(bpmDashboard.ajaxUrl, {
            action: 'bpm_cook_cold_storage',
            nonce: bpmDashboard.nonce,
            product_id: productId,
            quantity: cookQty,
            production_date: productionDate
        }, function (response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert(response.data.message || bpmDashboard.messages.error);
            }
        });
    });

    // Waste from Cold Storage
    $('.bpm-waste-cold-storage-btn').on('click', function () {
        const productId = $(this).data('product-id');
        const maxQty = $(this).data('max-qty');
        const qty = prompt(bpmDashboard.messages.enterWasteQty + ' (Max: ' + maxQty + ')');

        if (qty === null) return; // Cancelled

        const wasteQty = parseFloat(qty);
        if (isNaN(wasteQty) || wasteQty <= 0 || wasteQty > maxQty) {
            alert(bpmDashboard.messages.invalidQty);
            return;
        }

        const localDate = new Date();
        localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
        const productionDate = localDate.toISOString().slice(0, 16);

        $.post(bpmDashboard.ajaxUrl, {
            action: 'bpm_waste_cold_storage',
            nonce: bpmDashboard.nonce,
            product_id: productId,
            quantity: wasteQty,
            production_date: productionDate
        }, function (response) {
            if (response.success) {
                alert(response.data.message || bpmDashboard.messages.wasteRecorded);
                location.reload();
            } else {
                alert(response.data.message || bpmDashboard.messages.error);
            }
        });
    });

    // Quick Inventory Usage Submit
    $('#bpm-quick-usage-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');

        const data = {
            material_id: $('#bpm-quick-usage-material').val(),
            quantity: $('#bpm-quick-usage-qty').val(),
            reason: $('#bpm-quick-usage-reason').val(),
            type: 'use',
            transaction_date: new Date().toISOString().slice(0, 19).replace('T', ' ')
        };

        if (!data.material_id || !data.quantity) {
            alert(bpmDashboard.messages.validation);
            return;
        }

        $btn.prop('disabled', true);

        $.post(bpmDashboard.ajaxUrl, {
            action: 'rim_create_transaction',
            nonce: bpmDashboard.rimNonce,
            material_id: data.material_id,
            quantity: data.quantity,
            type: data.type,
            reason: data.reason,
            transaction_date: data.transaction_date
        }, function (response) {
            $btn.prop('disabled', false);
            if (response.success) {
                alert(bpmDashboard.messages.usageSaved);
                $form[0].reset();
                $('#bpm-quick-usage-material').val(null).trigger('change');
            } else {
                alert(response.data.message || bpmDashboard.messages.error);
            }
        });
    });
});
