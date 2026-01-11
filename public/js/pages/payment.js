(() => {
  "use strict";

  const data = window.PAYMENT_DATA || {};
  const FLOW = String(data.flow || "deposit"); // 'deposit' | 'full'
  const AREA_ID = Number(data.areaId || 0);
  const BOOKING_DATE = data.bookingDate ? String(data.bookingDate) : "";
  const CONTRACT_ID = data.contractId ? Number(data.contractId) : 0;
  const EXPIRES_AT = String(data.expiresAt || "");

  const els = {
    confirmBtn: null,
    cancelBtn: null,
    slipInput: null,
    slipPreview: null,
    timeRemaining: null,
  };

  function cacheElements() {
    els.confirmBtn = document.querySelector(".btn-confirm");
    els.cancelBtn = document.querySelector(".btn-cancel");
    els.slipInput = document.getElementById("slipFile");
    els.slipPreview = document.getElementById("slipPreview");
    els.timeRemaining = document.getElementById("timeRemaining");
  }

  function setConfirmEnabled(enabled) {
    if (!els.confirmBtn) return;
    els.confirmBtn.disabled = !enabled;
    els.confirmBtn.title = enabled
      ? "ยืนยันการชำระเงิน"
      : "⚠️ อัปโหลดสลิปให้เรียบร้อยก่อน";
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

      // ✅ ให้ตรงกับ server: 5MB
      if (file.size > 5 * 1024 * 1024) {
        alert("❌ ไฟล์มีขนาดเกิน 5MB");
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
        img.alt = "Slip preview";

        const badge = document.createElement("div");
        badge.style.marginTop = "0.5rem";
        badge.style.padding = "0.5rem 0.6rem";
        badge.style.borderRadius = "8px";
        badge.style.fontSize = "0.85rem";
        badge.style.fontWeight = "600";
        badge.style.background = "var(--bg-gray)";
        badge.style.border = "1px solid var(--border-color)";
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
  // Countdown Timer
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
        alert("หมดเวลาชำระเงิน กรุณาทำรายการใหม่");
        window.location.href = "?page=history";
        return;
      }

      const minutes = Math.floor(diffSec / 60);
      const seconds = diffSec % 60;

      els.timeRemaining.textContent = `${String(minutes).padStart(
        2,
        "0"
      )}:${String(seconds).padStart(2, "0")}`;

      setTimeout(tick, 1000);
    };

    tick();
  }

  // ================================
  // Confirm Payment (AJAX)
  // ================================
  async function confirmPayment() {
    if (
      !els.slipInput?.files ||
      els.slipInput.files.length === 0 ||
      els.slipPreview?.hidden
    ) {
      alert("⚠️ กรุณาอัปโหลดสลิปก่อน");
      els.slipInput?.focus();
      return;
    }

    const file = els.slipInput.files[0];

    if (!confirm("✓ ยืนยันว่าคุณได้ชำระเงินและอัปโหลดสลิปเรียบร้อยแล้ว?"))
      return;

    const originalBtnHtml = els.confirmBtn?.innerHTML || "";
    if (els.confirmBtn) {
      els.confirmBtn.disabled = true;
      els.confirmBtn.innerHTML = "<span>⏳ กำลังส่ง...</span>";
    }

    try {
      const formData = new FormData();
      formData.append("update_payment", "1");
      formData.append("flow", FLOW);
      formData.append("slip_file", file);
      formData.append("_csrf", String(data.csrf || ""));

      if (FLOW === "deposit") {
        if (!AREA_ID || !BOOKING_DATE) {
          alert("❌ ข้อมูลหน้าไม่ครบ (AREA_ID / BOOKING_DATE) กรุณารีเฟรชหน้า");
          return;
        }
        formData.append("area_id", String(AREA_ID));
        formData.append("booking_date", BOOKING_DATE);
      } else {
        if (!CONTRACT_ID) {
          alert("❌ ข้อมูลหน้าไม่ครบ (CONTRACT_ID) กรุณารีเฟรชหน้า");
          return;
        }
        formData.append("contract_id", String(CONTRACT_ID));
      }

      const res = await fetch(window.location.href, {
        method: "POST",
        body: formData,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      const text = await res.text();
      let payload = null;
      try {
        payload = text ? JSON.parse(text) : null;
      } catch (_) {}

      if (!res.ok || (payload && payload.success !== true)) {
        alert("❌ " + (payload?.message || "เกิดข้อผิดพลาด กรุณาลองใหม่"));
        return;
      }

      alert(payload?.message || "✅ ส่งสลิปเรียบร้อยแล้ว");
      window.location.href = "?page=history";
    } catch (err) {
      alert("❌ ส่งข้อมูลไม่สำเร็จ: " + String(err?.message || err));
    } finally {
      if (els.confirmBtn) {
        els.confirmBtn.disabled = false;
        els.confirmBtn.innerHTML =
          originalBtnHtml || "<span>ยืนยันการชำระเงิน</span>";
      }
    }
  }

  function cancelPayment() {
    if (confirm("คุณต้องการยกเลิกใช่หรือไม่?")) {
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

  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", init);
  else init();
})();
