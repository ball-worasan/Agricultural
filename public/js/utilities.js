/**
 * Utility Functions for Sirinat Agricultural Land Rental
 * ฟังก์ชันเสริมสำหรับระบบเช่าพื้นที่เกษตร
 */

// ============================================
// TOAST NOTIFICATIONS
// ============================================

/**
 * แสดง Toast Notification
 * @param {string} title - หัวข้อ
 * @param {string} message - ข้อความ
 * @param {string} type - ประเภท: 'success', 'error', 'warning'
 * @param {number} duration - ระยะเวลาแสดง (ms)
 */
function showToast(title, message, type = "success", duration = 3000) {
  // ลบ toast เก่าถ้ามี
  const existingToast = document.querySelector(".toast");
  if (existingToast) {
    existingToast.remove();
  }

  // สร้าง toast ใหม่
  const toast = document.createElement("div");
  toast.className = `toast toast-${type}`;

  // ไอคอนตามประเภท
  const icons = {
    success: "✅",
    error: "❌",
    warning: "⚠️",
  };

  toast.innerHTML = `
    <div class="toast-icon">${icons[type] || "📢"}</div>
    <div class="toast-content">
      <div class="toast-title">${title}</div>
      <div class="toast-message">${message}</div>
    </div>
  `;

  document.body.appendChild(toast);

  // ลบ toast หลังจากเวลาที่กำหนด
  setTimeout(() => {
    toast.style.animation = "toast-slide-in 0.3s ease reverse";
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// ============================================
// MODAL DIALOG
// ============================================

/**
 * แสดง Modal Dialog
 * @param {string} title - หัวข้อ
 * @param {string} content - เนื้อหา (HTML)
 * @param {object} options - ตัวเลือก
 */
function showModal(title, content, options = {}) {
  const {
    confirmText = "ยืนยัน",
    cancelText = "ยกเลิก",
    onConfirm = null,
    onCancel = null,
    showCancel = true,
  } = options;

  // ลบ modal เก่าถ้ามี
  const existingModal = document.querySelector(".modal-overlay");
  if (existingModal) {
    existingModal.remove();
  }

  // สร้าง modal overlay
  const overlay = document.createElement("div");
  overlay.className = "modal-overlay";

  // สร้าง modal
  const modal = document.createElement("div");
  modal.className = "modal";

  modal.innerHTML = `
    <div class="modal-header">
      <h3 class="modal-title">${title}</h3>
      <button class="modal-close" aria-label="ปิด">×</button>
    </div>
    <div class="modal-body">
      ${content}
    </div>
    <div class="modal-footer">
      ${
        showCancel
          ? `<button class="btn btn-cancel">${cancelText}</button>`
          : ""
      }
      <button class="btn btn-primary">${confirmText}</button>
    </div>
  `;

  overlay.appendChild(modal);
  document.body.appendChild(overlay);

  // Event handlers
  const closeBtn = modal.querySelector(".modal-close");
  const cancelBtn = modal.querySelector(".btn-cancel");
  const confirmBtn = modal.querySelector(".btn-primary");

  const closeModal = () => {
    overlay.style.animation = "modal-fade-in 0.2s ease reverse";
    modal.style.animation = "modal-scale-in 0.3s ease reverse";
    setTimeout(() => overlay.remove(), 200);
  };

  closeBtn.addEventListener("click", () => {
    if (onCancel) onCancel();
    closeModal();
  });

  if (cancelBtn) {
    cancelBtn.addEventListener("click", () => {
      if (onCancel) onCancel();
      closeModal();
    });
  }

  confirmBtn.addEventListener("click", () => {
    if (onConfirm) onConfirm();
    closeModal();
  });

  // ปิดเมื่อคลิกนอก modal
  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) {
      if (onCancel) onCancel();
      closeModal();
    }
  });

  // ปิดด้วย ESC
  const escHandler = (e) => {
    if (e.key === "Escape") {
      if (onCancel) onCancel();
      closeModal();
      document.removeEventListener("keydown", escHandler);
    }
  };
  document.addEventListener("keydown", escHandler);
}

// ============================================
// LAZY LOADING IMAGES
// ============================================

/**
 * Lazy load รูปภาพเมื่อเลื่อนเข้ามาใกล้
 */
