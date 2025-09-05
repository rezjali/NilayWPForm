jQuery(document).ready(function ($) {

    $('.nfb-form-container').each(function () {
        var $formContainer = $(this);
        initializeMultiStepForm($formContainer);
        initializeSignatureFields($formContainer);
    });

    function initializeMultiStepForm($formContainer) {
        var $steps = $formContainer.find('.nfb-step');
        if ($steps.length <= 1) return;

        var currentStep = 0;
        var $nav = $formContainer.find('.nfb-step-nav');
        var $nextBtn = $nav.find('.nfb-next-step');
        var $prevBtn = $nav.find('.nfb-prev-step');
        var $submitBtnGroup = $formContainer.find('.nfb-submit-group');

        function updateNav() {
            $steps.removeClass('active').hide();
            $steps.eq(currentStep).addClass('active').show();
            $prevBtn.toggle(currentStep > 0);
            $nextBtn.toggle(currentStep < $steps.length - 1);
            $submitBtnGroup.toggle(currentStep === $steps.length - 1);
        }

        function validateStep() {
            var $currentStepFields = $steps.eq(currentStep).find('[required]');
            var isValid = true;
            $currentStepFields.each(function () {
                if (!this.checkValidity()) {
                    this.reportValidity();
                    isValid = false;
                    return false;
                }
            });
            return isValid;
        }

        $nextBtn.on('click', function () {
            if (validateStep()) { currentStep++; updateNav(); }
        });
        $prevBtn.on('click', function () {
            if (currentStep > 0) { currentStep--; updateNav(); }
        });

        updateNav();
    }

    function initializeSignatureFields($formContainer) {
        if (typeof SignaturePad === 'undefined') return;

        $formContainer.find('.nfb-signature-canvas').each(function () {
            var canvas = this;
            var $wrapper = $(canvas).closest('.nfb-signature-wrapper');
            var $hiddenInput = $wrapper.find('.nfb-signature-input');
            var signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' });

            function resizeCanvas() {
                var ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                signaturePad.clear();
            }

            $(window).on("resize", resizeCanvas);
            resizeCanvas();

            signaturePad.onEnd = function () {
                if (!signaturePad.isEmpty()) $hiddenInput.val(signaturePad.toDataURL('image/png'));
                else $hiddenInput.val('');
            };

            $wrapper.find('.nfb-signature-clear').on('click', function (e) {
                e.preventDefault(); signaturePad.clear(); $hiddenInput.val('');
            });
        });
    }
});

