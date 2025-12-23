(function () {
  "use strict";

  const App = (window.App = window.App || {});

  App.initHomeFilters = function initHomeFilters() {
    const pageRoot = document.querySelector('[data-page="home"]');
    if (!pageRoot) return;

    const provinceSelect = document.getElementById("province");
    const priceSelect = document.getElementById("price");
    const sortSelect = document.getElementById("sort");
    const districtSelect = document.getElementById("district");

    const container = document.getElementById("itemsContainer");
    const emptyEl = document.getElementById("homeEmptyState");
    if (!container) return;

    let globalSearchText = "";

    const parsePriceRange = (value) => {
      const parts = String(value || "").split("-");
      if (parts.length !== 2) return null;
      const min = parseInt(parts[0], 10);
      const max = parseInt(parts[1], 10);
      if (!Number.isFinite(min) || !Number.isFinite(max)) return null;
      return { min, max };
    };

    const getQuery = () => {
      const queryInput = document.getElementById("globalSearch");
      const q = ((queryInput && queryInput.value) || globalSearchText || "")
        .trim()
        .toLowerCase();
      return q;
    };

    // แคชข้อมูลการ์ดครั้งเดียว
    const items = Array.from(container.querySelectorAll(".item-card")).map(
      (el) => {
        const title = (
          el.querySelector(".item-title")?.textContent || ""
        ).toLowerCase();
        const location = (
          el.querySelector(".item-location")?.textContent || ""
        ).toLowerCase();

        return {
          el,
          province: (el.getAttribute("data-province") || "").trim(),
          district: (el.getAttribute("data-district") || "").trim(),
          districtId:
            parseInt(el.getAttribute("data-district-id") || "0", 10) || 0,
          price: parseInt(el.getAttribute("data-price") || "0", 10) || 0,
          date: el.getAttribute("data-date") || "", // รูปแบบ YYYY-MM-DD
          dateTs:
            Date.parse(`${el.getAttribute("data-date") || ""}T00:00:00`) || 0,
          tags: (el.getAttribute("data-tags") || "")
            .toLowerCase()
            .split(",")
            .map((t) => t.trim())
            .filter(Boolean),
          title,
          location,
        };
      }
    );

    const apply = () => {
      try {
        if (items.length === 0) {
          if (emptyEl) emptyEl.hidden = false;
          return;
        }

        const provinceId = provinceSelect ? provinceSelect.value.trim() : "";
        const provinceName =
          provinceId && provinceSelect
            ? provinceSelect.selectedOptions[0]?.getAttribute("data-name") || ""
            : "";
        const priceFilter = priceSelect ? priceSelect.value : "";
        const sortFilter = sortSelect ? sortSelect.value : "price-low";
        const districtFilterId = districtSelect
          ? parseInt(districtSelect.value.trim(), 10) || 0
          : 0;

        const q = getQuery();
        const range = priceFilter ? parsePriceRange(priceFilter) : null;

        const visible = [];

        for (const it of items) {
          let ok = true;

          if (q && !it.title.includes(q) && !it.location.includes(q))
            ok = false;
          if (ok && provinceName && it.province !== provinceName) ok = false;
          if (ok && range && (it.price < range.min || it.price > range.max))
            ok = false;
          if (ok && districtFilterId && it.districtId !== districtFilterId)
            ok = false;

          it.el.style.display = ok ? "" : "none";
          if (ok) visible.push(it);
        }

        if (emptyEl) emptyEl.hidden = visible.length !== 0;

        visible.sort((a, b) => {
          switch (sortFilter) {
            case "price-high":
              return b.price - a.price;
            case "price-low":
            default:
              return a.price - b.price;
          }
        });
      } catch (err) {
        console.error("Home filter error:", err);
      }
    };

    // ดีบาวซ์ลดงานซ้ำ
    let t = null;
    const scheduleApply = () => {
      if (t) clearTimeout(t);
      t = setTimeout(apply, 80);
    };

    // Province-District cascade
    const setupProvinceCascade = () => {
      if (!provinceSelect || !districtSelect) return;

      // สำรองตัวเลือกอำเภอทั้งหมดครั้งแรก
      const allDistrictOptions = Array.from(
        districtSelect.querySelectorAll("option[data-province-id]")
      ).map((opt) => ({
        value: opt.value,
        text: opt.textContent,
        provinceId: String(opt.getAttribute("data-province-id")).trim(),
      }));

      const updateDistricts = () => {
        const selectedProvinceId = String(provinceSelect.value || "").trim();
        const placeholder = districtSelect.querySelector("option[value='']");

        // ลบตัวเลือกอำเภอ ยกเว้น placeholder
        Array.from(districtSelect.options)
          .slice(1)
          .forEach((opt) => opt.remove());

        if (!selectedProvinceId) {
          // ไม่ได้เลือกจังหวัด - ปิดการใช้งาน
          districtSelect.disabled = true;
          if (placeholder) placeholder.textContent = "เลือกจังหวัดก่อน";
          districtSelect.value = "";
        } else {
          // เลือกจังหวัดแล้ว - เปิดการใช้งาน และ เพิ่มอำเภอที่ตรงกัน
          districtSelect.disabled = false;
          if (placeholder) placeholder.textContent = "ทั้งหมด";
          districtSelect.value = "";

          const matchingDistricts = allDistrictOptions.filter(
            (d) => d.provinceId === selectedProvinceId
          );

          matchingDistricts.forEach((optData) => {
            const newOption = document.createElement("option");
            newOption.value = optData.value;
            newOption.textContent = optData.text;
            newOption.setAttribute("data-province-id", optData.provinceId);
            districtSelect.appendChild(newOption);
          });

          if (matchingDistricts.length === 0 && placeholder) {
            placeholder.textContent = "ไม่มีอำเภอในจังหวัดนี้";
          }
        }

        scheduleApply();
      };

      provinceSelect.addEventListener("change", updateDistricts);
      updateDistricts(); // ตั้งค่าเริ่มต้น
    };

    setupProvinceCascade();

    [districtSelect, priceSelect, sortSelect].forEach((el) => {
      if (!el) return;
      el.addEventListener("change", scheduleApply);
    });

    window.addEventListener("global:search-change", (event) => {
      try {
        const val =
          event && event.detail && typeof event.detail.value === "string"
            ? event.detail.value
            : "";
        globalSearchText = String(val).toLowerCase();
        scheduleApply();
      } catch (err) {
        console.error("Search event handler error:", err);
      }
    });

    apply();
  };

  // เรียกใช้อัตโนมัติเมื่อ DOM พร้อม
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", App.initHomeFilters);
  } else {
    App.initHomeFilters();
  }
})();
