<?php
/**
 * Plugin Name: HTR External Links Extractor
 * Plugin URI: https://example.com/htr-external-links
 * Description: اسکن و مدیریت تمام لینک‌های خارجی وب‌سایت با سازگاری کامل وودمارت و ووکامرس
 * Version: 1.0.0
 * Author: HTR Team
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: htr-external-links
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HTR_EL_VERSION', '1.0.0');
define('HTR_EL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HTR_EL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HTR_EL_PLUGIN_BASENAME', plugin_basename(__FILE__));

class HTR_External_Links_Extractor {

    private static $instance = null;
    private $db_version = '1.0';
    private $table_name_links;
    private $table_name_stats;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        global $wpdb;
        $this->table_name_links = $wpdb->prefix . 'htr_external_links';
        $this->table_name_stats = $wpdb->prefix . 'htr_external_links_stats';

        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        add_action('wp_ajax_htr_scan_links', [$this, 'ajax_scan_links']);
        add_action('htr_el_scheduled_scan', [$this, 'perform_scheduled_scan']);
        add_action('init', [$this, 'schedule_cron']);
    }

    public function activate() {
        $this->create_database_tables();
        if (!wp_next_scheduled('htr_el_scheduled_scan')) {
            wp_schedule_event(time(), 'daily', 'htr_el_scheduled_scan');
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('htr_el_scheduled_scan');
    }

    private function create_database_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // جدول لینک‌های استخراج‌شده
        $links_table = "CREATE TABLE IF NOT EXISTS {$this->table_name_links} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            url VARCHAR(2048) NOT NULL,
            source_url VARCHAR(2048) NOT NULL,
            source_post_id BIGINT UNSIGNED NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            post_title VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_link_source (url(255), source_post_id),
            KEY idx_post_id (source_post_id),
            KEY idx_content_type (content_type),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        // جدول آمار
        $stats_table = "CREATE TABLE IF NOT EXISTS {$this->table_name_stats} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            total_links BIGINT UNSIGNED DEFAULT 0,
            total_pages_with_links BIGINT UNSIGNED DEFAULT 0,
            last_scan_time TIMESTAMP NULL,
            scan_in_progress BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset_collate};";

        dbDelta($links_table);
        dbDelta($stats_table);

        // بررسی و ایجاد ردیف آماری اولیه
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name_stats}");
        if ($existing == 0) {
            $wpdb->insert($this->table_name_stats, [
                'total_links' => 0,
                'total_pages_with_links' => 0,
                'scan_in_progress' => FALSE
            ]);
        }
    }

    public function schedule_cron() {
        // فقط یک بار اجرا شود
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'لینک‌های خارجی',
            'لینک‌های خارجی',
            'manage_options',
            'htr-external-links',
            [$this, 'render_admin_page'],
            'dashicons-admin-links',
            25
        );
    }

    public function enqueue_admin_styles($hook) {
        if (strpos($hook, 'htr-external-links') === false) {
            return;
        }

        wp_enqueue_style('htr-external-links-style', HTR_EL_PLUGIN_URL . 'assets/style.css');
        wp_enqueue_script('htr-external-links-script', HTR_EL_PLUGIN_URL . 'assets/script.js', ['jquery'], HTR_EL_VERSION, true);

        wp_localize_script('htr-external-links-script', 'htrElAjax', [
            'nonce' => wp_create_nonce('htr_scan_links_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]);

        // اگر فایل‌های asset وجود نداشت، از CSS و JS درون صفحه استفاده کنید
        $this->output_inline_styles();
        $this->output_inline_scripts();
    }

    private function output_inline_styles() {
        echo '<style>
            .htr-container {
                max-width: 1200px;
                margin: 20px 0;
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .htr-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 15px;
            }
            .htr-header h1 {
                margin: 0;
                color: #0073aa;
            }
            .htr-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }
            .htr-stat-box {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
            }
            .htr-stat-box h3 {
                margin: 0 0 10px 0;
                font-size: 14px;
                opacity: 0.9;
            }
            .htr-stat-box .number {
                font-size: 32px;
                font-weight: bold;
            }
            .htr-button {
                background-color: #0073aa;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                transition: background-color 0.3s;
            }
            .htr-button:hover {
                background-color: #005a87;
            }
            .htr-button:disabled {
                background-color: #ccc;
                cursor: not-allowed;
            }
            .htr-loading {
                display: none;
                margin-left: 10px;
            }
            .htr-table-wrapper {
                overflow-x: auto;
                margin-top: 20px;
            }
            .htr-table {
                width: 100%;
                border-collapse: collapse;
            }
            .htr-table th {
                background-color: #f5f5f5;
                border: 1px solid #ddd;
                padding: 12px;
                text-align: right;
                font-weight: bold;
                color: #333;
            }
            .htr-table td {
                border: 1px solid #ddd;
                padding: 12px;
                text-align: right;
            }
            .htr-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .htr-table tr:hover {
                background-color: #f0f0f0;
            }
            .htr-link {
                color: #0073aa;
                text-decoration: none;
                word-break: break-all;
            }
            .htr-link:hover {
                text-decoration: underline;
            }
            .htr-success {
                color: #22863a;
                background-color: #f6f8fa;
                border: 1px solid #28a745;
                padding: 12px;
                border-radius: 4px;
                margin: 10px 0;
            }
            .htr-error {
                color: #cb2431;
                background-color: #ffeef0;
                border: 1px solid #d73a49;
                padding: 12px;
                border-radius: 4px;
                margin: 10px 0;
            }
            .htr-info {
                color: #0066cc;
                background-color: #f1f8ff;
                border: 1px solid #0366d6;
                padding: 12px;
                border-radius: 4px;
                margin: 10px 0;
            }
            .htr-pagination {
                margin-top: 20px;
                text-align: center;
            }
            .htr-pagination button {
                margin: 0 5px;
                padding: 8px 12px;
                border: 1px solid #ddd;
                background: #fff;
                cursor: pointer;
                border-radius: 4px;
            }
            .htr-pagination button:hover {
                background: #f5f5f5;
            }
            .htr-pagination .current {
                background: #0073aa;
                color: white;
                border-color: #0073aa;
            }
            .spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #0073aa;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>';
    }

    private function output_inline_scripts() {
        echo '<script>
        jQuery(document).ready(function($) {
            $("#htr-scan-button").on("click", function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $spinner = $(".htr-loading");

                $btn.prop("disabled", true);
                $spinner.show();

                $.ajax({
                    type: "POST",
                    url: htrElAjax.ajaxUrl,
                    data: {
                        action: "htr_scan_links",
                        nonce: htrElAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert("خطا: " + response.data.message);
                        }
                    },
                    error: function() {
                        alert("خطا در اتصال به سرور");
                    },
                    complete: function() {
                        $btn.prop("disabled", false);
                        $spinner.hide();
                    }
                });
            });
        });
        </script>';
    }

    public function ajax_scan_links() {
        check_ajax_referer('htr_scan_links_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی مجاز نیست']);
        }

        $result = $this->scan_all_content();

        if ($result) {
            wp_send_json_success(['message' => 'اسکن با موفقیت انجام شد']);
        } else {
            wp_send_json_error(['message' => 'خطا در اسکن']);
        }
    }

    public function perform_scheduled_scan() {
        $this->scan_all_content();
    }

    private function scan_all_content() {
        global $wpdb;

        // تنظیم وضعیت اسکن
        $wpdb->update($this->table_name_stats, ['scan_in_progress' => TRUE], [], ['%d']);

        // پاک کردن داده‌های قدیمی
        $wpdb->query("TRUNCATE TABLE {$this->table_name_links}");

        $this->scan_posts();
        $this->scan_woocommerce_products();
        $this->update_statistics();

        // به‌روز رسانی زمان آخرین اسکن و خروج از وضعیت اسکن
        $wpdb->update($this->table_name_stats, [
            'scan_in_progress' => FALSE,
            'last_scan_time' => current_time('mysql')
        ], [], ['%d', '%s']);

        return true;
    }

    private function scan_posts() {
        global $wpdb;

        $post_types = ['post', 'page'];

        // اگر وودمارت فعال است، انواع محصول را اضافه کنید
        if (class_exists('WooCommerce')) {
            $post_types[] = 'product';
        }

        $args = [
            'post_type' => $post_types,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ];

        $posts = get_posts($args);

        foreach ($posts as $post_id) {
            $post = get_post($post_id);
            $content = $post->post_content;

            // اضافه کردن محتوای متامتا برای WooCommerce
            if ($post->post_type === 'product') {
                $content .= ' ' . get_post_meta($post_id, '_product_attributes', true);
            }

            $external_links = $this->extract_external_links($content, $post_id, $post->post_title, $post->post_type);
            $this->store_links($external_links);
        }
    }

    private function scan_woocommerce_products() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        global $wpdb;

        // این بخش در scan_posts پوشش داده می‌شود
        // اما می‌توانیم به‌طور خاص توضیحات کوتاه را هم بررسی کنیم
        $products = wc_get_products(['limit' => -1]);

        foreach ($products as $product) {
            $content = $product->get_short_description() . ' ' . $product->get_description();
            $external_links = $this->extract_external_links($content, $product->get_id(), $product->get_name(), 'product');
            $this->store_links($external_links);
        }
    }

    private function extract_external_links($content, $post_id, $post_title, $content_type) {
        $links = [];

        // الگوی regex برای یافتن تمام لینک‌ها
        $pattern = '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.+?)\1/i';

        if (preg_match_all($pattern, $content, $matches)) {
            $home_url = home_url();
            $parsed_home = wp_parse_url($home_url);
            $home_domain = $parsed_home['host'] ?? '';

            foreach ($matches[2] as $url) {
                // تنظیف URL
                $url = trim($url);

                // نادیده گرفتن لینک‌های داخلی و لنگرها
                if (empty($url) || $url[0] === '#') {
                    continue;
                }

                // تبدیل URL‌های نسبی به مطلق
                if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
                    $url = trailingslashit($home_url) . ltrim($url, '/');
                }

                // بررسی لینک خارجی
                $parsed_url = wp_parse_url($url);
                $url_domain = $parsed_url['host'] ?? '';

                if ($url_domain && $url_domain !== $home_domain && strpos($url_domain, $home_domain) === false) {
                    $links[] = [
                        'url' => esc_url($url),
                        'source_url' => get_permalink($post_id),
                        'source_post_id' => $post_id,
                        'content_type' => $content_type,
                        'post_title' => $post_title
                    ];
                }
            }
        }

        return $links;
    }

    private function store_links($links) {
        global $wpdb;

        foreach ($links as $link) {
            $wpdb->insert(
                $this->table_name_links,
                $link,
                ['%s', '%s', '%d', '%s', '%s']
            );
        }
    }

    private function update_statistics() {
        global $wpdb;

        $total_links = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name_links}");
        $total_pages = $wpdb->get_var("SELECT COUNT(DISTINCT source_post_id) FROM {$this->table_name_links}");

        $wpdb->update(
            $this->table_name_stats,
            [
                'total_links' => $total_links,
                'total_pages_with_links' => $total_pages
            ],
            [],
            ['%d', '%d']
        );
    }

    public function render_admin_page() {
        global $wpdb;

        // بررسی دسترسی
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی مجاز نیست');
        }

        // صفحه‌بندی
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $offset = ($current_page - 1) * $per_page;

        // گرفتن آمار
        $stats = $wpdb->get_row("SELECT * FROM {$this->table_name_stats}");

        // فیلتر
        $content_type_filter = isset($_GET['content_type']) ? sanitize_text_field($_GET['content_type']) : '';
        $where = '';
        if ($content_type_filter) {
            $where = $wpdb->prepare("WHERE content_type = %s", $content_type_filter);
        }

        // گرفتن لینک‌ها
        $links = $wpdb->get_results(
            "SELECT * FROM {$this->table_name_links} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            [$per_page, $offset]
        );

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name_links} {$where}");
        $total_pages = ceil($total_items / $per_page);

        echo '<div class="htr-container">';

        // هدر
        echo '<div class="htr-header">
            <h1>🔗 مدیریت لینک‌های خارجی</h1>
            <button id="htr-scan-button" class="htr-button">
                <span>🔄 اسکن مجدد</span>
                <span class="htr-loading"><span class="spinner"></span></span>
            </button>
        </div>';

        // اطلاعات اسکن
        if ($stats && $stats->last_scan_time) {
            $last_scan = strtotime($stats->last_scan_time);
            echo '<div class="htr-info">
                آخرین اسکن: ' . wp_date('d/m/Y H:i:s', $last_scan) . '
            </div>';
        }

        // آمار
        echo '<div class="htr-stats">
            <div class="htr-stat-box">
                <h3>تعداد کل لینک‌های خارجی</h3>
                <div class="number">' . ($stats->total_links ?? 0) . '</div>
            </div>
            <div class="htr-stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h3>تعداد صفحات با لینک خارجی</h3>
                <div class="number">' . ($stats->total_pages_with_links ?? 0) . '</div>
            </div>
        </div>';

        // فیلتر
        echo '<div style="margin: 20px 0;">
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="page" value="htr-external-links">
                <label for="content-type">نوع محتوا:</label>
                <select id="content-type" name="content_type">
                    <option value="">همه</option>
                    <option value="post"' . ($content_type_filter === 'post' ? ' selected' : '') . '>پست</option>
                    <option value="page"' . ($content_type_filter === 'page' ? ' selected' : '') . '>صفحه</option>
                    <option value="product"' . ($content_type_filter === 'product' ? ' selected' : '') . '>محصول</option>
                </select>
                <button type="submit" class="htr-button">فیلتر</button>
            </form>
        </div>';

        // جدول
        if ($links) {
            echo '<div class="htr-table-wrapper">
                <table class="htr-table">
                    <thead>
                        <tr>
                            <th>لینک خارجی</th>
                            <th>صفحه مبدأ</th>
                            <th>عنوان صفحه</th>
                            <th>نوع محتوا</th>
                            <th>تاریخ اضافه</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($links as $link) {
                $content_type_label = [
                    'post' => 'پست',
                    'page' => 'صفحه',
                    'product' => 'محصول'
                ][$link->content_type] ?? $link->content_type;

                echo '<tr>
                    <td><a href="' . esc_url($link->url) . '" target="_blank" class="htr-link" title="' . esc_attr($link->url) . '">' . esc_html(substr($link->url, 0, 50)) . '...</a></td>
                    <td><a href="' . esc_url($link->source_url) . '" target="_blank" class="htr-link" title="' . esc_attr($link->source_url) . '">نمایش</a></td>
                    <td>' . esc_html($link->post_title) . '</td>
                    <td><span style="background: #e8f4f8; padding: 4px 8px; border-radius: 3px;">' . $content_type_label . '</span></td>
                    <td>' . wp_date('d/m/Y H:i', strtotime($link->created_at)) . '</td>
                </tr>';
            }

            echo '</tbody></table></div>';

            // صفحه‌بندی
            if ($total_pages > 1) {
                echo '<div class="htr-pagination">';

                for ($i = 1; $i <= $total_pages; $i++) {
                    $page_url = add_query_arg('paged', $i);
                    if ($content_type_filter) {
                        $page_url = add_query_arg('content_type', $content_type_filter, $page_url);
                    }

                    $class = ($i == $current_page) ? 'current' : '';
                    echo '<a href="' . esc_url($page_url) . '" class="' . $class . '">' . $i . '</a> ';
                }

                echo '</div>';
            }
        } else {
            echo '<div class="htr-info">
                هیچ لینک خارجی یافت نشد. <br>
                <strong>برای شروع، دکمه "اسکن مجدد" را کلیک کنید.</strong>
            </div>';
        }

        echo '</div>';
    }
}

// مقداردهی افزونه
HTR_External_Links_Extractor::get_instance();
?>
