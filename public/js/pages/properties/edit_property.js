// public/js/edit_property.js  (FULL FIXED)
// FIXES
// - ลบรูปผ่าน ?page=delete_property_image อย่างเดียว (กันยิงซ้ำ)
// - ส่ง area_id + _csrf ทุกครั้ง
// - ถ้า server ตอบ 200 แต่ payload ไม่ใช่ JSON -> ยังถือว่า fail (เพราะ endpoint JSON only)

(function () {
  "use strict";

  var container = document.querySelector(".add-property-container");
  var areaId = container ? container.dataset.areaId || "" : "";

  var provinceSelect = document.getElementById("province");
  var districtSelect = document.getElementById("district");
  var allDistrictOptions = [];
  var fileInput = document.getElementById("images");
  var previewGrid = document.getElementById("imagePreview");
  var existingWrapper = document.getElementById("existingImages");
  var deletingMap = Object.create(null); // กันยิงลบซ้ำ

  var selectedFiles = [];

  function filterDistricts() {
    if (!provinceSelect || !districtSelect) return;
    var provinceId = provinceSelect.value;
    var currentDistrict = districtSelect.value;

    districtSelect.innerHTML = "";

    var placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = provinceId
      ? "-- เลือกอำเภอ --"
      : "เลือกจังหวัดก่อน";
    districtSelect.appendChild(placeholder);

    allDistrictOptions.forEach(function (opt) {
      if (opt.value === "") return;
      if (provinceId && opt.getAttribute("data-province-id") === provinceId) {
        var clone = opt.cloneNode(true);
        if (currentDistrict && currentDistrict === opt.value) {
          clone.selected = true;
        }
        districtSelect.appendChild(clone);
      }
    });

    districtSelect.disabled = !provinceId;
  }

  function bindProvinceCascade() {
    if (!provinceSelect || !districtSelect) return;

    allDistrictOptions = Array.prototype.slice.call(
      districtSelect.querySelectorAll("option[data-province-id]")
    );
    filterDistricts();
    provinceSelect.addEventListener("change", function () {
      districtSelect.value = "";
      filterDistricts();
    });
  }

  function rebuildPreview() {
    if (!previewGrid) return;
    previewGrid.innerHTML = "";

    selectedFiles.forEach(function (file, idx) {
      var reader = new FileReader();
      reader.onload = function (e) {
        var wrap = document.createElement("div");
        wrap.className = "preview-item";

        var img = document.createElement("img");
        img.src = e.target.result;
        img.alt = "รูปใหม่ " + (idx + 1);

        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "remove-image";
        btn.textContent = "×";
        btn.addEventListener("click", function () {
          removeNewImage(idx);
        });

        wrap.appendChild(img);
        wrap.appendChild(btn);
        previewGrid.appendChild(wrap);
      };
      reader.readAsDataURL(file);
    });

    syncInputFiles();
  }

  function syncInputFiles() {
    if (!fileInput) return;
    var dt = new DataTransfer();
    selectedFiles.forEach(function (f) {
      dt.items.add(f);
    });
    fileInput.files = dt.files;
  }

  function removeNewImage(idx) {
    if (idx < 0 || idx >= selectedFiles.length) return;
    selectedFiles.splice(idx, 1);
    rebuildPreview();
  }

  function handleFileChange(event) {
    var files = Array.prototype.slice.call(event.target.files || []);
    var existingCount = existingWrapper
      ? existingWrapper.querySelectorAll(".existing-image-item").length
      : 0;
    var total = existingCount + selectedFiles.length + files.length;
    if (total > 10) {
      alert("สามารถมีรูปภาพได้สูงสุด 10 รูป (รวมรูปเดิม)");
      return;
    }

    files.forEach(function (file) {
      if (!(file instanceof File)) return;
      selectedFiles.push(file);
    });

    rebuildPreview();
  }

  function bindFileInput() {
    if (!fileInput) return;
    fileInput.addEventListener("change", handleFileChange);
  }

  function getCsrfToken() {
    var csrfEl = document.querySelector('input[name="_csrf"]');
    return csrfEl ? csrfEl.value : "";
  }

  async function removeExisting(imageId, buttonEl) {
    if (!imageId) return;
    if (!areaId) {
      alert("ไม่พบ area_id");
      return;
    }
    if (deletingMap[imageId]) return; // กันลบซ้ำซ้อน
    if (!confirm("คุณต้องการลบรูปภาพนี้ใช่หรือไม่?")) return;

    var prevDisabled = false;
    if (buttonEl) {
      prevDisabled = buttonEl.disabled;
      buttonEl.disabled = true;
    }
    deletingMap[imageId] = true;

    try {
      var formData = new FormData();
      formData.append("action", "delete_image");
      formData.append("image_id", String(imageId));
      formData.append("area_id", String(areaId));
      formData.append("_csrf", getCsrfToken());

      var res = await fetch("?page=delete_property_image", {
        method: "POST",
        body: formData,
        credentials: "same-origin",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
      });

      var text = await res.text();
      var payload = null;
      try {
        payload = text ? JSON.parse(text) : null;
      } catch (_) {
        payload = null;
      }

      console.log("Delete response:", {
        status: res.status,
        ok: res.ok,
        body: text,
        payload: payload,
      });

      if (!res.ok) {
        var serverMsg =
          payload && payload.message ? payload.message : "HTTP " + res.status;
        throw new Error(serverMsg);
      }

      if (!payload || payload.success !== true) {
        throw new Error(
          payload && payload.message ? payload.message : "ลบรูปภาพไม่สำเร็จ"
        );
      }

      var item = buttonEl ? buttonEl.closest(".existing-image-item") : null;
      if (item && item.parentNode) item.parentNode.removeChild(item);
    } catch (err) {
      console.error("Delete error:", err);
      alert(
        "ลบรูปภาพไม่สำเร็จ: " +
          (err && err.message ? err.message : "เกิดข้อผิดพลาด")
      );
    } finally {
      deletingMap[imageId] = false;
      if (buttonEl) buttonEl.disabled = prevDisabled;
    }
  }

  function bindExistingDeletes() {
    if (!existingWrapper) return;
    var buttons = existingWrapper.querySelectorAll(".js-remove-existing");
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var imageId = this.dataset.imageId;
        removeExisting(imageId, this);
      });
    });
  }

  function init() {
    bindProvinceCascade();
    bindFileInput();
    bindExistingDeletes();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
