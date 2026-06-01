/**
 * HTR External Links Dashboard - اسکریپت‌های ادمین
 * jQuery-based admin interface controller
 */

(function ($) {
  "use strict";

  var htrElDashboard = {
    /**
     * مقداردهی
     */
    init: function () {
      this.cacheElements();
      this.bindEvents();
    },

    /**
     * ذخیره عناصر DOM
     */
    cacheElements: function () {
      this.$scanBtn = $("#htr-el-scan-btn");
      this.$btnText = this.$scanBtn.find(".htr-el-btn-text");
      this.$spinner = this.$scanBtn.find(".htr-el-spinner");
      this.$container = $(".htr-el-container");
    },

    /**
     * بایند کردن رویدادها
     */
    bindEvents: function () {
      var self = this;

      this.$scanBtn.on("click", function (e) {
        e.preventDefault();
        self.handleScan();
      });
    },

    /**
     * مدیریت اسکن
     */
    handleScan: function () {
      var self = this;

      // بررسی دکمه
      if (this.$scanBtn.prop("disabled")) {
        return;
      }

      // نمایش تأیید
      if (!confirm(htrEl.i18n.confirmScan)) {
        return;
      }

      // غیرفعال کردن دکمه
      this.$scanBtn.prop("disabled", true);
      this.$spinner.show();
      this.$btnText.text(htrEl.i18n.scanning);

      // درخواست AJAX
      $.ajax({
        type: "POST",
        url: htrEl.ajaxUrl,
        data: {
          action: "htr_el_scan",
          nonce: htrEl.nonce,
        },
        success: function (response) {
          if (response.success) {
            self.showNotice(htrEl.i18n.success, "success");

            // رفرش صفحه پس از تأخیر
            setTimeout(function () {
              location.reload();
            }, 2000);
          } else {
            var message = response.data?.message || htrEl.i18n.error;
            self.showNotice(message, "error");
          }
        },
        error: function () {
          self.showNotice(htrEl.i18n.error, "error");
        },
        complete: function () {
          self.resetButton();
        },
      });
    },

    /**
     * ریست کردن دکمه
     */
    resetButton: function () {
      this.$scanBtn.prop("disabled", false);
      this.$spinner.hide();
      this.$btnText.text("🔄 اسکن مجدد");
    },

    /**
     * نمایش اعلان
     */
    showNotice: function (message, type) {
      var noticeClass = "htr-el-notice " + type;
      var $notice = $('<div class="' + noticeClass + '">' + message + "</div>");

      // حذف اعلان‌های قبلی
      this.$container.find(".htr-el-notice:not(.empty)").remove();

      // اضافه کردن اعلان جدید
      this.$container.find(".htr-el-header").after($notice);

      // پنهان کردن خودکار
      setTimeout(function () {
        $notice.fadeOut(function () {
          $(this).remove();
        });
      }, 4000);
    },
  };

  /**
   * مقداردهی هنگام بارگذاری صفحه
   */
  $(document).ready(function () {
    htrElDashboard.init();
  });
})(jQuery);
