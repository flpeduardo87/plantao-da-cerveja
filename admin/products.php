<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Produtos · Plantão Admin</title>
<link rel="icon" href="assets/favicon.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div id="toast"></div>

<div class="modal-backdrop" id="modal-product">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-product-title">Novo produto</h3>
      <button class="btn-icon" onclick="closeModal('modal-product')">✕</button>
    </div>
    <div class="modal-body">
      <form id="product-form" onsubmit="saveProduct(event)">
        <input type="hidden" id="p-id">
        <div class="form-grid" style="margin-bottom:16px">
          <div class="field form-full"><label>Nome do produto *</label><input type="text" id="p-name" required placeholder="Ex: Heineken 600ml"></div>
          <div class="field"><label>Preço (R$) *</label><input type="number" id="p-price" required min="0" step="0.01" placeholder="0,00"></div>
          <div class="field"><label>Categoria *</label><select id="p-category" required><option value="">Selecione…</option></select></div>
          <div class="field form-full"><label>Descrição</label><textarea id="p-description" placeholder="Descrição curta do produto"></textarea></div>
          <div class="field"><label>Estoque (0 = ilimitado)</label><input type="number" id="p-stock" min="0" value="0"></div>
          <div class="field"><label>Ordem de exibição</label><input type="number" id="p-sort" min="0" value="0"></div>
          <div class="field form-full">
            <label>Foto do produto</label>
            <div class="img-upload" id="img-drop" onclick="document.getElementById('p-img-file').click()">
              <img id="p-img-preview" src="" alt="" style="display:none">
              <p>Clique para escolher uma imagem (JPG, PNG, WebP · máx 3MB)</p>
              <input type="file" id="p-img-file" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="previewImg(this)">
            </div>
            <input type="hidden" id="p-image-url">
          </div>
          <div class="field" style="flex-direction:row;align-items:center;gap:10px">
            <label class="toggle"><input type="checkbox" id="p-active" checked><span class="toggle-slider"></span></label>
            <span style="font-size:13px;font-weight:700">Produto ativo</span>
          </div>
          <div class="field" style="flex-direction:row;align-items:center;gap:10px">
            <label class="toggle"><input type="checkbox" id="p-featured"><span class="toggle-slider"></span></label>
            <span style="font-size:13px;font-weight:700">Destaque</span>
          </div>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-product')">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btn-save-product">Salvar produto</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="modal-delete">
  <div class="modal" style="max-width:380px">
    <div class="modal-header"><h3>Excluir produto?</h3></div>
    <div class="modal-body"><p style="font-size:14px;color:var(--fg-2)">Esta ação não pode ser desfeita.</p></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-delete')">Cancelar</button>
      <button class="btn btn-danger" id="btn-confirm-delete">Excluir</button>
    </div>
  </div>
</div>

<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo"><img src="assets/logo.jpg" alt=""><div><span>Plantão da Cerveja</span><small>Painel Admin</small></div></div>
    <nav>
      <div class="nav-section">Menu</div>
      <a class="nav-link" href="dashboard.php"><span class="icon">📊</span> Dashboard</a>
      <a class="nav-link" href="orders.php"><span class="icon">📦</span> Pedidos</a>
      <a class="nav-link active" href="products.php"><span class="icon">🍺</span> Produtos</a>
      <a class="nav-link" href="settings.php"><span class="icon">⚙️</span> Configurações</a>
    </nav>
    <div class="sidebar-footer"><button class="btn-logout" data-logout>🚪 Sair</button></div>
  </aside>

  <div class="main">
    <div class="topbar">
      <button class="hamburger" id="hamburger">☰</button>
      <h1>Produtos</h1>
      <div class="topbar-right">
        <button class="store-status closed" id="store-status-btn" data-open="0"><span class="dot"></span>Carregando…</button>
        <button class="btn btn-primary" onclick="openNew()">+ Novo produto</button>
      </div>
    </div>
    <div class="page">
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px" id="cat-filters">
        <button class="btn btn-primary btn-sm" data-cat="">Todos</button>
      </div>
      <div id="products-list"></div>
    </div>
  </div>
</div>

<script src="assets/app.js"></script>
<script>
let categories = [];
let catFilter = '';
let deleteId = null;

async function loadCategories() {
  try {
    categories = await api('/categories');
    const sel = document.getElementById('p-category');
    const wrap = document.getElementById('cat-filters');
    categories.forEach(c => {
      sel.innerHTML += `<option value="${c.id}">${c.emoji} ${c.name}</option>`;
      const btn = document.createElement('button');
      btn.className = 'btn btn-ghost btn-sm'; btn.dataset.cat = c.slug;
      btn.textContent = `${c.emoji} ${c.name}`;
      btn.addEventListener('click', () => setCatFilter(c.slug, btn));
      wrap.appendChild(btn);
    });
  } catch {}
}

function setCatFilter(slug, btn) {
  catFilter = slug;
  document.querySelectorAll('[data-cat]').forEach(b => b.className = 'btn btn-ghost btn-sm');
  btn.className = 'btn btn-primary btn-sm';
  loadProducts();
}

