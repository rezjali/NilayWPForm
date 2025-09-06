jQuery(document).ready(function ($) {
    'use strict';

    function initializeAdvancedFields($container) {
        // Init datepicker
        $container.find('.nfb-datepicker:not(.hasDatepicker)').each(function () {
            $(this).datepicker({ dateFormat: 'yy-mm-dd' });
        });

        // Init signature pad
        $container.find('.nfb-signature-wrapper').each(function () {
            var wrapper = $(this);
            var canvas = wrapper.find('canvas.nfb-signature-pad')[0];
            var hiddenInput = wrapper.find('input[type="hidden"]');
            var clearButton = wrapper.find('.nfb-signature-clear');
            var signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(248, 248, 248)' });

            function updateHiddenInput() {
                hiddenInput.val(signaturePad.isEmpty() ? '' : signaturePad.toDataURL('image/png'));
            }

            signaturePad.addEventListener("endStroke", updateHiddenInput);
            clearButton.on('click', (e) => { e.preventDefault(); signaturePad.clear(); updateHiddenInput(); });

            function resizeCanvas() {
                var ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                signaturePad.clear();
            }
            $(window).on("resize", resizeCanvas);
            setTimeout(resizeCanvas, 200);
        });

        // Init map
        $container.find('.nfb-map-preview').each(function () {
            var $wrapper = $(this).closest('.nfb-map-field-wrapper');
            var input = $wrapper.find('input[type="text"]');
            var mapContainer = this;
            var latlng = [35.6892, 51.3890]; // Default to Tehran
            var map = L.map(mapContainer).setView(latlng, 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            var marker = L.marker(latlng, { draggable: true }).addTo(map);

            marker.on('dragend', (e) => input.val(e.target.getLatLng().lat.toFixed(6) + ',' + e.target.getLatLng().lng.toFixed(6)));
            map.on('click', (e) => { marker.setLatLng(e.latlng); input.val(e.latlng.lat.toFixed(6) + ',' + e.latlng.lng.toFixed(6)); });
            setTimeout(() => map.invalidateSize(), 200);
        });
    }

    function checkConditionalLogic($form) {
        $form.find('[data-conditional-logic="true"]').each(function () {
            var $dependentField = $(this);
            var action = $dependentField.data('conditional-action');
            var targetKey = $dependentField.data('conditional-target');
            var requiredValue = $dependentField.data('conditional-value');
            var $targetField = $form.find(`[name="${targetKey}"], [name="${targetKey}[]"]`);

            var currentValue;
            if ($targetField.is(':radio') || $targetField.is(':checkbox')) {
                currentValue = $targetField.filter(':checked').val() || '';
            } else {
                currentValue = $targetField.val() || '';
            }

            var conditionMet = (currentValue == requiredValue);
            var shouldShow = (action === 'show') ? conditionMet : !conditionMet;

            $dependentField.toggle(shouldShow);
        });
    }

    $('.nfb-form').each(function () {
        const $form = $(this);
        const $pages = $form.find('.nfb-page');
        const totalPages = $pages.length;
        let currentPageIdx = 0;

        if (totalPages <= 1) {
            $form.find('.nfb-nav-buttons, .nfb-step-indicator').hide();
            return;
        }

        const $nextBtn = $form.find('.nfb-next-btn');
        const $prevBtn = $form.find('.nfb-prev-btn');
        const $submitBtn = $form.find('.nfb-submit-btn');
        const $stepIndicator = $form.find('.nfb-step-indicator');

        function updateFormState() {
            $pages.removeClass('active').hide();
            $pages.eq(currentPageIdx).addClass('active').show();

            $prevBtn.toggle(currentPageIdx > 0);
            $nextBtn.toggle(currentPageIdx < totalPages - 1);
            $submitBtn.toggle(currentPageIdx === totalPages - 1);

            if ($stepIndicator.length) {
                $stepIndicator.text(`مرحله ${currentPageIdx + 1} از ${totalPages}`);
            }
        }

        function validateCurrentPage() {
            let isValid = true;
            $pages.eq(currentPageIdx).find('[required]:visible').each(function () {
                if (!this.checkValidity()) {
                    $(this).closest('.nfb-field-group').addClass('has-error');
                    this.reportValidity();
                    isValid = false;
                    return false; // break loop
                } else {
                    $(this).closest('.nfb-field-group').removeClass('has-error');
                }
            });
            return isValid;
        }

        $nextBtn.on('click', function () {
            if (validateCurrentPage() && currentPageIdx < totalPages - 1) {
                currentPageIdx++;
                updateFormState();
            }
        });

        $prevBtn.on('click', function () {
            if (currentPageIdx > 0) {
                currentPageIdx--;
                updateFormState();
            }
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            if (!validateCurrentPage()) return;

            const $responseDiv = $form.find('.nfb-response-message');
            const formData = new FormData(this);
            formData.append('action', 'nfb_submit_form');
            formData.append('nonce', nfb_vars.nonce);

            $.ajax({
                url: nfb_vars.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: () => {
                    $submitBtn.prop('disabled', true).text('در حال ارسال...');
                    $responseDiv.removeClass('success error').hide();
                },
                success: (response) => {
                    if (response.success) {
                        $responseDiv.addClass('success').html(response.data.message).show();
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            $form[0].reset();
                            $form.find('.nfb-gallery-preview').empty();
                        }
                    } else {
                        const errorMsg = response.data.errors ? Object.values(response.data.errors).join('<br>') : response.data.message;
                        $responseDiv.addClass('error').html(errorMsg).show();
                    }
                },
                error: () => {
                    $responseDiv.addClass('error').text('خطای سرور رخ داد. لطفا دوباره تلاش کنید.').show();
                },
                complete: () => {
                    $submitBtn.prop('disabled', false).text('ارسال');
                }
            });
        });

        // Initialize fields and logic
        initializeAdvancedFields($form);
        checkConditionalLogic($form);
        $form.on('change', 'input, select, textarea', () => checkConditionalLogic($form));
        updateFormState();

        // Repeater Logic
        $form.on('click', '.nfb-repeater-add-row-btn', function (e) {
            e.preventDefault();
            const $container = $(this).siblings('.nfb-repeater-rows-container');
            const $template = $(this).siblings('.nfb-repeater-template');
            const newIndex = $container.children().length;
            const newRowHtml = $template.html().replace(/__INDEX__/g, newIndex);
            const $newRow = $(newRowHtml).appendTo($container);
            initializeAdvancedFields($newRow);
        });

        $form.on('click', '.nfb-repeater-remove-row-btn', (e) => {
            e.preventDefault();
            $(e.currentTarget).closest('.nfb-repeater-row').remove();
        });
    });

    // Delegated event for gallery/file uploads
    $(document).on('click', '.nfb-upload-button', function (e) {
        e.preventDefault();
        const $button = $(this);
        const isMultiple = $button.data('multiple');
        const $preview = $button.siblings('.nfb-gallery-preview');
        const $input = $button.siblings('input[type="hidden"]');

        const frame = wp.media({
            title: isMultiple ? 'انتخاب تصاویر' : 'انتخاب فایل',
            multiple: isMultiple
        });

        frame.on('select', function () {
            const attachments = frame.state().get('selection').toJSON();
            const ids = attachments.map(att => att.id);
            $input.val(ids.join(','));

            $preview.empty();
            attachments.forEach(att => {
                const isImage = att.mime.startsWith('image');
                const previewHtml = isImage ? `<img src="${att.sizes.thumbnail.url}">` : `<span>${att.filename}</span>`;
                $preview.append(`<div class="image-container">${previewHtml}<span class="remove-image" data-id="${att.id}">×</span></div>`);
            });
        });

        frame.open();
    });

    $(document).on('click', '.nfb-gallery-preview .remove-image', function () {
        const $container = $(this).closest('.image-container');
        const $wrapper = $container.closest('.nfb-gallery-field-wrapper');
        const $input = $wrapper.find('input[type="hidden"]');
        const idToRemove = $(this).data('id');

        let ids = $input.val().split(',').map(Number);
        ids = ids.filter(id => id !== idToRemove);
        $input.val(ids.join(','));

        $container.remove();
    });
});

