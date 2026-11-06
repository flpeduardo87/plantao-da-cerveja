<?php require 'auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Produtos · Plantão Admin</title>
<link rel="icon" href="../favicon-icone.webp">
<link rel="stylesheet" href="assets/style.css?v=5">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<script src="assets/lucide.min.js"></script>
</head>
<body>
<div id="toast"></div>

<!-- Modal: criar/editar produto -->
<div class="modal-backdrop" id="modal-product">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-product-title">Novo produto</h3>
      <button class="btn-icon borderless" onclick="closeModal('modal-product')"><i data-lucide="x" width="16" height="16"></i></button>
    </div>
    <div class="modal-body">
      <form id="product-form" onsubmit="saveProduct(event)">
        <input type="hidden" id="p-id">
        <div class="form-grid" style="margin-bottom:14px">
          <div class="field form-full">
            <label>Nome do produto *</label>
            <input type="text" id="p-name" required placeholder="Ex: Heineken Long Neck 330ml">
          </div>
          <div class="field">
            <label>Preço (R$) *</label>
            <input type="number" id="p-price" required min="0" step="0.01" placeholder="0,00">
          </div>
          <div class="field">
            <label>Preço original (R$)</label>
            <input type="number" id="p-old-price" min="0" step="0.01" placeholder="0,00 (deixe vazio se não houver desconto)">
          </div>
          <div class="field">
            <label>Categoria *</label>
            <select id="p-category" required><option value="">Selecione…</option></select>
          </div>
          <div class="field">
            <label>Estoque (0 = ilimitado)</label>
            <input type="number" id="p-stock" min="0" value="0">
          </div>
          <div class="field form-full">
            <label>Descrição</label>
            <textarea id="p-description" placeholder="Descrição curta do produto"></textarea>
          </div>

          <!-- Marca / Tipo / Tamanho -->
          <div class="field-section-label form-full" style="font-size:12px;font-weight:600;color:var(--fg-3);text-transform:uppercase;letter-spacing:.05em;margin:4px 0 -2px">Variante do produto</div>
          <div class="field">
            <label>Marca</label>
            <input type="text" id="p-brand" placeholder="Ex: Brahma, Heineken, Skol">
          </div>
          <div class="field">
            <label>Tipo</label>
            <select id="p-unit-type">
              <option value="">— Selecione —</option>
              <option value="Lata">Lata</option>
              <option value="Long Neck">Long Neck</option>
              <option value="Garrafa">Garrafa</option>
              <option value="Retornável">Retornável</option>
              <option value="Pack">Pack</option>
              <option value="Caixa">Caixa</option>
              <option value="Bag-in-Box">Bag-in-Box</option>
              <option value="Dose">Dose</option>
              <option value="Outro">Outro</option>
            </select>
          </div>
          <div class="field">
            <label>Tamanho / Quantidade</label>
            <input type="text" id="p-size" placeholder="Ex: 350ml, 600ml, Pack c/12">
          </div>
          <div class="field" style="flex-direction:row;align-items:center;gap:10px">
            <label class="toggle"><input type="checkbox" id="p-has-deposit" onchange="toggleDeposit(this.checked)"><span class="toggle-slider"></span></label>
            <span style="font-size:13px;font-weight:500">Inclui depósito de casco?</span>
          </div>
          <div class="field" id="deposit-value-field" style="display:none">
            <label>Valor do depósito (R$)</label>
            <input type="number" id="p-deposit-value" min="0" step="0.01" placeholder="Ex: 5,00">
          </div>

          <div class="field">
            <label>Ordem de exibição</label>
            <input type="number" id="p-sort" min="0" value="0">
          </div>
          <div class="field form-full">
            <label>Foto do produto</label>
            <div class="img-upload" id="img-drop" onclick="document.getElementById('p-img-file').click()">
              <img id="p-img-preview" src="" alt="" style="display:none">
              <p>Clique para escolher uma imagem (JPG, PNG, WebP · máx 3 MB)</p>
              <input type="file" id="p-img-file" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="previewImg(this)">
            </div>
            <input type="hidden" id="p-image-url">
          </div>
          <div class="field" style="flex-direction:row;align-items:center;gap:10px">
            <label class="toggle"><input type="checkbox" id="p-active" checked><span class="toggle-slider"></span></label>
            <span style="font-size:13px;font-weight:500">Produto ativo</span>
          </div>
          <div class="field" style="flex-direction:row;align-items:center;gap:10px">
            <label class="toggle"><input type="checkbox" id="p-featured"><span class="toggle-slider"></span></label>
            <span style="font-size:13px;font-weight:500">Oferta</span>
          </div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-product')">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btn-save-product">Salvar produto</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: confirmar exclusão -->
