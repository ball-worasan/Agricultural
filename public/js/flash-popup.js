(function () {
  "use strict";

  var COOLDOWN_SECONDS = 5;
  var AUTO_CLOSE_MS = 10000;

  // init ทุก popup ที่มีอยู่ (รองรับหลายตัว)
  function initPopup(popup) {
    if (!popup || popup.__flashInited) return;
    popup.__flashInited = true;

    var btn = popup.querySelector(".flash-popup__close");
    var count = popup.querySelector(".flash-popup__count");
    if (!btn) return;

    var remaining = COOLDOWN_SECONDS;

    function removeThisPopup() {
      if (popup && popup.parentNode) popup.parentNode.removeChild(popup);
    }

    // initial disabled
    btn.disabled = true;
    btn.setAttribute("aria-disabled", "true");
    if (count) count.textContent = "ปิดได้ใน " + remaining + " วินาที";

    var timer = setInterval(function () {
      remaining--;

      if (remaining > 0) {
        if (count) count.textContent = "ปิดได้ใน " + remaining + " วินาที";
        return;
      }

      clearInterval(timer);
      if (count) count.textContent = "";

      btn.disabled = false;
      btn.removeAttribute("aria-disabled");
      btn.classList.add("enabled");
    }, 1000);

    // auto-close
    setTimeout(removeThisPopup, AUTO_CLOSE_MS);

    // คลิกปุ่มปิด
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (btn.disabled) return;
      removeThisPopup();
    });

    // คลิกที่ตัว popup (กันคนกดพลาด)
    popup.addEventListener("click", function () {
      if (btn.disabled) return;
      removeThisPopup();
    });
  }

  // init ของที่มีอยู่แล้ว
  document.querySelectorAll(".flash-popup").forEach(initPopup);

  // เผื่อมี popup โผล่มาทีหลัง (ajax/partial render)
  var obs = new MutationObserver(function (mutations) {
    for (var i = 0; i < mutations.length; i++) {
      var m = mutations[i];
      for (var j = 0; j < m.addedNodes.length; j++) {
        var n = m.addedNodes[j];
        if (!(n instanceof HTMLElement)) continue;

        if (n.classList && n.classList.contains("flash-popup")) {
          initPopup(n);
        } else {
          var found = n.querySelectorAll
            ? n.querySelectorAll(".flash-popup")
            : [];
          found.forEach(initPopup);
        }
      }
    }
  });

  obs.observe(document.documentElement, { childList: true, subtree: true });
})();
