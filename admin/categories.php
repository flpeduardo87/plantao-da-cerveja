<?php require 'auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Categorias · Plantão Admin</title>
<link rel="icon" href="../favicon-icone.webp">
<link rel="stylesheet" href="assets/style.css">
<script src="assets/lucide.min.js"></script>
</head>
<body>
<div id="toast"></div>

<!-- Modal: criar/editar categoria -->
<div class="modal-backdrop" id="modal-cat">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-cat-title">Nova categoria</h3>
      <button class="btn-icon borderless" onclick="closeModal('modal-cat')"><i data-lucide="x" width="16" height="16"></i></button>
    </div>
    <div class="modal-body">
      <form id="cat-form" onsubmit="saveCat(event)">
        <input type="hidden" id="c-id">
        <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:14px">
          <div class="field">
            <label>Nome da categoria *</label>
            <input type="text" id="c-name" required placeholder="Ex: Cervejas" oninput="autoSlug()">
          </div>
          <div class="field">
            <label>Slug (identificador único)</label>
            <input type="text" id="c-slug" required placeholder="cervejas" pattern="[a-z0-9\-]+" title="Apenas letras minúsculas, números e hífens">
            <span class="field-hint">Usado internamente. Não pode ser alterado depois de usado em produtos.</span>
          </div>
          <div class="field">
            <label>Ordem de exibição</label>
            <input type="number" id="c-sort" min="0" value="0">
          </div>
          <div class="field" style="flex-direction:row;align-items:center;gap:10px">
            <label class="toggle"><input type="checkbox" id="c-active" checked><span class="toggle-slider"></span></label>
            <span style="font-size:13px;font-weight:500">Categoria ativa</span>
          </div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-cat')">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btn-save-cat">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: confirmar exclusão -->
<div class="modal-backdrop" id="modal-delete">
  <div class="modal">
    <div class="modal-header"><h3>Excluir categoria?</h3></div>
    <div class="modal-body">
      <p style="font-size:13.5px;color:var(--fg-2)">Os produtos desta categoria não serão excluídos, mas ficarão sem categoria. Esta ação não pode ser desfeita.</p>
    </div>
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
      <a class="nav-link" href="dashboard.php"><i data-lucide="layout-dashboard" width="16" height="16"></i> Dashboard</a>
      <a class="nav-link" href="orders.php"><i data-lucide="package" width="16" height="16"></i> Pedidos <span class="badge" id="new-count" style="display:none">0</span></a>
      <div class="nav-section">Catálogo</div>
      <a class="nav-link" href="products.php"><i data-lucide="box" width="16" height="16"></i> Produtos</a>
      <a class="nav-link active" href="categories.php"><i data-lucide="tag" width="16" height="16"></i> Categorias</a>
      <a class="nav-link" href="stock.php"><i data-lucide="warehouse" width="16" height="16"></i> Estoque</a>
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
      <h1>Categorias</h1>
      <div class="topbar-right">
        <button class="store-status closed" id="store-status-btn" data-open="0"><span class="dot"></span>Carregando…</button>
        <button class="btn btn-primary" onclick="openNew()"><i data-lucide="plus" width="15" height="15"></i> Nova categoria</button>
      </div>
    </div>

    <div class="page" style="max-width:860px">
      <div class="card">
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr><th>Nome</th><th>Slug</th><th>Produtos</th><th>Ordem</th><th>Status</th><th></th></tr>
            </thead>
            <tbody id="cats-tbody">
              <tr><td colspan="6"><div class="empty"><i data-lucide="loader-2" width="28" height="28" class="spin"></i><p>Carregando…</p></div></td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <p style="font-size:12px;color:var(--fg-3);margin-top:12px">
        A ordem de exibição controla a sequência das categorias no app. Valores menores aparecem primeiro.
      </p>
    </div>
  </div>
</div>

<script src="assets/app.js"></script>
<script>
let deleteId = null;

