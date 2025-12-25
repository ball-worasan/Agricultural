(function () {
  "use strict";

  // ================================
  // State & Config
  // ================================
  const AREA_ID = window.PROPERTY_BOOKINGS?.areaId || 0;
  const ACTIVE_REQUESTS = new Set();

  // ================================
  // Modal Functions
  // ================================
  window.openSlipModal = function (imageUrl, userName) {
    const modal = document.getElementById("slipModal");
    const img = document.getElementById("slipModalImage");
    const title = document.getElementById("slipModalTitle");

    if (modal && img && title) {
      img.src = imageUrl;
      title.textContent = `สลิปการโอนของ ${userName || "ผู้ใช้"}`;
      modal.classList.add("active");
      document.body.style.overflow = "hidden";
    }
  };

  window.closeSlipModal = function (event) {
    const modal = document.getElementById("slipModal");
    if (
      modal &&
      (!event ||
        event.target.id === "slipModal" ||
        event.currentTarget?.classList.contains("modal-close"))
    ) {
      modal.classList.remove("active");
      document.body.style.overflow = "";
    }
  };

  // ================================
  // Action Handler (Approve/Reject)
  // ================================
  async function handleBookingAction(bookingId, action) {
    if (!bookingId || isNaN(bookingId)) {
      alert("❌ ข้อมูลการจองไม่ถูกต้อง");
      return;
    }

    const confirmMsg =
      action === "approve"
        ? "✓ คุณต้องการอนุมัติการจองนี้หรือไม่?\n\nจะมีการปฏิเสธการจองอื่น ๆ สำหรับพื้นที่นี้"
        : "⚠️ คุณต้องการปฏิเสธการจองนี้หรือไม่?";

    if (!confirm(confirmMsg)) {
      return;
    }

    const requestKey = `${action}-${bookingId}`;
    if (ACTIVE_REQUESTS.has(requestKey)) {
      alert("❌ คำขอกำลังดำเนินการ กรุณารอสักครู่");
      return;
    }

    const btn = document.querySelector(
      `[data-booking-id="${bookingId}"][data-action="${action}"]`
    );
    if (btn) {
      btn.disabled = true;
      btn.classList.add("disabled");
    }

    ACTIVE_REQUESTS.add(requestKey);

    try {
      const formData = new FormData();
      formData.append("action", action);
      formData.append("booking_id", String(bookingId));

      const response = await fetch(`?page=property_bookings&id=${AREA_ID}`, {
        method: "POST",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
        body: formData,
      });

      let data;
      const text = await response.text();
      try {
        data = JSON.parse(text);
      } catch {
        data = { success: false, message: text || "ไม่สามารถประมวลผลคำตอบได้" };
      }

      if (response.ok && data?.success) {
        alert("✅ " + (data.message || "ดำเนินการสำเร็จ"));
        window.location.reload();
        return;
      }

      const msg = data?.message || `คำขอล้มเหลว (${response.status})`;
      alert("❌ " + msg);
    } catch (err) {
      console.error("Error:", err);
      alert("❌ เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง");
    } finally {
      ACTIVE_REQUESTS.delete(requestKey);
      if (btn) {
        btn.disabled = false;
        btn.classList.remove("disabled");
      }
    }
  }

  // ================================
  // Event Delegation for Action Buttons
  // ================================
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".btn-action");
    if (!btn) return;

    e.preventDefault();
    const bookingId = parseInt(btn.dataset.bookingId, 10);
    const action = btn.classList.contains("approve") ? "approve" : "reject";
    handleBookingAction(bookingId, action);
  });

  // ================================
  // Handle Avatar Loading Error
  // ================================
  document.querySelectorAll(".user-avatar img").forEach(function (img) {
    img.addEventListener("error", function () {
      const alt = this.getAttribute("alt") || "User";
      this.src =
        "https://ui-avatars.com/api/?name=" +
        encodeURIComponent(alt) +
        "&size=60&background=667eea&color=fff&bold=true";
    });
  });

  // ================================
  // Close Modal on Escape Key
  // ================================
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      window.closeSlipModal();
    }
  });
})();