async function loadProducts() {
  const qs = catFilter ? `?category=${catFilter}` : '';
  const list = document.getElementById('products-list');
  list.innerHTML = '<div class="empty"><div class="icon">⏳</div><p>Carregando…</p></div>';
  try {
    const products = await api(`/products${qs}`);
    if (!products.length) { list.innerHTML = '<div class="empty"><div class="icon">📭</div><p>Nenhum produto cadastrado</p></div>'; return; }
    const grouped = {};
    products.forEach(p => { const key = p.category_name || 'Sem categoria'; if (!grouped[key]) grouped[key] = []; grouped[key].push(p); });
    list.innerHTML = Object.entries(grouped).map(([cat, prods]) => `
      <div style="margin-bottom:24px">
        <h3 style="font-size:13px;font-weight:800;color:var(--fg-2);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px">${cat}</h3>
        <div class="card"><div class="tbl-wrap"><table>
          <thead><tr><th style="width:60px">Foto</th><th>Nome</th><th>Preço</th><th>Estoque</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody>${prods.map(p => `
            <tr>
              <td>${p.image_url ? `<img src="${p.image_url}" style="width:44px;height:44px;border-radius:8px;object-fit:cover">` : '<div style="width:44px;height:44px;border-radius:8px;background:#F3F4F6;display:flex;align-items:center;justify-content:center;font-size:20px">🍺</div>'}</td>
              <td><div style="font-weight:700">${p.name}</div>${p.description?`<div style="font-size:12px;color:var(--fg-2)">${p.description.slice(0,60)}${p.description.length>60?'…':''}</div>`:''} ${p.featured?'<span class="badge" style="background:var(--amber-lt);color:#92400E;font-size:10px">Destaque</span>':''}</td>
              <td><strong>${fmtMoney(p.price)}</strong></td>
              <td style="font-size:13px">${p.stock > 0 ? p.stock + ' un.' : '<span style="color:var(--fg-3)">Ilimitado</span>'}</td>
              <td>${p.active ? '<span class="badge badge-delivered">Ativo</span>' : '<span class="badge badge-cancelled">Inativo</span>'}</td>
              <td style="white-space:nowrap">
                <button class="btn btn-ghost btn-sm" onclick="editProduct(${p.id})">Editar</button>
                <button class="btn btn-sm" style="background:var(--red-lt);color:#991B1B;margin-left:4px" onclick="confirmDelete(${p.id})">Excluir</button>
              </td>
            </tr>
          `).join('')}</tbody>
        </table></div></div>
      </div>
    `).join('');
  } catch (e) { list.innerHTML = `<div class="empty"><p style="color:var(--red)">${e.message}</p></div>`; }
}

function openNew() {
  document.getElementById('product-form').reset();
  document.getElementById('p-id').value = '';
  document.getElementById('p-image-url').value = '';
  const prev = document.getElementById('p-img-preview');
  prev.style.display = 'none'; prev.src = '';
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
    document.getElementById('p-category').value = p.category_id || '';
    document.getElementById('p-description').value = p.description || '';
    document.getElementById('p-stock').value = p.stock || 0;
    document.getElementById('p-sort').value = p.sort_order || 0;
    document.getElementById('p-active').checked = p.active == 1;
    document.getElementById('p-featured').checked = p.featured == 1;
    document.getElementById('p-image-url').value = p.image_url || '';
    const prev = document.getElementById('p-img-preview');
    if (p.image_url) { prev.src = p.image_url; prev.style.display = 'block'; } else { prev.style.display = 'none'; }
  } catch (e) { toast(e.message, 'error'); closeModal('modal-product'); }
}

function previewImg(input) {
  if (!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => { const prev = document.getElementById('p-img-preview'); prev.src = e.target.result; prev.style.display = 'block'; };
  reader.readAsDataURL(input.files[0]);
}

async function uploadImage(file) {
  const fd = new FormData(); fd.append('image', file);
  const res = await fetch(`${API}/upload`, { method: 'POST', headers: { 'Authorization': `Bearer ${getToken()}` }, body: fd });
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
    const body = {
      name: document.getElementById('p-name').value,
      price: parseFloat(document.getElementById('p-price').value),
      category_id: parseInt(document.getElementById('p-category').value) || null,
      description: document.getElementById('p-description').value,
      stock: parseInt(document.getElementById('p-stock').value) || 0,
      sort_order: parseInt(document.getElementById('p-sort').value) || 0,
      active: document.getElementById('p-active').checked ? 1 : 0,
      featured: document.getElementById('p-featured').checked ? 1 : 0,
      image_url: imageUrl,
    };
    if (id) { await api(`/products/${id}`, { method: 'PUT', body: JSON.stringify(body) }); toast('Produto atualizado!'); }
    else { await api('/products', { method: 'POST', body: JSON.stringify(body) }); toast('Produto criado!'); }
    closeModal('modal-product'); loadProducts();
  } catch (err) { toast(err.message, 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Salvar produto'; }
}

function confirmDelete(id) { deleteId = id; openModal('modal-delete'); }

document.getElementById('btn-confirm-delete').addEventListener('click', async () => {
  if (!deleteId) return;
  try {
    await api(`/products/${deleteId}`, { method: 'DELETE' });
    toast('Produto excluído'); closeModal('modal-delete'); loadProducts();
  } catch (e) { toast(e.message, 'error'); }
});

document.querySelectorAll('[data-cat]').forEach(btn => {
  btn.addEventListener('click', () => setCatFilter(btn.dataset.cat, btn));
});

loadCategories();
loadProducts();
</script>
</body>
</html>
