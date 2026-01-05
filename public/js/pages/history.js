(function () {
  "use strict";

  const root = document.querySelector(".history-container");

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
      if (btn.classList.contains("disabled") || btn.disabled) return;

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

        btn.disabled = true;
        btn.classList.add("disabled");

        try {
          const body = new URLSearchParams({
            action: "cancel_booking",
            booking_id: String(id),
          });

          const res = await fetch(`?page=history&action=cancel_booking`, {
            method: "POST",
            headers: {
              "Content-Type":
                "application/x-www-form-urlencoded; charset=UTF-8",
              "X-Requested-With": "XMLHttpRequest",
              Accept: "application/json",
            },
            body: body.toString(),
          });

          let data;
          const text = await res.text();
          try {
            data = JSON.parse(text);
          } catch {
            data = {
              success: false,
              message: text || "ไม่สามารถประมวลผลคำตอบได้",
            };
          }

          if (res.ok && data?.success) {
            alert(data.message || "ยกเลิกการจองสำเร็จ");
            window.location.reload();
            return;
          }

          const msg = data?.message || `คำขอล้มเหลว (${res.status})`;
          alert("เกิดข้อผิดพลาด: " + msg);
        } catch (err) {
          console.error(err);
          alert("เกิดข้อผิดพลาดในการเชื่อมต่อ");
        } finally {
          btn.disabled = false;
          btn.classList.remove("disabled");
        }
      } else if (action === "viewContract") {
        window.location = `?page=contract&booking_id=${encodeURIComponent(id)}`;
      }
    });
  });
})();
