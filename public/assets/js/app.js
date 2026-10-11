'use strict';
document.querySelectorAll('#mainNav .nav-link').forEach(link => {
  link.addEventListener('click', () => {
    const menu = document.getElementById('mainNav');
    if (menu.classList.contains('show') && window.bootstrap) {
      bootstrap.Collapse.getOrCreateInstance(menu).hide();
    }
  });
});
// All writes still require server authorization and CSRF.
document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => { if (!window.confirm(form.dataset.confirm)) event.preventDefault(); }));
const bookingForm = document.getElementById('booking-form');
if (bookingForm) {
  const service = document.getElementById('booking-service');
  const employee = document.getElementById('booking-employee');
  const date = document.getElementById('booking-date');
  const start = document.getElementById('booking-start');
  const slots = document.getElementById('slots');
  const message = document.getElementById('slot-message');
  const load = document.getElementById('load-slots');
  let requestNumber = 0;
  function clearSlots() { requestNumber++; slots.replaceChildren(); message.textContent = ''; }
  service.addEventListener('change', () => {
    for (const option of employee.options) {
      if (!option.value) continue;
      const allowed = !service.value || option.dataset.services.split(',').includes(service.value);
      option.disabled = !allowed; option.hidden = !allowed;
      if (!allowed && option.selected) employee.value = '';
    }
    clearSlots();
  });
  employee.addEventListener('change', clearSlots); date.addEventListener('change', clearSlots);
  load.addEventListener('click', async () => {
    clearSlots(); const number = requestNumber;
    if (!service.value || !employee.value || !date.value) { message.textContent = 'Önce hizmet, çalışan ve tarih seçin.'; return; }
    load.disabled = true; message.textContent = 'Müsait saatler hesaplanıyor…';
    try {
      const query = new URLSearchParams({service_id:service.value, employee_id:employee.value, date:date.value});
      const response = await fetch(bookingForm.dataset.availability + '?' + query, {headers:{Accept:'application/json'}, credentials:'same-origin'});
      if (response.redirected) throw new Error('Oturumunuz sona erdi. Tekrar giriş yapın.');
      const data = await response.json(); if (!response.ok) throw new Error(data.error || 'Saatler alınamadı.');
      if (number !== requestNumber) return;
      message.textContent = data.slots.length ? 'Bir saat seçin. Kayıt sırasında müsaitlik tekrar kontrol edilir.' : 'Bu gün için uygun saat bulunmuyor.';
      for (const time of data.slots) {
        const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-outline-dark'; button.textContent = time;
        button.addEventListener('click', () => { start.value = date.value + 'T' + time; slots.querySelectorAll('button').forEach(item => item.classList.remove('selected')); button.classList.add('selected'); });
        slots.append(button);
      }
    } catch (error) { if (number === requestNumber) message.textContent = error.message; }
    finally { load.disabled = false; }
  });
}

// Fragment tokens are never sent in GET URLs or Referer headers.
const secretToken = new URLSearchParams(location.hash.slice(1)).get('token');
if (secretToken && /^[a-f0-9]{64}$/.test(secretToken)) {
  document.querySelectorAll('input[name="token"]').forEach(input => { input.value = secretToken; });
  history.replaceState(null, '', location.pathname + location.search);
}
document.querySelectorAll('form[method="post"]').forEach(form => {
  form.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
    form.dataset.submitting = 'true'; form.setAttribute('aria-busy','true');
    // Keep clicked decision/name fields in the request when disabling the button.
    const button = event.submitter;
    if (button && button.name) { const copy = document.createElement('input'); copy.type='hidden'; copy.name=button.name; copy.value=button.value; copy.dataset.submitCopy='true'; form.append(copy); }
    const status = document.createElement('p'); status.className='form-text'; status.setAttribute('role','status'); status.dataset.submitStatus='true'; status.textContent='İşleminiz gerçekleştiriliyor…'; form.append(status);
    if (button) { button.disabled=true; button.dataset.busyButton='true'; }
  });
});
window.addEventListener('pageshow', () => {
  document.querySelectorAll('form[data-submitting]').forEach(form => { delete form.dataset.submitting; form.removeAttribute('aria-busy'); form.querySelectorAll('[data-submit-copy],[data-submit-status]').forEach(node=>node.remove()); form.querySelectorAll('[data-busy-button]').forEach(button=>{button.disabled=false;delete button.dataset.busyButton;}); });
});