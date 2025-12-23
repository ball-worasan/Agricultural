(function () {
  "use strict";

  const statusFilter = document.getElementById("statusFilter");
  const textFilter = document.getElementById("textFilter");
  const cardItems = Array.from(
    document.querySelectorAll("#bookingCards .booking-card")
  );

  function applyFilters() {
    const s = statusFilter.value.toLowerCase();
    const t = textFilter.value.toLowerCase();

    cardItems.forEach((el) => {
      const status = (el.dataset.status || "").toLowerCase();
      const title = (el.dataset.title || "").toLowerCase();

      let show = true;
      if (s !== "all") {
        show = status === s;
      }
      if (show && t) {
        show = title.includes(t);
      }
      el.style.display = show ? "" : "none";
    });
  }

  if (statusFilter && textFilter) {
    statusFilter.addEventListener("change", applyFilters);
    textFilter.addEventListener("input", applyFilters);

    document.getElementById("resetFilters")?.addEventListener("click", () => {
      statusFilter.value = "all";
      textFilter.value = "";
      applyFilters();
    });
  }

  document.querySelectorAll(".action-btn").forEach((btn) => {
    btn.addEventListener("click", async () => {
      if (btn.classList.contains("disabled")) return;

      const action = btn.dataset.action;
      const id = btn.dataset.id;

      if (action === "cancel") {
        if (
          !confirm(
            `คุณต้องการยกเลิกการจอง #${id} ใช่หรือไม่?\n\nการยกเลิกจะไม่สามารถกู้คืนได้`
          )
        ) {
          return;
        }

        try {
          const body = new URLSearchParams({
            action: "cancel_booking",
            booking_id: String(id),
            csrf: window.APP?.csrf || "",
          });

          const res = await fetch(`?page=history&action=cancel_booking`, {
            method: "POST",
            body: body.toString(),
          });

          const data = await res.json();

          if (data.success) {
            alert(data.message || "ยกเลิกการจองสำเร็จ");
            window.location.reload();
          } else {
            alert("เกิดข้อผิดพลาด: " + (data.message || "ไม่สามารถยกเลิกได้"));
          }
        } catch (err) {
          console.error(err);
          alert("เกิดข้อผิดพลาดในการเชื่อมต่อ");
        }
      } else if (action === "view") {
        window.location = `?page=detail&id=${encodeURIComponent(id)}`;
      }
    });
  });
})();
