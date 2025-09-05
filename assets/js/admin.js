jQuery(document).ready(function ($) {
    // ... existing code for form builder ...

    // --- کد جدید برای منطق شرطی ---
    var conditionalWrapper = $('#nfb-conditional-logic-rules');
    var fieldsCache = [];

    function updateConditionalFieldOptions() {
        fieldsCache = [];
        $('#nfb-form-fields-container .nfb-field').each(function () {
            var fieldName = $(this).find('.field-setting-name input').val();
            var fieldLabel = $(this).find('.field-setting-label input').val();
            if (fieldName && fieldLabel) {
                fieldsCache.push({ name: fieldName, label: fieldLabel });
            }
        });

        // آپدیت کردن لیست های موجود
        $('.nfb-conditional-field').each(function () {
            var currentVal = $(this).val();
            $(this).empty().append($('<option>', { value: '', text: 'یک فیلد انتخاب کنید' }));
            fieldsCache.forEach(function (field) {
                $(this).append($('<option>', { value: field.name, text: field.label }));
            }.bind(this));
            $(this).val(currentVal);
        });
    }

    // رویداد برای دکمه "افزودن قانون جدید"
    $('#nfb-add-conditional-rule').on('click', function () {
        var ruleTemplate = wp.template('nfb-conditional-rule');
        conditionalWrapper.append(ruleTemplate({ index: conditionalWrapper.children().length }));
        updateConditionalFieldOptions(); // آپدیت فیلدها برای قانون جدید
    });

    // رویداد برای حذف یک قانون (با استفاده از event delegation)
    conditionalWrapper.on('click', '.nfb-remove-rule-btn', function (e) {
        e.preventDefault();
        if (confirm('آیا از حذف این قانون مطمئن هستید؟')) {
            $(this).closest('.nfb-conditional-rule').remove();
        }
    });

    // آپدیت اولیه فیلدها در زمان بارگذاری صفحه
    updateConditionalFieldOptions();

    // آپدیت لیست فیلدها هر زمان که یک فیلد جدید اضافه می شود
    $('#nfb-form-fields-container').on('sortstop', updateConditionalFieldOptions);

    // آپدیت لیست فیلدها هر زمان که تنظیمات یک فیلد تغییر می کند
    $(document).on('change', '#nfb-field-settings-modal input', function () {
        setTimeout(updateConditionalFieldOptions, 200);
    });
});

