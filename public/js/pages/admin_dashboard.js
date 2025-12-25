(function () {
  "use strict";

  function selectAll(selector, root) {
    return Array.prototype.slice.call(
      (root || document).querySelectorAll(selector)
    );
  }

  function activateTab(name) {
    selectAll(".tab-content").forEach(function (el) {
      el.classList.remove("active");
    });
    selectAll(".tab-btn").forEach(function (el) {
      el.classList.remove("active");
    });
    var target = document.getElementById("tab-" + name);
    if (target) target.classList.add("active");
    var btn = selectAll(".admin-tabs .tab-btn").find(function (b) {
      return (b.textContent || "").indexOf(name) !== -1;
    });
    if (btn) btn.classList.add("active");
  }

  function wireTabs() {
    var tabs = selectAll(".admin-tabs .tab-btn");
    tabs.forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        var label = btn.getAttribute("onclick") || "";
        // Fallback: parse inline handler param 'switchTab(event, "<name>")'
        var match = label.match(/switchTab\(event,\s*'([^']+)'\)/);
        var name = match ? match[1] : btn.dataset.tab || "properties";
        // If inline function exists, let it handle; else do it here
        if (typeof window.switchTab === "function") return;
        e.preventDefault();
        activateTab(name);
      });
    });
  }

  function init() {
    wireTabs();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
