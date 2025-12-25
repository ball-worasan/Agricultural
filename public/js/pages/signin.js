(() => {
  "use strict";

  // ================================
  // DOM Cache & State
  // ================================
  const pageRoot = document.querySelector('[data-page="signin"]');
  if (!pageRoot) return;

  const passwordInput = document.getElementById("password");
  const usernameInput = document.getElementById("username");
  const signinForm = pageRoot.querySelector(".signin-form");
  const toggleButton = pageRoot.querySelector(".toggle-password");
  const eyeIcon = pageRoot.querySelector(".eye-icon");
  const eyeOffIcon = pageRoot.querySelector(".eye-off-icon");

  let isSubmitting = false;

  // ================================
  // Password Toggle
  // ================================
  if (toggleButton && passwordInput && eyeIcon && eyeOffIcon) {
    toggleButton.addEventListener("click", (e) => {
      e.preventDefault();
      const isPassword = passwordInput.type === "password";
      passwordInput.type = isPassword ? "text" : "password";
      eyeIcon.style.display = isPassword ? "none" : "inline";
      eyeOffIcon.style.display = isPassword ? "inline" : "none";
    });
  }

  // ================================
  // Form Validation & Submission
  // ================================
  if (signinForm) {
    signinForm.addEventListener("submit", (e) => {
      if (isSubmitting) {
        e.preventDefault();
        return;
      }

      const username = usernameInput ? usernameInput.value.trim() : "";
      const password = passwordInput ? passwordInput.value.trim() : "";

      if (!username || !password) {
        e.preventDefault();
        alert("กรุณากรอกชื่อผู้ใช้และรหัสผ่าน");
        return;
      }

      isSubmitting = true;

      const submitBtn = signinForm.querySelector(".btn-signin");
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add("loading");
        submitBtn.innerHTML =
          '<span class="btn-loader"></span> กำลังเข้าสู่ระบบ...';
      }
    });
  }
})();
