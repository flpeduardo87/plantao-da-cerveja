<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard · Plantão Admin</title>
<link rel="icon" href="assets/favicon.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap">
<link rel="stylesheet" href="assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/lucide@0.460.0/dist/umd/lucide.min.js"></script>
<style>.spin{animation:spin-ic 1s linear infinite}@keyframes spin-ic{to{transform:rotate(360deg)}}</style>
</head>
<body>
<div id="toast"></div>
<div class="layout">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="assets/logo.jpg" alt="">
      <div><span>Plantão da Cerveja</span><small>Painel Admin</small></div>
    </div>
    <nav>
      <div class="nav-section">Menu</div>
      <a class="nav-link active" href="dashboard.php"><i data-lucide="layout-dashboard" width="18" height="18"></i> Dashboard</a>
      <a class="nav-link" href="orders.php"><i data-lucide="package" width="18" height="18"></i> Pedidos <span class="badge" id="new-count" style="display:none">0</span></a>
      <a class="nav-link" href="products.php"><i data-lucide="beer" width="18" height="18"></i> Produtos</a>
      <a class="nav-link" href="settings.php"><i data-lucide="settings" width="18" height="18"></i> Configurações</a>
    </nav>
    <div class="sidebar-footer">
      <button class="btn-logout" data-logout><i data-lucide="log-out" width="16" height="16"></i> Sair</button>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <button class="hamburger" id="hamburger">☰</button>
      <h1>Dashboard</h1>
      <div class="topbar-right">
        <button class="store-status closed" id="store-status-btn" data-open="0">
          <span class="dot"></span>Carregando…
        </button>
      </div>
    </div>

    <div class="page">
      <div class="stats-grid" id="stats-grid">
        <div class="stat-tile amber">
          <div class="label">Pedidos hoje</div>
          <div class="value" id="stat-today">—</div>
          <div class="sub">Total do dia</div>
        </div>
        <div class="stat-tile green">
          <div class="label">Receita hoje</div>
          <div class="value" id="stat-revenue">—</div>
          <div class="sub">Pedidos entregues</div>
        </div>
        <div class="stat-tile blue">
          <div class="label">Em andamento</div>
          <div class="value" id="stat-active">—</div>
          <div class="sub">Aceitos + preparando + a caminho</div>
        </div>
        <div class="stat-tile red">
          <div class="label">Novos (aguardando)</div>
          <div class="value" id="stat-new">—</div>
          <div class="sub">Aguardando confirmação</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h2>Pedidos recentes</h2>
          <a href="orders.php" class="btn btn-ghost btn-sm">Ver todos</a>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>Pedido</th><th>Cliente</th><th>Total</th><th>Pagamento</th><th>Status</th><th>Horário</th><th></th>
              </tr>
            </thead>
            <tbody id="recent-tbody">
              <tr><td colspan="7" class="empty"><div class="icon"><i data-lucide="loader-2" width="36" height="36" class="spin"></i></div><p>Carregando…</p></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="assets/app.js"></script>
<script>
let lastNewCount = 0;
let audioCtx;

function beep() {
  try {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.connect(gain); gain.connect(audioCtx.destination);
    osc.frequency.value = 880; osc.type = 'sine';
    gain.gain.setValueAtTime(0.4, audioCtx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.4);
    osc.start(); osc.stop(audioCtx.currentTime + 0.4);
  } catch {}
}

async function loadStats() {
  try {
    const [all, today] = await Promise.all([
      api('/orders?limit=10'),
      api('/orders?limit=200')
    ]);

    const orders = today.data || [];
    const now = new Date();
    const todayOrders = orders.filter(o => {
      const d = new Date(o.created_at);
      return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
    });

    const revenue = todayOrders.filter(o => o.status === 'delivered').reduce((s, o) => s + parseFloat(o.total || 0), 0);
    const active = orders.filter(o => ['accepted','preparing','delivering'].includes(o.status)).length;
    const newOrders = orders.filter(o => o.status === 'new').length;

    document.getElementById('stat-today').textContent   = todayOrders.length;
    document.getElementById('stat-revenue').textContent = fmtMoney(revenue);
    document.getElementById('stat-active').textContent  = active;
    document.getElementById('stat-new').textContent     = newOrders;

    const badge = document.getElementById('new-count');
    if (newOrders > 0) { badge.textContent = newOrders; badge.style.display = ''; }
    else { badge.style.display = 'none'; }

    if (newOrders > lastNewCount && lastNewCount >= 0) { beep(); toast(`🔔 ${newOrders - lastNewCount} novo(s) pedido(s)!`); }
    lastNewCount = newOrders;

    const rows = (all.data || []).slice(0, 8);
    const tbody = document.getElementById('recent-tbody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7"><div class="empty"><div class="icon"><i data-lucide="inbox" width="36" height="36"></i></div><p>Nenhum pedido ainda</p></div></td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(o => `
      <tr>
        <td><strong>#${o.order_number}</strong></td>
        <td>${o.customer_name}</td>
        <td><strong>${fmtMoney(o.total)}</strong></td>
        <td>${fmtPayment(o.payment_method)}</td>
        <td>${fmtStatus(o.status)}</td>
        <td style="white-space:nowrap;font-size:13px;color:var(--fg-2)">${fmtDate(o.created_at)}</td>
        <td><a href="orders.php?id=${o.id}" class="btn btn-ghost btn-sm">Ver</a></td>
      </tr>
    `).join('');
  } catch (e) { console.error(e); }
}

loadStats();
setInterval(loadStats, 15000);
</script>
<script>document.addEventListener("DOMContentLoaded",()=>lucide.createIcons());</script>
</body>
</html>
