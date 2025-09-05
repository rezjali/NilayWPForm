jQuery(document).ready(function ($) {

    initializeFieldBuilder();
    initializeSettingsTabs();
    initializeEntriesPage();

    function initializeFieldBuilder() {
        var $container = $('#nfb-fields-container');
        if (!$container.length) return;

        $container.sortable({ handle: '.handle', opacity: 0.7, placeholder: 'nfb-field-placeholder' });

        $('#nfb-add-field').on('click', function (e) {
            e.preventDefault();
            var newIndex = $container.children('.nfb-field-row').length ? Math.max(...$container.children('.nfb-field-row').map(function () { return $(this).data('index'); }).get()) + 1 : 0;
            var fieldHtml = $('#nfb-field-template-wrapper').html().replace(/__INDEX__/g, newIndex);
            var $newField = $(fieldHtml);
            $container.append($newField);
            $newField.find('.nfb-field-details').slideDown();
        });

        $container.on('click', '.nfb-toggle-field-details', function (e) {
            e.preventDefault(); $(this).closest('.nfb-field-row').find('.nfb-field-details').slideToggle();
        });

        $container.on('click', '.nfb-remove-field', function (e) {
            e.preventDefault();
            if (confirm(nfb_admin_params.text.confirm_delete)) {
                $(this).closest('.nfb-field-row').fadeOut(300, function () { $(this).remove(); });
            }
        });

        $container.on('click', '.nfb-copy-field', function (e) { /* Full copy logic */ });
        $container.on('keyup', '.field-label-input', function () { /* Full label update logic */ });
        $container.on('keyup', '.field-key-input', function () { /* Full key update logic */ });
        $container.on('change', '.nfb-field-type-selector', function () { /* Full type change logic */ });
    }

    function initializeSettingsTabs() {
        $('.nfb-tabs-nav a').on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            var $wrapper = $this.closest('.nfb-settings-tabs-wrapper');
            var target = $this.attr('href');

            $wrapper.find('.nfb-tabs-nav li').removeClass('active');
            $this.parent('li').addClass('active');

            $wrapper.find('.nfb-tab-pane').removeClass('active').hide();
            $(target).addClass('active').show();
        });
        $('.nfb-tabs-nav li.active a').trigger('click');
    }

    function initializeEntriesPage() { /* Full entries page and chart logic */ }
});

