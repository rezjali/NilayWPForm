jQuery(document).ready(function ($) {
    'use strict';

    // ... existing code for multi-step forms ...
    $('.nfb-form-wrapper.nfb-multi-step-form').each(function () {
        var $formWrapper = $(this);
        var $form = $formWrapper.find('form');
        var $pages = $form.find('.nfb-page');
        var currentPage = 0;
        var totalPages = $pages.length;

        var $navContainer = $formWrapper.find('.nfb-nav-buttons');
        var $nextBtn = $navContainer.find('.nfb-next-btn');
        var $prevBtn = $navContainer.find('.nfb-prev-btn');
        var $submitBtn = $navContainer.find('.nfb-submit-btn');
        var $stepIndicator = $formWrapper.find('.nfb-step-indicator');

        function updateFormView() {
            $pages.hide();
            $pages.eq(currentPage).show();
            if ($stepIndicator.length) {
                $stepIndicator.text('مرحله ' + (currentPage + 1) + ' از ' + totalPages);
            }
            $prevBtn.toggle(currentPage > 0);
            $nextBtn.toggle(currentPage < totalPages - 1);
            $submitBtn.toggle(currentPage === totalPages - 1);
        }

        function validateCurrentPage() {
            var isValid = true;
            $pages.eq(currentPage).find('[required]:visible').each(function () {
                if ($(this).is('.nfb-signature-wrapper')) {
                    var hiddenInput = $(this).find('input[type="hidden"]');
                    if (hiddenInput.val() === '') {
                        alert('لطفا فیلد امضا را تکمیل کنید.');
                        isValid = false;
                        return false;
                    }
                }
                else if (!this.checkValidity()) {
                    this.reportValidity();
                    isValid = false;
                    return false;
                }
            });
            return isValid;
        }

        $nextBtn.on('click', function () {
            if (validateCurrentPage()) {
                if (currentPage < totalPages - 1) {
                    currentPage++;
                    updateFormView();
                }
            }
        });

        $prevBtn.on('click', function () {
            if (currentPage > 0) {
                currentPage--;
                updateFormView();
            }
        });
        updateFormView();
    });

    // --- کد جدید برای فیلد امضا ---
    $('.nfb-signature-wrapper').each(function () {
        var wrapper = $(this);
        var canvas = wrapper.find('canvas.nfb-signature-pad')[0];
        var hiddenInput = wrapper.find('input[type="hidden"]');
        var clearButton = wrapper.find('.nfb-signature-clear');

        var signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(248, 248, 248)'
        });

        // تابع برای آپدیت کردن مقدار فیلد مخفی
        var updateHiddenInput = function () {
            if (!signaturePad.isEmpty()) {
                hiddenInput.val(signaturePad.toDataURL('image/png'));
            } else {
                hiddenInput.val('');
            }
        };

        // آپدیت مقدار پس از هر بار رسم
        signaturePad.addEventListener("endStroke", function () {
            updateHiddenInput();
        });

        clearButton.on('click', function (event) {
            event.preventDefault();
            signaturePad.clear();
            hiddenInput.val('');
        });

        function resizeCanvas() {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            var canvasElem = $(canvas);
            canvas.width = canvasElem.outerWidth() * ratio;
            canvas.height = canvasElem.outerHeight() * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            signaturePad.clear();
        }

        $(window).on("resize", resizeCanvas);
        // تاخیر کوتاه برای اطمینان از رندر شدن کامل صفحه
        setTimeout(resizeCanvas, 200);
    });
});

