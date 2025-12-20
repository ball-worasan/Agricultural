(function () {
  "use strict";
  const App = (window.App = window.App || {});

  App.initSigninPage = function initSigninPage() {
    const pageRoot = document.querySelector('[data-page="signin"]');
    if (!pageRoot) return;

    const passwordInput = document.getElementById("password");
    const usernameInput = document.getElementById("username");
    const signinForm = pageRoot.querySelector(".signin-form");
    const toggleButton = pageRoot.querySelector(".toggle-password");
    const eyeIcon = pageRoot.querySelector(".eye-icon");
    const eyeOffIcon = pageRoot.querySelector(".eye-off-icon");

    if (toggleButton && passwordInput && eyeIcon && eyeOffIcon) {
      toggleButton.addEventListener("click", () => {
        const isPassword = passwordInput.type === "password";
        passwordInput.type = isPassword ? "text" : "password";
        eyeIcon.style.display = isPassword ? "none" : "block";
        eyeOffIcon.style.display = isPassword ? "block" : "none";
      });
    }

    let isSubmitting = false;

    if (signinForm) {
      signinForm.addEventListener("submit", (e) => {
        if (isSubmitting) {
          e.preventDefault();
          return;
        }

        const username = usernameInput ? usernameInput.value.trim() : "";
        const password = passwordInput ? passwordInput.value : "";

        if (!username || !password) {
          e.preventDefault();
          const msg = pageRoot.querySelector(".auth-message");
          if (msg) {
            msg.textContent = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
            msg.classList.add("error");
          } else {
            alert("กรุณากรอกชื่อผู้ใช้และรหัสผ่าน");
          }
          return;
        }

        isSubmitting = true;

        const submitBtn = signinForm.querySelector(".btn-signin");
        if (submitBtn) {
          submitBtn.classList.add("loading");
          submitBtn.disabled = true;
          submitBtn.innerHTML =
            '<span class="btn-loader"></span> กำลังเข้าสู่ระบบ...';
        }

        window.setTimeout(() => {
          const inputs = signinForm.querySelectorAll("input, button");
          inputs.forEach((el) => {
            if (el === submitBtn) return;
            el.disabled = true;
          });
        }, 80);
      });
    }
  };
})();
