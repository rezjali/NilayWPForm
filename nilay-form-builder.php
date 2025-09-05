<?php
/**
 * Plugin Name:       فرم‌ساز نیلای - Nilay Form Builder
 * Plugin URI:        https://nilayteam.ir/
 * Description:       یک فرم‌ساز پیشرفته و مدرن برای وردپرس با قابلیت‌های بی‌نظیر.
 * Version:           1.0.1
 * Author:            تیم نیلای
 * Author URI:        https://nilayteam.ir/
 * License:           GPL v2 or later
 * Text Domain:       nilay-form-builder
 * Domain Path:       /languages
 */

// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

// جلوگیری از تعریف مجدد کلاس
if (!class_exists('Nilay_Form_Builder')) {

    /**
     * کلاس اصلی افزونه که تمام بخش‌ها را مدیریت و راه‌اندازی می‌کند.
     */
    final class Nilay_Form_Builder
    {
        const VERSION = '1.0.1';
        private static $instance = null;

        // Properties to hold instances of other classes
        public $core;
        public $admin;
        public $frontend;
        public $services;

        public static function instance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct()
        {
            $this->define_constants();
            $this->includes();
            $this->init_hooks();
        }

        private function define_constants()
        {
            define('NFB_VERSION', self::VERSION);
            define('NFB_PLUGIN_FILE', __FILE__);
            define('NFB_PLUGIN_PATH', plugin_dir_path(NFB_PLUGIN_FILE));
            define('NFB_PLUGIN_URL', plugin_dir_url(NFB_PLUGIN_FILE));
        }

        private function includes()
        {
            require_once(NFB_PLUGIN_PATH . 'includes/class-nfb-core.php');
            require_once(NFB_PLUGIN_PATH . 'includes/class-nfb-frontend.php');
            require_once(NFB_PLUGIN_PATH . 'includes/class-nfb-services.php');

            if (is_admin()) {
                require_once(NFB_PLUGIN_PATH . 'includes/class-nfb-entries-list-table.php');
                require_once(NFB_PLUGIN_PATH . 'includes/class-nfb-admin.php');
            }
        }

        private function init_hooks()
        {
            add_action('plugins_loaded', [$this, 'init_plugin'], 10);
            register_activation_hook(NFB_PLUGIN_FILE, [$this, 'activate']);
            register_deactivation_hook(NFB_PLUGIN_FILE, [$this, 'deactivate']);
        }

        /**
         * Instantiate plugin classes.
         */
        public function init_plugin()
        {
            load_plugin_textdomain('nilay-form-builder', false, dirname(plugin_basename(NFB_PLUGIN_FILE)) . '/languages');
            
            $this->core     = new NFB_Core();
            $this->frontend = new NFB_Frontend();
            $this->services = new NFB_Services();
            if (is_admin()) {
                $this->admin = new NFB_Admin();
            }
        }

        /**
         * Activation hook logic.
         */
        public function activate()
        {
            // We need the core file for register_post_types
            require_once(NFB_PLUGIN_PATH . 'includes/class-nfb-core.php');
            
            // Register post types
            NFB_Core::register_post_types();
            
            // Flush rewrite rules
            flush_rewrite_rules();
        }

        public function deactivate()
        {
            flush_rewrite_rules();
        }
    }
}

/**
 * Main function to run the plugin.
 * @return Nilay_Form_Builder
 */
function NFB()
{
    return Nilay_Form_Builder::instance();
}

// Run the plugin
NFB();

