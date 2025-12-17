(function () {
  "use strict";

  // ---------- Config from DOM ----------
  // ใส่ data-* ใน <body> หรือใน container ก็ได้
  // ตัวอย่าง: <body data-csrf="..." data-property-id="123" data-delete-endpoint="?page=delete_property_image">
  var root = document.body;

  var csrf = root ? root.getAttribute("data-csrf") : "";
  var propertyId = root
    ? parseInt(root.getAttribute("data-property-id") || "0", 10)
    : 0;
  var deleteEndpoint = root
    ? root.getAttribute("data-delete-endpoint") || "?page=delete_property_image"
    : "?page=delete_property_image";

  // ---------- New images preview (add/edit) ----------
  var input = document.getElementById("images");
  var preview = document.getElementById("imagePreview");

  var selectedFiles = [];

  function syncInput() {
    if (!input) return;
    var dt = new DataTransfer();
    selectedFiles.forEach(function (f) {
      dt.items.add(f);
    });
    input.files = dt.files;
  }

  function renderPreview() {
    if (!preview) return;
    preview.innerHTML = "";

    selectedFiles.forEach(function (file, idx) {
      var reader = new FileReader();
      reader.onload = function (e) {
        var div = document.createElement("div");
        div.className = "preview-item";
        div.innerHTML =
          '<img src="' +
          e.target.result +
          '" alt="Preview">' +
          '<button type="button" class="remove-image" data-index="' +
          idx +
          '">×</button>';

        preview.appendChild(div);

        var btn = div.querySelector(".remove-image");
        if (btn) {
          btn.addEventListener("click", function () {
            var i = parseInt(btn.getAttribute("data-index") || "0", 10);
            if (i < 0 || i >= selectedFiles.length) return;
            selectedFiles.splice(i, 1);
            renderPreview();
            syncInput();
          });
        }
      };
      reader.readAsDataURL(file);
    });
  }

  function getExistingCount() {
    return document.querySelectorAll(".existing-image-item").length;
  }

  function onInputChange(e) {
    var files = Array.prototype.slice.call((e.target && e.target.files) || []);
    if (!files.length) return;

    var existingCount = getExistingCount();
    var total = existingCount + selectedFiles.length + files.length;
    if (total > 10) {
      alert(
        "สามารถมีรูปภาพได้สูงสุด 10 รูป (ปัจจุบันมี " +
          existingCount +
          " รูปเดิม)"
      );
      // reset file input selection (so user can re-pick)
      input.value = "";
      return;
    }

    files.forEach(function (file) {
      if (file.size > 5 * 1024 * 1024) {
        alert("ไฟล์ " + file.name + " มีขนาดใหญ่เกิน 5MB");
        return;
      }
      selectedFiles.push(file);
    });

    renderPreview();
    syncInput();
  }

  if (input) {
    input.addEventListener("change", onInputChange);
  }

  // ---------- Delete existing image (edit only) ----------
  // ต้องมี element .existing-image-item[data-image-id="..."]
  // และปุ่ม .remove-existing-image
  function removeExistingImage(imageId) {
    if (!imageId || imageId <= 0) return;
    if (!propertyId || propertyId <= 0) {
      alert("หา property_id ไม่เจอ (ตั้ง data-property-id ให้ด้วย)");
      return;
    }
    if (!csrf) {
      alert("CSRF หาย (ตั้ง data-csrf ให้ด้วย)");
      return;
    }

    if (!confirm("คุณต้องการลบรูปภาพนี้ใช่หรือไม่?")) return;

    var fd = new FormData();
    fd.append("action", "delete_image");
    fd.append("image_id", String(imageId));
    fd.append("property_id", String(propertyId));
    fd.append("csrf", csrf);

    fetch(deleteEndpoint, { method: "POST", body: fd })
      .then(function (res) {
        return res.text().then(function (txt) {
          var data = null;
          try {
            data = txt ? JSON.parse(txt) : null;
          } catch (e) {}

          if (!res.ok || !data || !data.success) {
            var msg =
              data && data.message ? data.message : "ไม่สามารถลบรูปภาพได้";
            throw new Error(msg);
          }
          return data;
        });
      })
      .then(function (data) {
        var item = document.querySelector(
          '.existing-image-item[data-image-id="' + imageId + '"]'
        );
        if (item && item.parentNode) item.parentNode.removeChild(item);
        alert(data.message || "ลบรูปภาพสำเร็จ");
      })
      .catch(function (err) {
        console.error(err);
        alert("เกิดข้อผิดพลาด: " + (err.message || "เชื่อมต่อไม่ได้"));
      });
  }

  // delegate click
  document.addEventListener("click", function (e) {
    var btn =
      e.target && e.target.closest
        ? e.target.closest(".remove-existing-image")
        : null;
    if (!btn) return;

    var item = btn.closest(".existing-image-item");
    if (!item) return;

    var imageId = parseInt(item.getAttribute("data-image-id") || "0", 10);
    removeExistingImage(imageId);
  });
})();
