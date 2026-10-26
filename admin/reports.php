<?php require 'auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Relatórios · Plantão Admin</title>
<link rel="icon" href="assets/favicon.png">
<link rel="stylesheet" href="assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/lucide@0.460.0/dist/umd/lucide.min.js" integrity="sha384-OLBgp1GsljhM2TJ+sbHjaiH9txEUvgdDTAzHv2P24donTt6/529l+9Ua0vFImLlb" crossorigin="anonymous"></script>
</head>
<body>
<div id="toast"></div>

<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="assets/logo.jpg" alt="Plantão">
      <div><span>Plantão da Cerveja</span><small>Painel Admin</small></div>
    </div>
    <nav>
      <div class="nav-section">Principal</div>
      <a class="nav-link" href="dashboard.php"><i data-lucide="layout-dashboard" width="16" height="16"></i> Dashboard</a>
      <a class="nav-link" href="orders.php"><i data-lucide="package" width="16" height="16"></i> Pedidos <span class="badge" id="new-count" style="display:none">0</span></a>
      <div class="nav-section">Catálogo</div>
      <a class="nav-link" href="products.php"><i data-lucide="box" width="16" height="16"></i> Produtos</a>
      <a class="nav-link" href="categories.php"><i data-lucide="tag" width="16" height="16"></i> Categorias</a>
      <a class="nav-link" href="stock.php"><i data-lucide="warehouse" width="16" height="16"></i> Estoque</a>
      <div class="nav-section">Análises</div>
      <a class="nav-link active" href="reports.php"><i data-lucide="bar-chart-2" width="16" height="16"></i> Relatórios</a>
      <div class="nav-section">Sistema</div>
      <a class="nav-link" href="settings.php"><i data-lucide="settings" width="16" height="16"></i> Configurações</a>
    </nav>
    <div class="sidebar-footer">
      <button class="btn-logout" data-logout><i data-lucide="log-out" width="14" height="14"></i> Sair</button>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <button class="hamburger" id="hamburger"><i data-lucide="menu" width="20" height="20"></i></button>
      <h1>Relatórios</h1>
      <div class="topbar-right">
        <button class="store-status closed" id="store-status-btn" data-open="0"><span class="dot"></span>Carregando…</button>
      </div>
    </div>

    <div class="page">
      <!-- Period selector -->
      <div class="period-tabs">
        <button class="period-tab active" data-period="today">Hoje</button>
        <button class="period-tab" data-period="week">7 dias</button>
        <button class="period-tab" data-period="month">30 dias</button>
        <button class="period-tab" data-period="current_month">Este mês</button>
      </div>

      <!-- KPI tiles -->
      <div class="stats-grid" id="kpi-grid">
        <div class="stat-tile green">
          <div class="st-icon"><i data-lucide="banknote" width="18" height="18"></i></div>
          <div class="label">Receita</div>
          <div class="value" id="kpi-revenue">—</div>
          <div class="sub">Pedidos entregues</div>
        </div>
        <div class="stat-tile amber">
          <div class="st-icon"><i data-lucide="shopping-bag" width="18" height="18"></i></div>
          <div class="label">Pedidos</div>
          <div class="value" id="kpi-total">—</div>
          <div class="sub">Total do período</div>
        </div>
        <div class="stat-tile blue">
          <div class="st-icon"><i data-lucide="receipt" width="18" height="18"></i></div>
          <div class="label">Ticket médio</div>
          <div class="value" id="kpi-ticket">—</div>
          <div class="sub">Por pedido entregue</div>
        </div>
        <div class="stat-tile purple">
          <div class="st-icon"><i data-lucide="check-circle-2" width="18" height="18"></i></div>
          <div class="label">Taxa de conclusão</div>
          <div class="value" id="kpi-rate">—</div>
          <div class="sub">Entregues / total</div>
        </div>
        <div class="stat-tile red">
          <div class="st-icon"><i data-lucide="x-circle" width="18" height="18"></i></div>
          <div class="label">Cancelados</div>
          <div class="value" id="kpi-cancelled">—</div>
          <div class="sub">No período</div>
        </div>
      </div>

      <!-- Status + Payment -->
      <div class="report-grid">
        <div class="card">
          <div class="card-header">
            <div class="ch-icon amber"><i data-lucide="layers" width="14" height="14"></i></div>
            <h2>Pedidos por status</h2>
          </div>
          <div class="card-body">
            <div class="prog-wrap" id="status-breakdown">
              <div class="empty"><i data-lucide="loader-2" width="24" height="24" class="spin"></i></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <div class="ch-icon blue"><i data-lucide="credit-card" width="14" height="14"></i></div>
            <h2>Forma de pagamento</h2>
          </div>
          <div class="card-body">
            <div class="prog-wrap" id="payment-breakdown">
              <div class="empty"><i data-lucide="loader-2" width="24" height="24" class="spin"></i></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Peak hours -->
      <div class="card" style="margin-bottom:16px">
        <div class="card-header">
          <div class="ch-icon purple"><i data-lucide="clock" width="14" height="14"></i></div>
          <h2>Horários de pico</h2>
          <span style="font-size:11.5px;color:var(--fg-3)">Pedidos por hora do dia</span>
        </div>
        <div class="card-body">
          <div id="peak-chart" style="display:flex;align-items:flex-end;gap:3px;height:80px;padding-bottom:18px;position:relative">
            <div class="empty"><i data-lucide="loader-2" width="24" height="24" class="spin"></i></div>
          </div>
        </div>
      </div>

      <!-- Top products -->
      <div class="card">
        <div class="card-header">
          <div class="ch-icon green"><i data-lucide="trending-up" width="14" height="14"></i></div>
          <h2>Produtos mais pedidos</h2>
          <span style="font-size:11.5px;color:var(--fg-3)">Por quantidade de unidades</span>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr><th>#</th><th>Produto</th><th>Qtd.</th><th>Receita</th></tr>
            </thead>
            <tbody id="top-products">
              <tr><td colspan="4"><div class="empty"><i data-lucide="loader-2" width="24" height="24" class="spin"></i><p>Carregando…</p></div></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="assets/app.js"></script>
