(function () {
  "use strict";
  const App = (window.App = window.App || {});

  function safeCall(fn) {
    if (typeof fn === "function") fn();
  }

  function init() {
    safeCall(App.initNavbars);
    safeCall(App.initFlashPopups);
    safeCall(App.initLazyLoading);
    safeCall(App.initHomeFilters);
    safeCall(App.initSigninPage);
    safeCall(App.initPropertyImages);

    // skeleton bg สำหรับ lazy images
    document.querySelectorAll("img[data-src]").forEach((img) => {
      img.style.backgroundColor = "var(--skeleton-bg)";
      img.addEventListener("load", () => {
        img.style.backgroundColor = "transparent";
      });
    });

    App.initPropertyImages && App.initPropertyImages();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
