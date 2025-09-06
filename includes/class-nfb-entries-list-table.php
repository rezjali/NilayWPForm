<?php
// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

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

    public function get_columns()
    {
        $columns = [
            'cb'         => '<input type="checkbox" />',
            'entry_id'   => __('ID', 'nilay-form-builder'),
        ];
        
        if ($this->form_id) {
            $form_fields = get_post_meta($this->form_id, '_nfb_form_fields', true);
            if (!empty($form_fields) && is_array($form_fields)) {
                $count = 0;
                foreach ($form_fields as $field) {
                    if ($count >= 4) break;
                    if (!in_array($field['type'], ['section_title', 'html_content', 'page_break', 'signature'])) {
                        $columns['field_' . $field['key']] = esc_html($field['label']);
                        $count++;
                    }
                }
            }
        }
        
        $columns['status'] = __('وضعیت', 'nilay-form-builder');
        $columns['date'] = __('تاریخ ثبت', 'nilay-form-builder');
        return $columns;
    }

    public function prepare_items()
    {
        $per_page = $this->get_items_per_page('entries_per_page', 20);
        $current_page = $this->get_pagenum();
        
        $this->_column_headers = [$this->get_columns(), [], []];

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

    public function column_default($item, $column_name)
    {
        if (strpos($column_name, 'field_') === 0) {
            $field_key = str_replace('field_', '', $column_name);
            $meta_key = '_nfb_field_' . $field_key;
            $value = get_post_meta($item->ID, $meta_key, true);

            if (is_array($value)) {
                return esc_html(implode(', ', $value));
            }
            return esc_html($value);
        }
        return '';
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="entry[]" value="%s" />', $item->ID);
    }

    public function column_entry_id($item)
    {
        $actions = [
            'view'   => sprintf('<a href="%s">%s</a>', get_edit_post_link($item->ID), __('مشاهده', 'nilay-form-builder')),
            'delete' => sprintf('<a href="%s" class="nfb-delete-entry">%s</a>', get_delete_post_link($item->ID, '', true), __('حذف', 'nilay-form-builder')),
        ];
        return sprintf('<strong>%1$s</strong> %2$s', $item->ID, $this->row_actions($actions));
    }
    
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

    public function column_date($item)
    {
        return get_the_date('Y/m/d H:i', $item);
    }

    public function get_bulk_actions()
    {
        return [
            'bulk-delete' => __('حذف', 'nilay-form-builder')
        ];
    }
}

