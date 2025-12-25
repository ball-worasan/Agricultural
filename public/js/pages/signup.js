(() => {
  "use strict";

  // ================================
  // DOM Cache & State
  // ================================
  const pageRoot = document.querySelector(".signup-container");
  if (!pageRoot) return;

  const signupForm = pageRoot.querySelector(".signup-form");
  const phoneInput = document.getElementById("phone");
  const usernameInput = document.getElementById("username");
  const firstnameInput = document.getElementById("firstname");
  const lastnameInput = document.getElementById("lastname");
  const passwordInput = document.getElementById("password");
  const passwordConfirmInput = document.getElementById("password_confirm");

  let isSubmitting = false;

  // ================================
  // Password Toggle (Event Delegation)
  // ================================
  pageRoot.addEventListener("click", (e) => {
    const btn = e.target.closest(".toggle-password");
    if (!btn) return;

    e.preventDefault();
    const targetId = btn.getAttribute("data-target");
    const input = targetId ? document.getElementById(targetId) : null;
    if (!input) return;

    const eyeIcon = btn.querySelector(".eye-icon");
    const eyeOffIcon = btn.querySelector(".eye-off-icon");

    const isPassword = input.type === "password";
    input.type = isPassword ? "text" : "password";

    if (eyeIcon && eyeOffIcon) {
      eyeIcon.style.display = isPassword ? "none" : "inline";
      eyeOffIcon.style.display = isPassword ? "inline" : "none";
    }
  });

  // ================================
  // Form Validation & Submission
  // ================================
  if (signupForm) {
    signupForm.addEventListener("submit", function (e) {
      // Client-side validation
      if (!signupForm.checkValidity()) {
        e.preventDefault();
        alert("กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง");
        return;
      }

      const phone = phoneInput.value.replace(/\D/g, "");
      if (phone.length < 9 || phone.length > 10) {
        e.preventDefault();
        alert("กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (9-10 หลัก)");
        phoneInput.focus();
        return;
      }

      const username = usernameInput.value.trim();
      if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
        e.preventDefault();
        alert(
          "ชื่อผู้ใช้ต้องมีความยาว 3-20 ตัวอักษร (a-z, A-Z, 0-9, _ เท่านั้น)"
        );
        usernameInput.focus();
        return;
      }

      const password = passwordInput.value;
      const passwordConfirm = passwordConfirmInput.value;
      if (password !== passwordConfirm) {
        e.preventDefault();
        alert("รหัสผ่านไม่ตรงกัน");
        passwordConfirmInput.focus();
        return;
      }

      if (password.length < 6) {
        e.preventDefault();
        alert("รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร");
        passwordInput.focus();
        return;
      }

      // ป้องกันการส่งซ้ำ
      if (isSubmitting) {
        e.preventDefault();
        return;
      }
      isSubmitting = true;

      // Disable ปุ่ม submit เท่านั้น (ไม่ disable inputs เพราะจะทำให้ข้อมูลไม่ส่ง)
      const submitBtn = signupForm.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "กำลังสมัคร...";
      }
    });
  }

  // ================================
  // Input Sanitization
  // ================================
  if (phoneInput) {
    phoneInput.addEventListener("input", () => {
      let v = String(phoneInput.value || "").replace(/[^0-9]/g, "");
      if (v.length > 10) v = v.slice(0, 10);
      phoneInput.value = v;
    });
  }

  if (usernameInput) {
    usernameInput.addEventListener("input", () => {
      let v = String(usernameInput.value || "").replace(/[^a-zA-Z0-9_]/g, "");
      if (v.length > 20) v = v.slice(0, 20);
      usernameInput.value = v;
    });
  }
})();
