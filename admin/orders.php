<?php require 'auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pedidos · Plantão Admin</title>
<link rel="icon" href="../favicon-icone.webp">
<link rel="stylesheet" href="assets/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<script src="assets/lucide.min.js"></script>
</head>
<body>
<div id="toast"></div>

<!-- Modal: detalhes do pedido -->
<div class="modal-backdrop" id="modal-order">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-order-title">Pedido</h3>
      <button class="btn-icon borderless" onclick="closeModal('modal-order')"><i data-lucide="x" width="16" height="16"></i></button>
    </div>
    <div class="modal-body" id="modal-order-body"></div>
    <div class="modal-footer" id="modal-order-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-order')">Fechar</button>
    </div>
  </div>
</div>

<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
<img src="../favicon-icone.webp" alt="Plantão" style="border-radius:50%">
      <div><span>Plantão da Cerveja</span><small>Painel Admin</small></div>
    </div>
    <nav>
      <div class="nav-section">Principal</div>
      <a class="nav-link" href="painel"><i data-lucide="layout-dashboard" width="16" height="16"></i> Dashboard</a>
      <a class="nav-link active" href="pedidos"><i data-lucide="package" width="16" height="16"></i> Pedidos <span class="badge" id="new-count" style="display:none">0</span></a>
      <div class="nav-section">Catálogo</div>
      <a class="nav-link" href="produtos"><i data-lucide="box" width="16" height="16"></i> Produtos</a>
      <a class="nav-link" href="categorias"><i data-lucide="tag" width="16" height="16"></i> Categorias</a>
      <a class="nav-link" href="estoque"><i data-lucide="warehouse" width="16" height="16"></i> Estoque</a>
      <div class="nav-section">Análises</div>
      <a class="nav-link" href="relatorios"><i data-lucide="bar-chart-2" width="16" height="16"></i> Relatórios</a>
      <div class="nav-section">Sistema</div>
      <a class="nav-link" href="configuracoes"><i data-lucide="settings" width="16" height="16"></i> Configurações</a>
    </nav>
    <div class="sidebar-footer">
      <button class="btn-logout" data-logout><i data-lucide="log-out" width="14" height="14"></i> Sair</button>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <button class="hamburger" id="hamburger"><i data-lucide="menu" width="24" height="24"></i></button>
      <h1>Pedidos</h1>
      <div class="topbar-right">
        <button id="btn-notify" class="btn btn-sm btn-ghost" onclick="askNotifyPermission()"><i data-lucide="bell-off" width="14" height="14"></i> Ativar alertas</button>
        <button class="store-status closed" id="store-status-btn" data-open="0"><span class="dot"></span>Carregando…</button>
      </div>
    </div>

    <div class="page">
      <div class="filter-bar">
        <button class="btn btn-primary btn-sm" data-filter="">Todos</button>
        <button class="btn btn-ghost btn-sm" data-filter="new">Novos</button>
        <button class="btn btn-ghost btn-sm" data-filter="accepted">Aceitos</button>
        <button class="btn btn-ghost btn-sm" data-filter="preparing">Preparando</button>
        <button class="btn btn-ghost btn-sm" data-filter="delivering">A caminho</button>
        <button class="btn btn-ghost btn-sm" data-filter="delivered">Entregues</button>
        <button class="btn btn-ghost btn-sm" data-filter="cancelled">Cancelados</button>
        <div class="spacer"></div>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--fg-2);cursor:pointer">
          <label class="toggle" style="margin:0"><input type="checkbox" id="auto-refresh" checked><span class="toggle-slider"></span></label>
          Auto-atualizar
        </label>
      </div>

      <div class="card">
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>Pedido</th><th>Cliente</th><th>Endereço</th><th>Total</th><th>Pagamento</th><th>Status</th><th>Horário</th><th></th>
              </tr>
            </thead>
            <tbody id="orders-tbody">
              <tr><td colspan="8"><div class="empty"><i data-lucide="loader-2" width="28" height="28" class="spin"></i><p>Carregando…</p></div></td></tr>
            </tbody>
          </table>
        </div>
        <div class="pagination" id="pagination"></div>
      </div>
    </div>
  </div>
</div>

<script src="assets/app.js"></script>
<script>
let currentFilter = '';
let currentPage = 1;
let lastNewCount = 0;
let audioCtx;
let refreshTimer;
let titleFlashTimer;
const PAGE_TITLE = 'Pedidos · Plantão Admin';

const urlParams = new URLSearchParams(window.location.search);
const directOrderId = urlParams.get('id');

/* ── Chime: C5 → E5 → G5 (acorde maior ascendente) ── */
function playChime() {
  try {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    if (audioCtx.state === 'suspended') audioCtx.resume();
    [[523.25, 0], [659.25, 0.15], [783.99, 0.30]].forEach(([freq, delay]) => {
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.type = 'sine';
      osc.frequency.value = freq;
      osc.connect(gain); gain.connect(audioCtx.destination);
      const t = audioCtx.currentTime + delay;
      gain.gain.setValueAtTime(0, t);
      gain.gain.linearRampToValueAtTime(0.35, t + 0.04);
      gain.gain.exponentialRampToValueAtTime(0.001, t + 0.55);
      osc.start(t); osc.stop(t + 0.6);
    });
  } catch {}
}

