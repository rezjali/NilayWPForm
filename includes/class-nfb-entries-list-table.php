<?php
// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

// برای استفاده از کلاس WP_List_Table
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * کلاس NFB_Entries_List_Table
 * مسئولیت نمایش جدول ورودی‌های فرم در پنل مدیریت را بر عهده دارد.
 */
class NFB_Entries_List_Table extends WP_List_Table
{
    private $form_id;

    public function __construct($form_id = 0)
    {
        $this->form_id = $form_id;

        parent::__construct([
            'singular' => __('ورودی', 'nilay-form-builder'),
            'plural'   => __('ورودی‌ها', 'nilay-form-builder'),
            'ajax'     => false
        ]);
    }

    /**
     * تعریف ستون‌های جدول.
     */
    public function get_columns()
    {
        $columns = [
            'cb'         => '<input type="checkbox" />',
            'entry_id'   => __('ID', 'nilay-form-builder'),
            'date'       => __('تاریخ ثبت', 'nilay-form-builder'),
            'status'     => __('وضعیت', 'nilay-form-builder'),
        ];
        
        // افزودن ستون‌های داینامیک بر اساس فیلدهای فرم
        if ($this->form_id) {
            $form_fields = get_post_meta($this->form_id, '_nfb_form_fields', true);
            if (!empty($form_fields) && is_array($form_fields)) {
                $count = 0;
                foreach ($form_fields as $field) {
                    if ($count >= 4) break; // نمایش حداکثر ۴ فیلد برای جلوگیری از شلوغی
                    if (!in_array($field['type'], ['section_title', 'html_content', 'page_break'])) {
                        $columns['field_' . $field['key']] = esc_html($field['label']);
                        $count++;
                    }
                }
            }
        }
        
        return $columns;
    }

    /**
     * آماده‌سازی آیتم‌ها برای نمایش.
     */
    public function prepare_items()
    {
        $per_page = $this->get_items_per_page('entries_per_page', 20);
        $current_page = $this->get_pagenum();
        
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $args = [
            'post_type'      => 'nilay_form_entry',
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
            'post_parent'    => $this->form_id,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new WP_Query($args);

        $this->items = $query->posts;

        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => $query->max_num_pages
        ]);
    }

    /**
     * رندر کردن محتوای پیش‌فرض ستون‌ها.
     */
    public function column_default($item, $column_name)
    {
        // برای ستون‌های فیلد داینامیک
        if (strpos($column_name, 'field_') === 0) {
            $field_key = str_replace('field_', '', $column_name);
            $meta_key = '_nfb_field_' . $field_key;
            $value = get_post_meta($item->ID, $meta_key, true);

            if (is_array($value)) {
                return esc_html(implode(', ', $value));
            }
            return esc_html($value);
        }

        return print_r($item, true); // Fallback
    }

    /**
     * رندر کردن ستون چک‌باکس.
     */
    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="entry[]" value="%s" />', $item->ID);
    }

    /**
     * رندر کردن ستون ID.
     */
    public function column_entry_id($item)
    {
        $actions = [
            'view'   => sprintf('<a href="#">%s</a>', __('مشاهده', 'nilay-form-builder')),
            'delete' => sprintf('<a href="#">%s</a>', __('حذف', 'nilay-form-builder')),
        ];
        return sprintf('%1$s %2$s', $item->ID, $this->row_actions($actions));
    }
    
    /**
     * رندر کردن ستون وضعیت.
     */
    public function column_status($item)
    {
        $status = get_post_meta($item->ID, '_entry_status', true);
        $status_text = [
            'completed' => __('تکمیل شده', 'nilay-form-builder'),
            'pending'   => __('در انتظار پرداخت', 'nilay-form-builder'),
            'failed'    => __('ناموفق', 'nilay-form-builder'),
        ];
        return $status_text[$status] ?? ucfirst($status);
    }
}
