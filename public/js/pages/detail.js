(function () {
  "use strict";

  var container = document.querySelector(".detail-container");
  if (!container) return;

  var images = [];
  try {
    images = JSON.parse(container.dataset.images || "[]") || [];
  } catch (e) {
    images = [];
  }
  // ห้ามสร้าง fallback placeholder - ให้ PHP จัดการเอง
  if (!Array.isArray(images) || images.length === 0) {
    images = [];
  }

  var areaId = container.dataset.areaId || "";
  var isAdmin = container.dataset.isAdmin === "1";

  var currentImageIndex = 0;
  var datePickerInitialized = false;

  // Cache frequently accessed elements
  var els = {};
  function getEl(id) {
    if (!els[id]) els[id] = document.getElementById(id);
    return els[id];
  }

  function setDisplay(el, value) {
    if (el) el.style.display = value;
  }

  function lazyLoad() {
    var imgs = container.querySelectorAll("img[data-src]");
    imgs.forEach(function (img) {
      var src = img.getAttribute("data-src");
      if (src) {
        img.src = src;
        img.removeAttribute("data-src");

        // Handle image load errors
        img.addEventListener(
          "error",
          function onErr() {
            var svgPlaceholder =
              'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f0f0f0" width="400" height="300"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-size="24"%3ENo Image%3C/text%3E%3C/svg%3E';
            this.src = svgPlaceholder;
          },
          { once: true }
        );
      }
    });
  }

  function updateMainImage() {
    var main = getEl("mainImage");
    var counter = getEl("imageCounter");
    if (!main || !images.length) return;

    var src = images[currentImageIndex];
    main.src = src;

    // Handle image load errors for main image
    main.addEventListener(
      "error",
      function onErr() {
        var svgPlaceholder =
          'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f0f0f0" width="400" height="300"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-size="24"%3ENo Image%3C/text%3E%3C/svg%3E';
        this.src = svgPlaceholder;
      },
      { once: true }
    );

    if (counter) {
      counter.textContent = currentImageIndex + 1 + " / " + images.length;
    }
    updateThumbActive();
  }

  function changeImage(direction) {
    if (!images.length) return;
    currentImageIndex += direction;
    if (currentImageIndex >= images.length) currentImageIndex = 0;
    if (currentImageIndex < 0) currentImageIndex = images.length - 1;
    updateMainImage();
  }

  function updateThumbActive() {
    var thumbs = container.querySelectorAll("#thumbs .thumb");
    thumbs.forEach(function (t, i) {
      t.classList.toggle("active", i === currentImageIndex);
    });
  }

  function bindGallery() {
    var mainImg = getEl("mainImage");
    if (mainImg) {
      mainImg.addEventListener("click", function () {
        changeImage(1);
      });
    }

    var navs = container.querySelectorAll(".js-gallery-nav");
    navs.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var dir = parseInt(this.dataset.direction, 10) || 0;
        changeImage(dir);
      });
    });

    var thumbs = container.querySelectorAll(".js-thumb");
    thumbs.forEach(function (t) {
      t.addEventListener("click", function () {
        var idx = parseInt(this.dataset.index, 10);
        if (!Number.isNaN(idx)) {
          currentImageIndex = idx;
          updateMainImage();
        }
โ      });
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "ArrowLeft") changeImage(-1);
      if (e.key === "ArrowRight") changeImage(1);
    });
  }

  /* ---------- Booking ---------- */

  function showBookingForm() {
    if (isAdmin) {
      alert("ผู้ดูแลระบบไม่สามารถจองได้");
      return;
    }

    var boxTitle = getEl("boxTitle");
    var userInfo = getEl("userBookingInfo");
    var specsBox = getEl("specsBox");
    var descBox = getEl("descriptionBox");
    var dateSection = getEl("dateSection");
    var statusBox = getEl("statusBox");
    var normalButtons = getEl("normalButtons");
    var bookingActions = getEl("bookingActions");

    if (!boxTitle) return;

    boxTitle.textContent = "จองพื้นที่การเกษตร";
    setDisplay(userInfo, "block");
    setDisplay(specsBox, "none");
    setDisplay(descBox, "none");
    setDisplay(dateSection, "block");
    setDisplay(statusBox, "none");
    setDisplay(normalButtons, "none");
    if (bookingActions) bookingActions.style.display = "flex";

    initializeDatePicker();
  }

  function initializeDatePicker() {
    if (datePickerInitialized) {
      updateDaysInMonth();
      updateDatePreview();
      return;
    }
    datePickerInitialized = true;

    var daySelect = getEl("daySelect");
    var monthSelect = getEl("monthSelect");
    var yearSelect = getEl("yearSelect");
    if (!daySelect || !monthSelect || !yearSelect) return;

    var tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);

    daySelect.value = String(tomorrow.getDate());
    monthSelect.value = String(tomorrow.getMonth());
    yearSelect.value = String(tomorrow.getFullYear());

    daySelect.addEventListener("change", updateDatePreview);
    monthSelect.addEventListener("change", function () {
      updateDaysInMonth();
      updateDatePreview();
    });
    yearSelect.addEventListener("change", function () {
      updateDaysInMonth();
      updateDatePreview();
    });

    updateDaysInMonth();
    updateDatePreview();
  }

  function updateDaysInMonth() {
    var daySelect = getEl("daySelect");
    var monthSelect = getEl("monthSelect");
    var yearSelect = getEl("yearSelect");
    if (!daySelect || !monthSelect || !yearSelect) return;

    var month = parseInt(monthSelect.value, 10);
    var year = parseInt(yearSelect.value, 10);
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var currentDay = parseInt(daySelect.value, 10) || 1;

    // Rebuild options with a fragment to reduce reflow
    var frag = document.createDocumentFragment();
    for (var d = 1; d <= daysInMonth; d++) {
      var option = document.createElement("option");
      option.value = String(d);
      option.textContent = String(d);
      frag.appendChild(option);
    }
    daySelect.innerHTML = "";
    daySelect.appendChild(frag);
    daySelect.value = String(
      currentDay <= daysInMonth ? currentDay : daysInMonth
    );
  }

  function updateDatePreview() {
    var daySelect = getEl("daySelect");
    var monthSelect = getEl("monthSelect");
    var yearSelect = getEl("yearSelect");
    var preview = getEl("datePreview");
    if (!daySelect || !monthSelect || !yearSelect || !preview) return;

    var day = parseInt(daySelect.value, 10);
    var monthIndex = parseInt(monthSelect.value, 10);
    var year = parseInt(yearSelect.value, 10);

    var thaiMonths = [
      "มกราคม",
      "กุมภาพันธ์",
      "มีนาคม",
      "เมษายน",
      "พฤษภาคม",
      "มิถุนายน",
      "กรกฎาคม",
      "สิงหาคม",
      "กันยายน",
      "ตุลาคม",
      "พฤศจิกายน",
      "ธันวาคม",
    ];

    var selectedDate = new Date(year, monthIndex, day);
    var today = new Date();
    today.setHours(0, 0, 0, 0);

    preview.style.display = "block";

    if (selectedDate <= today) {
      preview.innerHTML =
        '<span class="error-text">กรุณาเลือกวันที่ถัดจากวันนี้อย่างน้อย 1 วัน</span>';
      return;
    }

    var buddhistYear = year + 543;
    var dateStr = day + " " + thaiMonths[monthIndex] + " " + buddhistYear;

    preview.innerHTML =
      '<span class="preview-text">คุณเลือกวันที่ ' + dateStr + "</span>";
  }

  function confirmBooking() {
    var dayEl = getEl("daySelect");
    var monthEl = getEl("monthSelect");
    var yearEl = getEl("yearSelect");

    if (!dayEl || !monthEl || !yearEl) return;

    var day = dayEl.value;
    var month = monthEl.value;
    var year = yearEl.value;

    if (!day || !month || !year) return;

    var thaiMonths = [
      "มกราคม",
      "กุมภาพันธ์",
      "มีนาคม",
      "เมษายน",
      "พฤษภาคม",
      "มิถุนายน",
      "กรกฎาคม",
      "สิงหาคม",
      "กันยายน",
      "ตุลาคม",
      "พฤศจิกายน",
      "ธันวาคม",
    ];
    var buddhistYear = parseInt(year, 10) + 543;
    var dateStr =
      day + " " + thaiMonths[parseInt(month, 10)] + " " + buddhistYear;

    if (
      !confirm(
        "คุณต้องการจองพื้นที่นี้และนัดหมายวันที่ " + dateStr + " ใช่หรือไม่?"
      )
    )
      return;

    window.location.href =
      "?page=payment&id=" +
      encodeURIComponent(areaId) +
      "&day=" +
      encodeURIComponent(day) +
      "&month=" +
      encodeURIComponent(month) +
      "&year=" +
      encodeURIComponent(year);
  }

  function cancelBooking() {
    var boxTitle = getEl("boxTitle");
    var userInfo = getEl("userBookingInfo");
    var specsBox = getEl("specsBox");
    var descBox = getEl("descriptionBox");
    var dateSection = getEl("dateSection");
    var statusBox = getEl("statusBox");
    var normalButtons = getEl("normalButtons");
    var bookingActions = getEl("bookingActions");

    if (boxTitle) boxTitle.textContent = "ข้อมูลพื้นที่";
    setDisplay(userInfo, "none");
    setDisplay(specsBox, "block");
    setDisplay(descBox, "block");
    setDisplay(dateSection, "none");
    if (statusBox) statusBox.style.display = "flex";
    setDisplay(normalButtons, "block");
    setDisplay(bookingActions, "none");
  }

  function bindBookingButtons() {
    var showBtn = container.querySelector(".js-show-booking");
    var confirmBtn = container.querySelector(".js-confirm-booking");
    var cancelBtn = container.querySelector(".js-cancel-booking");

    if (showBtn) showBtn.addEventListener("click", showBookingForm);
    if (confirmBtn) confirmBtn.addEventListener("click", confirmBooking);
    if (cancelBtn) cancelBtn.addEventListener("click", cancelBooking);
  }

  function init() {
    lazyLoad();
    bindGallery();
    bindBookingButtons();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
