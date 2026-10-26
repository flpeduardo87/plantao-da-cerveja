<?php require 'auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Estoque · Plantão Admin</title>
<link rel="icon" href="../favicon-icone.webp">
<link rel="stylesheet" href="assets/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<script src="assets/lucide.min.js"></script>
<style>
.stock-filter-bar{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;align-items:center;}
.stock-filter-bar .spacer{flex:1;}
.stock-val{
  display:inline-flex;align-items:center;gap:6px;
  font-size:13px;font-weight:600;
}
.stock-val.unlimited{color:var(--fg-3);}
.stock-val.ok{color:var(--green);}
.stock-val.low{color:#D97706;}
.stock-val.empty{color:var(--red);}
.stock-badge-low{background:#FEF3C7;color:#92400E;font-size:10px;font-weight:700;padding:2px 7px;border-radius:99px;}
.stock-badge-empty{background:var(--red-lt);color:var(--red);font-size:10px;font-weight:700;padding:2px 7px;border-radius:99px;}
.qty-inline{
  width:70px;padding:5px 8px;border-radius:7px;
  border:1.5px solid var(--border);font:600 13px 'Inter',sans-serif;
  color:var(--fg);background:#F8FAFC;text-align:center;
  transition:border-color .15s;
}
.qty-inline:focus{border-color:var(--amber);outline:none;background:#fff;}
.qty-inline::-webkit-inner-spin-button{opacity:1;}
.prod-thumb{width:40px;height:40px;border-radius:8px;object-fit:cover;flex-shrink:0;}
.prod-thumb-ph{width:40px;height:40px;border-radius:8px;background:#F1F5F9;display:flex;align-items:center;justify-content:center;color:var(--fg-3);flex-shrink:0;}
.summary-bar{
  display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px;
}
.sum-chip{
  background:var(--card);border:1px solid var(--border);border-radius:10px;
  padding:10px 16px;display:flex;align-items:center;gap:8px;
  font-size:12px;color:var(--fg-2);
}
.sum-chip strong{font-size:18px;font-weight:700;color:var(--fg);display:block;line-height:1.2;}
.sum-chip.warn strong{color:#D97706;}
.sum-chip.danger strong{color:var(--red);}
</style>
</head>
<body>
<div id="toast"></div>

<!-- Modal: entrada de estoque -->
<div class="modal-backdrop" id="modal-entry">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <h3>Entrada de estoque</h3>
      <button class="btn-icon borderless" onclick="closeModal('modal-entry')"><i data-lucide="x" width="16" height="16"></i></button>
    </div>
    <div class="modal-body">
      <p id="entry-product-name" style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--fg)"></p>
      <div class="form-grid">
        <div class="field form-full">
          <label>Estoque atual</label>
          <input type="number" id="entry-current" disabled style="background:#F8FAFC;color:var(--fg-3)">
        </div>
        <div class="field form-full">
          <label>Quantidade a adicionar *</label>
          <input type="number" id="entry-qty" min="1" value="1" placeholder="Ex: 24">
        </div>
        <div class="field form-full">
          <label>Novo total (calculado)</label>
          <input type="number" id="entry-new-total" disabled style="background:#F8FAFC;font-weight:700;color:var(--green)">
        </div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-entry')">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btn-confirm-entry" onclick="confirmEntry()">Confirmar entrada</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: zerar estoque -->
<div class="modal-backdrop" id="modal-zero">
  <div class="modal" style="max-width:380px">
    <div class="modal-header"><h3>Zerar estoque?</h3></div>
    <div class="modal-body">
      <p style="font-size:13.5px;color:var(--fg-2)">O estoque de <strong id="zero-product-name"></strong> será zerado. O produto ficará como "esgotado".</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-zero')">Cancelar</button>
      <button class="btn btn-danger" id="btn-confirm-zero">Zerar</button>
    </div>
  </div>
</div>

<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="../favicon-icone.webp" alt="Plantão">
      <div><span>Plantão da Cerveja</span><small>Painel Admin</small></div>
    </div>
    <nav>
      <div class="nav-section">Principal</div>
      <a class="nav-link" href="dashboard.php"><i data-lucide="layout-dashboard" width="16" height="16"></i> Dashboard</a>
      <a class="nav-link" href="orders.php"><i data-lucide="package" width="16" height="16"></i> Pedidos <span class="badge" id="new-count" style="display:none">0</span></a>
      <div class="nav-section">Catálogo</div>
      <a class="nav-link" href="products.php"><i data-lucide="box" width="16" height="16"></i> Produtos</a>
      <a class="nav-link" href="categories.php"><i data-lucide="tag" width="16" height="16"></i> Categorias</a>
      <a class="nav-link active" href="stock.php"><i data-lucide="warehouse" width="16" height="16"></i> Estoque</a>
      <div class="nav-section">Análises</div>
      <a class="nav-link" href="reports.php"><i data-lucide="bar-chart-2" width="16" height="16"></i> Relatórios</a>
      <div class="nav-section">Sistema</div>
      <a class="nav-link" href="settings.php"><i data-lucide="settings" width="16" height="16"></i> Configurações</a>
    </nav>
    <div class="sidebar-footer">
      <button class="btn-logout" data-logout><i data-lucide="log-out" width="14" height="14"></i> Sair</button>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <button class="hamburger" id="hamburger"><i data-lucide="menu" width="24" height="24"></i></button>
      <h1>Estoque</h1>
      <div class="topbar-right">
        <button class="store-status closed" id="store-status-btn" data-open="0"><span class="dot"></span>Carregando…</button>
      </div>
    </div>

    <div class="page">
      <div class="summary-bar" id="summary-bar">
        <div class="sum-chip"><i data-lucide="boxes" width="18" height="18" style="color:var(--fg-3)"></i><div><strong id="s-total">—</strong>produtos</div></div>
        <div class="sum-chip warn"><i data-lucide="alert-triangle" width="18" height="18" style="color:#D97706"></i><div><strong id="s-low">—</strong>estoque baixo (≤5)</div></div>
        <div class="sum-chip danger"><i data-lucide="circle-x" width="18" height="18" style="color:var(--red)"></i><div><strong id="s-empty">—</strong>esgotados</div></div>
        <div class="sum-chip"><i data-lucide="infinity" width="18" height="18" style="color:var(--fg-3)"></i><div><strong id="s-unlimited">—</strong>ilimitados</div></div>
      </div>

      <div class="stock-filter-bar">
        <button class="btn btn-primary btn-sm" data-sf="">Todos</button>
        <button class="btn btn-ghost btn-sm" data-sf="low">Estoque baixo</button>
        <button class="btn btn-ghost btn-sm" data-sf="empty">Esgotados</button>
        <button class="btn btn-ghost btn-sm" data-sf="unlimited">Ilimitados</button>
        <div class="spacer"></div>
        <input type="search" id="stock-search" placeholder="Buscar produto…" style="padding:6px 12px;border-radius:8px;border:1.5px solid var(--border);font:13px 'Inter',sans-serif;width:200px;background:#F8FAFC;color:var(--fg);outline:none" oninput="filterRender()">
      </div>

      <div class="card">
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th style="width:52px">Foto</th>
                <th>Produto</th>
                <th style="width:130px">Categoria</th>
                <th style="width:120px">Estoque atual</th>
                <th style="width:100px">Status</th>
                <th style="width:160px"></th>
              </tr>
            </thead>
            <tbody id="stock-tbody">
              <tr><td colspan="6"><div class="empty"><i data-lucide="loader-2" width="28" height="28" class="spin"></i><p>Carregando…</p></div></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="assets/app.js"></script>
<script>
let allProducts = [];
let stockFilter = '';
let entryProductId = null;
let zeroProductId = null;

async function loadStock() {
  const tbody = document.getElementById('stock-tbody');
  try {
    const res = await api('/products?limit=500');
    allProducts = Array.isArray(res) ? res : (res.data || []);
    updateSummary();
    filterRender();
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty"><p style="color:var(--red)">${e.message}</p></div></td></tr>`;
  }
}

function updateSummary() {
  const total     = allProducts.length;
  const unlimited = allProducts.filter(p => p.stock == 0).length;
  const empty     = allProducts.filter(p => p.stock > 0 && p.stock <= 0).length; // stock === 0 means unlimited; negative not expected
  const esgotado  = allProducts.filter(p => Number(p.stock) < 0 || (Number(p.stock) > 0 && Number(p.stock) === 0)).length;
  // stock=0 → unlimited; stock>0 → limited; treat <0 as invalid
  const limited   = allProducts.filter(p => Number(p.stock) > 0);
  const low       = limited.filter(p => Number(p.stock) <= 5).length;
  const estoqueZero = allProducts.filter(p => Number(p.stock) < 0).length; // if API ever returns negative

  document.getElementById('s-total').textContent     = total;
  document.getElementById('s-low').textContent       = low;
  document.getElementById('s-empty').textContent     = estoqueZero;
  document.getElementById('s-unlimited').textContent = unlimited;
}

function stockStatus(stock) {
  const s = Number(stock);
  if (s === 0) return { cls:'unlimited', label:'Ilimitado', sf:'unlimited' };
  if (s < 0)  return { cls:'empty',     label:'Esgotado',  sf:'empty' };
  if (s <= 5) return { cls:'low',        label:'Baixo',     sf:'low' };
  return       { cls:'ok',       label:'OK',        sf:'ok' };
}

function filterRender() {
  const q = (document.getElementById('stock-search').value || '').toLowerCase();
  let rows = allProducts;
  if (stockFilter) rows = rows.filter(p => stockStatus(p.stock).sf === stockFilter);
  if (q)           rows = rows.filter(p => p.name.toLowerCase().includes(q) || (p.category_name||'').toLowerCase().includes(q));

  const tbody = document.getElementById('stock-tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="6"><div class="empty"><i data-lucide="inbox" width="28" height="28"></i><p>Nenhum produto encontrado</p></div></td></tr>';
    lucide.createIcons(); return;
  }

  tbody.innerHTML = rows.map(p => {
    const st = stockStatus(p.stock);
    const s  = Number(p.stock);
    const badge = st.sf === 'low' ? `<span class="stock-badge-low">Baixo</span>` :
                  s < 0           ? `<span class="stock-badge-empty">Esgotado</span>` : '';
    return `<tr>
      <td>${p.image_url
        ? `<img class="prod-thumb" src="${p.image_url}" alt="">`
        : `<div class="prod-thumb-ph"><i data-lucide="image" width="16" height="16"></i></div>`
      }</td>
      <td>
        <div style="font-weight:600">${p.name}</div>
        ${badge}
      </td>
      <td style="font-size:12.5px;color:var(--fg-2)">${p.category_name || '—'}</td>
      <td>
        <input class="qty-inline" type="number" min="0" value="${s}"
          data-id="${p.id}" data-orig="${s}"
          onchange="saveStockInline(this)"
          title="${s === 0 ? 'Ilimitado (0 = sem limite)' : s + ' unidades'}">
      </td>
      <td><span class="stock-val ${st.cls}">${s === 0 ? '<i data-lucide="infinity" width="13" height="13"></i> Ilimitado' : s + ' un.'}</span></td>
      <td style="white-space:nowrap">
        <button class="btn btn-ghost btn-sm" onclick="openEntry(${p.id})">+ Entrada</button>
        <button class="btn btn-sm btn-danger" style="margin-left:4px" onclick="openZero(${p.id})">Zerar</button>
      </td>
    </tr>`;
  }).join('');
  lucide.createIcons();
}

async function saveStockInline(input) {
  const id   = input.dataset.id;
  const orig = Number(input.dataset.orig);
  const val  = Math.max(0, parseInt(input.value) || 0);
  input.value = val;
  if (val === orig) return;
  try {
    await api(`/products/${id}`, { method: 'PUT', body: JSON.stringify({ stock: val }) });
    input.dataset.orig = val;
    const p = allProducts.find(x => x.id == id);
    if (p) p.stock = val;
    toast('Estoque atualizado');
    updateSummary();
    filterRender();
  } catch (e) {
    toast(e.message, 'error');
    input.value = orig;
  }
}

/* ── Entrada de estoque ── */
function openEntry(id) {
  const p = allProducts.find(x => x.id == id);
  if (!p) return;
  entryProductId = id;
  document.getElementById('entry-product-name').textContent = p.name;
  document.getElementById('entry-current').value = Number(p.stock) === 0 ? 'Ilimitado (0)' : p.stock;
  const qtyEl = document.getElementById('entry-qty');
  qtyEl.value = 1;
  calcEntryTotal();
  openModal('modal-entry');
  setTimeout(() => qtyEl.focus(), 100);
}
function calcEntryTotal() {
  const p    = allProducts.find(x => x.id == entryProductId);
  if (!p) return;
  const curr = Number(p.stock);
  const add  = parseInt(document.getElementById('entry-qty').value) || 0;
  document.getElementById('entry-new-total').value = curr === 0 ? '∞ (ainda ilimitado)' : curr + add;
}
document.getElementById('entry-qty').addEventListener('input', calcEntryTotal);
async function confirmEntry() {
  const p   = allProducts.find(x => x.id == entryProductId);
  if (!p) return;
  const add = parseInt(document.getElementById('entry-qty').value);
  if (!add || add < 1) { toast('Informe uma quantidade válida', 'error'); return; }
  const btn = document.getElementById('btn-confirm-entry');
  btn.disabled = true; btn.textContent = 'Salvando…';
  const newStock = Number(p.stock) === 0 ? 0 : Number(p.stock) + add;
  try {
    await api(`/products/${p.id}`, { method: 'PUT', body: JSON.stringify({ stock: newStock }) });
    p.stock = newStock;
    toast(`+${add} unidades adicionadas`);
    closeModal('modal-entry');
    updateSummary();
    filterRender();
  } catch (e) { toast(e.message, 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Confirmar entrada'; }
}

/* ── Zerar estoque ── */
function openZero(id) {
  const p = allProducts.find(x => x.id == id);
  if (!p) return;
  zeroProductId = id;
  document.getElementById('zero-product-name').textContent = p.name;
  openModal('modal-zero');
}
document.getElementById('btn-confirm-zero').addEventListener('click', async () => {
  const p = allProducts.find(x => x.id == zeroProductId);
  if (!p) return;
  try {
    await api(`/products/${p.id}`, { method: 'PUT', body: JSON.stringify({ stock: -1 }) });
    p.stock = -1;
    toast('Estoque zerado');
    closeModal('modal-zero');
    updateSummary();
    filterRender();
  } catch (e) { toast(e.message, 'error'); }
});

/* ── Filtros ── */
document.querySelectorAll('[data-sf]').forEach(btn => {
  btn.addEventListener('click', () => {
    stockFilter = btn.dataset.sf;
    document.querySelectorAll('[data-sf]').forEach(b => b.className = 'btn btn-ghost btn-sm');
    btn.className = 'btn btn-primary btn-sm';
    filterRender();
  });
});

loadStock();
</script>
</body>
</html>
