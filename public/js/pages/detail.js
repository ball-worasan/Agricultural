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
  if (!Array.isArray(images) || images.length === 0) {
    images = ["https://via.placeholder.com/800x600?text=No+Image"];
  }

  var areaId = container.dataset.areaId || "";
  var csrfToken = container.dataset.csrf || "";

  var currentImageIndex = 0;
  var datePickerInitialized = false;

  function lazyLoad() {
    var imgs = document.querySelectorAll("img[data-src]");
    imgs.forEach(function (img) {
      img.src = img.getAttribute("data-src");
      img.removeAttribute("data-src");
    });
  }

  function updateMainImage() {
    var main = document.getElementById("mainImage");
    var counter = document.getElementById("imageCounter");
    if (!main || !images.length) return;

    main.src = images[currentImageIndex];
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
    var thumbs = document.querySelectorAll("#thumbs .thumb");
    thumbs.forEach(function (t, i) {
      t.classList.toggle("active", i === currentImageIndex);
    });
  }

  function bindGallery() {
    var mainImg = document.getElementById("mainImage");
    if (mainImg) {
      mainImg.addEventListener("click", function () {
        changeImage(1);
      });
    }

    var navs = document.querySelectorAll(".js-gallery-nav");
    navs.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var dir = parseInt(this.dataset.direction, 10) || 0;
        changeImage(dir);
      });
    });

    var thumbs = document.querySelectorAll(".js-thumb");
    thumbs.forEach(function (t) {
      t.addEventListener("click", function () {
        var idx = parseInt(this.dataset.index, 10);
        if (!Number.isNaN(idx)) {
          currentImageIndex = idx;
          updateMainImage();
        }
      });
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "ArrowLeft") changeImage(-1);
      if (e.key === "ArrowRight") changeImage(1);
    });
  }

  /* ---------- Booking ---------- */

  function showBookingForm() {
    var boxTitle = document.getElementById("boxTitle");
    var userInfo = document.getElementById("userBookingInfo");
    var specsBox = document.getElementById("specsBox");
    var descBox = document.getElementById("descriptionBox");
    var dateSection = document.getElementById("dateSection");
    var statusBox = document.getElementById("statusBox");
    var normalButtons = document.getElementById("normalButtons");
    var bookingActions = document.getElementById("bookingActions");

    if (!boxTitle) return;

    boxTitle.textContent = "จองพื้นที่การเกษตร";
    if (userInfo) userInfo.style.display = "block";
    if (specsBox) specsBox.style.display = "none";
    if (descBox) descBox.style.display = "none";
    if (dateSection) dateSection.style.display = "block";
    if (statusBox) statusBox.style.display = "none";
    if (normalButtons) normalButtons.style.display = "none";
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

    var daySelect = document.getElementById("daySelect");
    var monthSelect = document.getElementById("monthSelect");
    var yearSelect = document.getElementById("yearSelect");
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
    var daySelect = document.getElementById("daySelect");
    var monthSelect = document.getElementById("monthSelect");
    var yearSelect = document.getElementById("yearSelect");
    if (!daySelect || !monthSelect || !yearSelect) return;

    var month = parseInt(monthSelect.value, 10);
    var year = parseInt(yearSelect.value, 10);
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var currentDay = parseInt(daySelect.value, 10) || 1;

    daySelect.innerHTML = "";
    for (var d = 1; d <= daysInMonth; d++) {
      var option = document.createElement("option");
      option.value = String(d);
      option.textContent = String(d);
      daySelect.appendChild(option);
    }
    daySelect.value = String(
      currentDay <= daysInMonth ? currentDay : daysInMonth
    );
  }

  function updateDatePreview() {
    var daySelect = document.getElementById("daySelect");
    var monthSelect = document.getElementById("monthSelect");
    var yearSelect = document.getElementById("yearSelect");
    var preview = document.getElementById("datePreview");
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
    var dayEl = document.getElementById("daySelect");
    var monthEl = document.getElementById("monthSelect");
    var yearEl = document.getElementById("yearSelect");

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
      encodeURIComponent(year) +
      "&csrf=" +
      encodeURIComponent(csrfToken);
  }

  function cancelBooking() {
    var boxTitle = document.getElementById("boxTitle");
    var userInfo = document.getElementById("userBookingInfo");
    var specsBox = document.getElementById("specsBox");
    var descBox = document.getElementById("descriptionBox");
    var dateSection = document.getElementById("dateSection");
    var statusBox = document.getElementById("statusBox");
    var normalButtons = document.getElementById("normalButtons");
    var bookingActions = document.getElementById("bookingActions");

    if (boxTitle) boxTitle.textContent = "ข้อมูลพื้นที่";
    if (userInfo) userInfo.style.display = "none";
    if (specsBox) specsBox.style.display = "block";
    if (descBox) descBox.style.display = "block";
    if (dateSection) dateSection.style.display = "none";
    if (statusBox) statusBox.style.display = "flex";
    if (normalButtons) normalButtons.style.display = "block";
    if (bookingActions) bookingActions.style.display = "none";
  }

  function bindBookingButtons() {
    var showBtn = document.querySelector(".js-show-booking");
    var confirmBtn = document.querySelector(".js-confirm-booking");
    var cancelBtn = document.querySelector(".js-cancel-booking");

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
