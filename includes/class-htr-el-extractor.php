<?php
/**
 * HTR External Links - Extractor (Business Logic Layer)
 * استخراج لینک‌های خارجی از محتوای سایت
 */

if (!defined('ABSPATH')) {
    exit;
}

class HTR_EL_Extractor {
    /**
     * اجرای اسکن کامل و استخراج لینک‌های خارجی
     */
    public static function run_scan() {
        HTR_EL_Repository::init();
        HTR_EL_Repository::set_scan_status(true);

        try {
            HTR_EL_Repository::truncate_links();

            self::scan_posts();
            self::scan_pages();
            self::scan_woocommerce_products();

            HTR_EL_Repository::set_scan_status(false);
            self::log('اسکن کامل با موفقیت انجام شد');

            return true;
        } catch (Exception $e) {
            self::log('❌ خطا در اسکن: ' . $e->getMessage());
            HTR_EL_Repository::set_scan_status(false);
            return false;
        }
    }

    /**
     * اسکن پست‌های سایت
     */
    private static function scan_posts() {
        $args = [
            'post_type' => 'post',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ];

        $posts = get_posts($args);

        foreach ($posts as $post_id) {
            self::process_post($post_id, 'post');
        }

        self::log('✅ اسکن ' . count($posts) . ' پست کامل شد');
    }

    /**
     * اسکن صفحات سایت
     */
    private static function scan_pages() {
        $args = [
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ];

        $pages = get_posts($args);

        foreach ($pages as $page_id) {
            self::process_post($page_id, 'page');
        }

        self::log('✅ اسکن ' . count($pages) . ' صفحه کامل شد');
    }

    /**
     * اسکن محصولات WooCommerce
     */
    private static function scan_woocommerce_products() {
        if (!class_exists('WooCommerce')) {
            self::log('⚠️ WooCommerce فعال نیست، اسکن محصولات نشد');
            return;
        }

        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ];

        $products = get_posts($args);

        foreach ($products as $product_id) {
            self::process_post($product_id, 'product');
        }

        self::log('✅ اسکن ' . count($products) . ' محصول کامل شد');
    }

    /**
     * پردازش یک محتوا و استخراج لینک‌های خارجی
     */
    private static function process_post($post_id, $content_type) {
        $post = get_post($post_id);

        if (!$post) {
            return;
        }

        $content = $post->post_content;

        // اضافه کردن متامتا برای محصولات
        if ($content_type === 'product' && class_exists('WooCommerce')) {
            $short_desc = get_post_meta($post_id, '_product_short_description', true);
            $description = get_post_meta($post_id, '_product_description', true);

            if ($short_desc) {
                $content .= ' ' . $short_desc;
            }
            if ($description) {
                $content .= ' ' . $description;
            }
        }

        // استخراج لینک‌های خارجی
        $links = self::extract_links($content, $post_id, $post->post_title, $content_type);

        if (!empty($links)) {
            HTR_EL_Repository::save_links($links);
        }
    }

    /**
     * استخراج لینک‌های خارجی از محتوا با استفاده از regex
     */
    private static function extract_links($content, $post_id, $post_title, $content_type) {
        $links = [];
        $home_url = home_url();
        $parsed_home = wp_parse_url($home_url);
        $home_domain = strtolower($parsed_home['host'] ?? '');

        // regex برای یافتن تمام تگ‌های a
        if (!preg_match_all('/<a\s+(?:[^>]*?\s+)?href=(["\'])(.+?)\1/i', $content, $matches)) {
            return $links;
        }

        foreach ($matches[2] as $url) {
            $url = trim($url);

            // نادیده گرفتن URL‌های خالی و لنگرها
            if (empty($url) || $url[0] === '#') {
                continue;
            }

            // تبدیل URL‌های نسبی به مطلق
            if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
                $url = trailingslashit($home_url) . ltrim($url, '/');
            }

            // بررسی اینکه آیا لینک خارجی است
            if (self::is_external($url, $home_domain)) {
                $links[] = [
                    'url' => esc_url($url),
                    'source_url' => get_permalink($post_id),
                    'source_post_id' => $post_id,
                    'content_type' => $content_type,
                    'post_title' => $post_title
                ];
            }
        }

        return $links;
    }

    /**
     * تعیین اینکه آیا یک URL خارجی است
     */
    private static function is_external($url, $home_domain) {
        $parsed = wp_parse_url($url);
        $domain = strtolower($parsed['host'] ?? '');

        if (empty($domain)) {
            return false;
        }

        // بررسی اینکه دامنه صفحه منبع نیست
        return $domain !== $home_domain && strpos($domain, $home_domain) === false;
    }

    /**
     * لوگ‌کردن در debug.log
     */
    private static function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[HTR-EL] ' . $message);
        }
    }
}
?>