<div class="modal-backdrop" id="modal-delete">
  <div class="modal modal-sm">
    <div class="modal-header"><h3>Excluir produto?</h3></div>
    <div class="modal-body"><p style="font-size:13.5px;color:var(--fg-2)">Esta ação não pode ser desfeita. O produto será removido permanentemente.</p></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-delete')">Cancelar</button>
      <button class="btn btn-danger" id="btn-confirm-delete">Excluir</button>
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
      <a class="nav-link" href="pedidos"><i data-lucide="package" width="16" height="16"></i> Pedidos <span class="badge" id="new-count" style="display:none">0</span></a>
      <div class="nav-section">Catálogo</div>
      <a class="nav-link active" href="produtos"><i data-lucide="box" width="16" height="16"></i> Produtos</a>
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
      <h1>Produtos</h1>
      <div class="topbar-right">
        <button class="store-status closed" id="store-status-btn" data-open="0"><span class="dot"></span>Carregando…</button>
        <button class="btn btn-primary" onclick="openNew()"><i data-lucide="plus" width="15" height="15"></i> Novo produto</button>
      </div>
    </div>

    <div class="page">
      <div class="stats-grid prod-stats-grid">
        <div class="stat-tile blue"><div class="st-icon"><i data-lucide="box" width="18" height="18"></i></div><div class="label">Cadastrados</div><div class="value" id="stat-total">—</div></div>
        <div class="stat-tile green"><div class="st-icon"><i data-lucide="check-circle" width="18" height="18"></i></div><div class="label">Disponíveis</div><div class="value" id="stat-avail">—</div></div>
        <div class="stat-tile red"><div class="st-icon"><i data-lucide="x-circle" width="18" height="18"></i></div><div class="label">Indisponíveis</div><div class="value" id="stat-unavail">—</div></div>
        <div class="stat-tile amber"><div class="st-icon"><i data-lucide="tag" width="18" height="18"></i></div><div class="label">Em oferta</div><div class="value" id="stat-featured">—</div></div>
        <div class="stat-tile purple"><div class="st-icon"><i data-lucide="layers" width="18" height="18"></i></div><div class="label">Categorias</div><div class="value" id="stat-cats">—</div></div>
      </div>
      <div class="filter-bar">
        <input type="search" id="search-input" class="filter-input" placeholder="Buscar produto…">
        <div class="filter-selects-row">
          <select id="cat-select" class="filter-select"><option value="">Todas as categorias</option></select>
          <select id="status-select" class="filter-select">
            <option value="">Todos os status</option>
            <option value="1">Disponível</option>
            <option value="0">Indisponível</option>
          </select>
        </div>
        <div class="spacer"></div>
        <button class="btn btn-ghost btn-sm" onclick="exportCSV()"><i data-lucide="download" width="14" height="14"></i> Exportar</button>
      </div>
      <div class="card"><div class="prod-list" id="products-list"></div></div>
    </div>
  </div>
</div>

<script src="assets/app.js?v=5"></script>
<script>
let categories = [];
let allProds = [];
let searchVal = '', catFilter = '', statusFilter = '';
let deleteId = null;

async function toggleProduct(id, current) {
  const newVal = current ? 0 : 1;
  const cb  = document.getElementById(`cb-active-${id}`);
  const div = document.getElementById(`toggle-active-${id}`);
  const lbl = document.getElementById(`lbl-active-${id}`);
  if (cb)  cb.checked = !!newVal;
  if (div) div.setAttribute('onclick', `toggleProduct(${id},${newVal})`);
  if (lbl) { lbl.textContent = newVal ? 'Disponível' : 'Inativo'; lbl.className = `prod-toggle-lbl${newVal?' active-lbl':''}`; }
  try {
    await api(`/products/${id}`, { method: 'PUT', body: JSON.stringify({ active: newVal }) });
    toast(newVal ? 'Produto ativado' : 'Produto desativado');
    const p = allProds.find(x => x.id === id);
    if (p) { p.active = newVal; updateStats(); }
  } catch(e) {
    if (cb)  cb.checked = !!current;
    if (div) div.setAttribute('onclick', `toggleProduct(${id},${current})`);
    if (lbl) { lbl.textContent = current ? 'Disponível' : 'Indisponível'; lbl.className = `prod-toggle-lbl${current?' active-lbl':''}`; }
    toast(e.message, 'error');
  }
}

