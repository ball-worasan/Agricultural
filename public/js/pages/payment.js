/**
 * Payment Page JavaScript
 * Handles payment confirmation, slip upload, and countdown timer
 */

(() => {
  "use strict";

  // Get data from window (set by PHP)
  const AREA_ID = window.PAYMENT_DATA?.areaId || 0;
  const BOOKING_DATE = window.PAYMENT_DATA?.bookingDate || "";
  const CSRF_TOKEN = window.PAYMENT_DATA?.csrfToken || "";

  // ================================
  // Confirm Payment
  // ================================
  async function confirmPayment() {
    const slipInput = document.getElementById("slipFile");
    if (!slipInput?.files || slipInput.files.length === 0) {
      alert("กรุณาอัปโหลดสลิปการโอนก่อนยืนยัน");
      return;
    }

    if (!confirm("ยืนยันว่าคุณได้ชำระเงินและอัปโหลดสลิปเรียบร้อยแล้ว?")) return;

    try {
      const formData = new FormData();
      formData.append("update_payment", "1");
      formData.append("csrf", CSRF_TOKEN);
      formData.append("area_id", String(AREA_ID));
      formData.append("booking_date", BOOKING_DATE);
      formData.append("slip_file", slipInput.files[0]);

      const res = await fetch(window.location.href, {
        method: "POST",
        body: formData,
      });

      let data = null;
      try {
        data = await res.json();
      } catch (e) {
        console.error("JSON parse error:", e);
      }

      if (data?.success) {
        alert(
          "การจองเสร็จสมบูรณ์!\nระบบจะตรวจสอบสลิปและอนุมัติภายใน 5-10 นาที"
        );
        window.location.href = "?page=history";
        return;
      }

      if (data?.message) {
        alert("ℹ️ " + data.message);
        return;
      }

      if (!res.ok) {
        alert("เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง");
        return;
      }

      alert("บันทึกแล้ว (ระบบจะตรวจสอบสลิป)");
      window.location.href = "?page=history";
    } catch (err) {
      console.error("confirmPayment error:", err);
      alert("เกิดข้อผิดพลาดในการส่งข้อมูล กรุณาลองใหม่อีกครั้ง");
    }
  }

  // ================================
  // Cancel Payment
  // ================================
  function cancelPayment() {
    if (confirm("คุณต้องการยกเลิกการจองใช่หรือไม่?")) {
      window.location.href = "?page=history";
    }
  }

  // Export to global scope for onclick handlers (if needed)
  window.confirmPayment = confirmPayment;
  window.cancelPayment = cancelPayment;

  // ================================
  // Countdown Timer
  // ================================
  (function initTimer() {
    let timeLeft = 60 * 60; // 60 minutes
    const timeRemainingEl = document.getElementById("timeRemaining");
    const timeRemainingTextEl = document.getElementById("timeRemainingText");

    if (!timeRemainingEl) return;

    const countdown = setInterval(() => {
      timeLeft--;

      const minutes = Math.floor(timeLeft / 60);
      const seconds = timeLeft % 60;
      const mmss = `${String(minutes).padStart(2, "0")}:${String(
        seconds
      ).padStart(2, "0")}`;

      timeRemainingEl.textContent = mmss;
      if (timeRemainingTextEl) {
        timeRemainingTextEl.textContent = `${minutes} นาที`;
      }

      if (timeLeft <= 0) {
        clearInterval(countdown);
        alert("หมดเวลาชำระเงิน การจองอาจถูกยกเลิกโดยอัตโนมัติ");
        window.location.href = "?page=history";
      } else if (timeLeft <= 60) {
        timeRemainingEl.style.color = "var(--status-sold-text)";
      } else if (timeLeft <= 300) {
        timeRemainingEl.style.color = "var(--status-booked-text)";
      }
    }, 1000);
  })();

  // ================================
  // Slip Preview
  // ================================
  const slipFileInput = document.getElementById("slipFile");
  const slipPreview = document.getElementById("slipPreview");

  if (slipFileInput && slipPreview) {
    slipFileInput.addEventListener("change", function () {
      slipPreview.innerHTML = "";

      if (this.files && this.files[0]) {
        const file = this.files[0];

        if (file.size > 5 * 1024 * 1024) {
          alert("ไฟล์มีขนาดเกิน 5MB");
          this.value = "";
          slipPreview.hidden = true;
          return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
          const img = document.createElement("img");
          img.src = e.target.result;
          img.style.maxWidth = "200px";
          img.style.borderRadius = "6px";
          slipPreview.appendChild(img);
        };
        reader.readAsDataURL(file);
        slipPreview.hidden = false;
      } else {
        slipPreview.hidden = true;
      }
    });
  }
})();
