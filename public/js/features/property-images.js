(function () {
  "use strict";
  const App = (window.App = window.App || {});
  const { MAX_IMAGE_SIZE, MAX_IMAGES } = App.constants;

  class PropertyImagesManager {
    constructor() {
      this.root = document.body;
      this.propertyId = this.root
        ? parseInt(this.root.getAttribute("data-property-id") || "0", 10)
        : 0;

      this.deleteEndpoint =
        this.root?.getAttribute("data-delete-endpoint") ||
        "?page=delete_property_image";

      this.input = document.getElementById("images");
      this.preview = document.getElementById("imagePreview");
      this.selectedFiles = [];

      this.init();
    }

    init() {
      if (this.input) {
        this.input.addEventListener("change", (e) => this.handleInputChange(e));
      }

      document.addEventListener("click", (e) => {
        const btn = e.target.closest
          ? e.target.closest(".remove-existing-image")
          : null;
        if (!btn) return;

        const item = btn.closest(".existing-image-item");
        if (!item) return;

        const imageId = parseInt(item.getAttribute("data-image-id") || "0", 10);
        if (imageId > 0) this.removeExistingImage(imageId);
      });
    }

    syncInput() {
      if (!this.input) return;
      const dt = new DataTransfer();
      this.selectedFiles.forEach((f) => dt.items.add(f));
      this.input.files = dt.files;
    }

    renderPreview() {
      if (!this.preview) return;
      this.preview.innerHTML = "";

      this.selectedFiles.forEach((file, idx) => {
        const reader = new FileReader();
        reader.onload = (e) => {
          const div = document.createElement("div");
          div.className = "preview-item";
          div.innerHTML = `
            <img src="${App.escapeHtml(e.target.result)}" alt="Preview">
            <button type="button" class="remove-image" data-index="${idx}">×</button>
          `;

          this.preview.appendChild(div);

          const btn = div.querySelector(".remove-image");
          if (btn) {
            btn.addEventListener("click", () => {
              const i = parseInt(btn.getAttribute("data-index") || "0", 10);
              if (i >= 0 && i < this.selectedFiles.length) {
                this.selectedFiles.splice(i, 1);
                this.renderPreview();
                this.syncInput();
              }
            });
          }
        };
        reader.readAsDataURL(file);
      });
    }

    getExistingCount() {
      return document.querySelectorAll(".existing-image-item").length;
    }

    handleInputChange(e) {
      const files = Array.from((e.target && e.target.files) || []);
      if (!files.length) return;

      const existingCount = this.getExistingCount();
      const total = existingCount + this.selectedFiles.length + files.length;

      if (total > MAX_IMAGES) {
        App.showToast(
          "แจ้งเตือน",
          `สามารถมีรูปภาพได้สูงสุด ${MAX_IMAGES} รูป (ปัจจุบันมี ${existingCount} รูปเดิม)`,
          "warning",
          4000
        );
        this.input.value = "";
        return;
      }

      files.forEach((file) => {
        if (file.size > MAX_IMAGE_SIZE) {
          App.showToast(
            "แจ้งเตือน",
            `ไฟล์ ${file.name} มีขนาดใหญ่เกิน 5MB`,
            "error",
            4000
          );
          return;
        }
        this.selectedFiles.push(file);
      });

      this.renderPreview();
      this.syncInput();
    }

    removeExistingImage(imageId) {
      if (!imageId || imageId <= 0) return;

      if (!this.propertyId || this.propertyId <= 0) {
        App.showToast(
          "ข้อผิดพลาด",
          "หา property_id ไม่เจอ (ตั้ง data-property-id ให้ด้วย)",
          "error"
        );
        return;
      }

      App.confirmDialog(
        "ยืนยันการลบ",
        "คุณต้องการลบรูปภาพนี้ใช่หรือไม่?",
        () => {
          const fd = new FormData();
          fd.append("action", "delete_image");
          fd.append("image_id", String(imageId));
          fd.append("property_id", String(this.propertyId));

          fetch(this.deleteEndpoint, { method: "POST", body: fd })
            .then((res) =>
              res.text().then((txt) => {
                let data = null;
                try {
                  data = txt ? JSON.parse(txt) : null;
                } catch (_) {}
                if (!res.ok || !data || !data.success) {
                  const msg = data?.message || "ไม่สามารถลบรูปภาพได้";
                  throw new Error(msg);
                }
                return data;
              })
            )
            .then((data) => {
              const item = document.querySelector(
                `.existing-image-item[data-image-id="${imageId}"]`
              );
              if (item?.parentNode) item.parentNode.removeChild(item);
              App.showToast(
                "สำเร็จ",
                data.message || "ลบรูปภาพสำเร็จ",
                "success"
              );
            })
            .catch((err) => {
              console.error("Image deletion error:", err);
              App.showToast(
                "ข้อผิดพลาด",
                err.message || "เชื่อมต่อไม่ได้",
                "error"
              );
            });
        }
      );
    }
  }

  App.PropertyImagesManager = PropertyImagesManager;

  App.initPropertyImages = function initPropertyImages() {
    if (
      document.getElementById("images") ||
      document.getElementById("imagePreview")
    ) {
      new PropertyImagesManager();
    }
  };
})();
