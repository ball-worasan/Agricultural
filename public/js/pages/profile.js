(() => {
  "use strict";

  // กันยิงในหน้าอื่น
  const pageRoot =
    document.querySelector('[data-page="profile"]') ||
    document.querySelector(".profile-container");
  if (!pageRoot) return;

  // ================================
  // DOM Cache
  // ================================
  const profileView = document.getElementById("profileView");
  const profileForm = document.getElementById("profileForm");
  const editBtn = document.getElementById("editProfileBtn");
  const cancelBtn = document.getElementById("cancelEditBtn");
  const passwordForm = document.querySelector(".password-form");

  // หมายเหตุ: ใน profile.php มี input phone id="phone" อยู่ใน edit form
  const phoneInput = profileForm
    ? profileForm.querySelector('input[name="phone"]')
    : null;

  // ================================
  // Utilities
  // ================================
  const toast = (type, message) => {
    // ถ้ามีระบบ toast ของคุณ (เช่น render_flash_popup) อาจมี global function
    if (typeof window.showToast === "function") {
      window.showToast(type, message);
      return;
    }
    // fallback
    alert(message);
  };

  const setSubmitting = (form, isSubmitting) => {
    if (!form) return;
    form.dataset.submitting = isSubmitting ? "1" : "0";
    form.setAttribute("aria-busy", isSubmitting ? "true" : "false");

    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
      btn.disabled = !!isSubmitting;
      btn.classList.toggle("is-loading", !!isSubmitting);
    }
  };

  const digitsOnly = (s) => String(s || "").replace(/\D+/g, "");

  // ================================
  // Edit Mode Toggle
  // ================================
  function toggleEditMode(isEdit) {
    if (!profileView || !profileForm) return;
    if (isEdit) {
      profileView.classList.add("hidden");
      profileForm.classList.remove("hidden");
      // focus first input
      const first = profileForm.querySelector('input[name="full_name"]');
      if (first) first.focus();
    } else {
      profileForm.classList.add("hidden");
      profileView.classList.remove("hidden");
      if (editBtn) editBtn.focus();
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

      // reset ค่าในฟอร์มกลับเป็นค่าเดิมใน DOM (ที่ server render มา)
      // (ถ้าคุณอยากให้ cancel แล้วกลับค่าเดิม)
      if (profileForm) profileForm.reset();

      toggleEditMode(false);
    });
  }

  // ================================
  // Auto open edit mode when form has "old input" (PRG fail)
  // ================================
  // แนวคิด: ถ้า edit form ไม่ว่าง/ต่างจาก view แปลว่า user เพิ่งแก้และโดน PRG กลับมา
  // ให้เปิดโหมด edit เพื่อไม่ให้ user งง
  (() => {
    if (!profileForm || !profileView) return;

    const viewName = profileView.querySelector(
      ".info-item:nth-child(1) p"
    )?.textContent;

    const editName = profileForm.querySelector(
      'input[name="full_name"]'
    )?.value;

    // ถ้ามีค่าใน input และต่างจาก view (แบบหยาบ ๆ) => เปิด edit
    if (
      editName &&
      typeof viewName === "string" &&
      editName.trim() !== viewName.trim()
    ) {
      toggleEditMode(true);
    }
  })();

  // ================================
  // Profile Form Validation + Anti double-submit
  // ================================
  if (profileForm) {
    profileForm.addEventListener("submit", (e) => {
      if (profileForm.dataset.submitting === "1") {
        e.preventDefault();
        return;
      }

      const fullName =
        profileForm.querySelector('[name="full_name"]')?.value.trim() || "";
      const phoneRaw =
        profileForm.querySelector('[name="phone"]')?.value.trim() || "";
      const phone = digitsOnly(phoneRaw);

      // normalize phone field before submit
      const phoneEl = profileForm.querySelector('[name="phone"]');
      if (phoneEl) phoneEl.value = phone;

      if (!fullName) {
        e.preventDefault();
        toast("error", "กรุณากรอกชื่อ-นามสกุล");
        toggleEditMode(true);
        return;
      }

      if (phone && !/^[0-9]{9,10}$/.test(phone)) {
        e.preventDefault();
        toast("error", "กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (9-10 หลัก)");
        toggleEditMode(true);
        return;
      }

      setSubmitting(profileForm, true);
    });
  }

  // ================================
  // Password Form Validation + Anti double-submit
  // ================================
  if (passwordForm) {
    passwordForm.addEventListener("submit", (e) => {
      if (passwordForm.dataset.submitting === "1") {
        e.preventDefault();
        return;
      }

      const currentPassword =
        passwordForm.querySelector('[name="current_password"]')?.value || "";
      const newPassword =
        passwordForm.querySelector('[name="new_password"]')?.value || "";
      const confirmPassword =
        passwordForm.querySelector('[name="confirm_new_password"]')?.value ||
        "";

      if (!currentPassword || !newPassword || !confirmPassword) {
        e.preventDefault();
        toast("error", "กรุณากรอกข้อมูลให้ครบถ้วน");
        return;
      }

      if (newPassword.length < 8) {
        e.preventDefault();
        toast("error", "รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร");
        return;
      }

      if (newPassword !== confirmPassword) {
        e.preventDefault();
        toast("error", "รหัสผ่านใหม่ไม่ตรงกัน");
        return;
      }

      if (newPassword === currentPassword) {
        e.preventDefault();
        toast("error", "รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม");
        return;
      }

      setSubmitting(passwordForm, true);
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
      phoneInput.value = digitsOnly(phoneInput.value).slice(0, 10);
    });
  }
})();