<script>
let currentPeriod = 'today';
let allOrders = [];

function periodDates(period) {
  const now = new Date();
  const start = new Date(now);
  if (period === 'today') {
    start.setHours(0,0,0,0);
  } else if (period === 'week') {
    start.setDate(start.getDate() - 6); start.setHours(0,0,0,0);
  } else if (period === 'month') {
    start.setDate(start.getDate() - 29); start.setHours(0,0,0,0);
  } else if (period === 'current_month') {
    start.setDate(1); start.setHours(0,0,0,0);
  }
  return start;
}

function filterByPeriod(orders, period) {
  const start = periodDates(period);
  return orders.filter(o => new Date(o.created_at) >= start);
}

const STATUS_LABELS = {
  new:'Novo', accepted:'Aceito', preparing:'Preparando',
  delivering:'A caminho', delivered:'Entregue', cancelled:'Cancelado'
};
const STATUS_COLORS = {
  new:'#3B82F6', accepted:'#F59E0B', preparing:'#F97316',
  delivering:'#8B5CF6', delivered:'#16A34A', cancelled:'#DC2626'
};
const PAYMENT_LABELS = {
  pix:'PIX', credit_card:'Cartão crédito', debit_card:'Cartão débito',
  cash:'Dinheiro', machine:'Maquininha'
};

