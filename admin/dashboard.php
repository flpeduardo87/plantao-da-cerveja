<?php require 'auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard · Plantão Admin</title>
<link rel="icon" href="../favicon-icone.webp">
<link rel="stylesheet" href="assets/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<script src="assets/lucide.min.js"></script>
</head>
<body>
<div id="toast"></div>
<div class="layout">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
<img src="../favicon-icone.webp" alt="Plantão" style="border-radius:50%">
      <div><span>Plantão da Cerveja</span><small>Painel Admin</small></div>
    </div>
    <nav>
      <div class="nav-section">Principal</div>
      <a class="nav-link active" href="painel"><i data-lucide="layout-dashboard" width="16" height="16"></i> Dashboard</a>
      <a class="nav-link" href="pedidos"><i data-lucide="package" width="16" height="16"></i> Pedidos <span class="badge" id="new-count" style="display:none">0</span></a>
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
      <h1>Dashboard</h1>
      <div class="topbar-right">
        <button class="store-status closed" id="store-status-btn" data-open="0">
          <span class="dot"></span>Carregando…
        </button>
      </div>
    </div>

    <div class="page">
      <div class="stats-grid">
        <div class="stat-tile amber">
          <div class="st-icon"><i data-lucide="shopping-bag" width="18" height="18"></i></div>
          <div class="label">Pedidos hoje</div>
          <div class="value" id="stat-today">—</div>
          <div class="sub">Total do dia</div>
        </div>
        <div class="stat-tile green">
          <div class="st-icon"><i data-lucide="banknote" width="18" height="18"></i></div>
          <div class="label">Receita hoje</div>
          <div class="value" id="stat-revenue">—</div>
          <div class="sub">Pedidos entregues</div>
        </div>
        <div class="stat-tile blue">
          <div class="st-icon"><i data-lucide="clock" width="18" height="18"></i></div>
          <div class="label">Em andamento</div>
          <div class="value" id="stat-active">—</div>
          <div class="sub">Aceitos + preparando + a caminho</div>
        </div>
        <div class="stat-tile red">
          <div class="st-icon"><i data-lucide="bell" width="18" height="18"></i></div>
          <div class="label">Aguardando</div>
          <div class="value" id="stat-new">—</div>
          <div class="sub">Novos pedidos</div>
        </div>
        <div class="stat-tile purple">
          <div class="st-icon"><i data-lucide="receipt" width="18" height="18"></i></div>
          <div class="label">Ticket médio</div>
          <div class="value" id="stat-ticket">—</div>
          <div class="sub">Pedidos entregues hoje</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h2>Pedidos recentes</h2>
          <a href="pedidos" class="btn btn-ghost btn-sm">Ver todos</a>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>Pedido</th><th>Cliente</th><th>Total</th><th>Pagamento</th><th>Status</th><th>Horário</th><th></th>
              </tr>
            </thead>
            <tbody id="recent-tbody">
              <tr><td colspan="7"><div class="empty"><i data-lucide="loader-2" width="28" height="28" class="spin"></i><p>Carregando…</p></div></td></tr>
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
    const [recent, all] = await Promise.all([
      api('/orders?limit=8'),
      api('/orders?limit=500')
    ]);

    const orders = all.data || [];
    const now = new Date();
    const todayOrders = orders.filter(o => {
      const d = new Date(o.created_at);
      return d.getFullYear()===now.getFullYear() && d.getMonth()===now.getMonth() && d.getDate()===now.getDate();
    });

    const delivered = todayOrders.filter(o => o.status === 'delivered');
    const revenue = delivered.reduce((s, o) => s + parseFloat(o.total || 0), 0);
    const active = orders.filter(o => ['accepted','preparing','delivering'].includes(o.status)).length;
    const newOrders = orders.filter(o => o.status === 'new').length;
    const ticket = delivered.length ? revenue / delivered.length : 0;

    document.getElementById('stat-today').textContent   = todayOrders.length;
    document.getElementById('stat-revenue').textContent = fmtMoney(revenue);
    document.getElementById('stat-active').textContent  = active;
    document.getElementById('stat-new').textContent     = newOrders;
    document.getElementById('stat-ticket').textContent  = delivered.length ? fmtMoney(ticket) : '—';

    const badge = document.getElementById('new-count');
    if (newOrders > 0) { badge.textContent = newOrders; badge.style.display = ''; }
    else badge.style.display = 'none';

    if (newOrders > lastNewCount && lastNewCount >= 0) {
      beep();
      toast(`${newOrders - lastNewCount} novo(s) pedido(s) aguardando`);
    }
    lastNewCount = newOrders;

    const rows = (recent.data || []).slice(0, 8);
    const tbody = document.getElementById('recent-tbody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7"><div class="empty"><i data-lucide="inbox" width="28" height="28"></i><p>Nenhum pedido ainda</p></div></td></tr>';
      lucide.createIcons(); return;
    }
    tbody.innerHTML = rows.map(o => `
      <tr>
        <td><strong>#${o.order_number}</strong></td>
        <td>${o.customer_name}</td>
        <td><strong>${fmtMoney(o.total)}</strong></td>
        <td style="color:var(--fg-2)">${fmtPayment(o.payment_method)}</td>
        <td>${fmtStatus(o.status)}</td>
        <td style="white-space:nowrap;font-size:12px;color:var(--fg-2)">${fmtDate(o.created_at)}</td>
        <td><a href="pedidos?id=${o.id}" class="btn btn-ghost btn-sm">Detalhes</a></td>
      </tr>
    `).join('');
    lucide.createIcons();
  } catch (e) { console.error(e); }
}

loadStats();
setInterval(loadStats, 15000);
</script>
</body>
</html>
