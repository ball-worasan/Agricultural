(function () {
  "use strict";
  const App = (window.App = window.App || {});

  App.initNavbars = function initNavbars() {
    document.querySelectorAll(".navbar[data-nav-id]").forEach((nav) => {
      if (nav.__navbarInited) return;
      nav.__navbarInited = true;

      const accountBtn = nav.querySelector('[data-account-btn="true"]');
      const accountMenu = nav.querySelector('[data-account-menu="true"]');
      const searchInput = nav.querySelector("#globalSearch");

      if (accountBtn && accountMenu) {
        const menuItems = Array.from(
          accountMenu.querySelectorAll('[data-menu-item="true"], .menu-btn')
        );

        const openMenu = () => {
          accountMenu.removeAttribute("hidden");
          accountMenu.classList.add("is-open");
          accountBtn.setAttribute("aria-expanded", "true");
        };

        const closeMenu = () => {
          accountMenu.setAttribute("hidden", "");
          accountMenu.classList.remove("is-open");
          accountBtn.setAttribute("aria-expanded", "false");
        };

        const isMenuOpen = () => !accountMenu.hasAttribute("hidden");

        const focusFirstItem = () => {
          const first = menuItems.find(
            (el) => el && typeof el.focus === "function"
          );
          first && first.focus();
        };

        const focusLastItem = () => {
          const last = [...menuItems]
            .reverse()
            .find((el) => el && typeof el.focus === "function");
          last && last.focus();
        };

        const focusNextItem = (current) => {
          if (!menuItems.length) return;
          const index = menuItems.indexOf(current);
          const nextIndex = (index + 1) % menuItems.length;
          menuItems[nextIndex]?.focus?.();
        };

        const focusPrevItem = (current) => {
          if (!menuItems.length) return;
          const index = menuItems.indexOf(current);
          const prevIndex = (index - 1 + menuItems.length) % menuItems.length;
          menuItems[prevIndex]?.focus?.();
        };

        accountBtn.addEventListener("click", (e) => {
          e.stopPropagation();
          if (isMenuOpen()) closeMenu();
          else {
            openMenu();
            focusFirstItem();
          }
        });

        accountBtn.addEventListener("keydown", (e) => {
          if (e.key === "ArrowDown" || e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            if (!isMenuOpen()) openMenu();
            focusFirstItem();
          }
        });

        document.addEventListener("click", (e) => {
          if (!isMenuOpen()) return;
          if (!accountMenu.contains(e.target) && !accountBtn.contains(e.target))
            closeMenu();
        });

        document.addEventListener("keydown", (e) => {
          if (e.key === "Escape" && isMenuOpen()) {
            closeMenu();
            accountBtn.focus();
          }
        });

        accountMenu.addEventListener("keydown", (e) => {
          const currentItem =
            e.target.closest('[data-menu-item="true"]') ||
            e.target.closest(".menu-btn");
          if (!currentItem) return;

          switch (e.key) {
            case "ArrowDown":
              e.preventDefault();
              focusNextItem(currentItem);
              break;
            case "ArrowUp":
              e.preventDefault();
              focusPrevItem(currentItem);
              break;
            case "Home":
              e.preventDefault();
              focusFirstItem();
              break;
            case "End":
              e.preventDefault();
              focusLastItem();
              break;
            case "Escape":
              e.preventDefault();
              closeMenu();
              accountBtn.focus();
              break;
          }
        });

        accountMenu.addEventListener("click", (e) => {
          const disabledItem = e.target.closest('[data-disabled="true"]');
          if (disabledItem) {
            e.preventDefault();
            App.showToast(
              "แจ้งเตือน",
              "ฟีเจอร์นี้จะพร้อมใช้งานเร็วๆ นี้",
              "warning",
              2500
            );
            return;
          }

          const menuItem =
            e.target.closest('[data-menu-item="true"]') ||
            e.target.closest(".menu-btn");
          if (menuItem && !disabledItem) closeMenu();
        });
      }

      // ส่งอีเวนต์ค้นหากลาง
      if (searchInput) {
        let searchTimeout = null;

        const emitSearchEvent = (value) => {
          window.dispatchEvent(
            new CustomEvent("global:search-change", { detail: { value } })
          );
        };

        searchInput.addEventListener("input", (e) => {
          const value = (e.target.value || "").trim();
          if (searchTimeout) clearTimeout(searchTimeout);
          searchTimeout = setTimeout(() => emitSearchEvent(value), 250);
        });
      }
    });
  };
})();
