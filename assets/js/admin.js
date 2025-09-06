jQuery(document).ready(function ($) {
    'use strict';

    if (typeof nfb_admin_vars === 'undefined' || typeof wp.template !== 'function') {
        console.error('NFB Admin Error: Required variables or wp.template is not available.');
        return;
    }

    var formBuilder = {
        init: function () {
            this.fieldsList = $('#nfb-form-fields-list');
            this.fieldsJsonInput = $('#nfb_form_fields_json');
            this.emptyMessage = $('.nfb-empty-message');
            this.repeaterModal = $('#nfb-repeater-field-modal');
            this.currentRepeaterContainer = null;

            this.loadInitialFields();
            this.bindEvents();
        },

        loadInitialFields: function () {
            try {
                var fieldsData = JSON.parse(this.fieldsJsonInput.val() || '[]');
                if (fieldsData.length > 0) {
                    $.each(fieldsData, (i, fieldData) => this.renderField(this.fieldsList, fieldData));
                }
            } catch (e) {
                console.error('Error parsing initial form fields JSON.', e);
            }
            this.updateEmptyMessage();
        },

        bindEvents: function () {
            // Add field via button or dropping
            $('.nfb-add-field-btn').draggable({
                connectToSortable: '#nfb-form-fields-list',
                helper: 'clone',
                revert: 'invalid'
            });

            $('.nfb-add-field-btn').on('click', (e) => {
                e.preventDefault();
                this.addNewField(this.fieldsList, $(e.currentTarget).data('field-type'));
            });

            this.initSortable(this.fieldsList);

            // Field actions
            this.fieldsList.on('click', '.nfb-toggle-field-details, .nfb-tab-link, .nfb-copy-field, .nfb-remove-field, .nfb-quick-remove-field', (e) => e.preventDefault());
            this.fieldsList.on('click', '.nfb-toggle-field-details', (e) => $(e.currentTarget).closest('.nfb-field-row').find('.nfb-field-details').slideToggle('fast'));
            this.fieldsList.on('click', '.nfb-quick-remove-field, .nfb-remove-field', (e) => this.removeField(e.currentTarget));
            this.fieldsList.on('click', '.nfb-copy-field', (e) => this.copyField(e.currentTarget));
            this.fieldsList.on('click', '.nfb-tab-link', (e) => this.switchTab(e.currentTarget));
            this.fieldsList.on('keyup change', '.nfb-setting-input', (e) => this.handleSettingChange(e.currentTarget));

            // Repeater actions
            this.fieldsList.on('click', '.nfb-add-sub-field', (e) => {
                this.currentRepeaterContainer = $(e.currentTarget).siblings('.nfb-repeater-sub-fields');
                this.repeaterModal.dialog('open');
            });

            this.repeaterModal.dialog({
                autoOpen: false,
                modal: true,
                width: 500
            });

            $('.nfb-modal-add-field').on('click', (e) => {
                e.preventDefault();
                if (this.currentRepeaterContainer) {
                    this.addNewField(this.currentRepeaterContainer, $(e.currentTarget).data('field-type'));
                }
                this.repeaterModal.dialog('close');
            });
        },

        initSortable: function ($container) {
            $container.sortable({
                handle: '.handle',
                placeholder: 'nfb-sortable-placeholder',
                update: () => this.saveFields(),
                receive: (event, ui) => {
                    const fieldType = ui.item.data('field-type');
                    const $placeholder = $(event.target).find('.ui-sortable-placeholder');
                    this.addNewField($container, fieldType, $placeholder);
                    ui.item.remove(); // Remove the dragged helper
                }
            }).disableSelection();
        },

        addNewField: function ($container, type, $placeholder = null) {
            const fieldConfig = this.getFieldConfig(type);
            if (!fieldConfig) return;

            const newFieldData = {
                id: 'field_' + new Date().getTime(),
                type: type,
                label: fieldConfig.label,
                meta_key: type + '_' + Math.floor(Math.random() * 1000),
                width_class: 'full'
            };

            const $newFieldRow = this.renderField($container, newFieldData, true);

            if ($placeholder) {
                $placeholder.replaceWith($newFieldRow);
            } else {
                $container.append($newFieldRow);
            }

            this.saveFields();
        },

        renderField: function ($container, fieldData, returnElement = false) {
            const fieldConfig = this.getFieldConfig(fieldData.type);
            if (!fieldConfig) return;

            const templateData = { ...fieldData, typeLabel: fieldConfig.label };
            const $fieldRow = $(wp.template('nfb-field-row')(templateData));

            this.buildSettingsPanel($fieldRow, fieldData);

            if (returnElement) return $fieldRow;

            $container.append($fieldRow);
            this.initSortable($fieldRow.find('.nfb-repeater-sub-fields'));
        },

        buildSettingsPanel: function ($fieldRow, fieldData) {
            const $details = $fieldRow.find('.nfb-field-details');
            const $tabs = $(wp.template('nfb-settings-tabs')({}));
            const $panels = $tabs.find('.nfb-settings-panels');

            $panels.append(wp.template('nfb-panel-base')(fieldData));
            $panels.append(wp.template('nfb-panel-display')(fieldData));
            $panels.append(wp.template('nfb-panel-validation')(fieldData));
            $panels.append(wp.template('nfb-panel-conditional')(fieldData));

            if (['image', 'file', 'gallery'].includes(fieldData.type)) {
                $panels.find('[data-panel="base"]').append(wp.template('nfb-panel-file-settings')(fieldData));
            }
            if (fieldData.type === 'product') {
                $panels.find('[data-panel="base"]').append(wp.template('nfb-panel-product-settings')(fieldData));
            }
            if (fieldData.type === 'repeater') {
                $details.append(wp.template('nfb-repeater-wrapper')({}));
                const $subFieldsContainer = $details.find('.nfb-repeater-sub-fields');
                if (fieldData.sub_fields && fieldData.sub_fields.length) {
                    $.each(fieldData.sub_fields, (i, subFieldData) => this.renderField($subFieldsContainer, subFieldData));
                }
            }

            $details.prepend($tabs);
            this.togglePanelVisibility($details, fieldData.type);
        },

        togglePanelVisibility: function ($panelContainer, type) {
            const showOptions = ['select', 'multiselect', 'checkbox', 'radio'];
            const showPlaceholder = ['text', 'textarea', 'email', 'number', 'url', 'mobile', 'phone', 'date', 'time'];

            $panelContainer.find('.nfb-options-wrapper').toggle(showOptions.includes(type));
            $panelContainer.find('.nfb-placeholder-wrapper').toggle(showPlaceholder.includes(type));
            $panelContainer.find('.nfb-html-content-wrapper').toggle(type === 'html_content');
        },

        getFieldConfig: function (type) {
            for (const group of Object.values(nfb_admin_vars.fields)) {
                if (group.fields[type]) return { ...group.fields[type], type: type };
            }
            return null;
        },

        updateEmptyMessage: function () {
            this.emptyMessage.toggle(!this.fieldsList.children('.nfb-field-row').length);
        },

        updateConditionalDropdowns: function () {
            const fields = [];
            this.fieldsList.find('> .nfb-field-row').each((i, el) => {
                const $row = $(el);
                fields.push({ key: $row.data('field-key'), label: $row.find('.field-label-input').val() });
            });

            $('.nfb-conditional-target-field').each((i, el) => {
                const $select = $(el);
                const currentValue = $select.val();
                const parentKey = $select.closest('.nfb-field-row').data('field-key');

                $select.html('<option value="">یک فیلد انتخاب کنید</option>');
                $.each(fields, (i, field) => {
                    if (field.key && field.key !== parentKey) {
                        $select.append($('<option>', { value: field.key, text: field.label }));
                    }
                });
                $select.val(currentValue);
            });
        },

        serializeRow: function ($row) {
            const fieldData = {
                id: $row.data('field-id'),
                type: $row.data('field-type'),
                sub_fields: []
            };

            $row.find('> .nfb-field-details .nfb-setting-input').each((i, el) => {
                const $input = $(el);
                const key = $input.data('setting');
                if (key) {
                    fieldData[key] = $input.is(':checkbox') ? ($input.is(':checked') ? 1 : 0) : $input.val();
                }
            });

            const $subFieldsContainer = $row.find('.nfb-repeater-sub-fields');
            if ($subFieldsContainer.length) {
                $subFieldsContainer.find('> .nfb-field-row').each((i, el) => {
                    fieldData.sub_fields.push(this.serializeRow($(el)));
                });
            }

            return fieldData;
        },

        saveFields: function () {
            const fieldsData = [];
            this.fieldsList.find('> .nfb-field-row').each((i, el) => {
                fieldsData.push(this.serializeRow($(el)));
            });

            this.fieldsJsonInput.val(JSON.stringify(fieldsData));
            this.updateEmptyMessage();
            this.updateConditionalDropdowns();
        },

        removeField: function (element) {
            if (confirm('آیا از حذف این فیلد مطمئن هستید؟')) {
                $(element).closest('.nfb-field-row').remove();
                this.saveFields();
            }
        },

        copyField: function (element) {
            const $originalRow = $(element).closest('.nfb-field-row');
            const fieldData = this.serializeRow($originalRow);

            fieldData.id = 'field_' + new Date().getTime();
            fieldData.meta_key += '_copy';
            fieldData.label += ' (کپی)';

            this.renderField($originalRow.parent(), fieldData);
            this.saveFields();
        },

        switchTab: function (element) {
            const $link = $(element);
            const tabId = $link.data('tab');
            $link.addClass('active').siblings().removeClass('active');
            $link.closest('.nfb-field-details').find('.nfb-settings-panel').hide().removeClass('active');
            $link.closest('.nfb-field-details').find(`[data-panel="${tabId}"]`).show().addClass('active');
        },

        handleSettingChange: function (element) {
            const $input = $(element);
            const $row = $input.closest('.nfb-field-row');
            const setting = $input.data('setting');

            if (setting === 'label') $row.find('.nfb-field-header strong').text($input.val() || ' ');
            if (setting === 'meta_key') $row.data('field-key', $input.val());
            if (setting === 'conditional_logic_enabled') $row.find('.nfb-conditional-rules').toggle($input.is(':checked'));

            this.saveFields();
        }
    };

    formBuilder.init();
});

