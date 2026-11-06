<?php require 'auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pedidos · Plantão Admin</title>
<link rel="icon" href="../favicon-icone.webp">
<link rel="stylesheet" href="assets/style.css?v=7">
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
      <img src="../favicon-icone.webp" class="topbar-logo" alt="">
      <h1>Pedidos</h1>
      <div class="topbar-right">
        <button id="btn-notify" class="btn btn-sm btn-ghost" onclick="askNotifyPermission()"><i data-lucide="bell-off" width="14" height="14"></i> Ativar alertas</button>
        <button class="store-status closed" id="store-status-btn" data-open="0"><span class="dot"></span>Carregando…</button>
      </div>
    </div>

    <div class="page">

      <!-- Stats -->
      <div class="stats-grid ord-stats-grid">
        <div class="stat-tile blue">
          <div class="st-icon"><i data-lucide="shopping-bag" width="18" height="18"></i></div>
          <div class="label">Total de pedidos</div>
          <div class="value" id="stat-total">—</div>
        </div>
        <div class="stat-tile green">
          <div class="st-icon"><i data-lucide="check-circle" width="18" height="18"></i></div>
          <div class="label">Concluídos</div>
          <div class="value" id="stat-delivered">—</div>
        </div>
        <div class="stat-tile purple">
          <div class="st-icon"><i data-lucide="truck" width="18" height="18"></i></div>
          <div class="label">Em entrega</div>
          <div class="value" id="stat-delivering">—</div>
        </div>
        <div class="stat-tile red">
          <div class="st-icon"><i data-lucide="x-circle" width="18" height="18"></i></div>
          <div class="label">Cancelados</div>
          <div class="value" id="stat-cancelled">—</div>
        </div>
        <div class="stat-tile amber">
          <div class="st-icon"><i data-lucide="banknote" width="18" height="18"></i></div>
          <div class="label">Faturamento</div>
          <div class="value" id="stat-revenue">—</div>
        </div>
      </div>

      <!-- Filtros -->
      <div class="filter-bar">
        <input type="search" id="search-input" class="filter-input" placeholder="Buscar pedido ou cliente…">
        <select id="period-select" class="filter-select">
          <option value="today">Hoje</option>
          <option value="week">Últimos 7 dias</option>
          <option value="month">Este mês</option>
          <option value="all" selected>Todos os períodos</option>
        </select>
        <select id="status-select" class="filter-select">
          <option value="">Todos os status</option>
          <option value="new">Novos</option>
          <option value="accepted">Aceitos</option>
          <option value="preparing">Preparando</option>
          <option value="delivering">A caminho</option>
          <option value="delivered">Entregues</option>
          <option value="cancelled">Cancelados</option>
        </select>
        <button class="btn btn-ghost btn-sm" onclick="clearFilters()"><i data-lucide="x" width="14" height="14"></i> Limpar filtros</button>
        <div class="spacer"></div>
        <label class="ord-autorefresh-lbl">
          <label class="toggle" style="margin:0"><input type="checkbox" id="auto-refresh" checked><span class="toggle-slider"></span></label>
          Auto-atualizar
        </label>
      </div>

      <!-- Desktop table -->
      <div class="card ord-desktop-card">
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>Pedido</th>
                <th>Data e Hora</th>
                <th>Cliente</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Pagamento</th>
                <th>Entrega</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="orders-tbody">
              <tr><td colspan="8"><div class="empty"><i data-lucide="loader-2" width="28" height="28" class="spin"></i><p>Carregando…</p></div></td></tr>
            </tbody>
          </table>
        </div>
        <div class="ord-pagination" id="pagination-desktop"></div>
      </div>

      <!-- Mobile cards -->
      <div class="ord-mobile-list" id="orders-mobile"></div>
      <div class="ord-pagination" id="pagination-mobile"></div>

    </div>
  </div>
</div>

<script src="assets/app.js?v=5"></script>
<script>
let allOrders = [];
let currentPage = 1;
const perPage = 20;
let lastNewCount = 0;
let audioCtx;
let refreshTimer;
let titleFlashTimer;
const PAGE_TITLE = 'Pedidos · Plantão Admin';

const urlParams = new URLSearchParams(window.location.search);
const directOrderId = urlParams.get('id');

