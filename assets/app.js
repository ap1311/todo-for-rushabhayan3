function syncDeadlineType() {
  var type = document.getElementById('deadlineType');
  var timeField = document.getElementById('timeField');
  var timeInput = document.getElementById('deadlineTime');
  if (!type || !timeField) return;
  var isDateTime = type.value === 'datetime';
  timeField.style.display = isDateTime ? '' : 'none';
  if (timeInput) {
    timeInput.required = isDateTime;
    if (!isDateTime) timeInput.value = '';
  }
}

function initAutoFilters() {
  var form = document.getElementById('filterForm');
  if (!form) return;
  var timer = null;

  function fire(delay) {
    clearTimeout(timer);
    timer = setTimeout(function () {
      var active = document.activeElement;
      if (active && active.name) {
        sessionStorage.setItem('filterFocus', active.name);
      }
      sessionStorage.setItem('filterScroll', String(window.scrollY));
      form.submit();
    }, delay);
  }

  form.querySelectorAll('select, input[type="date"]').forEach(function (el) {
    el.addEventListener('change', function () { fire(60); });
  });

  var search = form.querySelector('input[type="search"]');
  if (search) {
    search.addEventListener('input', function () { fire(450); });
  }

  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      clearTimeout(timer);
      form.submit();
    }
  });

  var focusName = sessionStorage.getItem('filterFocus');
  if (focusName) {
    sessionStorage.removeItem('filterFocus');
    var target = form.querySelector('input[name="' + focusName + '"], select[name="' + focusName + '"]');
    if (target) {
      target.focus({ preventScroll: true });
      if (target.setSelectionRange) {
        target.setSelectionRange(target.value.length, target.value.length);
      }
    }
  }

  var scrollY = sessionStorage.getItem('filterScroll');
  if (scrollY !== null) {
    sessionStorage.removeItem('filterScroll');
    window.scrollTo(0, parseInt(scrollY, 10) || 0);
  }
}

function initEditToggle() {
  var toggle = document.getElementById('toggleEdit');
  var box = document.getElementById('editFormBox');
  var cancel = document.getElementById('cancelEdit');
  if (!toggle || !box) return;
  function show(open) {
    box.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  toggle.addEventListener('click', function () { show(box.hidden); });
  if (cancel) cancel.addEventListener('click', function () { show(false); });
}

document.addEventListener('DOMContentLoaded', function () {
  syncDeadlineType();
  var type = document.getElementById('deadlineType');
  if (type) type.addEventListener('change', syncDeadlineType);
  initAutoFilters();
  initEditToggle();
});