function renderReport(orders) {
  // KPIs
  const delivered = orders.filter(o => o.status === 'delivered');
  const cancelled = orders.filter(o => o.status === 'cancelled');
  const revenue = delivered.reduce((s,o) => s + parseFloat(o.total||0), 0);
  const ticket = delivered.length ? revenue / delivered.length : 0;
  const rate = orders.length ? Math.round(delivered.length / orders.length * 100) : 0;

  document.getElementById('kpi-revenue').textContent = fmtMoney(revenue);
  document.getElementById('kpi-total').textContent = orders.length;
  document.getElementById('kpi-ticket').textContent = delivered.length ? fmtMoney(ticket) : '—';
  document.getElementById('kpi-rate').textContent = orders.length ? rate + '%' : '—';
  document.getElementById('kpi-cancelled').textContent = cancelled.length;

  // Status breakdown
  const statusCounts = {};
  orders.forEach(o => { statusCounts[o.status] = (statusCounts[o.status]||0) + 1; });
  const maxStatus = Math.max(...Object.values(statusCounts), 1);
  const statusHTML = Object.entries(statusCounts)
    .sort((a,b) => b[1]-a[1])
    .map(([s,n]) => `
      <div class="prog-row">
        <span class="prog-label">${STATUS_LABELS[s]||s}</span>
        <div class="prog-bar"><div class="prog-bar-fill" style="width:${Math.round(n/maxStatus*100)}%;background:${STATUS_COLORS[s]||'var(--amber)'}"></div></div>
        <span class="prog-val">${n}</span>
      </div>`).join('');
  document.getElementById('status-breakdown').innerHTML = statusHTML || '<div class="empty"><p>Sem dados</p></div>';

  // Payment breakdown
  const paymentCounts = {};
  const paymentRevenue = {};
  delivered.forEach(o => {
    const k = o.payment_method || 'other';
    paymentCounts[k] = (paymentCounts[k]||0) + 1;
    paymentRevenue[k] = (paymentRevenue[k]||0) + parseFloat(o.total||0);
  });
  const maxPay = Math.max(...Object.values(paymentCounts), 1);
  const payHTML = Object.entries(paymentCounts)
    .sort((a,b) => b[1]-a[1])
    .map(([p,n]) => `
      <div class="prog-row">
        <span class="prog-label">${PAYMENT_LABELS[p]||p}</span>
        <div class="prog-bar"><div class="prog-bar-fill" style="width:${Math.round(n/maxPay*100)}%;background:var(--blue)"></div></div>
        <span class="prog-val">${fmtMoney(paymentRevenue[p]||0)}</span>
      </div>`).join('');
  document.getElementById('payment-breakdown').innerHTML = payHTML || '<div class="empty"><p>Sem dados</p></div>';

  // Peak hours (0–23)
  const hourCounts = new Array(24).fill(0);
  orders.forEach(o => { const h = new Date(o.created_at).getHours(); hourCounts[h]++; });
  const maxH = Math.max(...hourCounts, 1);
  const peakWrap = document.getElementById('peak-chart');
  const barColor = (n) => n === Math.max(...hourCounts) ? '#FCDA24' : '#E2E8F0';
  peakWrap.innerHTML = hourCounts.map((n,h) => `
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;height:100%;justify-content:flex-end">
      <div title="${h}h: ${n} pedido(s)" style="width:100%;border-radius:3px 3px 0 0;min-height:2px;height:${Math.round(n/maxH*64)}px;background:${n===Math.max(...hourCounts)?'#FCDA24':'#CBD5E1'};transition:height .4s"></div>
      <span style="font-size:9px;color:var(--fg-3);font-weight:600">${h}</span>
    </div>`).join('');

  // Top products from items
  const productMap = {};
  orders.forEach(o => {
    (o.items||[]).forEach(i => {
      if (!productMap[i.product_name]) productMap[i.product_name] = {qty:0,rev:0};
      productMap[i.product_name].qty += parseInt(i.quantity)||1;
      productMap[i.product_name].rev += parseFloat(i.total)||0;
    });
  });
  const topProducts = Object.entries(productMap)
    .sort((a,b) => b[1].qty - a[1].qty)
    .slice(0,10);

  const tbody = document.getElementById('top-products');
  if (!topProducts.length) {
    tbody.innerHTML = '<tr><td colspan="4"><div class="empty"><i data-lucide="package-2" width="24" height="24"></i><p>Dados de itens indisponíveis neste período</p></div></td></tr>';
    lucide.createIcons(); return;
  }
  tbody.innerHTML = topProducts.map(([name, d], i) => `
    <tr>
      <td style="color:var(--fg-3);font-weight:600;width:30px">${i+1}</td>
      <td style="font-weight:500">${name}</td>
      <td><strong>${d.qty}</strong> <span style="font-size:11.5px;color:var(--fg-3)">un.</span></td>
      <td><strong>${fmtMoney(d.rev)}</strong></td>
    </tr>`).join('');
  lucide.createIcons();
}

async function loadReport() {
  try {
    const data = await api('/orders?limit=1000');
    allOrders = data.data || data || [];
    const filtered = filterByPeriod(allOrders, currentPeriod);
    renderReport(filtered);
  } catch (e) {
    toast(e.message, 'error');
  }
}

document.querySelectorAll('.period-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    currentPeriod = btn.dataset.period;
    document.querySelectorAll('.period-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const filtered = filterByPeriod(allOrders, currentPeriod);
    renderReport(filtered);
  });
});

loadReport();
</script>
</body>
</html>
