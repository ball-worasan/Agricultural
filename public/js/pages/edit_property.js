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

  async function removeExisting(imageId, buttonEl) {
    if (!imageId) return;
    if (!confirm("คุณต้องการลบรูปภาพนี้ใช่หรือไม่?")) return;

    // กันกดซ้ำ
    var prevDisabled = false;
    if (buttonEl) {
      prevDisabled = buttonEl.disabled;
      buttonEl.disabled = true;
    }

    try {
      var formData = new FormData();
      formData.append("action", "delete_image");
      formData.append("image_id", String(imageId));
      formData.append("area_id", String(areaId));

      var res = await fetch("?page=delete_property_image", {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      });

      var payload = null;
      var text = await res.text();
      try {
        payload = JSON.parse(text);
      } catch (_) {
        payload = null;
      }

      if (!res.ok) {
        var serverMsg =
          payload && payload.message ? payload.message : "HTTP " + res.status;
        throw new Error(serverMsg);
      }

      if (!payload || payload.success !== true) {
        var msg =
          payload && payload.message ? payload.message : "ลบรูปภาพไม่สำเร็จ";
        alert(msg);
        return;
      }

      var item = buttonEl ? buttonEl.closest(".existing-image-item") : null;
      if (item && item.parentNode) {
        item.parentNode.removeChild(item);
      }
      alert("ลบรูปภาพสำเร็จ");
    } catch (err) {
      console.error("Delete error:", err);
      alert(
        "ลบรูปภาพไม่สำเร็จ: " +
          (err && err.message ? err.message : "เกิดข้อผิดพลาด")
      );
    } finally {
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

  function initLazyLoad() {
    var imgs = document.querySelectorAll("img[data-src]");
    imgs.forEach(function (img) {
      img.src = img.getAttribute("data-src");
      img.removeAttribute("data-src");
    });
  }

  function init() {
    initLazyLoad();
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
