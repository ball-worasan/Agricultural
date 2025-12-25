(() => {
  "use strict";

  // ================================
  // DOM Cache & State
  // ================================
  const profileView = document.getElementById("profileView");
  const profileForm = document.getElementById("profileForm");
  const editBtn = document.getElementById("editProfileBtn");
  const cancelBtn = document.getElementById("cancelEditBtn");
  const passwordForm = document.querySelector(".password-form");
  const phoneInput = document.getElementById("phone");

  // ================================
  // Profile Edit Mode Toggle
  // ================================
  function toggleEditMode(isEdit) {
    if (!profileView || !profileForm) return;
    if (isEdit) {
      profileView.classList.add("hidden");
      profileForm.classList.remove("hidden");
    } else {
      profileForm.classList.add("hidden");
      profileView.classList.remove("hidden");
    }
  }

  if (editBtn) {
    editBtn.addEventListener("click", (e) => {
      e.preventDefault();
      toggleEditMode(true);
    });
  }

  if (cancelBtn) {
    cancelBtn.addEventListener("click", (e) => {
      e.preventDefault();
      toggleEditMode(false);
    });
  }

  // ================================
  // Profile Form Validation
  // ================================
  if (profileForm) {
    profileForm.addEventListener("submit", (e) => {
      const fullName =
        profileForm.querySelector('[name="full_name"]')?.value.trim() || "";
      const phone =
        profileForm.querySelector('[name="phone"]')?.value.trim() || "";

      if (!fullName) {
        e.preventDefault();
        alert("กรุณากรอกชื่อ-นามสกุล");
        return;
      }

      if (phone && !/^[0-9]{9,10}$/.test(phone)) {
        e.preventDefault();
        alert("กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (9-10 หลัก)");
        return;
      }
    });
  }

  // ================================
  // Password Form Validation
  // ================================
  if (passwordForm) {
    passwordForm.addEventListener("submit", (e) => {
      const currentPassword =
        passwordForm.querySelector('[name="current_password"]')?.value || "";
      const newPassword =
        passwordForm.querySelector('[name="new_password"]')?.value || "";
      const confirmPassword =
        passwordForm.querySelector('[name="confirm_new_password"]')?.value ||
        "";

      if (!currentPassword || !newPassword || !confirmPassword) {
        e.preventDefault();
        alert("กรุณากรอกข้อมูลให้ครบถ้วน");
        return;
      }

      if (newPassword.length < 8) {
        e.preventDefault();
        alert("รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร");
        return;
      }

      if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert("รหัสผ่านใหม่ไม่ตรงกัน");
        return;
      }

      if (newPassword === currentPassword) {
        e.preventDefault();
        alert("รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม");
        return;
      }
    });
  }

  // ================================
  // Password Toggle (Event Delegation)
  // ================================
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".toggle-password");
    if (!btn) return;

    const targetId = btn.getAttribute("data-target");
    const input = targetId ? document.getElementById(targetId) : null;
    if (!input) return;

    const eye = btn.querySelector(".eye-icon");
    const eyeOff = btn.querySelector(".eye-off-icon");

    const isPassword = input.type === "password";
    input.type = isPassword ? "text" : "password";

    if (eye && eyeOff) {
      eye.style.display = isPassword ? "none" : "inline";
      eyeOff.style.display = isPassword ? "inline" : "none";
    }
  });

  // ================================
  // Phone Input: Digits Only
  // ================================
  if (phoneInput) {
    phoneInput.addEventListener("input", () => {
      phoneInput.value = phoneInput.value.replace(/[^0-9]/g, "").slice(0, 10);
    });
  }
})();
