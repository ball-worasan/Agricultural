(function () {
  "use strict";

  function $all(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }
  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  // ---------- Tabs ----------
  function activateTab(name) {
    $all(".tab-content").forEach(function (el) {
      el.classList.toggle("active", el.id === "tab-" + name);
    });
    $all(".admin-tabs .tab-btn").forEach(function (btn) {
      btn.classList.toggle("active", btn.dataset.tab === name);
    });
  }

  function wireTabs() {
    $all(".admin-tabs .tab-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        activateTab(btn.dataset.tab || "properties");
      });
    });
  }

  // ---------- Confirm forms ----------
  function wireConfirmForms() {
    $all("form.confirm-form[data-confirm]").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        var msg = form.getAttribute("data-confirm") || "ยืนยันการทำรายการ?";
        if (!confirm("⚠️ " + msg + "\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้")) {
          e.preventDefault();
        }
      });
    });
  }

  // ---------- Auto submit select ----------
  function wireAutoSubmitSelects() {
    // สถานะพื้นที่: เปลี่ยนแล้ว submit เลย (ถ้าจะให้ confirm ก็เติมเอง)
    $all("select.js-auto-submit-select").forEach(function (sel) {
      sel.addEventListener("change", function () {
        var form = sel.closest("form");
        if (form) form.submit();
      });
    });

    // สถานะมัดจำ: ต้อง confirm
    var labels = {
      pending: "รอดำเนินการ",
      approved: "อนุมัติแล้ว",
      rejected: "ปฏิเสธ",
    };
    $all("select.js-deposit-status").forEach(function (sel) {
      var original = sel.value;
      sel.addEventListener("change", function (e) {
        var next = sel.value;
        var ok = confirm(
          'ยืนยันการเปลี่ยนสถานะเป็น "' + (labels[next] || next) + '" หรือไม่?'
        );
        if (!ok) {
          sel.value = original;
          e.preventDefault();
          return;
        }
        original = next;
        var form = sel.closest("form");
        if (form) form.submit();
      });
    });
  }

  // ---------- Fee form validation ----------
  function wireFeeValidation() {
    var form = $(".settings-form[data-validate='fee']");
    if (!form) return;

    form.addEventListener("submit", function (e) {
      var errors = [];

      var feeRate = form.querySelector('input[name="fee_rate"]');
      var accountNumber = form.querySelector('input[name="account_number"]');
      var accountName = form.querySelector('input[name="account_name"]');
      var bankName = form.querySelector('input[name="bank_name"]');

      function mark(el, ok) {
        if (!el) return;
        el.classList.toggle("error", !ok);
      }

      var rate = feeRate ? parseFloat(feeRate.value) : NaN;
      if (isNaN(rate) || rate < 0 || rate > 100) {
        errors.push("ค่าธรรมเนียมต้องอยู่ระหว่าง 0-100%");
        mark(feeRate, false);
      } else mark(feeRate, true);

      if (!accountNumber || accountNumber.value.trim() === "") {
        errors.push("กรุณาระบุเลขบัญชีหรือพร้อมเพย์");
        mark(accountNumber, false);
      } else mark(accountNumber, true);

      if (!accountName || accountName.value.trim() === "") {
        errors.push("กรุณาระบุชื่อบัญชี");
        mark(accountName, false);
      } else mark(accountName, true);

      if (!bankName || bankName.value.trim() === "") {
        errors.push("กรุณาระบุชื่อธนาคาร");
        mark(bankName, false);
      } else mark(bankName, true);

      if (errors.length) {
        e.preventDefault();
        alert("กรุณาตรวจสอบข้อมูล:\n\n- " + errors.join("\n- "));
      }
    });

    $all(".settings-form input", form).forEach(function (input) {
      input.addEventListener("input", function () {
        input.classList.remove("error");
      });
    });
  }

  // ---------- Slip Modal ----------
  function wireSlipModal() {
    var modal = $("#slipModal");
    if (!modal) return;

    var img = $("#slipImage");
    var bookingIdSpan = $("#slipBookingId");
    var paymentIdSpan = $("#slipPaymentId");
    var paymentIdRow = $("#slipPaymentIdRow");

    function open(url, bookingId, paymentId) {
      if (img) img.src = url || "";
      if (bookingIdSpan) bookingIdSpan.textContent = bookingId || "";
      
      if (paymentId && paymentIdSpan && paymentIdRow) {
        paymentIdSpan.textContent = paymentId;
        paymentIdRow.style.display = "block";
      } else if (paymentIdRow) {
        paymentIdRow.style.display = "none";
      }
      
      modal.classList.add("show");
      modal.setAttribute("aria-hidden", "false");
    }

    function close() {
      modal.classList.remove("show");
      modal.setAttribute("aria-hidden", "true");
      if (img) img.src = "";
    }

    $all(".js-view-slip").forEach(function (btn) {
      btn.addEventListener("click", function () {
        open(
          btn.dataset.slipUrl || "",
          btn.dataset.bookingId || "",
          btn.dataset.paymentId || ""
        );
      });
    });

    $all(".js-close-slip").forEach(function (btn) {
      btn.addEventListener("click", close);
    });

    window.addEventListener("click", function (event) {
      if (event.target === modal) close();
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") close();
    });
  }

  // ---------- User Detail Modal ----------
  function wireUserDetailModal() {
    var modal = $("#userDetailModal");
    if (!modal) return;

    function open(data) {
      $("#userDetailId").textContent = data.userId || "";
      $("#userDetailName").textContent = data.userName || "";
      $("#userDetailUsername").textContent = data.username || "ไม่ระบุ";
      $("#userDetailPhone").textContent = data.phone || "ไม่ระบุ";
      $("#userDetailEmail").textContent = data.email || "ไม่ระบุ";
      $("#userDetailAddress").textContent = data.address || "ไม่ระบุ";
      $("#userDetailRole").textContent = data.role || "";
      $("#userDetailCreated").textContent = data.createdAt || "";
      $("#userDetailAccountNumber").textContent = data.accountNumber || "ไม่ได้ระบุ";
      $("#userDetailBankName").textContent = data.bankName || "ไม่ได้ระบุ";
      $("#userDetailAccountName").textContent = data.accountName || "ไม่ได้ระบุ";

      modal.classList.add("show");
      modal.setAttribute("aria-hidden", "false");
    }

    function close() {
      modal.classList.remove("show");
      modal.setAttribute("aria-hidden", "true");
    }

    $all(".js-view-user-detail").forEach(function (btn) {
      btn.addEventListener("click", function () {
        open({
          userId: btn.dataset.userId,
          userName: btn.dataset.userName,
          username: btn.dataset.username,
          phone: btn.dataset.phone,
          email: btn.dataset.email,
          address: btn.dataset.address,
          role: btn.dataset.role,
          createdAt: btn.dataset.createdAt,
          accountNumber: btn.dataset.accountNumber,
          bankName: btn.dataset.bankName,
          accountName: btn.dataset.accountName,
        });
      });
    });

    $all(".js-close-user-detail").forEach(function (btn) {
      btn.addEventListener("click", close);
    });

    window.addEventListener("click", function (event) {
      if (event.target === modal) close();
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") close();
    });
  }

  function init() {
    wireTabs();
    wireConfirmForms();
    wireAutoSubmitSelects();
    wireFeeValidation();
    wireSlipModal();
    wireUserDetailModal();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
