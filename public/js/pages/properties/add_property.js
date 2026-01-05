(() => {
  "use strict";

  // Constants
  const MAX_IMAGES = 10;
  const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

  // State
  const state = {
    selectedFiles: [],
  };

  // DOM cache
  const els = {
    imageInput: null,
    imagePreview: null,
    provinceSelect: null,
    districtSelect: null,
    priceInput: null,
  };

  function cacheElements() {
    els.imageInput = document.getElementById("images");
    els.imagePreview = document.getElementById("imagePreview");
    els.provinceSelect = document.getElementById("province");
    els.districtSelect = document.getElementById("district");
    els.priceInput = document.getElementById("price");
  }

  // ================================
  // Image Preview Management
  // ================================
  function rebuildPreview() {
    if (!els.imagePreview) return;
    els.imagePreview.innerHTML = "";

    state.selectedFiles.forEach((file, idx) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        const div = document.createElement("div");
        div.className = "preview-item";

        const img = document.createElement("img");
        img.src = String(e.target?.result || "");
        img.alt = "Preview";

        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "remove-image";
        btn.textContent = "×";
        btn.dataset.index = String(idx);
        btn.addEventListener("click", () => removeImage(idx));

        div.appendChild(img);
        div.appendChild(btn);
        els.imagePreview.appendChild(div);
      };
      reader.readAsDataURL(file);
    });
  }

  function syncInput() {
    if (!els.imageInput) return;
    const dt = new DataTransfer();
    state.selectedFiles.forEach((f) => dt.items.add(f));
    els.imageInput.files = dt.files;
  }

  function removeImage(idx) {
    if (idx < 0 || idx >= state.selectedFiles.length) return;
    state.selectedFiles.splice(idx, 1);
    rebuildPreview();
    syncInput();
  }

  function handleImageChange(event) {
    const files = Array.from(event.target?.files || []);
    if (!files.length) return;

    if (state.selectedFiles.length + files.length > MAX_IMAGES) {
      alert(`สามารถอัปโหลดรูปภาพได้สูงสุด ${MAX_IMAGES} รูป`);
      return;
    }

    const validFiles = [];
    for (const file of files) {
      if (file.size > MAX_FILE_SIZE) {
        alert(`ไฟล์ ${file.name} มีขนาดใหญ่เกิน 5MB`);
        continue;
      }
      validFiles.push(file);
    }

    state.selectedFiles.push(...validFiles);
    rebuildPreview();
    syncInput();
  }

  // ================================
  // Province-District Cascade
  // ================================
  function setupCascade() {
    if (!els.provinceSelect || !els.districtSelect) return;

    const initialOptions = Array.from(
      els.districtSelect.querySelectorAll("option[data-province-id]")
    ).map((opt) => ({
      value: opt.value,
      text: opt.textContent || "",
      provinceId: opt.getAttribute("data-province-id") || "",
      selected: opt.selected || opt.defaultSelected,
    }));

    const updateDistricts = () => {
      const selectedProvinceId = String(els.provinceSelect.value || "").trim();
      const placeholder = els.districtSelect.querySelector("option[value='']");

      // Remove all non-placeholder options
      while (els.districtSelect.options.length > 1) {
        els.districtSelect.remove(1);
      }

      els.districtSelect.value = "";

      if (!selectedProvinceId) {
        els.districtSelect.disabled = true;
        if (placeholder) placeholder.textContent = "เลือกจังหวัดก่อน";
        return;
      }

      els.districtSelect.disabled = false;
      if (placeholder) placeholder.textContent = "ทั้งหมด";

      let count = 0;
      initialOptions.forEach((opt) => {
        if (String(opt.provinceId || "").trim() === selectedProvinceId) {
          const o = document.createElement("option");
          o.value = opt.value;
          o.textContent = opt.text;
          o.setAttribute("data-province-id", opt.provinceId || "");
          if (opt.selected) o.selected = true;
          els.districtSelect.appendChild(o);
          count++;
        }
      });

      if (count === 0 && placeholder) {
        placeholder.textContent = "ไม่มีอำเภอในจังหวัดนี้";
      }
    };

    els.provinceSelect.addEventListener("change", updateDistricts);
    updateDistricts();
  }

  // ================================
  // Price Validation
  // ================================
  function setupPriceValidation() {
    if (!els.priceInput) return;

    const MAX_PRICE = 999999.99;

    els.priceInput.addEventListener("input", function () {
      const value = parseFloat(this.value);
      if (value > MAX_PRICE) {
        this.setCustomValidity(
          `ราคาต้องไม่เกิน ${MAX_PRICE.toLocaleString("th-TH", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })} บาท`
        );
      } else {
        this.setCustomValidity("");
      }
    });

    els.priceInput.addEventListener("blur", function () {
      const value = parseFloat(this.value);
      if (value > MAX_PRICE) {
        alert(
          `⚠️ ราคาต้องไม่เกิน ${MAX_PRICE.toLocaleString("th-TH", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })} บาท`
        );
        this.focus();
      }
    });
  }

  // ================================
  // Event Bindings
  // ================================
  function bindEvents() {
    if (els.imageInput) {
      els.imageInput.addEventListener("change", handleImageChange);
    }
  }

  // ================================
  // Initialization
  // ================================
  function init() {
    cacheElements();
    bindEvents();
    setupCascade();
    setupPriceValidation();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
