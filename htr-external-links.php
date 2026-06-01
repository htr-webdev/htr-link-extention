<?php
/**
 * Plugin Name: HTR External Links Extractor
 * Description: استخراج و مدیریت لینک‌های خارجی سایت
 * Version: 2.0.0
 * Author: HTR Team
 * License: GPL v2 or later
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// ثابت‌های پلاگین
define('HTR_EL_VERSION', '2.0.0');
define('HTR_EL_DIR', plugin_dir_path(__FILE__));
define('HTR_EL_URL', plugin_dir_url(__FILE__));
define('HTR_EL_INCLUDES', HTR_EL_DIR . 'includes/');

// بارگذاری کلاس‌ها
require_once HTR_EL_INCLUDES . 'class-htr-el-repository.php';
require_once HTR_EL_INCLUDES . 'class-htr-el-extractor.php';
require_once HTR_EL_INCLUDES . 'class-htr-el-dashboard.php';

class HTR_EL_Plugin {
    private static $instance = null;

    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'bootstrap']);
    }

    public function bootstrap() {
        if (is_admin()) {
            HTR_EL_Dashboard::init();
        }

        add_action('wp_ajax_htr_el_scan', [$this, 'ajax_scan']);
        add_action('htr_el_scheduled_scan', [$this, 'scheduled_scan']);
    }

    public function activate() {
        HTR_EL_Repository::create_tables();

        if (!wp_next_scheduled('htr_el_scheduled_scan')) {
            wp_schedule_event(time(), 'daily', 'htr_el_scheduled_scan');
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('htr_el_scheduled_scan');
    }

    public function ajax_scan() {
        check_ajax_referer('htr_el_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی مجاز نیست']);
        }

        $result = HTR_EL_Extractor::run_scan();

        if ($result) {
            wp_send_json_success(['message' => 'اسکن با موفقیت انجام شد']);
        } else {
            wp_send_json_error(['message' => 'خطا در اسکن']);
        }
    }

    public function scheduled_scan() {
        HTR_EL_Extractor::run_scan();
    }
}

HTR_EL_Plugin::init();
?>
