const API = 'https://api.plantaodacerveja.com.br';

/* ── Auth ── */
function getToken() { return localStorage.getItem('plantao_token'); }
function getUser()  { try { return JSON.parse(localStorage.getItem('plantao_user') || '{}'); } catch { return {}; } }

function isTokenExpired() {
  const token = getToken();
  if (!token) return true;
  try {
    const p = JSON.parse(atob(token.split('.')[1].replace(/-/g,'+').replace(/_/g,'/')));
    return p.exp && p.exp < Math.floor(Date.now() / 1000);
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
  const res = await fetch(`${API}${path}`, { ...opts, headers, signal: opts.signal ?? AbortSignal.timeout(15000) });
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
    setStoreStatus(data.store_open === '1' || data.store_open === 1, btn);
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
  const d = new Date(s.replace(' ', 'T') + 'Z');
  return d.toLocaleDateString('pt-BR', {timeZone: 'America/Sao_Paulo'}) + ' ' +
         d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Sao_Paulo' });
}
function fmtDateShort(s) {
  if (!s) return '—';
  return new Date(s.replace(' ', 'T') + 'Z').toLocaleDateString('pt-BR', {timeZone: 'America/Sao_Paulo'});
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
  return { pix: 'PIX', credit_card: 'Cartão crédito', debit_card: 'Cartão débito', cash: 'Dinheiro', machine: 'Maquininha' }[p] || p || '—';
}

/* ── Modal helpers ── */
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

/* ── Status change (shared) ── */
async function changeStatus(id, status) {
  if (!status) return;
  try {
    await api(`/orders/${id}`, { method: 'PUT', body: JSON.stringify({ status }) });
    toast('Status atualizado');
    if (typeof loadOrders === 'function') loadOrders();
    else if (typeof loadStats === 'function') loadStats();
  } catch (e) { toast(e.message, 'error'); }
}

function statusOptions(current) {
  return [
    {v:'new',l:'Novo'},{v:'accepted',l:'Aceito'},{v:'preparing',l:'Preparando'},
    {v:'delivering',l:'A caminho'},{v:'delivered',l:'Entregue'},{v:'cancelled',l:'Cancelado'},
  ].map(o => `<option value="${o.v}"${o.v===current?' selected':''}>${o.l}</option>`).join('');
}

/* ── Order details modal (shared) ── */
let _viewingOrder = null;

async function viewOrder(id) {
  const bodyEl   = document.getElementById('modal-order-body');
  const titleEl  = document.getElementById('modal-order-title');
  const footerEl = document.getElementById('modal-order-footer');
  if (!bodyEl) return;

  titleEl.textContent = 'Carregando…';
  bodyEl.innerHTML = '<div class="empty"><i data-lucide="loader-2" width="28" height="28" class="spin"></i></div>';
  footerEl.innerHTML = '<button class="btn btn-ghost" onclick="closeModal(\'modal-order\')">Fechar</button>';
  openModal('modal-order');
  lucide.createIcons();

  try {
    const o = _viewingOrder = await api(`/orders/${id}`);
    titleEl.textContent = `Pedido #${o.order_number}`;
    bodyEl.innerHTML = `
      <div class="info-grid">
        <div class="info-cell">
          <div class="ic-label">Cliente</div>
          <div class="ic-value">${o.customer_name}</div>
          <div class="ic-sub">${o.customer_phone || ''}</div>
          ${o.customer_email ? `<div class="ic-sub">${o.customer_email}</div>` : ''}
        </div>
        <div class="info-cell">
          <div class="ic-label">Endereço</div>
          <div class="ic-value">${o.address_street ? o.address_street + ', ' + (o.address_number||'s/n') : '—'}</div>
          <div class="ic-sub">${o.address_neighborhood||''}${o.address_cep ? ' · CEP '+o.address_cep : ''}</div>
          ${o.address_complement ? `<div class="ic-sub">${o.address_complement}</div>` : ''}
          ${o.address_reference  ? `<div class="ic-sub" style="color:var(--fg-3)">Ref: ${o.address_reference}</div>` : ''}
        </div>
        <div class="info-cell">
          <div class="ic-label">Pagamento</div>
          <div class="ic-value">${fmtPayment(o.payment_method)}</div>
        </div>
        <div class="info-cell">
          <div class="ic-label">Status</div>
          <div class="ic-value">${fmtStatus(o.status)}</div>
        </div>
        ${o.notes ? `<div class="info-cell" style="grid-column:1/-1"><div class="ic-label">Observações</div><div style="font-size:13px;background:var(--surface-2,#F8FAFC);padding:10px 12px;border-radius:8px;margin-top:4px;border:1px solid var(--border)">${o.notes}</div></div>` : ''}
      </div>
      <div class="divider"></div>
      <div style="font-size:10.5px;font-weight:700;color:var(--fg-3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Itens do pedido</div>
      <ul class="order-items-list">
        ${(o.items||[]).map(i=>`<li><span class="qty">${i.quantity}x</span><span style="flex:1;font-weight:500">${i.product_name}</span><span class="price">${fmtMoney(i.total)}</span></li>`).join('')}
      </ul>
      <div style="margin-top:14px;border-top:1px solid var(--border);padding-top:12px">
        <div style="display:flex;justify-content:space-between;font-size:12.5px;color:var(--fg-2);margin-bottom:5px"><span>Subtotal</span><span>${fmtMoney(o.subtotal)}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:12.5px;color:var(--fg-2);margin-bottom:8px"><span>Taxa de entrega</span><span>${fmtMoney(o.delivery_fee)}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:800"><span>Total</span><span>${fmtMoney(o.total)}</span></div>
      </div>`;
    footerEl.innerHTML = `
      <button class="btn btn-ghost" onclick="closeModal('modal-order')">Fechar</button>
      <select class="status-select" style="flex:1;max-width:150px" onchange="changeStatus(${o.id},this.value);this.blur()">${statusOptions(o.status)}</select>
      <button class="btn btn-ghost btn-sm" onclick="printOrder(_viewingOrder)"><i data-lucide="printer" width="14" height="14"></i> Imprimir</button>
      <button class="btn btn-sm" style="background:#25D366;color:#fff;border:none;gap:5px" onclick="shareOrderWhatsapp(_viewingOrder)"><i data-lucide="message-circle" width="14" height="14"></i> WhatsApp</button>
      <button class="btn btn-sm" style="background:#ef4444;color:#fff;border:none;gap:5px;margin-left:auto" onclick="deleteOrder(${o.id})"><i data-lucide="trash-2" width="14" height="14"></i> Excluir</button>`;
  } catch (e) {
    bodyEl.innerHTML = `<div class="empty"><p style="color:var(--red)">${e.message}</p></div>`;
  }
  lucide.createIcons();
}

async function deleteOrder(id) {
  if (!confirm('Excluir este pedido? Esta ação não pode ser desfeita.')) return;
  try {
    await api(`/orders/${id}`, { method: 'DELETE' });
    closeModal('modal-order');
    if (typeof loadOrders === 'function') loadOrders();
  } catch (e) {
    alert('Erro ao excluir: ' + e.message);
  }
}

/* ── Print comanda — estilo Zé Delivery ── */
function printOrder(o) {
  if (!o) return;
  const items = (o.items||[]).map(i => `
    <div class="item-row">
      <span class="item-qty">${i.quantity}x</span>
      <span class="item-name">${i.product_name}</span>
      <span class="item-price">${fmtMoney(i.total)}</span>
    </div>`).join('');

  const w = window.open('', '_blank', 'width=420,height=700');
  w.document.write(`<!DOCTYPE html><html lang="pt-BR"><head>
<meta charset="UTF-8"><title>Comanda #${o.order_number}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#fff;color:#111;max-width:380px;margin:0 auto}
.hd{background:#FF6B00;color:#fff;padding:18px 20px 14px;text-align:center}
.hd .logo{font-size:20px;font-weight:800;letter-spacing:-.4px}
.hd .sub{font-size:10.5px;font-weight:600;opacity:.85;text-transform:uppercase;letter-spacing:.1em;margin-top:2px}
.order-badge{background:#111;color:#fff;margin:0 16px;border-radius:10px;padding:12px 16px}
.ob-label{font-size:10px;font-weight:700;color:#FF6B00;text-transform:uppercase;letter-spacing:.1em}
.ob-num{font-size:26px;font-weight:800;margin-top:2px;letter-spacing:-.5px}
.ob-time{font-size:11px;color:#aaa;margin-top:2px}
.sec{padding:12px 16px;border-top:1px dashed #e5e5e5}
.sec-title{font-size:9.5px;font-weight:700;color:#FF6B00;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px}
.info-row{font-size:13px;margin-bottom:3px;line-height:1.4}
.item-row{display:flex;align-items:flex-start;gap:8px;padding:5px 0;font-size:13px;border-top:1px solid #f5f5f5}
.item-row:first-child{border-top:none}
.item-qty{background:#FF6B00;color:#fff;font-weight:800;font-size:11px;padding:2px 7px;border-radius:4px;white-space:nowrap;margin-top:1px;flex-shrink:0}
.item-name{flex:1;font-weight:500}
.item-price{font-weight:700;white-space:nowrap}
.totals{background:#fafafa;padding:12px 16px;border-top:1px dashed #e5e5e5}
.tot-row{display:flex;justify-content:space-between;font-size:12.5px;color:#666;margin-bottom:5px}
.tot-final{display:flex;justify-content:space-between;font-size:18px;font-weight:800;margin-top:8px;padding-top:8px;border-top:2px solid #111}
.foot{padding:12px 16px;text-align:center;border-top:1px dashed #e5e5e5}
.pay-badge{display:inline-block;background:#FF6B00;color:#fff;font-size:11.5px;font-weight:700;padding:4px 14px;border-radius:20px;text-transform:uppercase;letter-spacing:.05em}
.notes{margin-top:9px;font-size:12px;color:#555;background:#fff8f0;border:1px solid #ffe4cc;border-radius:8px;padding:8px 12px;text-align:left}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<div class="hd"><div class="logo">🍺 PLANTÃO DA CERVEJA</div><div class="sub">Delivery de Bebidas</div></div>
<div class="order-badge"><div class="ob-label">Pedido</div><div class="ob-num">#${o.order_number}</div><div class="ob-time">${fmtDate(o.created_at)}</div></div>
<div class="sec"><div class="sec-title">Cliente</div>
  <div class="info-row"><strong>${o.customer_name}</strong></div>
  ${o.customer_phone ? `<div class="info-row">${o.customer_phone}</div>` : ''}
  ${o.customer_email ? `<div class="info-row">${o.customer_email}</div>` : ''}
</div>
<div class="sec"><div class="sec-title">Entrega</div>
  ${o.address_street
    ? `<div class="info-row"><strong>${o.address_street}, ${o.address_number||'s/n'}</strong></div>
       ${o.address_neighborhood ? `<div class="info-row">${o.address_neighborhood}${o.address_cep?' · CEP '+o.address_cep:''}</div>` : ''}
       ${o.address_complement  ? `<div class="info-row">${o.address_complement}</div>` : ''}
       ${o.address_reference   ? `<div class="info-row" style="color:#888">Ref: ${o.address_reference}</div>` : ''}`
    : '<div class="info-row">Retirada no local</div>'}
</div>
<div class="sec"><div class="sec-title">Itens</div>${items}</div>
<div class="totals">
  <div class="tot-row"><span>Subtotal</span><span>${fmtMoney(o.subtotal)}</span></div>
  <div class="tot-row"><span>Taxa de entrega</span><span>${fmtMoney(o.delivery_fee)}</span></div>
  <div class="tot-final"><span>TOTAL</span><span>${fmtMoney(o.total)}</span></div>
</div>
<div class="foot">
  <div class="pay-badge">${fmtPayment(o.payment_method)}</div>
  ${o.notes ? `<div class="notes">📝 ${o.notes}</div>` : ''}
</div>
</body></html>`);
  w.document.close();
  w.focus();
  setTimeout(() => { w.print(); }, 600);
}

/* ── WhatsApp share ── */
function shareOrderWhatsapp(o) {
  if (!o) return;
  const phone = (o.customer_phone || '').replace(/\D/g, '');
  const items = (o.items||[]).map(i => `• ${i.quantity}x ${i.product_name} — ${fmtMoney(i.total)}`).join('\n');
  const addr  = o.address_street
    ? `${o.address_street}, ${o.address_number||'s/n'}${o.address_neighborhood ? ' — ' + o.address_neighborhood : ''}`
    : 'Retirada no local';
  const text  = `🍺 *Pedido #${o.order_number} — Plantão da Cerveja*\n\n`
    + `*Cliente:* ${o.customer_name}\n`
    + `*Fone:* ${o.customer_phone || '—'}\n`
    + `*Endereço:* ${addr}\n`
    + (o.address_reference ? `*Ref:* ${o.address_reference}\n` : '')
    + `\n*Itens:*\n${items}\n\n`
    + `*Subtotal:* ${fmtMoney(o.subtotal)}\n`
    + (parseFloat(o.delivery_fee) > 0 ? `*Entrega:* ${fmtMoney(o.delivery_fee)}\n` : '')
    + `*Total:* ${fmtMoney(o.total)}\n`
    + `*Pagamento:* ${fmtPayment(o.payment_method)}`;
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`, '_blank');
}

/* ── Push notifications ── */
let _pushReg = null;

async function initPushSW() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return null;
  try {
    _pushReg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    return _pushReg;
  } catch { return null; }
}

function _b64uToUint8(b) {
  const pad = '='.repeat((4 - b.length % 4) % 4);
  const raw = atob((b + pad).replace(/-/g, '+').replace(/_/g, '/'));
  return Uint8Array.from(raw, c => c.charCodeAt(0));
}

async function subscribePush() {
  const reg = _pushReg || await initPushSW();
  if (!reg) return false;
  try {
    const existing = await reg.pushManager.getSubscription();
    if (existing) { await _savePushSub(existing); return true; }
    const { public_key } = await api('/push');
    const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: _b64uToUint8(public_key) });
    await _savePushSub(sub);
    return true;
  } catch (e) { console.warn('Push subscribe failed:', e); return false; }
}

async function _savePushSub(sub) {
  await api('/push', { method: 'POST', body: JSON.stringify(sub.toJSON()) });
}

function sendBrowserNotification(count) {
  if (Notification.permission !== 'granted') return;
  new Notification('🍺 Novo pedido!', {
    body: `${count} pedido(s) aguardando confirmação`,
    icon: '../favicon-icone.webp',
    tag: 'new-order',
    renotify: true,
  });
}

async function askNotifyPermission() {
  if (typeof playChime === 'function') playChime();
  else if (typeof beep === 'function') beep();
  if (!('Notification' in window)) { toast('Som ativado!'); return; }
  const p = await Notification.requestPermission();
  if (p === 'granted') {
    const ok = await subscribePush();
    toast(ok ? '🔔 Alertas push ativados! Funciona mesmo com a aba fechada.' : '🔔 Alertas ativados no browser!');
  } else {
    toast('🔔 Som ativado! (permissão de notificação negada)');
  }
  updateNotifyBtn();
}

function updateNotifyBtn() {
  const btn = document.getElementById('btn-notify');
  if (!btn) return;
  if (!('Notification' in window)) { btn.style.display = 'none'; return; }
  if (Notification.permission === 'granted') {
    btn.innerHTML = '<i data-lucide="bell" width="14" height="14"></i> Alertas ativos';
    btn.style.color = 'var(--green)';
    btn.disabled = true;
    btn.onclick = null;
  } else {
    btn.innerHTML = '<i data-lucide="bell-off" width="14" height="14"></i> Ativar alertas';
    btn.style.color = '';
    btn.disabled = false;
    btn.onclick = askNotifyPermission;
  }
  lucide.createIcons();
}

/* ── Init on load ── */
document.addEventListener('DOMContentLoaded', () => {
  if (!authGuard()) return;
  initSidebar();
  initStoreStatus();
  initPushSW();
  document.querySelectorAll('[data-logout]').forEach(el => el.addEventListener('click', logout));
  if (typeof lucide !== 'undefined') lucide.createIcons();
});
