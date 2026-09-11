/* TREADLINE - shared front-end helpers */

const TL = {
  csrf: document.querySelector('meta[name="csrf"]')?.content || '',

  toast(msg, type = 'info') {
    const wrap = document.getElementById('toastWrap');
    if (!wrap) return alert(msg);
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = msg;
    wrap.appendChild(el);
    setTimeout(() => el.remove(), 3800);
  },

  async post(url, data) {
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': TL.csrf },
        body: JSON.stringify(data || {})
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok || json.error) {
        TL.toast(json.error || ('Request failed (' + res.status + ')'), 'error');
        return { ok: false, ...json };
      }
      return { ok: true, ...json };
    } catch (e) {
      TL.toast('Network error: ' + e.message, 'error');
      return { ok: false, error: e.message };
    }
  },

  openModal(id) { document.getElementById(id)?.classList.add('show'); },
  closeModal(id) { document.getElementById(id)?.classList.remove('show'); },
};

// Close modal on backdrop click
document.addEventListener('click', (e) => {
  if (e.target.classList && e.target.classList.contains('tl-modal-backdrop')) {
    e.target.classList.remove('show');
  }
});

// Chip toggle (single-select within group by default)
function chipToggle(el, groupName) {
  const group = el.closest('.chip-options');
  group.querySelectorAll('.chip').forEach(c => {
    if (c !== el && group.dataset.multi !== '1') c.classList.remove('active');
  });
  el.classList.toggle('active');
  const input = el.querySelector('input');
  if (input) input.checked = el.classList.contains('active');
}

// Generic confirm-and-post for delete/danger actions
function tlConfirmPost(url, data, msg) {
  if (!confirm(msg || 'Are you sure?')) return;
  TL.post(url, data).then(r => {
    if (r.ok) { TL.toast(r.message || 'Done', 'success'); setTimeout(() => location.reload(), 700); }
  });
}