async function toggleFeatured(id, current) {
  const newVal = current ? 0 : 1;
  const cb  = document.getElementById(`cb-featured-${id}`);
  const div = document.getElementById(`toggle-featured-${id}`);
  const lbl = document.getElementById(`lbl-featured-${id}`);
  if (cb)  cb.checked = !!newVal;
  if (div) div.setAttribute('onclick', `toggleFeatured(${id},${newVal})`);
  if (lbl) { lbl.textContent = newVal ? 'Promoção' : '—'; lbl.className = `prod-toggle-lbl${newVal?' promo-lbl':''}`; }
  try {
    await api(`/products/${id}`, { method: 'PUT', body: JSON.stringify({ featured: newVal }) });
    toast(newVal ? 'Marcado como oferta' : 'Oferta removida');
    const p = allProds.find(x => x.id === id);
    if (p) { p.featured = newVal; updateStats(); }
  } catch(e) {
    if (cb)  cb.checked = !!current;
    if (div) div.setAttribute('onclick', `toggleFeatured(${id},${current})`);
    if (lbl) { lbl.textContent = current ? 'Promoção' : '—'; lbl.className = `prod-toggle-lbl${current?' promo-lbl':''}`; }
    toast(e.message, 'error');
  }
}

async function loadCategories() {
  try {
    const res = await api('/categories');
    categories = Array.isArray(res) ? res : (res.data || []);
    const sel = document.getElementById('p-category');
    const catSel = document.getElementById('cat-select');
    const seen = new Map();
    categories.forEach(c => {
      const key = c.name.toLowerCase();
      if (!seen.has(key) || c.display_type === 'icon') seen.set(key, c);
    });
    const deduped = [...seen.values()].sort((a, b) => {
      const aO = a.slug === 'ofertas', bO = b.slug === 'ofertas';
      if (aO !== bO) return aO ? -1 : 1;
      return a.name.localeCompare(b.name, 'pt');
    });
    deduped.forEach(c => {
      sel.innerHTML += `<option value="${c.id}">${c.name}</option>`;
      catSel.innerHTML += `<option value="${c.id}">${c.name}</option>`;
    });
  } catch {}
}