/* ── Browser Notification ── */
function askNotifyPermission() {
  // Unlock + test audio immediately (requires user gesture)
  playChime();
  if (!('Notification' in window)) { toast('Som ativado! (notificações não suportadas neste browser)'); return; }
  Notification.requestPermission().then(p => {
    toast(p === 'granted' ? '🔔 Alertas e som ativados!' : '🔔 Som ativado! (notificações bloqueadas)');
    updateNotifyBtn();
  });
}
function sendBrowserNotification(count) {
  if (Notification.permission !== 'granted') return;
  const n = new Notification('🍺 Novo pedido!', {
    body: `${count} pedido(s) aguardando confirmação`,
    icon: '../favicon-icone.webp',
    tag: 'new-order',
    renotify: true,
  });
  n.onclick = () => { window.focus(); n.close(); };
}
function updateNotifyBtn() {
  const btn = document.getElementById('btn-notify');
  if (!btn) return;
  if (!('Notification' in window)) { btn.style.display = 'none'; return; }
  if (Notification.permission === 'granted') {
    btn.innerHTML = '<i data-lucide="bell" width="14" height="14"></i> Alertas ativos';
    btn.className = 'btn btn-sm btn-ghost'; btn.style.color = 'var(--green)';
    btn.onclick = null; btn.disabled = true;
  } else {
    btn.innerHTML = '<i data-lucide="bell-off" width="14" height="14"></i> Ativar alertas';
    btn.className = 'btn btn-sm btn-ghost'; btn.style.color = '';
    btn.onclick = askNotifyPermission; btn.disabled = false;
  }
  lucide.createIcons();
}

/* ── Tab title flash ── */
function flashTitle(count) {
  clearInterval(titleFlashTimer);
  let on = true;
  titleFlashTimer = setInterval(() => {
    document.title = on ? `🔔 ${count} NOVO(S) PEDIDO(S)` : PAGE_TITLE;
    on = !on;
  }, 900);
}
function stopFlash() {
  clearInterval(titleFlashTimer);
  document.title = PAGE_TITLE;
}

/* ── Alert hub: called when new orders detected ── */
function alertNewOrders(count) {
  playChime();
  sendBrowserNotification(count);
  flashTitle(count);
}
function clearNewAlerts() {
  stopFlash();
}

async function loadOrders() {
  const qs = new URLSearchParams({ limit: 20, page: currentPage });
  if (currentFilter) qs.set('status', currentFilter);
  try {
    const data = await api(`/orders?${qs}`);
    const orders = data.data || [];
    const total  = data.total || 0;

    const newOrders = orders.filter(o => o.status === 'new').length;
    const badge = document.getElementById('new-count');
    if (newOrders > 0) { badge.textContent = newOrders; badge.style.display = ''; }
    else { badge.style.display = 'none'; clearNewAlerts(); }
    if (newOrders > lastNewCount && lastNewCount >= 0) alertNewOrders(newOrders);
    lastNewCount = newOrders;

    const tbody = document.getElementById('orders-tbody');
    if (!orders.length) {
      tbody.innerHTML = '<tr><td colspan="8"><div class="empty"><i data-lucide="inbox" width="28" height="28"></i><p>Nenhum pedido encontrado</p></div></td></tr>';
      lucide.createIcons(); return;
    }
    tbody.innerHTML = orders.map(o => `
      <tr>
        <td><strong>#${o.order_number}</strong></td>
        <td>
          <div style="font-weight:600">${o.customer_name}</div>
          <div style="font-size:11.5px;color:var(--fg-2)">${o.customer_phone || ''}</div>
        </td>
        <td style="font-size:12.5px;color:var(--fg-2);max-width:180px">
          ${o.address_street ? o.address_street + ', ' + (o.address_number||'') : '—'}
          ${o.address_neighborhood ? '<br>' + o.address_neighborhood : ''}
        </td>
        <td><strong>${fmtMoney(o.total)}</strong></td>
        <td style="font-size:12.5px;color:var(--fg-2)">${fmtPayment(o.payment_method)}</td>
        <td><select class="status-select" onchange="changeStatus(${o.id},this.value)">${statusOptions(o.status)}</select></td>
        <td style="white-space:nowrap;font-size:12px;color:var(--fg-2)">${fmtDate(o.created_at)}</td>
        <td><button class="btn btn-ghost btn-sm" onclick="viewOrder(${o.id})">Detalhes</button></td>
      </tr>
    `).join('');
    lucide.createIcons();

    const pages = Math.ceil(total / 20);
    const pg = document.getElementById('pagination');
    if (pages <= 1) { pg.innerHTML = `<span class="info">${total} pedido(s)</span>`; return; }
    let html = `<button ${currentPage===1?'disabled':''} onclick="goPage(${currentPage-1})">&#8249;</button>`;
    for (let i = 1; i <= Math.min(pages,7); i++)
      html += `<button class="${i===currentPage?'active':''}" onclick="goPage(${i})">${i}</button>`;
    html += `<button ${currentPage>=pages?'disabled':''} onclick="goPage(${currentPage+1})">&#8250;</button>`;
    html += `<span class="info">${total} pedido(s)</span>`;
    pg.innerHTML = html;
  } catch (e) { console.error(e); }
}

