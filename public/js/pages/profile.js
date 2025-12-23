(() => {
  "use strict";

  const view = document.getElementById("profileView");
  const form = document.getElementById("profileForm");
  const editBtn = document.getElementById("editProfileBtn");
  const cancelBtn = document.getElementById("cancelEditBtn");

  function showEdit() {
    if (!view || !form) return;
    view.classList.add("hidden");
    form.classList.remove("hidden");
  }

  function cancelEdit() {
    if (!view || !form) return;
    form.classList.add("hidden");
    view.classList.remove("hidden");
  }

  if (editBtn)
    editBtn.addEventListener("click", (e) => {
      e.preventDefault();
      showEdit();
    });
  if (cancelBtn)
    cancelBtn.addEventListener("click", (e) => {
      e.preventDefault();
      cancelEdit();
    });

  // -------- Profile form validation --------
  if (form) {
    form.addEventListener("submit", (e) => {
      const fullName =
        form.querySelector('[name="full_name"]')?.value.trim() || "";
      const phone = form.querySelector('[name="phone"]')?.value.trim() || "";

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

  // -------- Password form validation --------
  const passwordForm = document.querySelector(".password-form");
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

  // -------- Toggle password --------
  document.querySelectorAll(".toggle-password").forEach((btn) => {
    btn.addEventListener("click", () => {
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
  });

  // -------- Phone digits only --------
  const phoneInput = document.getElementById("phone");
  if (phoneInput) {
    phoneInput.addEventListener("input", () => {
      phoneInput.value = phoneInput.value.replace(/[^0-9]/g, "").slice(0, 10);
    });
  }
})();
