<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pedidos · Plantão Admin</title>
<link rel="icon" href="assets/favicon.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div id="toast"></div>

<div class="modal-backdrop" id="modal-order">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-order-title">Pedido</h3>
      <button class="btn-icon" onclick="closeModal('modal-order')">✕</button>
    </div>
    <div class="modal-body" id="modal-order-body"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-order')">Fechar</button>
    </div>
  </div>
</div>

<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="assets/logo.jpg" alt="">
      <div><span>Plantão da Cerveja</span><small>Painel Admin</small></div>
    </div>
    <nav>
      <div class="nav-section">Menu</div>
      <a class="nav-link" href="dashboard.php"><span class="icon">📊</span> Dashboard</a>
      <a class="nav-link active" href="orders.php"><span class="icon">📦</span> Pedidos <span class="badge" id="new-count" style="display:none">0</span></a>
      <a class="nav-link" href="products.php"><span class="icon">🍺</span> Produtos</a>
      <a class="nav-link" href="settings.php"><span class="icon">⚙️</span> Configurações</a>
    </nav>
    <div class="sidebar-footer">
      <button class="btn-logout" data-logout>🚪 Sair</button>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <button class="hamburger" id="hamburger">☰</button>
      <h1>Pedidos</h1>
      <div class="topbar-right">
        <button class="store-status closed" id="store-status-btn" data-open="0"><span class="dot"></span>Carregando…</button>
      </div>
    </div>

    <div class="page">
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
        <button class="btn btn-primary btn-sm" data-filter="">Todos</button>
        <button class="btn btn-ghost btn-sm" data-filter="new">Novos</button>
        <button class="btn btn-ghost btn-sm" data-filter="accepted">Aceitos</button>
        <button class="btn btn-ghost btn-sm" data-filter="preparing">Preparando</button>
        <button class="btn btn-ghost btn-sm" data-filter="delivering">A caminho</button>
        <button class="btn btn-ghost btn-sm" data-filter="delivered">Entregues</button>
        <button class="btn btn-ghost btn-sm" data-filter="cancelled">Cancelados</button>
        <div style="margin-left:auto;display:flex;align-items:center;gap:6px">
          <label style="font-size:13px">Auto-atualizar</label>
          <label class="toggle"><input type="checkbox" id="auto-refresh" checked><span class="toggle-slider"></span></label>
        </div>
      </div>

      <div class="card">
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>Pedido</th><th>Cliente</th><th>Endereço</th><th>Total</th><th>Pagamento</th><th>Status</th><th>Horário</th><th>Ações</th>
              </tr>
            </thead>
            <tbody id="orders-tbody">
              <tr><td colspan="8"><div class="empty"><div class="icon">⏳</div><p>Carregando…</p></div></td></tr>
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

const urlParams = new URLSearchParams(window.location.search);
const directOrderId = urlParams.get('id');

function beep() {
  try {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = audioCtx.createOscillator(); const gain = audioCtx.createGain();
    osc.connect(gain); gain.connect(audioCtx.destination);
    osc.frequency.value = 880; gain.gain.setValueAtTime(0.4, audioCtx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.4);
    osc.start(); osc.stop(audioCtx.currentTime + 0.4);
  } catch {}
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
    else badge.style.display = 'none';
    if (newOrders > lastNewCount && lastNewCount >= 0) beep();
    lastNewCount = newOrders;

    const tbody = document.getElementById('orders-tbody');
    if (!orders.length) {
      tbody.innerHTML = '<tr><td colspan="8"><div class="empty"><div class="icon">📭</div><p>Nenhum pedido</p></div></td></tr>';
    } else {
      tbody.innerHTML = orders.map(o => `
        <tr>
          <td><strong>#${o.order_number}</strong></td>
          <td><div style="font-weight:700">${o.customer_name}</div><div style="font-size:12px;color:var(--fg-2)">${o.customer_phone}</div></td>
          <td style="font-size:13px">${o.address_street}, ${o.address_number} — ${o.address_neighborhood}</td>
          <td><strong>${fmtMoney(o.total)}</strong></td>
          <td style="font-size:13px">${fmtPayment(o.payment_method)}</td>
          <td><select class="status-select" onchange="changeStatus(${o.id}, this.value)">${statusOptions(o.status)}</select></td>
          <td style="white-space:nowrap;font-size:13px;color:var(--fg-2)">${fmtDate(o.created_at)}</td>
          <td><button class="btn btn-ghost btn-sm" onclick="viewOrder(${o.id})">Detalhes</button></td>
        </tr>
      `).join('');
    }

    const pages = Math.ceil(total / 20);
    const pg = document.getElementById('pagination');
    if (pages <= 1) { pg.innerHTML = `<span class="info">${total} pedido(s)</span>`; return; }
    let html = `<button ${currentPage===1?'disabled':''} onclick="goPage(${currentPage-1})">‹</button>`;
    for (let i = 1; i <= Math.min(pages, 7); i++) {
      html += `<button class="${i===currentPage?'active':''}" onclick="goPage(${i})">${i}</button>`;
    }
    html += `<button ${currentPage>=pages?'disabled':''} onclick="goPage(${currentPage+1})">›</button>`;
    html += `<span class="info">${total} pedido(s)</span>`;
    pg.innerHTML = html;
  } catch (e) { console.error(e); }
}

