(() => {
  "use strict";

  const data = window.PAYMENT_DATA || {};
  const AREA_ID = Number(data.areaId || 0);
  const BOOKING_DATE = String(data.bookingDate || "");
  const EXPIRES_AT = String(data.expiresAt || ""); // ISO string (DATE_ATOM)

  const els = {
    confirmBtn: null,
    cancelBtn: null,
    slipInput: null,
    slipPreview: null,
    timeRemaining: null,
    timeRemainingText: null,
  };

  function cacheElements() {
    els.confirmBtn = document.querySelector(".btn-confirm-payment");
    els.cancelBtn = document.querySelector(".btn-cancel-payment");
    els.slipInput = document.getElementById("slipFile");
    els.slipPreview = document.getElementById("slipPreview");
    els.timeRemaining = document.getElementById("timeRemaining");
    els.timeRemainingText = document.getElementById("timeRemainingText");
  }

  function setConfirmEnabled(enabled) {
    if (!els.confirmBtn) return;
    els.confirmBtn.disabled = !enabled;
    els.confirmBtn.title = enabled
      ? "ยืนยันการชำระเงิน"
      : "⚠️ อัปโหลดสลิปการโอนให้เรียบร้อยก่อน";
  }

  // ================================
  // Slip Preview + validate
  // ================================
  function initSlipPreview() {
    if (!els.slipInput || !els.slipPreview) return;

    els.slipInput.addEventListener("change", function () {
      els.slipPreview.innerHTML = "";

      if (!this.files || !this.files[0]) {
        els.slipPreview.hidden = true;
        setConfirmEnabled(false);
        return;
      }

      const file = this.files[0];

      if (file.size > 20 * 1024 * 1024) {
        alert("❌ ไฟล์มีขนาดเกิน 20MB");
        this.value = "";
        els.slipPreview.hidden = true;
        setConfirmEnabled(false);
        return;
      }

      const allowedMimes = [
        "image/jpeg",
        "image/png",
        "image/gif",
        "image/webp",
      ];
      if (!allowedMimes.includes(file.type)) {
        alert("❌ รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WebP)");
        this.value = "";
        els.slipPreview.hidden = true;
        setConfirmEnabled(false);
        return;
      }

      const reader = new FileReader();
      reader.onload = (e) => {
        const img = document.createElement("img");
        img.src = String(e.target?.result || "");
        img.style.maxWidth = "220px";
        img.style.borderRadius = "8px";
        img.style.border = "2px solid var(--secondary-color)";
        img.alt = "Slip preview";

        const badge = document.createElement("div");
        badge.style.marginTop = "0.5rem";
        badge.style.padding = "0.5rem 0.6rem";
        badge.style.background = "var(--surface-muted)";
        badge.style.border = "1px solid var(--border-color)";
        badge.style.borderRadius = "8px";
        badge.style.fontSize = "0.85rem";
        badge.style.fontWeight = "600";
        badge.textContent = "✓ สลิปพร้อมส่ง";

        els.slipPreview.appendChild(img);
        els.slipPreview.appendChild(badge);
        els.slipPreview.hidden = false;

        setConfirmEnabled(true);
      };
      reader.readAsDataURL(file);
    });
  }

  // ================================
  // Countdown Timer (based on expiresAt from server)
  // ================================
  function initTimer() {
    if (!els.timeRemaining || !EXPIRES_AT) return;

    const expiresAtMs = Date.parse(EXPIRES_AT);
    if (Number.isNaN(expiresAtMs)) {
      els.timeRemaining.textContent = "--:--";
      return;
    }

    const tick = () => {
      const now = Date.now();
      let diffSec = Math.floor((expiresAtMs - now) / 1000);

      if (diffSec <= 0) {
        els.timeRemaining.textContent = "00:00";
        if (els.timeRemainingText) els.timeRemainingText.textContent = "0 นาที";
        alert("หมดเวลาชำระเงิน กรุณาทำรายการใหม่");
        window.location.href = "?page=history";
        return;
      }

      const minutes = Math.floor(diffSec / 60);
      const seconds = diffSec % 60;
      const mmss = `${String(minutes).padStart(2, "0")}:${String(
        seconds
      ).padStart(2, "0")}`;
      els.timeRemaining.textContent = mmss;
      if (els.timeRemainingText)
        els.timeRemainingText.textContent = `${minutes} นาที`;

      // สีตามความเสี่ยง
      if (diffSec <= 60) {
        els.timeRemaining.style.color = "var(--status-sold-text)";
      } else if (diffSec <= 300) {
        els.timeRemaining.style.color = "var(--status-booked-text)";
      } else {
        els.timeRemaining.style.color = "";
      }

      setTimeout(tick, 1000);
    };

    tick();
  }

  // ================================
  // Confirm Payment (AJAX)
  // ================================
  async function confirmPayment() {
    if (!AREA_ID || !BOOKING_DATE) {
      alert("❌ ข้อมูลหน้าไม่ครบ (AREA_ID / BOOKING_DATE) กรุณารีเฟรชหน้า");
      return;
    }

    if (
      !els.slipInput?.files ||
      els.slipInput.files.length === 0 ||
      els.slipPreview?.hidden
    ) {
      alert("⚠️ กรุณาอัปโหลดสลิปการโอนและตรวจสอบรูปภาพก่อนยืนยัน");
      els.slipInput?.focus();
      return;
    }

    const file = els.slipInput.files[0];
    if (file.size > 5 * 1024 * 1024) {
      alert("❌ ไฟล์มีขนาดเกิน 5MB กรุณาเลือกไฟล์อื่น");
      els.slipInput.value = "";
      els.slipPreview.hidden = true;
      setConfirmEnabled(false);
      return;
    }

    const allowedMimes = ["image/jpeg", "image/png", "image/gif", "image/webp"];
    if (!allowedMimes.includes(file.type)) {
      alert("❌ รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WebP)");
      els.slipInput.value = "";
      els.slipPreview.hidden = true;โ
      setConfirmEnabled(false);
      return;
    }

    if (!confirm("✓ ยืนยันว่าคุณได้ชำระเงินและอัปโหลดสลิปเรียบร้อยแล้ว?"))
      return;

    // กันกดรัว
    if (els.confirmBtn) {
      els.confirmBtn.disabled = true;
      els.confirmBtn.textContent = "⏳ กำลังส่ง...";
    }

    try {
      const formData = new FormData();
      formData.append("update_payment", "1");
      formData.append("area_id", String(AREA_ID));
      formData.append("booking_date", BOOKING_DATE);
      formData.append("slip_file", file);

      console.log("Sending payment confirmation...", {
        areaId: AREA_ID,
        bookingDate: BOOKING_DATE,
        fileName: file.name,
        fileSize: file.size,
        fileType: file.type,
      });

      const res = await fetch(window.location.href, {
        method: "POST",
        body: formData,
      });

      let payload = null;
      const contentType = res.headers.get("content-type");
      const responseText = await res.text();

      console.log("Response status:", res.status);
      console.log("Response content-type:", contentType);
      console.log("Response text:", responseText);

      try {
        payload = JSON.parse(responseText);
      } catch (e) {
        console.error("Failed to parse JSON:", e);
      }

      if (!res.ok) {
        const errorMsg = payload?.message || "เกิดข้อผิดพลาด กรุณาลองใหม่";
        alert("❌ " + errorMsg);
        console.error("Server error response:", {
          status: res.status,
          payload,
        });
        if (els.confirmBtn) {
          els.confirmBtn.disabled = false;
          els.confirmBtn.textContent = "ยืนยันการชำระเงิน";
        }
        return;
      }

      if (payload?.success) {
        alert("✅ ส่งสลิปเรียบร้อยแล้ว\nระบบจะตรวจสอบและแจ้งผลภายหลัง");
        window.location.href = "?page=history";
        return;
      }

      alert("⚠️ " + (payload?.message || "ทำรายการไม่สำเร็จ"));
      if (els.confirmBtn) {
        els.confirmBtn.disabled = false;
        els.confirmBtn.textContent = "ยืนยันการชำระเงิน";
      }
    } catch (err) {
      console.error("confirmPayment error:", err);
      alert(
        "❌ ส่งข้อมูลไม่สำเร็จ กรุณาลองใหม่อีกครั้ง\n\n" +
          String(err.message || err)
      );
      if (els.confirmBtn) {
        els.confirmBtn.disabled = false;
        els.confirmBtn.textContent = "ยืนยันการชำระเงิน";
      }
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

  function bindEvents() {
    els.confirmBtn?.addEventListener("click", confirmPayment);
    els.cancelBtn?.addEventListener("click", cancelPayment);
  }

  function init() {
    cacheElements();
    bindEvents();
    initSlipPreview();
    initTimer();
    setConfirmEnabled(false);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
