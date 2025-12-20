(function () {
  "use strict";

  const App = (window.App = window.App || {});

  // ============================================
  // ค่าคงที่ (แชร์ร่วมใช้)
  // ============================================
  App.constants = {
    FLASH_COOLDOWN_SECONDS: 5,
    FLASH_AUTO_CLOSE_MS: 10000,
    MAX_IMAGE_SIZE: 5 * 1024 * 1024, // สูงสุด 5MB
    MAX_IMAGES: 10,
  };

  // ============================================
  // ฟังก์ชันยูทิล
  // ============================================
  App.escapeHtml = function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = String(text ?? "");
    return div.innerHTML;
  };

  App.showToast = function showToast(
    title,
    message,
    type = "success",
    duration = 3000
  ) {
    const existingToast = document.querySelector(".toast");
    if (existingToast) existingToast.remove();

    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;

    const icons = { success: "✅", error: "❌", warning: "⚠️" };

    toast.innerHTML = `
      <div class="toast-icon">${icons[type] || "📢"}</div>
      <div class="toast-content">
        <div class="toast-title">${App.escapeHtml(title)}</div>
        <div class="toast-message">${App.escapeHtml(message)}</div>
      </div>
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.animation = "toast-slide-in 0.3s ease reverse";
      setTimeout(() => toast.remove(), 300);
    }, duration);
  };

  App.showModal = function showModal(title, content, options = {}) {
    const {
      confirmText = "ยืนยัน",
      cancelText = "ยกเลิก",
      onConfirm = null,
      onCancel = null,
      showCancel = true,
    } = options;

    const existingModal = document.querySelector(".modal-overlay");
    if (existingModal) existingModal.remove();

    const overlay = document.createElement("div");
    overlay.className = "modal-overlay";

    const modal = document.createElement("div");
    modal.className = "modal";

    modal.innerHTML = `
      <div class="modal-header">
        <h3 class="modal-title">${App.escapeHtml(title)}</h3>
        <button class="modal-close" aria-label="ปิด">×</button>
      </div>
      <div class="modal-body">${content}</div>
      <div class="modal-footer">
        ${
          showCancel
            ? `<button class="btn btn-cancel">${App.escapeHtml(
                cancelText
              )}</button>`
            : ""
        }
        <button class="btn btn-primary">${App.escapeHtml(confirmText)}</button>
      </div>
    `;

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    const closeBtn = modal.querySelector(".modal-close");
    const cancelBtn = modal.querySelector(".btn-cancel");
    const confirmBtn = modal.querySelector(".btn-primary");

    const closeModal = () => {
      overlay.style.animation = "modal-fade-in 0.2s ease reverse";
      modal.style.animation = "modal-scale-in 0.3s ease reverse";
      setTimeout(() => overlay.remove(), 200);
    };

    closeBtn.addEventListener("click", () => {
      onCancel && onCancel();
      closeModal();
    });

    if (cancelBtn) {
      cancelBtn.addEventListener("click", () => {
        onCancel && onCancel();
        closeModal();
      });
    }

    confirmBtn.addEventListener("click", () => {
      onConfirm && onConfirm();
      closeModal();
    });

    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) {
        onCancel && onCancel();
        closeModal();
      }
    });

    const escHandler = (e) => {
      if (e.key === "Escape") {
        onCancel && onCancel();
        closeModal();
        document.removeEventListener("keydown", escHandler);
      }
    };
    document.addEventListener("keydown", escHandler);
  };

  App.confirmDialog = function confirmDialog(title, message, onConfirm) {
    App.showModal(
      title,
      `<p style="line-height: 1.6;">${App.escapeHtml(message)}</p>`,
      {
        confirmText: "ยืนยัน",
        cancelText: "ยกเลิก",
        onConfirm,
        showCancel: true,
      }
    );
  };

  App.initLazyLoading = function initLazyLoading() {
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
        { rootMargin: "50px 0px", threshold: 0.01 }
      );

      images.forEach((img) => imageObserver.observe(img));
    } else {
      images.forEach((img) => {
        img.src = img.dataset.src;
        img.removeAttribute("data-src");
      });
    }
  };

  App.showSkeleton = function showSkeleton(containerId, count = 3) {
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
  };

  App.hideSkeleton = function hideSkeleton(containerId, content) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = content;
  };

  App.showEmptyState = function showEmptyState(
    containerId,
    icon = "📭",
    title = "ไม่พบข้อมูล",
    description = "ลองค้นหาด้วยเงื่อนไขอื่น"
  ) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon">${App.escapeHtml(icon)}</div>
        <div class="empty-state-title">${App.escapeHtml(title)}</div>
        <div class="empty-state-desc">${App.escapeHtml(description)}</div>
      </div>
    `;
  };
})();