async function loadProducts() {
  const tbody = document.getElementById('products-list');
  tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--fg-3)">Carregando…</td></tr>';
  try {
    const res = await api('/products?all=1');
    allProds = Array.isArray(res) ? res : (res.data || []);
    renderProds();
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="8"><div class="empty"><p style="color:var(--red)">${e.message}</p></div></td></tr>`;
  }
}

function updateStats() {
  const total = allProds.length;
  const avail = allProds.filter(p => p.active).length;
  const feat  = allProds.filter(p => p.featured).length;
  const cats  = new Set(allProds.map(p => p.category_id).filter(Boolean)).size;
  document.getElementById('stat-total').textContent    = total;
  document.getElementById('stat-avail').textContent    = avail;
  document.getElementById('stat-unavail').textContent  = total - avail;
  document.getElementById('stat-featured').textContent = feat;
  document.getElementById('stat-cats').textContent     = cats;
}

function renderProds() {
  updateStats();
  const q = searchVal.toLowerCase();
  const prods = allProds.filter(p => {
    if (q && !p.name.toLowerCase().includes(q) && !(p.brand||'').toLowerCase().includes(q)) return false;
    if (catFilter && String(p.category_id) !== catFilter) return false;
    if (statusFilter !== '' && String(p.active ? 1 : 0) !== statusFilter) return false;
    return true;
  });

  const list = document.getElementById('products-list');
  if (!prods.length) {
    list.innerHTML = '<div class="empty"><i data-lucide="inbox" width="28" height="28"></i><p>Nenhum produto encontrado</p></div>';
    lucide.createIcons(); return;
  }
  list.innerHTML = prods.map(p => {
    const isUnlimited = p.stock <= 0;
    const stockTxt = isUnlimited ? '∞ ilimitado' : `${p.stock} un.`;
    return `
    <div class="prod-card" id="prod-row-${p.id}">
      <input type="checkbox" class="row-chk" value="${p.id}">
      ${p.image_url
        ? `<img src="${p.image_url}" class="prod-card-img" alt="">`
        : `<div class="prod-card-ph"><i data-lucide="image" width="20" height="20"></i></div>`}
      <div class="prod-card-info">
        <div class="prod-card-name">${p.name}</div>
        <div class="prod-card-sku">#${p.id}</div>
        ${p.category_name ? `<span class="cat-badge">${p.category_name}</span>` : ''}
        <div class="prod-card-price">${fmtMoney(p.price)}${p.old_price?` <s style="font-size:11px;color:var(--fg-3);font-weight:400">${fmtMoney(p.old_price)}</s>`:''}</div>
      </div>
      <div class="prod-card-right">
        <div class="prod-card-stock${isUnlimited?' unlimited':''}">${stockTxt}</div>
        <div class="prod-toggles">
          <div class="prod-toggle-col" id="toggle-active-${p.id}" onclick="toggleProduct(${p.id},${p.active?1:0})">
            <label class="toggle" style="pointer-events:none">
              <input type="checkbox" id="cb-active-${p.id}" ${p.active?'checked':''}>
              <span class="toggle-slider"></span>
            </label>
            <span id="lbl-active-${p.id}" class="prod-toggle-lbl${p.active?' active-lbl':''}">${p.active?'Disponível':'Inativo'}</span>
          </div>
          <div class="prod-toggle-col" id="toggle-featured-${p.id}" onclick="toggleFeatured(${p.id},${p.featured?1:0})">
            <label class="toggle" style="pointer-events:none">
              <input type="checkbox" id="cb-featured-${p.id}" ${p.featured?'checked':''}>
              <span class="toggle-slider"></span>
            </label>
            <span id="lbl-featured-${p.id}" class="prod-toggle-lbl${p.featured?' promo-lbl':''}">${p.featured?'Promoção':'—'}</span>
          </div>
        </div>
      </div>
      <div class="prod-card-menu-wrap">
        <button class="prod-card-menu-btn" onclick="toggleProdMenu(event,${p.id})" aria-label="Ações">
          <i data-lucide="more-vertical" width="16" height="16"></i>
        </button>
        <div class="prod-menu-dropdown" id="prod-menu-${p.id}" style="display:none">
          <button onclick="editProduct(${p.id});closeProdMenus()"><i data-lucide="pencil" width="13" height="13"></i> Editar</button>
          <button onclick="duplicateProduct(${p.id});closeProdMenus()"><i data-lucide="copy" width="13" height="13"></i> Duplicar</button>
          <button onclick="confirmDelete(${p.id});closeProdMenus()" style="color:var(--red)"><i data-lucide="trash-2" width="13" height="13"></i> Excluir</button>
        </div>
      </div>
    </div>`;
  }).join('');
  lucide.createIcons();
}

function toggleAllChk(master) {
  document.querySelectorAll('.row-chk').forEach(cb => cb.checked = master.checked);
}

function toggleProdMenu(e, id) {
  e.stopPropagation();
  const menu = document.getElementById(`prod-menu-${id}`);
  const isOpen = menu.style.display !== 'none';
  closeProdMenus();
  if (!isOpen) menu.style.display = 'flex';
}
function closeProdMenus() {
  document.querySelectorAll('.prod-menu-dropdown').forEach(m => m.style.display = 'none');
}
document.addEventListener('click', () => closeProdMenus());

function exportCSV() {
  const rows = [['ID','Nome','Categoria','Preço','Estoque','Ativo','Oferta']];
  allProds.forEach(p => rows.push([p.id, p.name, p.category_name||'', p.price, p.stock, p.active?'Sim':'Não', p.featured?'Sim':'Não']));
  const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'produtos.csv'; a.click();
}

function toggleDeposit(checked) {
  document.getElementById('deposit-value-field').style.display = checked ? '' : 'none';
  if (!checked) document.getElementById('p-deposit-value').value = '';
}

function openNew() {
  document.getElementById('product-form').reset();
  document.getElementById('p-id').value = '';
  document.getElementById('p-image-url').value = '';
  const prev = document.getElementById('p-img-preview');
  prev.style.display = 'none'; prev.src = '';
  toggleDeposit(false);
  document.getElementById('modal-product-title').textContent = 'Novo produto';
  openModal('modal-product');
}

async function editProduct(id) {
  openModal('modal-product');
  document.getElementById('modal-product-title').textContent = 'Editar produto';
  try {
    const p = await api(`/products/${id}`);
    document.getElementById('p-id').value = p.id;
    document.getElementById('p-name').value = p.name;
    document.getElementById('p-price').value = p.price;
    document.getElementById('p-old-price').value = p.old_price || '';
    document.getElementById('p-category').value = p.category_id || '';
    document.getElementById('p-description').value = p.description || '';
    document.getElementById('p-stock').value = p.stock || 0;
    document.getElementById('p-sort').value = p.sort_order || 0;
    document.getElementById('p-active').checked = p.active == 1;
    document.getElementById('p-featured').checked = p.featured == 1;
    document.getElementById('p-image-url').value = p.image_url || '';
    document.getElementById('p-brand').value = p.brand || '';
    document.getElementById('p-unit-type').value = p.unit_type || '';
    document.getElementById('p-size').value = p.size || '';
    const hasDeposit = p.has_deposit == 1;
    document.getElementById('p-has-deposit').checked = hasDeposit;
    document.getElementById('p-deposit-value').value = p.deposit_value || '';
    toggleDeposit(hasDeposit);
    const prev = document.getElementById('p-img-preview');
    if (p.image_url) { prev.src = p.image_url; prev.style.display = 'block'; }
    else { prev.style.display = 'none'; }
  } catch(e) { toast(e.message, 'error'); closeModal('modal-product'); }
}

function previewImg(input) {
  if (!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const prev = document.getElementById('p-img-preview');
    prev.src = e.target.result; prev.style.display = 'block';
  };
  reader.readAsDataURL(input.files[0]);
}

async function uploadImage(file) {
  const fd = new FormData(); fd.append('image', file);
  const res = await fetch(`${API}/upload`, {
    method: 'POST', headers: { 'Authorization': `Bearer ${getToken()}` }, body: fd
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Erro no upload');
  return data.url;
}

async function saveProduct(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-save-product');
  btn.disabled = true; btn.textContent = 'Salvando…';
  try {
    const fileInput = document.getElementById('p-img-file');
    let imageUrl = document.getElementById('p-image-url').value;
    if (fileInput.files[0]) imageUrl = await uploadImage(fileInput.files[0]);
    const id = document.getElementById('p-id').value;
    const oldPrice = document.getElementById('p-old-price').value;
    const body = {
      name: document.getElementById('p-name').value,
      price: parseFloat(document.getElementById('p-price').value),
      old_price: oldPrice ? parseFloat(oldPrice) : null,
      category_id: parseInt(document.getElementById('p-category').value) || null,
      description: document.getElementById('p-description').value,
      stock: parseInt(document.getElementById('p-stock').value) || 0,
      sort_order: parseInt(document.getElementById('p-sort').value) || 0,
      active: document.getElementById('p-active').checked ? 1 : 0,
      featured: document.getElementById('p-featured').checked ? 1 : 0,
      image_url: imageUrl,
      brand: document.getElementById('p-brand').value.trim() || null,
      unit_type: document.getElementById('p-unit-type').value || null,
      size: document.getElementById('p-size').value.trim() || null,
      has_deposit: document.getElementById('p-has-deposit').checked ? 1 : 0,
      deposit_value: parseFloat(document.getElementById('p-deposit-value').value) || 0,
    };
    if (id) { await api(`/products/${id}`, { method: 'PUT', body: JSON.stringify(body) }); toast('Produto atualizado'); }
    else { await api('/products', { method: 'POST', body: JSON.stringify(body) }); toast('Produto criado'); }
    closeModal('modal-product'); loadProducts();
  } catch(err) { toast(err.message, 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Salvar produto'; }
}

async function duplicateProduct(id) {
  const p = allProds.find(x => x.id === id);
  if (!p) return;
  try {
    const body = {
      name: p.name + ' (cópia)', price: p.price, old_price: p.old_price || null,
      category_id: p.category_id || null, description: p.description || '',
      stock: p.stock || 0, sort_order: p.sort_order || 0, active: 0, featured: 0,
      image_url: p.image_url || '', brand: p.brand || null,
      unit_type: p.unit_type || null, size: p.size || null,
      has_deposit: p.has_deposit || 0, deposit_value: p.deposit_value || 0,
    };
    await api('/products', { method: 'POST', body: JSON.stringify(body) });
    toast('Produto duplicado'); loadProducts();
  } catch(e) { toast(e.message, 'error'); }
}

function confirmDelete(id) { deleteId = id; openModal('modal-delete'); }

document.getElementById('btn-confirm-delete').addEventListener('click', async () => {
  if (!deleteId) return;
  try {
    await api(`/products/${deleteId}`, { method: 'DELETE' });
    toast('Produto excluído'); closeModal('modal-delete'); loadProducts();
  } catch(e) { toast(e.message, 'error'); }
});

document.getElementById('search-input').addEventListener('input', e => { searchVal = e.target.value; renderProds(); });
document.getElementById('cat-select').addEventListener('change', e => { catFilter = e.target.value; renderProds(); });
document.getElementById('status-select').addEventListener('change', e => { statusFilter = e.target.value; renderProds(); });

loadCategories();
loadProducts();
</script>
</body>
</html>
