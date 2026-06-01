# HTR External Links Extractor - نسخه 2.0

## 📋 معماری حرفه‌ای و ماژولار

### ساختار پروژه

```
htr-external-links/
│
├── 📄 htr-external-links.php
│   └── فایل bootstrap اصلی
│       • بارگذاری کلاس‌ها
│       • ثبت hooks اصلی
│       • مدیریت lifecycle
│
├── 📁 includes/
│   ├── class-htr-el-plugin.php
│   │   └── هسته اصلی پلاگین
│   │       • پیاده‌سازی Singleton
│   │       • مدیریت lifecycle
│   │       • orchestration hooks
│   │
│   ├── class-htr-el-repository.php
│   │   └── Data Access Layer
│   │       • عملیات CRUD بر روی DB
│   │       • مدیریت جداول
│   │       • کوئری‌های بهینه
│   │
│   ├── class-htr-el-extractor.php
│   │   └── Business Logic Layer
│   │       • استخراج لینک‌های خارجی
│   │       • اسکن محتوا
│   │       • تحلیل و فیلترینگ
│   │
│   └── class-htr-el-dashboard.php
│       └── Presentation Layer
│           • رابط کاربری ادمین
│           • صفحه مدیریت
│           • رندرینگ UI
│
├── 📁 assets/
│   ├── css/
│   │   └── admin-dashboard.css
│   │       • استایل صفحه ادمین
│   │       • واکنش‌پذیری (responsive)
│   │       • RTL support
│   │
│   └── js/
│       └── admin-dashboard.js
│           • منطق رابط کاربری
│           • AJAX requests
│           • رویدادهای DOM
│
└── 📄 README.md
    └── این فایل
```

---

## 🏗️ الگوهای طراحی

### 1. **Singleton Pattern**

هر کلاس تنها یک نمونه دارد:

```php
public static function init() {
    if (null === self::$instance) {
        self::$instance = new self();
    }
    return self::$instance;
}
```

### 2. **Repository Pattern**

جدایی DB logic از business logic:

- `HTR_EL_Repository` - تمام عملیات database
- `HTR_EL_Extractor` - تمام عملیات extraction
- `HTR_EL_Dashboard` - تمام عملیات UI

### 3. **Separation of Concerns**

هر کلاس یک وظیفه دارد:

- **Plugin Core** - orchestration
- **Repository** - persistence
- **Extractor** - business logic
- **Dashboard** - presentation

---

## 📝 نام‌گذاری و قراردادهای کد

### نام‌گذاری کلاس‌ها

```
class-htr-el-{component}.php
├── HTR     = Company/Project initials
├── EL      = External Links
└── {component} = specific functionality
```

**مثال:**

- `class-htr-el-repository.php` → `HTR_EL_Repository`
- `class-htr-el-dashboard.php` → `HTR_EL_Dashboard`

### نام‌گذاری متغیرهای CSS

```css
.htr-el-{component}-{element}
├── htr-el         = plugin namespace
├── {component}    = بخش (header, button, table, etc)
└── {element}      = عنصر خاص (text, icon, etc)
```

**مثال:**

- `.htr-el-container` - پوسته اصلی
- `.htr-el-button` - دکمه‌ها
- `.htr-el-stat-card` - کارت‌های آمار
- `.htr-el-table-wrapper` - جدول

### نام‌گذاری متغیرهای JavaScript

```js
var htrElDashboard = {
    init: function () { ... }
}
```

---

## 🔍 انواع محتوای پشتیبانی‌شده

| نوع | کد | توضیح |
|------|------|------|
| 📝 پست | `post` | مقالات وبلاگ |
| 📄 صفحات | `page` | صفحات استاتیک |
| 🛒 محصولات | `product` | محصولات WooCommerce |
| 📁 دسته محصول | `product_cat` | دسته‌بندی‌های محصول |
| 🎯 Custom Types | `custom_*` | انواع محتوای سفارشی |

### استخراج Anchor Text

افزونه متن داخل تگ‌های `<a>` را استخراج می‌کند:

```html
<a href="https://example.com">متن قابل کلیک</a>
                         ↓ ↓ ↓
                    متن لینک ذخیره می‌شود
```

---

## 📊 ستون‌های جدول

