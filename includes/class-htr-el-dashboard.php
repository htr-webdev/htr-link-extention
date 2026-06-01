<?php
/**
 * HTR External Links - Dashboard (Presentation Layer)
 * رابط کاربری و صفحه مدیریت ادمین
 */

if (!defined('ABSPATH')) {
    exit;
}

class HTR_EL_Dashboard {
    private static $instance = null;

    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        self::$instance->register_hooks();
        return self::$instance;
    }

    /**
     * ثبت اکشن‌های WordPress
     */
    private function register_hooks() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'load_assets']);
    }

    /**
     * ثبت منوی ادمین
     */
    public function register_menu() {
        add_menu_page(
            'لینک‌های خارجی',
            'لینک‌های خارجی',
            'manage_options',
            'htr-el-links',
            [$this, 'render_dashboard'],
            'dashicons-admin-links',
            25
        );
    }

    /**
     * بارگذاری منابع (CSS و JavaScript)
     */
    public function load_assets($hook) {
        if (strpos($hook, 'htr-el-links') === false) {
            return;
        }

        // jQuery (ضروری)
        wp_enqueue_script('jquery');

        // استایل‌ها
        wp_enqueue_style(
            'htr-el-admin-dashboard',
            HTR_EL_URL . 'assets/css/admin-dashboard.css',
            [],
            HTR_EL_VERSION
        );

        // اسکریپت‌ها
        wp_enqueue_script(
            'htr-el-admin-dashboard',
            HTR_EL_URL . 'assets/js/admin-dashboard.js',
            ['jquery'],
            HTR_EL_VERSION,
            true
        );

        // متغیرهای JavaScript
        wp_localize_script('htr-el-admin-dashboard', 'htrEl', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('htr_el_nonce'),
            'i18n' => [
                'scanning' => 'در حال اسکن...',
                'success' => '✅ اسکن کامل شد',
                'error' => '❌ خطا در اسکن',
                'confirmScan' => 'آیا مطمئن هستید؟ این عملیات ممکن است چند دقیقه طول بکشد'
            ]
        ]);
    }

    /**
     * رندر کردن صفحه داشبورد
     */
    public function render_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die('🔒 دسترسی مجاز نیست');
        }

        HTR_EL_Repository::init();

        // بازیابی پارامترهای صفحه
        $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 50;
        $content_type = isset($_GET['content_type']) ? sanitize_text_field($_GET['content_type']) : '';

        // دریافت داده‌ها از پایگاه‌داده
        $links = HTR_EL_Repository::get_links($current_page, $per_page, $content_type);
        $total_items = HTR_EL_Repository::count_links($content_type);
        $total_pages = ceil($total_items / $per_page);
        $stats = HTR_EL_Repository::get_stats();

        ?>
        <div class="htr-el-container">
            <!-- هدر -->
            <div class="htr-el-header">
                <h1>🔗 مدیریت لینک‌های خارجی</h1>
                <button id="htr-el-scan-btn" class="htr-el-button">
                    <span class="htr-el-btn-text">🔄 اسکن مجدد</span>
                    <span class="htr-el-spinner" style="display:none;"></span>
                </button>
            </div>

            <!-- اطلاعات آخرین اسکن -->
            <?php if ($stats && $stats->last_scan_time) : ?>
                <div class="htr-el-notice info">
                    📅 آخرین اسکن: <strong><?php echo wp_date('d/m/Y ساعت H:i', strtotime($stats->last_scan_time)); ?></strong>
                </div>
            <?php endif; ?>

            <!-- کارت‌های آمار -->
            <div class="htr-el-stats-grid">
                <div class="htr-el-stat-card primary">
                    <div class="htr-el-stat-value"><?php echo intval($stats->total_links ?? 0); ?></div>
                    <div class="htr-el-stat-label">تعداد کل لینک‌های خارجی</div>
                </div>

                <div class="htr-el-stat-card secondary">
                    <div class="htr-el-stat-value"><?php echo intval($stats->total_pages_with_links ?? 0); ?></div>
                    <div class="htr-el-stat-label">تعداد صفحات با لینک خارجی</div>
                </div>
            </div>

            <!-- بخش فیلتر -->
            <?php $this->render_filter_section($content_type); ?>

            <!-- جدول لینک‌ها یا پیام خالی -->
            <?php $this->render_links_table($links, $total_items, $total_pages, $current_page, $per_page, $content_type); ?>
        </div>
        <?php
    }

    /**
     * رندر بخش فیلتر
     */
    private function render_filter_section($content_type) {
        ?>
        <div class="htr-el-filter-section">
            <form method="GET" class="htr-el-filter-form">
                <input type="hidden" name="page" value="htr-el-links">

                <label for="htr-el-content-type">نوع محتوا:</label>
                <select id="htr-el-content-type" name="content_type">
                    <option value="">📊 همه</option>
                    <option value="post" <?php selected($content_type, 'post'); ?>>📝 پست</option>
                    <option value="page" <?php selected($content_type, 'page'); ?>>📄 صفحه</option>
                    <option value="product" <?php selected($content_type, 'product'); ?>>🛒 محصول</option>
                </select>

                <button type="submit" class="htr-el-button">🔍 فیلتر</button>
            </form>
        </div>
        <?php
    }

    /**
     * رندر جدول لینک‌ها
     */
    private function render_links_table($links, $total_items, $total_pages, $current_page, $per_page, $content_type) {
        if (!$links) {
            ?>
            <div class="htr-el-notice empty">
                <strong>ℹ️ هیچ لینک خارجی یافت نشد</strong>
                <p>برای شروع، دکمه <strong>"🔄 اسکن مجدد"</strong> را کلیک کنید</p>
            </div>
            <?php
            return;
        }

        ?>
        <div class="htr-el-table-wrapper">
            <table class="htr-el-table">
                <thead>
                    <tr>
                        <th width="35%">لینک خارجی</th>
                        <th width="20%">صفحه منبع</th>
                        <th width="25%">عنوان صفحه</th>
                        <th width="10%">نوع</th>
                        <th width="10%">تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($links as $link) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url($link->url); ?>" target="_blank" class="htr-el-external-link" title="<?php echo esc_attr($link->url); ?>">
                                    <?php echo esc_html(substr($link->url, 0, 45)); ?>...
                                </a>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($link->source_url); ?>" target="_blank" class="htr-el-source-link">
                                    🔗 نمایش
                                </a>
                            </td>
                            <td><?php echo esc_html($link->post_title); ?></td>
                            <td>
                                <span class="htr-el-badge htr-el-badge-<?php echo esc_attr($link->content_type); ?>">
                                    <?php echo esc_html($this->get_type_label($link->content_type)); ?>
                                </span>
                            </td>
                            <td><?php echo wp_date('d/m/Y', strtotime($link->created_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- صفحه‌بندی -->
        <?php if ($total_pages > 1) : ?>
            <div class="htr-el-pagination">
                <?php
                $base_url = admin_url('admin.php?page=htr-el-links');
                if ($content_type) {
                    $base_url = add_query_arg('content_type', $content_type, $base_url);
                }

                for ($i = 1; $i <= $total_pages; $i++) {
                    $page_url = add_query_arg('paged', $i, $base_url);
                    $active = ($i == $current_page) ? 'active' : '';
                    echo '<a href="' . esc_url($page_url) . '" class="' . $active . '">' . $i . '</a>';
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- معلومات صفحه‌بندی -->
        <div class="htr-el-pagination-info">
            نمایش <strong><?php echo (($current_page - 1) * $per_page) + 1; ?></strong> تا <strong><?php echo min($current_page * $per_page, $total_items); ?></strong> از <strong><?php echo $total_items; ?></strong> لینک
        </div>
        <?php
    }

    /**
     * تبدیل کد نوع محتوا به برچسب
     */
    private function get_type_label($type) {
        $labels = [
            'post' => '📝 پست',
            'page' => '📄 صفحه',
            'product' => '🛒 محصول'
        ];

        return $labels[$type] ?? $type;
    }
}
?>
