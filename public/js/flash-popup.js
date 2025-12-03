(function () {
  "use strict";

  var popup = document.querySelector(".flash-popup");
  if (!popup) {
    return;
  }

  var btn = popup.querySelector(".flash-popup__close");
  var count = popup.querySelector(".flash-popup__count");

  // กัน script พังถ้าไม่มีปุ่ม
  if (!btn) {
    return;
  }

  var COOLDOWN_SECONDS = 5; // รอให้กดปิดได้
  var AUTO_CLOSE_MS = 10000; // ปิดเองอัตโนมัติ (10 วินาที)

  var remaining = COOLDOWN_SECONDS;

  // initial: ปุ่มปิดยังใช้ไม่ได้
  btn.disabled = true;
  btn.style.cursor = "not-allowed";

  if (count) {
    count.textContent = "ปิดได้ใน " + remaining + " วินาที";
  }

  var timer = setInterval(function () {
    remaining--;

    if (remaining > 0) {
      if (count) {
        count.textContent = "ปิดได้ใน " + remaining + " วินาที";
      }
      return;
    }

    // หมดเวลา cooldown แล้ว
    clearInterval(timer);

    if (count) {
      count.textContent = "";
    }

    btn.disabled = false;
    btn.classList.add("enabled");
    btn.style.cursor = "pointer";
  }, 1000);

  btn.addEventListener("click", function () {
    if (btn.disabled) {
      return;
    }

    if (popup && popup.parentNode) {
      popup.parentNode.removeChild(popup);
    }
  });

  // auto-close หลังจากครบเวลา
  setTimeout(function () {
    if (popup && popup.parentNode) {
      popup.parentNode.removeChild(popup);
    }
  }, AUTO_CLOSE_MS);
})();