function initLazyLoading() {
  const images = document.querySelectorAll("img[data-src]");

  if ("IntersectionObserver" in window) {
    const imageObserver = new IntersectionObserver(
      (entries, observer) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const img = entry.target;
            img.src = img.dataset.src;
            img.removeAttribute("data-src");
            img.classList.add("loaded");
            observer.unobserve(img);
          }
        });
      },
      {
        rootMargin: "50px 0px",
        threshold: 0.01,
      }
    );

    images.forEach((img) => imageObserver.observe(img));
  } else {
    // Fallback สำหรับ browser เก่า
    images.forEach((img) => {
      img.src = img.dataset.src;
      img.removeAttribute("data-src");
    });
  }
}

// ============================================
// SKELETON LOADING
// ============================================

/**
 * แสดง skeleton loading แทนเนื้อหา
 * @param {string} containerId - ID ของ container
 * @param {number} count - จำนวน skeleton items
 */
function showSkeleton(containerId, count = 3) {
  const container = document.getElementById(containerId);
  if (!container) return;

  let skeletonHTML = "";
  for (let i = 0; i < count; i++) {
    skeletonHTML += `
      <div class="item-card skeleton" style="height: 150px; margin-bottom: 1rem;">
        <div style="width: 140px; height: 100%; background: var(--skeleton-bg);"></div>
        <div style="flex: 1; padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
          <div class="skeleton" style="width: 70%; height: 1.2rem;"></div>
          <div class="skeleton" style="width: 50%; height: 0.9rem;"></div>
          <div style="margin-top: auto; display: flex; justify-content: space-between;">
            <div class="skeleton" style="width: 30%; height: 0.8rem;"></div>
            <div class="skeleton" style="width: 25%; height: 1rem;"></div>
          </div>
        </div>
      </div>
    `;
  }

  container.innerHTML = skeletonHTML;
}

/**
 * ซ่อน skeleton loading และแสดงเนื้อหาจริง
 * @param {string} containerId - ID ของ container
 * @param {string} content - HTML content ที่จะแสดง
 */
function hideSkeleton(containerId, content) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = content;
}

// ============================================
// EMPTY STATE
// ============================================

/**
 * แสดง empty state เมื่อไม่มีข้อมูล
 * @param {string} containerId - ID ของ container
 * @param {string} icon - อีโมจิหรือไอคอน
 * @param {string} title - หัวข้อ
 * @param {string} description - คำอธิบาย
 */
function showEmptyState(
  containerId,
  icon = "📭",
  title = "ไม่พบข้อมูล",
  description = "ลองค้นหาด้วยเงื่อนไขอื่น"
) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = `
    <div class="empty-state">
      <div class="empty-state-icon">${icon}</div>
      <div class="empty-state-title">${title}</div>
      <div class="empty-state-desc">${description}</div>
    </div>
  `;
}

// ============================================
// CONFIRM DIALOG (ใช้แทน confirm() ธรรมดา)
// ============================================

/**
 * แสดง confirm dialog แบบสวยงาม
 * @param {string} title - หัวข้อ
 * @param {string} message - ข้อความ
 * @param {function} onConfirm - callback เมื่อยืนยัน
 */
function confirmDialog(title, message, onConfirm) {
  showModal(title, `<p style="line-height: 1.6;">${message}</p>`, {
    confirmText: "ยืนยัน",
    cancelText: "ยกเลิก",
    onConfirm: onConfirm,
    showCancel: true,
  });
}

// ============================================
// AUTO INIT
// ============================================

// เรียกใช้ lazy loading เมื่อหน้าเพจโหลดเสร็จ
document.addEventListener("DOMContentLoaded", () => {
  initLazyLoading();

  // เพิ่ม loading class ให้กับรูปที่ยังไม่โหลด
  document.querySelectorAll("img[data-src]").forEach((img) => {
    img.style.backgroundColor = "var(--skeleton-bg)";
    img.addEventListener("load", () => {
      img.style.backgroundColor = "transparent";
    });
  });
});

// ============================================
// EXPORT FUNCTIONS (สำหรับใช้ใน HTML)
// ============================================
window.showToast = showToast;
window.showModal = showModal;
window.confirmDialog = confirmDialog;
window.showSkeleton = showSkeleton;
window.hideSkeleton = hideSkeleton;
window.showEmptyState = showEmptyState;
window.initLazyLoading = initLazyLoading;
