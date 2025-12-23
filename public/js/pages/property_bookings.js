(function() {
  'use strict';

  window.showSlipModal = function(imageUrl) {
    const modal = document.getElementById('slipModal');
    const img = document.getElementById('slipModalImage');
    if (modal && img) {
      img.src = imageUrl;
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  };

  window.closeSlipModal = function(event) {
    if (event.target.id === 'slipModal' || event.currentTarget.classList.contains('modal-close')) {
      const modal = document.getElementById('slipModal');
      if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
      }
    }
  };

  window.approveBooking = async function(bookingId) {
    if (!confirm('ยืนยันการอนุมัติการจองนี้?\n\nพื้นที่จะถูกอัปเดตเป็น "ติดจอง" และการจองอื่น ๆ จะถูกปฏิเสธอัตโนมัติ')) {
      return;
    }

    try {
      const body = new URLSearchParams({
        action: 'approve',
        booking_id: String(bookingId)
      });

      const res = await fetch(window.location.href, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      });

      const data = await res.json();

      if (data.success) {
        alert('' + data.message);
        window.location.reload();
      } else {
        alert('⚠️ ' + (data.message || 'เกิดข้อผิดพลาด'));
      }
    } catch (err) {
      console.error('approveBooking error:', err);
      alert('เกิดข้อผิดพลาดในการส่งข้อมูล กรุณาลองใหม่อีกครั้ง');
    }
  };

  window.rejectBooking = async function(bookingId) {
    if (!confirm('ยืนยันการปฏิเสธการจองนี้?')) {
      return;
    }

    try {
      const body = new URLSearchParams({
        action: 'reject',
        booking_id: String(bookingId),
        csrf: window.APP?.csrf || ''
      });

      const res = await fetch(window.location.href, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      });

      const data = await res.json();

      if (data.success) {
        alert(data.message || 'ปฏิเสธการจองสำเร็จ');
        window.location.reload();
      } else {
        alert('เกิดข้อผิดพลาด: ' + (data.message || 'ไม่สามารถปฏิเสธได้'));
      }
    } catch (err) {
      console.error('rejectBooking error:', err);
      alert('เกิดข้อผิดพลาดในการส่งข้อมูล กรุณาลองใหม่อีกครั้ง');
    }
  };

  // จัดการรูปภาพที่โหลดไม่ได้
  document.querySelectorAll('.user-avatar img').forEach(function(img) {
    img.addEventListener('error', function() {
      // ถ้ารูปโหลดไม่ได้ ให้ใช้ ui-avatars
      const alt = this.getAttribute('alt') || 'User';
      this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(alt) + '&size=60&background=667eea&color=fff&bold=true';
    });
  });
})();
