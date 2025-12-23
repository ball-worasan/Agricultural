(function () {
  'use strict';

  function submitDelete(areaId, areaName, csrfToken) {
    var message = 'คุณต้องการลบ "' + String(areaName || '') + '" ใช่หรือไม่?\n\nการลบจะไม่สามารถกู้คืนได้';
    if (!confirm(message)) return;

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '?page=delete_property';

    var idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'area_id';
    idInput.value = String(areaId);
    form.appendChild(idInput);

    var csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);

    document.body.appendChild(form);
    form.submit();
  }

  function bindDeleteButtons() {
    var container = document.querySelector('.my-properties-container');
    var csrfToken = container ? container.dataset.csrf || '' : '';
    var buttons = document.querySelectorAll('.js-delete-area');

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function (event) {
        event.preventDefault();
        var areaId = this.dataset.areaId;
        if (!areaId) return;
        submitDelete(areaId, this.dataset.areaName || '', csrfToken);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindDeleteButtons);
  } else {
    bindDeleteButtons();
  }
})();