function autoSlug() {
  const nameEl = document.getElementById('c-name');
  const slugEl = document.getElementById('c-slug');
  if (!slugEl.dataset.manual) {
    slugEl.value = nameEl.value.toLowerCase()
      .normalize('NFD').replace(/[̀-ͯ]/g,'')
      .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
  }
}

document.getElementById('c-slug').addEventListener('input', function() {
  this.dataset.manual = '1';
});

async function loadCats() {
  const tbody = document.getElementById('cats-tbody');
  try {
    const res = await api('/categories?include_count=1');
    const cats = Array.isArray(res) ? res : (res.data || []);
    if (!cats.length) {
      tbody.innerHTML = '<tr><td colspan="6"><div class="empty"><i data-lucide="inbox" width="28" height="28"></i><p>Nenhuma categoria cadastrada</p></div></td></tr>';
      lucide.createIcons(); return;
    }
    tbody.innerHTML = cats.map(c => `
      <tr>
        <td style="font-weight:600">${c.name}</td>
        <td><code style="font-size:12px;background:#F1F5F9;padding:2px 7px;border-radius:5px;color:var(--fg-2)">${c.slug}</code></td>
        <td style="font-size:13px;color:var(--fg-2)">${c.product_count ?? '—'} produto(s)</td>
        <td style="font-size:13px;color:var(--fg-2)">${c.sort_order ?? 0}</td>
        <td>${c.active ? '<span class="badge badge-delivered">Ativa</span>' : '<span class="badge badge-cancelled">Inativa</span>'}</td>
        <td style="white-space:nowrap">
          <button class="btn btn-ghost btn-sm" onclick="editCat(${c.id})">Editar</button>
          <button class="btn btn-sm btn-danger" style="margin-left:4px" onclick="confirmDelete(${c.id})">Excluir</button>
        </td>
      </tr>`).join('');
    lucide.createIcons();
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty"><p style="color:var(--red)">${e.message}</p></div></td></tr>`;
  }
}

function openNew() {
  document.getElementById('cat-form').reset();
  document.getElementById('c-id').value = '';
  delete document.getElementById('c-slug').dataset.manual;
  document.getElementById('modal-cat-title').textContent = 'Nova categoria';
  openModal('modal-cat');
}

async function editCat(id) {
  openModal('modal-cat');
  document.getElementById('modal-cat-title').textContent = 'Editar categoria';
  document.getElementById('c-slug').dataset.manual = '1';
  try {
    const c = await api(`/categories/${id}`);
    document.getElementById('c-id').value = c.id;
    document.getElementById('c-name').value = c.name;
    document.getElementById('c-slug').value = c.slug;
    document.getElementById('c-sort').value = c.sort_order ?? 0;
    document.getElementById('c-active').checked = c.active == 1;
  } catch (e) { toast(e.message, 'error'); closeModal('modal-cat'); }
}

async function saveCat(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-save-cat');
  btn.disabled = true; btn.textContent = 'Salvando…';
  try {
    const id = document.getElementById('c-id').value;
    const body = {
      name: document.getElementById('c-name').value,
      slug: document.getElementById('c-slug').value,
      sort_order: parseInt(document.getElementById('c-sort').value) || 0,
      active: document.getElementById('c-active').checked ? 1 : 0,
    };
    if (id) { await api(`/categories/${id}`, { method: 'PUT', body: JSON.stringify(body) }); toast('Categoria atualizada'); }
    else { await api('/categories', { method: 'POST', body: JSON.stringify(body) }); toast('Categoria criada'); }
    closeModal('modal-cat'); loadCats();
  } catch (err) { toast(err.message, 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Salvar'; }
}

function confirmDelete(id) { deleteId = id; openModal('modal-delete'); }

document.getElementById('btn-confirm-delete').addEventListener('click', async () => {
  if (!deleteId) return;
  try {
    await api(`/categories/${deleteId}`, { method: 'DELETE' });
    toast('Categoria excluída'); closeModal('modal-delete'); loadCats();
  } catch (e) { toast(e.message, 'error'); }
  deleteId = null;
});

loadCats();
</script>
</body>
</html>
