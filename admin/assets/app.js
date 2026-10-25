const API = 'https://api.plantaodacerveja.com.br';

/* ── Auth ── */
function getToken() { return localStorage.getItem('plantao_token'); }
function getUser()  { try { return JSON.parse(localStorage.getItem('plantao_user') || '{}'); } catch { return {}; } }

function isTokenExpired() {
  const token = getToken();
  if (!token) return true;
  try {
    const payload = JSON.parse(atob(token.split('.')[1].replace(/-/g,'+').replace(/_/g,'/')));
    return payload.exp && payload.exp < Math.floor(Date.now() / 1000);
  } catch { return false; }
}

function authGuard() {
  if (!getToken() || isTokenExpired()) {
    localStorage.removeItem('plantao_token');
    localStorage.removeItem('plantao_user');
    window.location.href = 'index.php';
    return false;
  }
  return true;
}

function logout() {
  fetch(`${API}/auth/logout`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${getToken()}` }
  }).finally(() => {
    localStorage.removeItem('plantao_token');
    localStorage.removeItem('plantao_user');
    window.location.href = 'logout.php';
  });
}

/* ── API helper ── */
async function api(path, opts = {}) {
  const headers = { 'Content-Type': 'application/json', ...opts.headers };
  if (getToken()) headers['Authorization'] = `Bearer ${getToken()}`;
  const res = await fetch(`${API}${path}`, { ...opts, headers });
  if (res.status === 401) { localStorage.clear(); window.location.href = 'index.php'; return; }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `Erro ${res.status}`);
  return data;
}

/* ── Toast ── */
function toast(msg, type = 'success') {
  const el = document.createElement('div');
  el.className = `toast-item ${type}`;
  el.textContent = msg;
  document.getElementById('toast').appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

/* ── Sidebar toggle (mobile) ── */
function initSidebar() {
  const sidebar = document.getElementById('sidebar');
  const hamburger = document.getElementById('hamburger');
  if (!sidebar || !hamburger) return;
  hamburger.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.addEventListener('click', e => {
    if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !hamburger.contains(e.target))
      sidebar.classList.remove('open');
  });
}

/* ── Store open/closed toggle ── */
async function initStoreStatus() {
  const btn = document.getElementById('store-status-btn');
  if (!btn) return;
  try {
    const data = await api('/settings');
    const open = data.store_open === '1' || data.store_open === 1;
    setStoreStatus(open, btn);
  } catch {}

  btn.addEventListener('click', async () => {
    const open = btn.dataset.open === '1';
    try {
      await api('/settings', { method: 'PUT', body: JSON.stringify({ store_open: open ? '0' : '1' }) });
      setStoreStatus(!open, btn);
      toast(!open ? 'Loja aberta' : 'Loja fechada', !open ? 'success' : 'error');
    } catch (e) { toast(e.message, 'error'); }
  });
}

function setStoreStatus(open, btn) {
  btn.dataset.open = open ? '1' : '0';
  btn.className = `store-status ${open ? 'open' : 'closed'}`;
  btn.innerHTML = `<span class="dot"></span>${open ? 'Loja aberta' : 'Loja fechada'}`;
}

/* ── Format helpers ── */
function fmtMoney(v) {
  return 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function fmtDate(s) {
  if (!s) return '—';
  const d = new Date(s);
  return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function fmtDateShort(s) {
  if (!s) return '—';
  return new Date(s).toLocaleDateString('pt-BR');
}

function fmtStatus(s) {
  const map = {
    new:        { label: 'Novo',       cls: 'badge-new' },
    accepted:   { label: 'Aceito',     cls: 'badge-accepted' },
    preparing:  { label: 'Preparando', cls: 'badge-preparing' },
    delivering: { label: 'A caminho',  cls: 'badge-delivering' },
    delivered:  { label: 'Entregue',   cls: 'badge-delivered' },
    cancelled:  { label: 'Cancelado',  cls: 'badge-cancelled' },
  };
  const m = map[s] || { label: s, cls: '' };
  return `<span class="badge ${m.cls}">${m.label}</span>`;
}

function fmtPayment(p) {
  const map = {
    pix: 'PIX',
    credit_card: 'Cartão crédito',
    debit_card: 'Cartão débito',
    cash: 'Dinheiro',
    machine: 'Maquininha'
  };
  return map[p] || p || '—';
}

/* ── Modal helpers ── */
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

/* ── Init on load ── */
document.addEventListener('DOMContentLoaded', () => {
  if (!authGuard()) return;
  initSidebar();
  initStoreStatus();
  document.querySelectorAll('[data-logout]').forEach(el => el.addEventListener('click', logout));
  if (typeof lucide !== 'undefined') lucide.createIcons();
});
