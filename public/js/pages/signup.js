(function () {
  "use strict";

  const App = (window.App = window.App || {});

  App.initSignupPage = function initSignupPage() {
    const pageRoot = document.querySelector(".signup-container");
    if (!pageRoot) return;

    // -------- Toggle password (รองรับ 2 ช่อง) --------
    const toggleButtons = pageRoot.querySelectorAll(".toggle-password");
    for (let i = 0; i < toggleButtons.length; i++) {
      const button = toggleButtons[i];
      const targetId = button.getAttribute("data-target");
      const input = document.getElementById(targetId);
      const eyeIcon = button.querySelector(".eye-icon");
      const eyeOffIcon = button.querySelector(".eye-off-icon");
      if (!input || !eyeIcon || !eyeOffIcon) continue;

      button.addEventListener("click", function () {
        const isPassword = input.type === "password";
        input.type = isPassword ? "text" : "password";
        eyeIcon.style.display = isPassword ? "none" : "block";
        eyeOffIcon.style.display = isPassword ? "block" : "none";
      });
    }

    // -------- Form submit guard + validation --------
    const signupForm = pageRoot.querySelector(".signup-form");
    let isSubmitting = false;

    function toastOrAlert(title, msg, type) {
      if (typeof window.showToast === "function") {
        window.showToast(title, msg, type);
      } else {
        alert(msg);
      }
    }

    function getVal(id) {
      const el = document.getElementById(id);
      return el ? String(el.value || "") : "";
    }

    if (signupForm) {
      signupForm.addEventListener("submit", function (e) {
        if (isSubmitting) {
          e.preventDefault();
          return;
        }

        try {
          const firstname = getVal("firstname").trim();
          const lastname = getVal("lastname").trim();
          const username = getVal("username").trim();
          const phone = getVal("phone").trim();
          const password = getVal("password");
          const passwordConfirm = getVal("password_confirm");

          if (!firstname || !lastname || !username || !password) {
            e.preventDefault();
            toastOrAlert(
              "แจ้งเตือน",
              "กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน",
              "warning"
            );
            return;
          }

          if (password.length < 6) {
            e.preventDefault();
            toastOrAlert(
              "แจ้งเตือน",
              "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร",
              "warning"
            );
            return;
          }

          if (password !== passwordConfirm) {
            e.preventDefault();
            toastOrAlert("แจ้งเตือน", "รหัสผ่านไม่ตรงกัน", "error");
            return;
          }

          if (phone && !/^[0-9]{9,10}$/.test(phone)) {
            e.preventDefault();
            toastOrAlert(
              "แจ้งเตือน",
              "กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (9-10 หลัก)",
              "warning"
            );
            return;
          }

          if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
            e.preventDefault();
            toastOrAlert(
              "แจ้งเตือน",
              "ชื่อผู้ใช้ต้องมีความยาว 3-20 ตัวอักษร และประกอบด้วย a-z, A-Z, 0-9, _ เท่านั้น",
              "warning"
            );
            return;
          }
        } catch (err) {
          console.error("Signup validation error:", err);
          e.preventDefault();
          toastOrAlert(
            "ข้อผิดพลาด",
            "เกิดข้อผิดพลาดในการตรวจสอบข้อมูล",
            "error"
          );
          return;
        }

        isSubmitting = true;

        const submitBtn = signupForm.querySelector(".btn-signup");
        if (submitBtn) {
          submitBtn.classList.add("loading");
          submitBtn.disabled = true;
          submitBtn.innerHTML =
            '<span class="btn-loader"></span> กำลังสมัครสมาชิก...';
        }
      });
    }

    // -------- input sanitize: phone --------
    const phoneInput = document.getElementById("phone");
    if (phoneInput) {
      phoneInput.addEventListener("input", function () {
        let v = String(this.value || "").replace(/[^0-9]/g, "");
        if (v.length > 10) v = v.slice(0, 10);
        this.value = v;
      });
    }

    // -------- input sanitize: username --------
    const usernameInput = document.getElementById("username");
    if (usernameInput) {
      usernameInput.addEventListener("input", function () {
        let v = String(this.value || "").replace(/[^a-zA-Z0-9_]/g, "");
        if (v.length > 20) v = v.slice(0, 20);
        this.value = v;
      });
    }
  };
})();
