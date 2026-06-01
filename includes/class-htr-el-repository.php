<?php
/**
 * HTR External Links - Repository Pattern (Data Access Layer)
 * ذخیره‌سازی و دسترسی به داده‌های لینک‌های خارجی
 */

if (!defined('ABSPATH')) {
    exit;
}

class HTR_EL_Repository {
    private static $instance = null;
    public static $table_links;
    public static $table_stats;
    public static $table_logs;

    public static function init() {
        global $wpdb;

        if (null === self::$instance) {
            self::$instance = new self();
        }

        self::$table_links = $wpdb->prefix . 'htr_el_external_links';
        self::$table_stats = $wpdb->prefix . 'htr_el_stats';
        self::$table_logs = $wpdb->prefix . 'htr_el_logs';

        return self::$instance;
    }

    /**
     * ایجاد جداول پایگاه‌داده
     */
    public static function create_tables() {
        global $wpdb;

        self::init();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        // جدول لینک‌های خارجی
        $sql_links = "CREATE TABLE IF NOT EXISTS " . self::$table_links . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            url VARCHAR(2048) NOT NULL,
            source_url VARCHAR(2048) NOT NULL,
            source_post_id BIGINT UNSIGNED NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            anchor_text VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_link_source (url(255), source_post_id),
            KEY idx_post_id (source_post_id),
            KEY idx_content_type (content_type),
            KEY idx_created_at (created_at)
        ) $charset;";

        // جدول آمار
        $sql_stats = "CREATE TABLE IF NOT EXISTS " . self::$table_stats . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            total_links BIGINT UNSIGNED DEFAULT 0,
            total_pages_with_links BIGINT UNSIGNED DEFAULT 0,
            last_scan_time TIMESTAMP NULL,
            scan_in_progress BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset;";

        // جدول لاگ‌ها
        $sql_logs = "CREATE TABLE IF NOT EXISTS " . self::$table_logs . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $charset;";

        dbDelta($sql_links);
        dbDelta($sql_stats);
        dbDelta($sql_logs);

        // ایجاد ردیف اولیه آمار
        $exists = $wpdb->get_var("SELECT COUNT(*) FROM " . self::$table_stats);
        if (!$exists) {
            $wpdb->insert(self::$table_stats, [
                'total_links' => 0,
                'total_pages_with_links' => 0
            ]);
        }
    }

    /**
     * ذخیره لینک‌های استخراج‌شده
     */
    public static function save_links($links) {
        global $wpdb;

        foreach ($links as $link) {
            $wpdb->insert(
                self::$table_links,
                $link,
                ['%s', '%s', '%d', '%s', '%s']
            );
        }

        self::update_stats();
    }

    /**
     * پاک کردن تمام لینک‌های قبلی
     */
    public static function truncate_links() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE " . self::$table_links);
    }

    /**
     * بازیابی آمار
     */
    public static function get_stats() {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM " . self::$table_stats . " LIMIT 1");
    }

    /**
     * به‌روز رسانی آمار کلی
     */
    public static function update_stats() {
        global $wpdb;

        $total_links = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::$table_links);
        $total_pages = (int) $wpdb->get_var("SELECT COUNT(DISTINCT source_post_id) FROM " . self::$table_links);

        $wpdb->update(
            self::$table_stats,
            [
                'total_links' => $total_links,
                'total_pages_with_links' => $total_pages,
                'last_scan_time' => current_time('mysql')
            ],
            ['id' => 1],
            ['%d', '%d', '%s'],
            ['%d']
        );
    }

    /**
     * بازیابی لینک‌ها (با صفحه‌بندی و فیلتر)
     */
    public static function get_links($page = 1, $per_page = 50, $content_type = '', $search = '', $source_url = '', $order = 'DESC') {
        global $wpdb;

        $offset = ($page - 1) * $per_page;
        
        $where_clauses = [];
        $values = [];

        if ($content_type) {
            $where_clauses[] = "content_type = %s";
            $values[] = $content_type;
        }

        if ($search) {
            $where_clauses[] = "(url LIKE %s OR anchor_text LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $values[] = $search_like;
            $values[] = $search_like;
        }

        if ($source_url) {
            $where_clauses[] = "source_url LIKE %s";
            $values[] = '%' . $wpdb->esc_like($source_url) . '%';
        }

        $where = '';
        if (!empty($where_clauses)) {
            $where = "WHERE " . implode(' AND ', $where_clauses);
        }

        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $query = "SELECT * FROM " . self::$table_links . " $where ORDER BY created_at $order LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        return $wpdb->get_results($wpdb->prepare($query, $values));
    }

    /**
     * شمارش کل لینک‌ها
     */
    public static function count_links($content_type = '', $search = '', $source_url = '') {
        global $wpdb;

        $where_clauses = [];
        $values = [];

        if ($content_type) {
            $where_clauses[] = "content_type = %s";
            $values[] = $content_type;
        }

        if ($search) {
            $where_clauses[] = "(url LIKE %s OR anchor_text LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $values[] = $search_like;
            $values[] = $search_like;
        }

        if ($source_url) {
            $where_clauses[] = "source_url LIKE %s";
            $values[] = '%' . $wpdb->esc_like($source_url) . '%';
        }

        $where = '';
        if (!empty($where_clauses)) {
            $where = "WHERE " . implode(' AND ', $where_clauses);
        }

        $query = "SELECT COUNT(*) FROM " . self::$table_links . " $where";

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * ثبت لاگ در دیتابیس
     */
    public static function add_log($type, $message) {
        global $wpdb;
        $wpdb->insert(
            self::$table_logs,
            [
                'type' => $type,
                'message' => $message
            ],
            ['%s', '%s']
        );
    }

    /**
     * دریافت لاگ‌ها
     */
    public static function get_logs($page = 1, $per_page = 50) {
        global $wpdb;
        $offset = ($page - 1) * $per_page;
        
        $query = $wpdb->prepare(
            "SELECT * FROM " . self::$table_logs . " ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
        return $wpdb->get_results($query);
    }

    /**
     * پاک کردن لاگ‌ها
     */
    public static function clear_logs() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE " . self::$table_logs);
    }

    /**
     * شمارش لاگ‌ها
     */
    public static function count_logs() {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::$table_logs);
    }

    /**
     * تنظیم وضعیت اسکن
     */
    public static function set_scan_status($status) {
        global $wpdb;

        $wpdb->update(
            self::$table_stats,
            ['scan_in_progress' => $status ? 1 : 0],
            ['id' => 1],
            ['%d'],
            ['%d']
        );
    }
}

HTR_EL_Repository::init();
?>
