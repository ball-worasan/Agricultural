(function () {
  "use strict";

  // Preview upload images (max 10, 5MB each)
  const state = {
    selectedFiles: [],
  };

  function rebuildPreview() {
    const preview = document.getElementById("imagePreview");
    if (!preview) return;
    preview.innerHTML = "";

    state.selectedFiles.forEach((file, idx) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        const div = document.createElement("div");
        div.className = "preview-item";
        div.innerHTML =
          '<img src="' +
          e.target.result +
          '" alt="Preview">' +
          '<button type="button" class="remove-image" data-index="' +
          idx +
          '">×</button>';
        preview.appendChild(div);

        const btn = div.querySelector(".remove-image");
        if (btn) {
          btn.addEventListener("click", () => {
            removeImage(idx);
          });
        }
      };
      reader.readAsDataURL(file);
    });
  }

  function syncInput() {
    const input = document.getElementById("images");
    if (!input) return;
    const dt = new DataTransfer();
    state.selectedFiles.forEach((f) => dt.items.add(f));
    input.files = dt.files;
  }

  function removeImage(idx) {
    if (idx < 0 || idx >= state.selectedFiles.length) return;
    state.selectedFiles.splice(idx, 1);
    rebuildPreview();
    syncInput();
  }

  window.previewImages = function (event) {
    const files = Array.prototype.slice.call(event.target.files || []);
    const preview = document.getElementById("imagePreview");
    if (!preview) return;

    if (state.selectedFiles.length + files.length > 10) {
      alert("สามารถอัปโหลดรูปภาพได้สูงสุด 10 รูป");
      return;
    }

    files.forEach((file) => {
      if (file.size > 5 * 1024 * 1024) {
        alert("ไฟล์ " + file.name + " มีขนาดใหญ่เกิน 5MB");
        return;
      }
      state.selectedFiles.push(file);
    });

    rebuildPreview();
    syncInput();
  };

  window.removeImage = removeImage;

  // Province-District cascade (mobile-safe by rebuilding options)
  function setupCascade() {
    const provinceSelect = document.getElementById("province");
    const districtSelect = document.getElementById("district");
    if (!provinceSelect || !districtSelect) return;

    const initialOptions = Array.from(
      districtSelect.querySelectorAll("option[data-province-id]")
    ).map((opt) => ({
      value: opt.value,
      text: opt.textContent,
      provinceId: opt.getAttribute("data-province-id"),
      selected: opt.selected || opt.defaultSelected,
    }));

    const updateDistricts = () => {
      const selectedProvinceId = String(provinceSelect.value || "").trim();
      const placeholder = districtSelect.querySelector("option[value='']");

      // Remove all non-placeholder options
      while (districtSelect.options.length > 1) {
        districtSelect.remove(1);
      }

      districtSelect.value = "";

      if (!selectedProvinceId) {
        districtSelect.disabled = true;
        if (placeholder) placeholder.textContent = "เลือกจังหวัดก่อน";
        return;
      }

      districtSelect.disabled = false;
      if (placeholder) placeholder.textContent = "ทั้งหมด";

      let count = 0;
      initialOptions.forEach((opt) => {
        if (String(opt.provinceId || "").trim() === selectedProvinceId) {
          const o = document.createElement("option");
          o.value = opt.value;
          o.textContent = opt.text;
          o.setAttribute("data-province-id", opt.provinceId || "");
          // preserve prior selection if still relevant
          if (opt.selected) {
            o.selected = true;
          }
          districtSelect.appendChild(o);
          count++;
        }
      });

      if (count === 0 && placeholder) {
        placeholder.textContent = "ไม่มีอำเภอในจังหวัดนี้";
      }
    };

    provinceSelect.addEventListener("change", updateDistricts);
    updateDistricts();
  }

  document.addEventListener("DOMContentLoaded", () => {
    setupCascade();
  });
})();
