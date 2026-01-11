(function () {
  "use strict";

  const App = (window.App = window.App || {});

  // ============================================
  // Constants
  // ============================================
  App.constants = {
    FLASH_COOLDOWN_SECONDS: 5,
    FLASH_AUTO_CLOSE_MS: 8000,
    TOAST_DURATION: 5000,
    MAX_IMAGE_SIZE: 5 * 1024 * 1024, // 5MB
    MAX_IMAGES: 10,
    ANIMATION_DURATION: 300,
  };

  // ============================================
  // Utility Functions
  // ============================================
  
  /**
   * Escape HTML to prevent XSS
   */
  App.escapeHtml = function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = String(text ?? "");
    return div.innerHTML;
  };

  /**
   * Debounce function calls
   */
  App.debounce = function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  };

  /**
   * Throttle function calls
   */
  App.throttle = function throttle(func, limit = 300) {
    let inThrottle;
    return function (...args) {
      if (!inThrottle) {
        func.apply(this, args);
        inThrottle = true;
        setTimeout(() => (inThrottle = false), limit);
      }
    };
  };

  /**
   * Format currency
   */
  App.formatCurrency = function formatCurrency(amount) {
    return new Intl.NumberFormat("th-TH", {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(amount);
  };

  /**
   * Format date
   */
  App.formatDate = function formatDate(date, format = "short") {
    const d = new Date(date);
    if (isNaN(d.getTime())) return "";

    const options = {
      short: { year: "numeric", month: "short", day: "numeric" },
      long: { year: "numeric", month: "long", day: "numeric" },
      full: {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
      },
    };

    return new Intl.DateTimeFormat("th-TH", options[format] || options.short).format(d);
  };

  // ============================================
  // Toast Notifications
  // ============================================
  
  App.showToast = function showToast(
    title,
    message,
    type = "success",
    duration = App.constants.TOAST_DURATION
  ) {
    // Remove existing toast
    const existingToast = document.querySelector(".toast");
    if (existingToast) {
      existingToast.remove();
    }

    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    toast.setAttribute("role", "alert");
    toast.setAttribute("aria-live", "polite");

    const icons = {
      success: "✓",
      error: "✕",
      warning: "⚠",
      info: "ℹ",
    };

    const iconSpan = `<span class="toast-icon" aria-hidden="true">${icons[type] || "📢"}</span>`;
    const titleDiv = title ? `<div class="toast-title">${App.escapeHtml(title)}</div>` : "";
    const messageDiv = message ? `<div class="toast-message">${App.escapeHtml(message)}</div>` : "";

    toast.innerHTML = `
      ${iconSpan}
      <div class="toast-content">
        ${titleDiv}
        ${messageDiv}
      </div>
    `;

    document.body.appendChild(toast);

    // Auto remove
    if (duration > 0) {
      setTimeout(() => {
        toast.style.opacity = "0";
        toast.style.transform = "translateX(calc(100% + 2rem))";
        setTimeout(() => toast.remove(), App.constants.ANIMATION_DURATION);
      }, duration);
    }

    // Click to dismiss
    toast.addEventListener("click", () => {
      toast.style.opacity = "0";
      toast.style.transform = "translateX(calc(100% + 2rem))";
      setTimeout(() => toast.remove(), App.constants.ANIMATION_DURATION);
    });
  };

  // ============================================
  // Modal Dialog
  // ============================================
  
  App.showModal = function showModal(title, content, options = {}) {
    const {
      confirmText = "ยืนยัน",
      cancelText = "ยกเลิก",
      onConfirm = null,
      onCancel = null,
      showCancel = true,
      size = "md", // sm, md, lg
      closeOnOverlay = true,
      closeOnEscape = true,
    } = options;

    // Remove existing modal
    const existingModal = document.querySelector(".modal-overlay");
    if (existingModal) {
      existingModal.remove();
    }

    const overlay = document.createElement("div");
    overlay.className = "modal-overlay";
    overlay.setAttribute("role", "dialog");
    overlay.setAttribute("aria-modal", "true");
    overlay.setAttribute("aria-labelledby", "modal-title");

    const modal = document.createElement("div");
    modal.className = `modal modal-${size}`;

    const cancelButton = showCancel
      ? `<button class="btn btn-secondary" data-action="cancel">${App.escapeHtml(cancelText)}</button>`
      : "";

    modal.innerHTML = `
      <div class="modal-header">
        <h3 id="modal-title" class="modal-title">${App.escapeHtml(title)}</h3>
        <button class="modal-close" aria-label="ปิด" data-action="close">
          <span aria-hidden="true">×</span>
        </button>
      </div>
      <div class="modal-body">${content}</div>
      <div class="modal-footer">
        ${cancelButton}
        <button class="btn btn-primary" data-action="confirm">${App.escapeHtml(confirmText)}</button>
      </div>
    `;

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    // Prevent body scroll
    document.body.style.overflow = "hidden";

    const closeModal = (triggerCallback = true) => {
      document.body.style.overflow = "";
      overlay.style.opacity = "0";
      modal.style.transform = "scale(0.95)";
      
      setTimeout(() => {
        overlay.remove();
        if (triggerCallback && onCancel) {
          onCancel();
        }
      }, App.constants.ANIMATION_DURATION);
    };

    // Event delegation
    overlay.addEventListener("click", (e) => {
      const action = e.target.dataset.action || e.target.closest("[data-action]")?.dataset.action;

      if (action === "close" || action === "cancel") {
        closeModal(true);
      } else if (action === "confirm") {
        if (onConfirm) {
          onConfirm();
        }
        closeModal(false);
      } else if (closeOnOverlay && e.target === overlay) {
        closeModal(true);
      }
    });

    // ESC key handler
    if (closeOnEscape) {
      const escHandler = (e) => {
        if (e.key === "Escape") {
          closeModal(true);
          document.removeEventListener("keydown", escHandler);
        }
      };
      document.addEventListener("keydown", escHandler);
    }

    // Focus trap
    const focusableElements = modal.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (focusableElements.length > 0) {
      focusableElements[0].focus();
    }

    return {
      close: () => closeModal(false),
      element: modal,
    };
  };

  /**
   * Show confirmation dialog
   */
  App.confirmDialog = function confirmDialog(title, message, onConfirm, onCancel) {
    return App.showModal(
      title,
      `<p style="line-height: 1.6; color: var(--text-secondary);">${App.escapeHtml(message)}</p>`,
      {
        confirmText: "ยืนยัน",
        cancelText: "ยกเลิก",
        onConfirm,
        onCancel,
        showCancel: true,
        size: "sm",
      }
    );
  };

  /**
   * Show alert dialog
   */
  App.alertDialog = function alertDialog(title, message) {
    return App.showModal(
      title,
      `<p style="line-height: 1.6; color: var(--text-secondary);">${App.escapeHtml(message)}</p>`,
      {
        confirmText: "ตกลง",
        showCancel: false,
        size: "sm",
      }
    );
  };

  // ============================================
  // Lazy Loading Images
  // ============================================
  
  App.initLazyLoading = function initLazyLoading(selector = "img[data-src]") {
    const images = document.querySelectorAll(selector);

    if ("IntersectionObserver" in window) {
      const imageObserver = new IntersectionObserver(
        (entries, observer) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              const img = entry.target;
              
              // Add loading state
              img.style.backgroundColor = "var(--skeleton-bg)";
              
              // Load image
              if (img.dataset.src) {
                img.src = img.dataset.src;
                img.removeAttribute("data-src");
              }

              img.addEventListener("load", () => {
                img.style.backgroundColor = "transparent";
                img.classList.add("loaded");
              });

              img.addEventListener("error", () => {
                img.src = "/images/placeholder.jpg";
                img.style.backgroundColor = "transparent";
              });

              observer.unobserve(img);
            }
          });
        },
        {
          rootMargin: "100px 0px",
          threshold: 0.01,
        }
      );

      images.forEach((img) => imageObserver.observe(img));
    } else {
      // Fallback for browsers without IntersectionObserver
      images.forEach((img) => {
        if (img.dataset.src) {
          img.src = img.dataset.src;
          img.removeAttribute("data-src");
        }
      });
    }
  };

  // ============================================
  // Skeleton & Empty States
  // ============================================
  
  App.showSkeleton = function showSkeleton(containerId, count = 3, template = "card") {
    const container = document.getElementById(containerId);
    if (!container) return;

    const templates = {
      card: `
        <div class="skeleton-card" style="display: flex; gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: var(--bg-white); border-radius: var(--radius-md); border: 1px solid var(--border-light);">
          <div class="skeleton" style="width: 120px; height: 120px; border-radius: var(--radius-sm);"></div>
          <div style="flex: 1; display: flex; flex-direction: column; gap: 0.75rem; padding: 0.5rem 0;">
            <div class="skeleton" style="width: 70%; height: 1.25rem; border-radius: var(--radius-sm);"></div>
            <div class="skeleton" style="width: 50%; height: 1rem; border-radius: var(--radius-sm);"></div>
            <div style="margin-top: auto; display: flex; gap: 1rem;">
              <div class="skeleton" style="width: 30%; height: 0.875rem; border-radius: var(--radius-sm);"></div>
              <div class="skeleton" style="width: 25%; height: 0.875rem; border-radius: var(--radius-sm);"></div>
            </div>
          </div>
        </div>
      `,
      list: `
        <div class="skeleton-list" style="padding: 1rem; background: var(--bg-white); border-radius: var(--radius-md); border: 1px solid var(--border-light); margin-bottom: 0.75rem;">
          <div class="skeleton" style="width: 60%; height: 1rem; margin-bottom: 0.5rem; border-radius: var(--radius-sm);"></div>
          <div class="skeleton" style="width: 40%; height: 0.875rem; border-radius: var(--radius-sm);"></div>
        </div>
      `,
    };

    const selectedTemplate = templates[template] || templates.card;
    let skeletonHTML = "";
    for (let i = 0; i < count; i++) {
      skeletonHTML += selectedTemplate;
    }
    
    container.innerHTML = skeletonHTML;
  };

  App.showEmptyState = function showEmptyState(
    containerId,
    options = {}
  ) {
    const {
      icon = "📭",
      title = "ไม่พบข้อมูล",
      description = "ลองค้นหาด้วยเงื่อนไขอื่น",
      actionText = "",
      actionLink = "",
    } = options;

    const container = document.getElementById(containerId);
    if (!container) return;

    const actionButton = actionText && actionLink
      ? `<a href="${App.escapeHtml(actionLink)}" class="btn btn-primary" style="margin-top: 1rem;">${App.escapeHtml(actionText)}</a>`
      : "";

    container.innerHTML = `
      <div class="empty-state" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-secondary);">
        <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;">${App.escapeHtml(icon)}</div>
        <div style="font-size: var(--text-lg); font-weight: var(--font-semibold); color: var(--text-primary); margin-bottom: 0.5rem;">${App.escapeHtml(title)}</div>
        <div style="font-size: var(--text-sm); color: var(--text-secondary);">${App.escapeHtml(description)}</div>
        ${actionButton}
      </div>
    `;
  };

  // ============================================
  // Form Validation Helper
  // ============================================
  
  App.validateForm = function validateForm(formElement, rules) {
    let isValid = true;
    const errors = {};

    Object.keys(rules).forEach((fieldName) => {
      const field = formElement.elements[fieldName];
      const fieldRules = rules[fieldName];

      if (!field) return;

      // Clear previous errors
      field.classList.remove("error");
      const errorEl = field.parentElement?.querySelector(".field-error");
      if (errorEl) errorEl.remove();

      // Validate required
      if (fieldRules.required && !field.value.trim()) {
        errors[fieldName] = fieldRules.messages?.required || "กรุณากรอกข้อมูล";
        isValid = false;
      }

      // Validate min length
      if (fieldRules.minLength && field.value.length < fieldRules.minLength) {
        errors[fieldName] = fieldRules.messages?.minLength || `ต้องมีอย่างน้อย ${fieldRules.minLength} ตัวอักษร`;
        isValid = false;
      }

      // Validate pattern
      if (fieldRules.pattern && !fieldRules.pattern.test(field.value)) {
        errors[fieldName] = fieldRules.messages?.pattern || "รูปแบบไม่ถูกต้อง";
        isValid = false;
      }

      // Show error
      if (errors[fieldName]) {
        field.classList.add("error");
        const errorDiv = document.createElement("div");
        errorDiv.className = "field-error";
        errorDiv.textContent = errors[fieldName];
        errorDiv.style.cssText = "color: var(--error-color); font-size: var(--text-xs); margin-top: 0.25rem;";
        field.parentElement?.appendChild(errorDiv);
      }
    });

    return { isValid, errors };
  };

  // ============================================
  // Loading Spinner
  // ============================================
  
  App.showLoading = function showLoading(message = "กำลังโหลด...") {
    const existing = document.querySelector(".app-loading");
    if (existing) return;

    const loading = document.createElement("div");
    loading.className = "app-loading";
    loading.innerHTML = `
      <div style="position: fixed; inset: 0; background: var(--overlay-dark); display: flex; align-items: center; justify-content: center; z-index: var(--z-modal); backdrop-filter: blur(4px);">
        <div style="background: var(--bg-white); padding: 2rem; border-radius: var(--radius-lg); text-align: center; box-shadow: var(--shadow-2xl);">
          <div class="spinner" style="width: 40px; height: 40px; border: 4px solid var(--border-light); border-top-color: var(--primary-color); border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem;"></div>
          <div style="color: var(--text-primary); font-weight: var(--font-medium);">${App.escapeHtml(message)}</div>
        </div>
      </div>
    `;

    // Add spin animation if not exists
    if (!document.getElementById("spinner-style")) {
      const style = document.createElement("style");
      style.id = "spinner-style";
      style.textContent = "@keyframes spin { to { transform: rotate(360deg); } }";
      document.head.appendChild(style);
    }

    document.body.appendChild(loading);
    document.body.style.overflow = "hidden";
  };

  App.hideLoading = function hideLoading() {
    const loading = document.querySelector(".app-loading");
    if (loading) {
      loading.remove();
      document.body.style.overflow = "";
    }
  };

  // ============================================
  // Initialize
  // ============================================
  
  console.log("🚀 App.core.js loaded");
})();
