jQuery(document).ready(function ($) {

    /**
     * ================================================================
     * Main Initialization for All Forms on a Page
     * ================================================================
     */
    $('.nfb-form-container').each(function () {
        var $form = $(this);
        initializeMultiStepForm($form);
        initializeSignatureFields($form);
        initializeConditionalLogic($form);
    });


    /**
     * ================================================================
     * 1. Multi-Step Form Logic
     * ================================================================
     */
    function initializeMultiStepForm($form) {
        var $steps = $form.find('.nfb-step');
        if ($steps.length <= 1) return; // Not a multi-step form

        var currentStep = 0;
        var $nav = $form.find('.nfb-step-nav');
        var $nextBtn = $nav.find('.nfb-next-step');
        var $prevBtn = $nav.find('.nfb-prev-step');
        var $submitBtn = $form.find('.nfb-submit-button');

        function showStep(index) {
            $steps.removeClass('active').eq(index).addClass('active');
            $prevBtn.toggle(index > 0);
            $nextBtn.toggle(index < $steps.length - 1);
            $submitBtn.toggle(index === $steps.length - 1);
        }

        $nextBtn.on('click', function () {
            // Optional: Add validation here before proceeding
            if (currentStep < $steps.length - 1) {
                currentStep++;
                showStep(currentStep);
            }
        });

        $prevBtn.on('click', function () {
            if (currentStep > 0) {
                currentStep--;
                showStep(currentStep);
            }
        });

        // Initialize view
        showStep(currentStep);
    }


    /**
     * ================================================================
     * 2. Signature Field Logic (using signature_pad.js)
     * ================================================================
     */
    function initializeSignatureFields($form) {
        if (typeof SignaturePad === 'undefined') {
            // SignaturePad library is not loaded
            return;
        }

        $form.find('.nfb-signature-pad').each(function () {
            var canvas = this;
            var $wrapper = $(canvas).closest('.nfb-signature-pad-wrapper');
            var $hiddenInput = $wrapper.find('.nfb-signature-value');
            var signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgb(253, 253, 253)'
            });

            // When drawing ends, save the signature image data to the hidden input
            signaturePad.onEnd = function () {
                if (!signaturePad.isEmpty()) {
                    $hiddenInput.val(signaturePad.toDataURL('image/png'));
                } else {
                    $hiddenInput.val('');
                }
            };

            // Clear button functionality
            $wrapper.find('.nfb-signature-clear').on('click', function (e) {
                e.preventDefault();
                signaturePad.clear();
                $hiddenInput.val('');
            });
        });
    }

    /**
     * ================================================================
     * 3. Conditional Logic for Frontend Fields
     * ================================================================
     */
    function initializeConditionalLogic($form) {
        function checkLogic() {
            $form.find('[data-conditional-logic]').each(function () {
                var $dependentField = $(this).closest('.nfb-form-group');
                var logic;
                try {
                    logic = JSON.parse($(this).attr('data-conditional-logic'));
                } catch (e) { return; }

                if (!logic.enabled || !logic.target_field) return;

                var $targetField = $form.find('[name="nfb_fields[' + logic.target_field + ']"], [name="nfb_fields[' + logic.target_field + '][]"]');
                var targetValue;

                if ($targetField.is(':radio') || $targetField.is(':checkbox')) {
                    targetValue = $targetField.filter(':checked').val() || '';
                } else {
                    targetValue = $targetField.val() || '';
                }

                var conditionMet = false;
                switch (logic.operator) {
                    case 'is': conditionMet = (targetValue == logic.value); break;
                    case 'is_not': conditionMet = (targetValue != logic.value); break;
                    case 'is_empty': conditionMet = (targetValue === '' || targetValue === null || (Array.isArray(targetValue) && targetValue.length === 0)); break;
                    case 'is_not_empty': conditionMet = (targetValue !== '' && targetValue !== null && (!Array.isArray(targetValue) || targetValue.length > 0)); break;
                }

                var shouldShow = (logic.action === 'show') ? conditionMet : !conditionMet;

                if (shouldShow) {
                    $dependentField.slideDown('fast');
                } else {
                    $dependentField.slideUp('fast');
                }
            });
        }

        // Check logic on load and on any form change
        checkLogic();
        $form.on('change', 'input, select, textarea', checkLogic);
    }
});
