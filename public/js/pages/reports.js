(function () {
  "use strict";
  function init() {
    // Reports page bootstrap; wire filters/charts here later
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