function statusOptions(current) {
  const opts = [
    { v: 'new', l: 'Novo' }, { v: 'accepted', l: 'Aceito' }, { v: 'preparing', l: 'Preparando' },
    { v: 'delivering', l: 'A caminho' }, { v: 'delivered', l: 'Entregue' }, { v: 'cancelled', l: 'Cancelado' },
  ];
  return opts.map(o => `<option value="${o.v}" ${o.v===current?'selected':''}>${o.l}</option>`).join('');
}

async function changeStatus(id, status) {
  try {
    await api(`/orders/${id}`, { method: 'PUT', body: JSON.stringify({ status }) });
    toast('Status atualizado!');
  } catch (e) { toast(e.message, 'error'); loadOrders(); }
}

async function viewOrder(id) {
  const body = document.getElementById('modal-order-body');
  document.getElementById('modal-order-title').textContent = 'Carregando…';
  body.innerHTML = '<div class="empty"><div class="icon">⏳</div></div>';
  openModal('modal-order');
  try {
    const o = await api(`/orders/${id}`);
    document.getElementById('modal-order-title').textContent = `Pedido #${o.order_number}`;
    body.innerHTML = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div><div style="font-size:11px;font-weight:800;color:var(--fg-2);margin-bottom:2px">CLIENTE</div><div style="font-weight:700">${o.customer_name}</div><div style="font-size:13px;color:var(--fg-2)">${o.customer_phone}</div>${o.customer_email?`<div style="font-size:13px;color:var(--fg-2)">${o.customer_email}</div>`:''}</div>
        <div><div style="font-size:11px;font-weight:800;color:var(--fg-2);margin-bottom:2px">ENDEREÇO</div><div style="font-size:14px">${o.address_street}, ${o.address_number}${o.address_complement?', '+o.address_complement:''}</div><div style="font-size:13px;color:var(--fg-2)">${o.address_neighborhood}${o.address_cep?' — CEP '+o.address_cep:''}</div>${o.address_reference?`<div style="font-size:12px;color:var(--fg-2)">Ref: ${o.address_reference}</div>`:''}</div>
        <div><div style="font-size:11px;font-weight:800;color:var(--fg-2);margin-bottom:2px">PAGAMENTO</div><div style="font-weight:700">${fmtPayment(o.payment_method)}</div></div>
        <div><div style="font-size:11px;font-weight:800;color:var(--fg-2);margin-bottom:2px">STATUS</div>${fmtStatus(o.status)}</div>
        ${o.notes?`<div style="grid-column:1/-1"><div style="font-size:11px;font-weight:800;color:var(--fg-2);margin-bottom:2px">OBSERVAÇÕES</div><div style="font-size:14px;background:#F9FAFB;padding:8px 12px;border-radius:8px">${o.notes}</div></div>`:''}
      </div>
      <div style="font-size:11px;font-weight:800;color:var(--fg-2);margin-bottom:8px">ITENS</div>
      <ul class="order-items-list">
        ${(o.items||[]).map(i=>`<li><span class="qty">${i.quantity}x</span><span style="flex:1">${i.product_name}</span><span class="price">${fmtMoney(i.total)}</span></li>`).join('')}
      </ul>
      <div style="border-top:1px solid var(--border);margin-top:12px;padding-top:12px">
        <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--fg-2);margin-bottom:4px"><span>Subtotal</span><span>${fmtMoney(o.subtotal)}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--fg-2);margin-bottom:8px"><span>Taxa de entrega</span><span>${fmtMoney(o.delivery_fee)}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:900"><span>Total</span><span>${fmtMoney(o.total)}</span></div>
      </div>
    `;
  } catch (e) { body.innerHTML = `<div class="empty"><p style="color:var(--red)">${e.message}</p></div>`; }
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

function startRefresh() { refreshTimer = setInterval(loadOrders, 10000); }
function stopRefresh()  { clearInterval(refreshTimer); }
document.getElementById('auto-refresh').addEventListener('change', function() {
  this.checked ? startRefresh() : stopRefresh();
});

loadOrders();
startRefresh();

if (directOrderId) setTimeout(() => viewOrder(parseInt(directOrderId)), 600);
</script>
</body>
</html>