function statusOptions(current) {
  return [
    {v:'new',l:'Novo'},{v:'accepted',l:'Aceito'},{v:'preparing',l:'Preparando'},
    {v:'delivering',l:'A caminho'},{v:'delivered',l:'Entregue'},{v:'cancelled',l:'Cancelado'},
  ].map(o => `<option value="${o.v}" ${o.v===current?'selected':''}>${o.l}</option>`).join('');
}

async function changeStatus(id, status) {
  try {
    await api(`/orders/${id}`, { method: 'PUT', body: JSON.stringify({ status }) });
    toast('Status atualizado');
  } catch (e) { toast(e.message, 'error'); loadOrders(); }
}

async function viewOrder(id) {
  const body = document.getElementById('modal-order-body');
  document.getElementById('modal-order-title').textContent = 'Carregando…';
  body.innerHTML = '<div class="empty"><i data-lucide="loader-2" width="28" height="28" class="spin"></i></div>';
  openModal('modal-order');
  lucide.createIcons();
  try {
    const o = await api(`/orders/${id}`);
    document.getElementById('modal-order-title').textContent = `Pedido #${o.order_number}`;
    body.innerHTML = `
      <div class="info-grid">
        <div class="info-cell">
          <div class="ic-label">Cliente</div>
          <div class="ic-value">${o.customer_name}</div>
          <div class="ic-sub">${o.customer_phone || ''}</div>
          ${o.customer_email ? `<div class="ic-sub">${o.customer_email}</div>` : ''}
        </div>
        <div class="info-cell">
          <div class="ic-label">Endereço</div>
          <div class="ic-value">${o.address_street || '—'}, ${o.address_number || ''}</div>
          <div class="ic-sub">${o.address_neighborhood || ''}${o.address_cep ? ' · CEP ' + o.address_cep : ''}</div>
          ${o.address_complement ? `<div class="ic-sub">${o.address_complement}</div>` : ''}
          ${o.address_reference ? `<div class="ic-sub" style="color:var(--fg-3)">Ref: ${o.address_reference}</div>` : ''}
        </div>
        <div class="info-cell">
          <div class="ic-label">Pagamento</div>
          <div class="ic-value">${fmtPayment(o.payment_method)}</div>
        </div>
        <div class="info-cell">
          <div class="ic-label">Status</div>
          <div class="ic-value">${fmtStatus(o.status)}</div>
        </div>
        ${o.notes ? `<div class="info-cell" style="grid-column:1/-1"><div class="ic-label">Observações</div><div style="font-size:13px;background:#F8FAFC;padding:10px 12px;border-radius:8px;margin-top:4px;border:1px solid var(--border)">${o.notes}</div></div>` : ''}
      </div>
      <div class="divider"></div>
      <div style="font-size:10.5px;font-weight:700;color:var(--fg-3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Itens do pedido</div>
      <ul class="order-items-list">
        ${(o.items||[]).map(i=>`
          <li>
            <span class="qty">${i.quantity}x</span>
            <span style="flex:1;font-weight:500">${i.product_name}</span>
            <span class="price">${fmtMoney(i.total)}</span>
          </li>`).join('')}
      </ul>
      <div style="margin-top:14px;border-top:1px solid var(--border);padding-top:12px">
        <div style="display:flex;justify-content:space-between;font-size:12.5px;color:var(--fg-2);margin-bottom:5px"><span>Subtotal</span><span>${fmtMoney(o.subtotal)}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:12.5px;color:var(--fg-2);margin-bottom:8px"><span>Taxa de entrega</span><span>${fmtMoney(o.delivery_fee)}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:800"><span>Total</span><span>${fmtMoney(o.total)}</span></div>
      </div>
    `;
  } catch (e) {
    body.innerHTML = `<div class="empty"><p style="color:var(--red)">${e.message}</p></div>`;
  }
  lucide.createIcons();
}

function goPage(p) { currentPage = p; loadOrders(); }

document.querySelectorAll('[data-filter]').forEach(btn => {
  btn.addEventListener('click', () => {
    currentFilter = btn.dataset.filter;
    currentPage = 1;
    document.querySelectorAll('[data-filter]').forEach(b => b.className = 'btn btn-ghost btn-sm');
    btn.className = 'btn btn-primary btn-sm';
    loadOrders();
  });
});

function startRefresh() { refreshTimer = setInterval(loadOrders, 12000); }
function stopRefresh()  { clearInterval(refreshTimer); }

document.getElementById('auto-refresh').addEventListener('change', function() {
  this.checked ? startRefresh() : stopRefresh();
});

loadOrders();
startRefresh();
updateNotifyBtn();
if (directOrderId) setTimeout(() => viewOrder(parseInt(directOrderId)), 700);
</script>
</body>
</html>