function playChime() {
  try {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    if (audioCtx.state === 'suspended') audioCtx.resume();
    [[523.25, 0], [659.25, 0.15], [783.99, 0.30]].forEach(([freq, delay]) => {
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.type = 'sine'; osc.frequency.value = freq;
      osc.connect(gain); gain.connect(audioCtx.destination);
      const t = audioCtx.currentTime + delay;
      gain.gain.setValueAtTime(0, t);
      gain.gain.linearRampToValueAtTime(0.35, t + 0.04);
      gain.gain.exponentialRampToValueAtTime(0.001, t + 0.55);
      osc.start(t); osc.stop(t + 0.6);
    });
  } catch {}
}

function flashTitle(count) {
  clearInterval(titleFlashTimer); let on = true;
  titleFlashTimer = setInterval(() => { document.title = on ? `🔔 ${count} NOVO(S) PEDIDO(S)` : PAGE_TITLE; on = !on; }, 900);
}
function stopFlash() { clearInterval(titleFlashTimer); document.title = PAGE_TITLE; }
function alertNewOrders(count) { playChime(); sendBrowserNotification(count); flashTitle(count); }
function clearNewAlerts() { stopFlash(); }

function fmtDelivery(type) {
  if (!type) return '—';
  const map = { delivery: 'Entrega', pickup: 'Retirada', 'take-away': 'Retirada' };
  return map[type] || type;
}

const STATUS_CLS   = { new:'ord-s-new', accepted:'ord-s-accepted', preparing:'ord-s-preparing', delivering:'ord-s-delivering', delivered:'ord-s-delivered', cancelled:'ord-s-cancelled' };
const STATUS_LABEL = { new:'Novo', accepted:'Aceito', preparing:'Preparando', delivering:'A caminho', delivered:'Entregue', cancelled:'Cancelado' };

function statusBadge(status) {
  return `<span class="ord-badge ${STATUS_CLS[status]||''}">${STATUS_LABEL[status]||status}</span>`;
}

/* ── Filters ── */
let searchVal = '', periodVal = 'all', statusVal = '';

document.getElementById('search-input').addEventListener('input', function() { searchVal = this.value.toLowerCase().trim(); currentPage = 1; renderOrders(); });
document.getElementById('period-select').addEventListener('change', function() { periodVal = this.value; currentPage = 1; renderOrders(); });
document.getElementById('status-select').addEventListener('change', function() { statusVal = this.value; currentPage = 1; renderOrders(); });

function clearFilters() {
  searchVal = ''; periodVal = 'all'; statusVal = '';
  document.getElementById('search-input').value = '';
  document.getElementById('period-select').value = 'all';
  document.getElementById('status-select').value = '';
  currentPage = 1; renderOrders();
}

function matchesPeriod(o) {
  if (periodVal === 'all') return true;
  const d = new Date(o.created_at), now = new Date();
  if (periodVal === 'today') return d.toDateString() === now.toDateString();
  if (periodVal === 'week') { const ago = new Date(now); ago.setDate(now.getDate()-7); return d >= ago; }
  if (periodVal === 'month') return d.getFullYear()===now.getFullYear() && d.getMonth()===now.getMonth();
  return true;
}

function filteredOrders() {
  return allOrders.filter(o => {
    if (statusVal && o.status !== statusVal) return false;
    if (!matchesPeriod(o)) return false;
    if (searchVal) {
      const hay = `#${o.order_number} ${o.customer_name} ${o.customer_phone||''}`.toLowerCase();
      if (!hay.includes(searchVal)) return false;
    }
    return true;
  });
}

function updateStats() {
  document.getElementById('stat-total').textContent      = allOrders.length;
  document.getElementById('stat-delivered').textContent  = allOrders.filter(o=>o.status==='delivered').length;
  document.getElementById('stat-delivering').textContent = allOrders.filter(o=>o.status==='delivering').length;
  document.getElementById('stat-cancelled').textContent  = allOrders.filter(o=>o.status==='cancelled').length;
  const rev = allOrders.filter(o=>o.status==='delivered').reduce((s,o)=>s+parseFloat(o.total||0),0);
  document.getElementById('stat-revenue').textContent = fmtMoney(rev);
}

