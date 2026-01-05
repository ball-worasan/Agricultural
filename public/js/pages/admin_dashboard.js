(function () {
  "use strict";

  function selectAll(selector, root) {
    return Array.prototype.slice.call(
      (root || document).querySelectorAll(selector)
    );
  }

  function activateTab(name) {
    selectAll(".tab-content").forEach(function (el) {
      el.classList.remove("active");
    });
    selectAll(".tab-btn").forEach(function (el) {
      el.classList.remove("active");
    });
    var target = document.getElementById("tab-" + name);
    if (target) target.classList.add("active");
    var btn = selectAll(".admin-tabs .tab-btn").find(function (b) {
      return (b.textContent || "").indexOf(name) !== -1;
    });
    if (btn) btn.classList.add("active");
  }

  function wireTabs() {
    var tabs = selectAll(".admin-tabs .tab-btn");
    tabs.forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        var label = btn.getAttribute("onclick") || "";
        // Fallback: parse inline handler param 'switchTab(event, "<name>")'
        var match = label.match(/switchTab\(event,\s*'([^']+)'\)/);
        var name = match ? match[1] : btn.dataset.tab || "properties";
        // If inline function exists, let it handle; else do it here
        if (typeof window.switchTab === "function") return;
        e.preventDefault();
        activateTab(name);
      });
    });
  }

  function wireFormValidation() {
    var settingsForm = document.querySelector(".settings-form");
    if (!settingsForm) return;

    settingsForm.addEventListener("submit", function (e) {
      var isValid = true;
      var errors = [];

      // ตรวจสอบค่าธรรมเนียม
      var feeRate = settingsForm.querySelector('input[name="fee_rate"]');
      if (feeRate) {
        var rate = parseFloat(feeRate.value);
        if (isNaN(rate) || rate < 0 || rate > 100) {
          isValid = false;
          errors.push("ค่าธรรมเนียมต้องอยู่ระหว่าง 0-100%");
          feeRate.classList.add("error");
        } else {
          feeRate.classList.remove("error");
        }
      }

      // ตรวจสอบเลขบัญชี/พร้อมเพย์
      var accountNumber = settingsForm.querySelector('input[name="account_number"]');
      if (accountNumber && accountNumber.value.trim() === "") {
        isValid = false;
        errors.push("กรุณาระบุเลขบัญชีหรือพร้อมเพย์");
        accountNumber.classList.add("error");
      } else if (accountNumber) {
        accountNumber.classList.remove("error");
      }

      // ตรวจสอบชื่อบัญชี
      var accountName = settingsForm.querySelector('input[name="account_name"]');
      if (accountName && accountName.value.trim() === "") {
        isValid = false;
        errors.push("กรุณาระบุชื่อบัญชี");
        accountName.classList.add("error");
      } else if (accountName) {
        accountName.classList.remove("error");
      }

      // ตรวจสอบธนาคาร
      var bankName = settingsForm.querySelector('input[name="bank_name"]');
      if (bankName && bankName.value.trim() === "") {
        isValid = false;
        errors.push("กรุณาระบุชื่อธนาคาร");
        bankName.classList.add("error");
      } else if (bankName) {
        bankName.classList.remove("error");
      }

      // ตรวจสอบวันที่มีผล
      var effectiveFrom = settingsForm.querySelector('input[name="effective_from"]');
      if (effectiveFrom && effectiveFrom.value === "") {
        isValid = false;
        errors.push("กรุณาระบุวันที่มีผล");
        effectiveFrom.classList.add("error");
      } else if (effectiveFrom) {
        effectiveFrom.classList.remove("error");
      }

      // ตรวจสอบวันที่สิ้นสุด (ถ้ามี) ต้องมากกว่าวันที่เริ่ม
      var effectiveTo = settingsForm.querySelector('input[name="effective_to"]');
      if (effectiveFrom && effectiveTo && effectiveTo.value !== "") {
        var dateFrom = new Date(effectiveFrom.value);
        var dateTo = new Date(effectiveTo.value);
        if (dateTo <= dateFrom) {
          isValid = false;
          errors.push("วันที่สิ้นสุดต้องมากกว่าวันที่เริ่มมีผล");
          effectiveTo.classList.add("error");
        } else {
          effectiveTo.classList.remove("error");
        }
      }

      if (!isValid) {
        e.preventDefault();
        alert("กรุณาตรวจสอบข้อมูล:\n\n" + errors.join("\n"));
      }
    });

    // เคลียร์ error เมื่อ user แก้ไข
    selectAll(".settings-form input").forEach(function (input) {
      input.addEventListener("input", function () {
        this.classList.remove("error");
      });
    });
  }

  function wireDeleteConfirmations() {
    // ยืนยันการลบพื้นที่
    selectAll('form[onsubmit*="ยืนยันการลบพื้นที่"]').forEach(function (form) {
      form.addEventListener("submit", function (e) {
        var confirmed = confirm("⚠️ คุณแน่ใจหรือไม่ว่าต้องการลบพื้นที่นี้?\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้");
        if (!confirmed) {
          e.preventDefault();
        }
      });
    });

    // ยืนยันการลบการจอง
    selectAll('form[onsubmit*="ยืนยันการลบการจอง"]').forEach(function (form) {
      form.addEventListener("submit", function (e) {
        var confirmed = confirm("⚠️ คุณแน่ใจหรือไม่ว่าต้องการลบการจองนี้?\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้");
        if (!confirmed) {
          e.preventDefault();
        }
      });
    });

    // ยืนยันการลบผู้ใช้
    selectAll('form[onsubmit*="ยืนยันการลบผู้ใช้"]').forEach(function (form) {
      form.addEventListener("submit", function (e) {
        var confirmed = confirm("⚠️ คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้นี้?\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้");
        if (!confirmed) {
          e.preventDefault();
        }
      });
    });
  }

  function wireStatusChangeConfirmations() {
    // ยืนยันการเปลี่ยนสถานะมัดจำ
    selectAll('select[name="deposit_status"]').forEach(function (select) {
      var originalValue = select.value;
      select.addEventListener("change", function (e) {
        var newStatus = this.value;
        var statusLabels = {
          pending: "รอดำเนินการ",
          approved: "อนุมัติแล้ว",
          rejected: "ปฏิเสธ"
        };
        var confirmed = confirm(
          "ยืนยันการเปลี่ยนสถานะเป็น \"" + (statusLabels[newStatus] || newStatus) + "\" หรือไม่?"
        );
        if (!confirmed) {
          this.value = originalValue;
          e.preventDefault();
        } else {
          originalValue = newStatus;
        }
      });
    });
  }

  function addLoadingStates() {
    // เพิ่ม loading state ให้ปุ่ม submit
    selectAll("form").forEach(function (form) {
      form.addEventListener("submit", function () {
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn && !submitBtn.disabled) {
          submitBtn.disabled = true;
          var originalText = submitBtn.textContent;
          submitBtn.textContent = "กำลังดำเนินการ...";
          setTimeout(function () {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
          }, 3000);
        }
      });
    });
  }

  function init() {
    wireTabs();
    wireFormValidation();
    wireDeleteConfirmations();
    wireStatusChangeConfirmations();
    addLoadingStates();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
