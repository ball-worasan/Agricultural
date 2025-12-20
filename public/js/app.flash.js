(function () {
  "use strict";
  const App = (window.App = window.App || {});
  const { FLASH_COOLDOWN_SECONDS, FLASH_AUTO_CLOSE_MS } = App.constants;

  function initFlashPopup(popup) {
    if (!popup || popup.__flashInited) return;
    popup.__flashInited = true;

    const btn = popup.querySelector(".flash-popup__close");
    const count = popup.querySelector(".flash-popup__count");
    if (!btn) return;

    let remaining = FLASH_COOLDOWN_SECONDS;

    const removeThisPopup = () => {
      if (popup && popup.parentNode) popup.parentNode.removeChild(popup);
    };

    btn.disabled = true;
    btn.setAttribute("aria-disabled", "true");
    if (count) count.textContent = `ปิดได้ใน ${remaining} วินาที`;

    const timer = setInterval(() => {
      remaining--;
      if (remaining > 0) {
        if (count) count.textContent = `ปิดได้ใน ${remaining} วินาที`;
        return;
      }
      clearInterval(timer);
      if (count) count.textContent = "";
      btn.disabled = false;
      btn.removeAttribute("aria-disabled");
      btn.classList.add("enabled");
    }, 1000);

    setTimeout(removeThisPopup, FLASH_AUTO_CLOSE_MS);

    btn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (btn.disabled) return;
      removeThisPopup();
    });

    popup.addEventListener("click", () => {
      if (btn.disabled) return;
      removeThisPopup();
    });
  }

  App.initFlashPopups = function initFlashPopups() {
    document.querySelectorAll(".flash-popup").forEach(initFlashPopup);

    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (!(node instanceof HTMLElement)) return;
          if (node.classList && node.classList.contains("flash-popup")) {
            initFlashPopup(node);
          } else {
            const found = node.querySelectorAll
              ? node.querySelectorAll(".flash-popup")
              : [];
            found.forEach(initFlashPopup);
          }
        });
      });
    });

    observer.observe(document.documentElement, {
      childList: true,
      subtree: true,
    });
  };
})();
