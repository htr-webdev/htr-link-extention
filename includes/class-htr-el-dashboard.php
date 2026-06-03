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
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'links';
        $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 50;
        
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

            <!-- تب‌ها -->
            <h2 class="nav-tab-wrapper">
                <a href="?page=htr-el-links&tab=links" class="nav-tab <?php echo $current_tab === 'links' ? 'nav-tab-active' : ''; ?>">لینک‌ها</a>
                <a href="?page=htr-el-links&tab=logs" class="nav-tab <?php echo $current_tab === 'logs' ? 'nav-tab-active' : ''; ?>">گزارش لاگ‌ها</a>
            </h2>

            <?php if ($current_tab === 'links') : ?>
                <?php $this->render_links_tab($current_page, $per_page); ?>
            <?php elseif ($current_tab === 'logs') : ?>
                <?php $this->render_logs_tab($current_page, $per_page); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * رندر تب لینک‌ها
     */
    private function render_links_tab($current_page, $per_page) {
        $content_type = isset($_GET['content_type']) ? sanitize_text_field($_GET['content_type']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $source_url = isset($_GET['source_url']) ? sanitize_url($_GET['source_url']) : '';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

        // دریافت داده‌ها از پایگاه‌داده
        $links = HTR_EL_Repository::get_links($current_page, $per_page, $content_type, $search, $source_url, $order);
        $total_items = HTR_EL_Repository::count_links($content_type, $search, $source_url);
        $total_pages = ceil($total_items / $per_page);
        $stats = HTR_EL_Repository::get_stats();

        ?>
            <!-- اطلاعات آخرین اسکن -->
            <?php if ($stats && $stats->last_scan_time) : ?>
                <div class="htr-el-notice info" style="margin-top: 20px;">
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
            <?php $this->render_filter_section($content_type, $search, $source_url, $order); ?>

            <!-- جدول لینک‌ها یا پیام خالی -->
            <?php $this->render_links_table($links, $total_items, $total_pages, $current_page, $per_page, $content_type, $search, $source_url, $order); ?>
        <?php
    }

    /**
     * رندر تب لاگ‌ها
     */
    private function render_logs_tab($current_page, $per_page) {
        $logs = HTR_EL_Repository::get_logs($current_page, $per_page);
        $total_items = HTR_EL_Repository::count_logs();
        $total_pages = ceil($total_items / $per_page);

        if (!$logs) {
            ?>
            <div class="htr-el-notice success" style="margin-top: 20px;">
                <strong>✅ مشکلی نیست</strong>
                <p>هیچ خطا یا لاگی در سیستم ثبت نشده است.</p>
            </div>
            <?php
            return;
        }

        ?>
        <div class="htr-el-table-wrapper" style="margin-top: 20px;">
            <table class="htr-el-table">
                <thead>
                    <tr>
                        <th width="15%">نوع</th>
                        <th width="65%">پیام</th>
                        <th width="20%">تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td>
                                <?php
                                $badge_class = 'info';
                                if ($log->type === 'error') $badge_class = 'error';
                                elseif ($log->type === 'warning') $badge_class = 'warning';
                                elseif ($log->type === 'success') $badge_class = 'success';
                                ?>
                                <span class="htr-el-badge htr-el-badge-<?php echo esc_attr($badge_class); ?>">
                                    <?php echo esc_html(strtoupper($log->type)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log->message); ?></td>
                            <td><?php echo wp_date('d/m/Y H:i', strtotime($log->created_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- صفحه‌بندی لاگ‌ها -->
        <?php if ($total_pages > 1) : ?>
            <div class="htr-el-pagination">
                <?php
                $base_url = admin_url('admin.php?page=htr-el-links&tab=logs');
                for ($i = 1; $i <= $total_pages; $i++) {
                    $page_url = add_query_arg('paged', $i, $base_url);
                    $active = ($i == $current_page) ? 'active' : '';
                    echo '<a href="' . esc_url($page_url) . '" class="' . $active . '">' . $i . '</a>';
                }
                ?>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * رندر بخش فیلتر
     */
    private function render_filter_section($content_type, $search, $source_url, $order) {
        $next_order = $order === 'DESC' ? 'ASC' : 'DESC';
        $order_icon = $order === 'DESC' ? '🔽' : '🔼';
        ?>
        <div class="htr-el-filter-section">
            <form method="GET" class="htr-el-filter-form" id="htr-el-filter-form">
                <input type="hidden" name="page" value="htr-el-links">
                <input type="hidden" name="order" id="htr-el-order" value="<?php echo esc_attr($order); ?>">

                <div class="htr-el-filter-group">
                    <label for="htr-el-search">جستجو:</label>
                    <input type="text" id="htr-el-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="متن یا لینک..." class="htr-el-input">
                </div>

                <div class="htr-el-filter-group">
                    <label for="htr-el-source-url">آدرس صفحه منبع:</label>
                    <input type="text" id="htr-el-source-url" name="source_url" value="<?php echo esc_attr($source_url); ?>" placeholder="مثال: https://site.com/page" class="htr-el-input" style="min-width: 250px;">
                </div>

                <div class="htr-el-filter-group">
                    <label for="htr-el-content-type">نوع محتوا:</label>
                    <select id="htr-el-content-type" name="content_type">
                        <option value="">📊 همه</option>
                        <option value="post" <?php selected($content_type, 'post'); ?>>📝 پست</option>
                        <option value="page" <?php selected($content_type, 'page'); ?>>📄 صفحه</option>
                        <option value="product" <?php selected($content_type, 'product'); ?>>🛒 محصول</option>
                        <option value="product_cat" <?php selected($content_type, 'product_cat'); ?>>📁 دسته محصول</option>
                    </select>
                </div>

                <button type="submit" class="htr-el-button">🔍 اعمال</button>
            </form>
        </div>
        <?php
    }

    /**
     * رندر جدول لینک‌ها
     */
    private function render_links_table($links, $total_items, $total_pages, $current_page, $per_page, $content_type, $search, $source_url, $order) {
        if (!$links) {
            ?>
            <div class="htr-el-notice empty">
                <strong>ℹ️ هیچ لینک خارجی یافت نشد</strong>
                <p>برای شروع، دکمه <strong>"🔄 اسکن مجدد"</strong> را کلیک کنید</p>
            </div>
            <?php
            return;
        }

        $next_order = $order === 'DESC' ? 'ASC' : 'DESC';
        $order_icon = $order === 'DESC' ? '🔽' : '🔼';
        ?>
        <div class="htr-el-table-wrapper" id="htr-el-table-container">
            <table class="htr-el-table">
                <thead>
                    <tr>
                        <th width="30%">لینک خارجی</th>
                        <th width="25%">متن لینک (Anchor Text)</th>
                        <th width="20%">صفحه منبع</th>
                        <th width="12%">نوع</th>
                        <th width="13%">
                            <a href="#" id="htr-el-sort-date" data-order="<?php echo esc_attr($next_order); ?>" style="text-decoration: none; color: inherit;">
                                تاریخ <?php echo $order_icon; ?>
                            </a>
                        </th>
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
                            <td title="<?php echo esc_attr($link->anchor_text ?? ''); ?>">
                                <?php echo esc_html(substr($link->anchor_text ?? '', 0, 35)) ?: '(بدون متن)'; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($link->source_url); ?>" target="_blank" class="htr-el-source-link">
                                    🔗 نمایش
                                </a>
                            </td>
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
                if ($search) {
                    $base_url = add_query_arg('s', urlencode($search), $base_url);
                }
                if ($source_url) {
                    $base_url = add_query_arg('source_url', urlencode($source_url), $base_url);
                }
                if ($order) {
                    $base_url = add_query_arg('order', $order, $base_url);
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
            'product' => '🛒 محصول',
            'product_cat' => '📁 دسته محصول'
        ];

        return $labels[$type] ?? $type;
    }
}
?>