| ستون | توضیح |
|------|------|
| لینک خارجی | URL کامل لینک خارجی (اول 45 کاراکتر) |
| متن لینک (Anchor Text) | متن نمایشی داخل تگ `<a>` (اول 35 کاراکتر) |
| صفحه منبع | لینک به صفحه‌ای که لینک در آن قرار دارد |
| نوع | نوع محتوایی که شامل لینک است |
| تاریخ | تاریخ اضافه‌شدن لینک (بدون ساعت) |

---

## 🚀 نحوه استفاده

### 1. حذف نسخه قدیم

```bash
wp plugin deactivate htr-external-links
wp plugin delete htr-external-links
```

### 2. آپلود نسخه جدید

```
/wp-content/plugins/htr-external-links/
├── htr-external-links.php          ✅ Main bootstrap
├── includes/
│   ├── class-htr-el-plugin.php    ✅ Core plugin
│   ├── class-htr-el-repository.php ✅ Data layer
│   ├── class-htr-el-extractor.php  ✅ Business logic
│   └── class-htr-el-dashboard.php  ✅ UI layer
├── assets/
│   ├── css/admin-dashboard.css
│   └── js/admin-dashboard.js
└── README.md
```

### 3. فعال‌کردن

```bash
wp plugin activate htr-external-links
```

### 4. اول اسکن

```
WP Admin → لینک‌های خارجی → دکمه 🔄 اسکن مجدد
```

---

## 🔧 نکات تکنیکی

### جداول بیس‌داده

```sql
wp_htr_el_external_links     -- ذخیره لینک‌ها
wp_htr_el_stats              -- آمار کلی
```

### WP-Cron

اسکن خودکار هر روز:

```php
wp_schedule_event(time(), 'daily', 'htr_el_scheduled_scan');
```

### AJAX Handler

```php
add_action('wp_ajax_htr_el_scan', [$this, 'ajax_scan']);
```

---

## 📊 گردش کار کد

```
1. User clicks button
   ↓
2. htrElDashboard.handleScan() [JS]
   ↓
3. AJAX → admin-ajax.php
   ↓
4. HTR_EL_Plugin::ajax_scan() [PHP]
   ↓
5. HTR_EL_Extractor::run_scan()
   ↓
6. Scan posts/pages/products/categories/custom types
   ↓
7. Extract external links with anchor text
   ↓
8. HTR_EL_Repository::save_links()
   ↓
9. JSON response to JS
   ↓
10. Page reload
```

---

## 🐛 رفع‌شدن مشکلات

✅ jQuery is not defined

- jQuery correctly enqueued with dependencies

✅ Syntax error in script.js

- Proper inline IIFE syntax

✅ Missing external links

- Enhanced extraction logic
- Better content parsing

✅ Modular architecture

- 5 focused files
- Clear responsibilities
- Easy to maintain

---

## 🎯 ویژگی‌های نسخه 2.0

- ✅ معماری ماژولار و حرفه‌ای
- ✅ Singleton pattern
- ✅ Repository pattern (Data Access)
- ✅ جدایی منطق و ارائه
- ✅ نام‌گذاری حرفه‌ای
- ✅ کامنت‌های معنی‌دار
- ✅ بدون تکرار کد
- ✅ واکنش‌پذیری کامل
- ✅ RTL support
- ✅ لوگ‌گیری خطاها
- ✅ اسکن دسته‌بندی‌های محصول
- ✅ پشتیبانی از Custom Post Types
- ✅ استخراج Anchor Text
- ✅ جدول HTML حرفه‌ای

---

## ❓ سوالات و اطلاعات اضافی

اگر نیاز به موارد زیر دارید، مرا مطلع کنید:

1. **صادرات CSV** - خروجی به صورت فایل Excel
2. **فیلترهای پیشرفته** - فیلتر بر اساس تاریخ، دامنه، الغ
3. **Bulk actions** - حذف دسته‌ای لینک‌ها
4. **Schedule مخصوص** - اسکن ساعتی یا هفتگی
5. **Webhook integration** - ارسال داده‌ها به سرویس خارجی
6. **API endpoint** - دسترسی به اطلاعات از خارج

---

**ایجاد شده توسط:** HTR Team
**نسخه:** 2.0.0
**ورژن PHP:** 7.4+
**ورژن WordPress:** 5.0+