function renderPagination(total, cid) {
  const pg = document.getElementById(cid); if (!pg) return;
  const pages = Math.ceil(total / perPage);
  if (total === 0) { pg.innerHTML = ''; return; }
  if (pages <= 1) { pg.innerHTML = `<span class="info">${total} pedido(s)</span>`; return; }
  let html = `<button ${currentPage===1?'disabled':''} onclick="goPage(${currentPage-1})">&#8249;</button>`;
  for (let i = 1; i <= Math.min(pages,7); i++)
    html += `<button class="${i===currentPage?'active':''}" onclick="goPage(${i})">${i}</button>`;
  html += `<button ${currentPage>=pages?'disabled':''} onclick="goPage(${currentPage+1})">&#8250;</button>`;
  html += `<span class="info">${total} pedido(s)</span>`;
  pg.innerHTML = html;
}

function goPage(p) { currentPage = p; renderOrders(); window.scrollTo(0,0); }

function renderOrders() {
  updateStats();
  const list = filteredOrders();
  const start = (currentPage-1)*perPage;
  const page = list.slice(start, start+perPage);

  /* Desktop */
  const tbody = document.getElementById('orders-tbody');
  if (!page.length) {
    tbody.innerHTML = `<tr><td colspan="8"><div class="empty"><i data-lucide="inbox" width="28" height="28"></i><p>Nenhum pedido encontrado</p></div></td></tr>`;
  } else {
    tbody.innerHTML = page.map(o => `
      <tr class="ord-row-${o.status}">
        <td><strong>#${o.order_number}</strong></td>
        <td style="font-size:12px;color:var(--fg-2);white-space:nowrap">${fmtDate(o.created_at)}</td>
        <td>
          <div style="font-weight:600;font-size:13px">${o.customer_name}</div>
          <div style="font-size:11.5px;color:var(--fg-3)">${o.customer_phone||''}</div>
        </td>
        <td><strong>${fmtMoney(o.total)}</strong></td>
        <td style="white-space:nowrap">${statusBadge(o.status)}</td>
        <td style="font-size:12.5px;color:var(--fg-2)">${fmtPayment(o.payment_method)}</td>
        <td style="font-size:12.5px;color:var(--fg-2)">${fmtDelivery(o.delivery_type)}</td>
        <td style="white-space:nowrap">${nextStatusBtn(o.id,o.status)} <button class="btn btn-ghost btn-sm" onclick="viewOrder(${o.id})">Detalhes</button></td>
      </tr>`).join('');
  }

  /* Mobile */
  const mob = document.getElementById('orders-mobile');
  mob.innerHTML = page.map(o => `
    <div class="ord-card ord-card-${o.status}">
      <div class="ord-card-left">
        <div class="ord-card-num">#${String(o.order_number).slice(-6)}</div>
        <div class="ord-card-time">${fmtDate(o.created_at)}</div>
        ${statusBadge(o.status)}
      </div>
      <div class="ord-card-mid">
        <div class="ord-card-name">${o.customer_name}</div>
        <div class="ord-card-meta">${o.customer_phone||''}</div>
        <div class="ord-card-meta">${fmtPayment(o.payment_method)} · ${fmtDelivery(o.delivery_type)}</div>
        <div class="ord-card-value">${fmtMoney(o.total)}</div>
      </div>
      <div class="ord-card-actions">
        ${nextStatusBtn(o.id, o.status)}
        <button class="btn btn-ghost btn-sm" onclick="viewOrder(${o.id})" style="padding:6px"><i data-lucide="chevron-right" width="16" height="16"></i></button>
      </div>
    </div>`).join('');

  lucide.createIcons();
  renderPagination(list.length, 'pagination-desktop');
  renderPagination(list.length, 'pagination-mobile');
}

async function loadOrders() {
  try {
    const data = await api('/orders?limit=500');
    if (!data) return;
    allOrders = data.data || [];

    const newOrders = allOrders.filter(o => o.status === 'new').length;
    const badge = document.getElementById('new-count');
    if (newOrders > 0) { badge.textContent = newOrders; badge.style.display = ''; }
    else { badge.style.display = 'none'; clearNewAlerts(); }
    if (newOrders > lastNewCount && lastNewCount >= 0) alertNewOrders(newOrders);
    lastNewCount = newOrders;

    renderOrders();
  } catch (e) {
    console.error(e);
    const tbody = document.getElementById('orders-tbody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="8"><div class="empty"><p style="color:var(--red)">Erro ao carregar: ${e.message}</p><button class="btn btn-primary btn-sm" onclick="loadOrders()">Tentar novamente</button></div></td></tr>`;
    document.getElementById('orders-mobile').innerHTML = '';
  }
}

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
