<?php require 'auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configurações · Plantão Admin</title>
<link rel="icon" href="assets/favicon.png">
<link rel="stylesheet" href="assets/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
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
      <div class="nav-section">Análises</div>
      <a class="nav-link" href="reports.php"><i data-lucide="bar-chart-2" width="16" height="16"></i> Relatórios</a>
      <div class="nav-section">Sistema</div>
      <a class="nav-link active" href="settings.php"><i data-lucide="settings" width="16" height="16"></i> Configurações</a>
    </nav>
    <div class="sidebar-footer">
      <button class="btn-logout" data-logout><i data-lucide="log-out" width="14" height="14"></i> Sair</button>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <button class="hamburger" id="hamburger"><i data-lucide="menu" width="20" height="20"></i></button>
      <h1>Configurações</h1>
      <div class="topbar-right">
        <button class="store-status closed" id="store-status-btn" data-open="0"><span class="dot"></span>Carregando…</button>
      </div>
    </div>

    <div class="page" style="max-width:760px;display:flex;flex-direction:column;gap:20px">

      <!-- Informações da loja -->
      <div class="card">
        <div class="card-header">
          <div class="ch-icon amber"><i data-lucide="store" width="14" height="14"></i></div>
          <h2>Informações da loja</h2>
        </div>
        <div class="card-body">
          <form id="form-store" onsubmit="saveStore(event)">
            <div class="form-grid" style="margin-bottom:14px">
              <div class="field form-full">
                <label>Nome da loja</label>
                <input type="text" id="s-store-name" placeholder="Plantão da Cerveja 24h">
              </div>
              <div class="field">
                <label>Pedido mínimo (R$)</label>
                <input type="number" id="s-min-order" min="0" step="0.01" placeholder="20,00">
              </div>
              <div class="field">
                <label>Taxa de entrega (R$)</label>
                <input type="number" id="s-delivery-fee" min="0" step="0.01" placeholder="0,00">
              </div>
              <div class="field">
                <label>Tempo mínimo de entrega (min)</label>
                <input type="number" id="s-time-min" min="0" placeholder="15">
              </div>
              <div class="field">
                <label>Tempo máximo de entrega (min)</label>
                <input type="number" id="s-time-max" min="0" placeholder="40">
              </div>
              <div class="field form-full">
                <div class="toggle-wrap">
                  <label class="toggle"><input type="checkbox" id="s-open"><span class="toggle-slider"></span></label>
                  <div>
                    <div style="font-size:13px;font-weight:600">Loja aberta</div>
                    <div style="font-size:11.5px;color:var(--fg-3)">Clientes conseguem fazer pedidos quando ativo</div>
                  </div>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Salvar configurações</button>
          </form>
        </div>
      </div>

      <!-- WhatsApp -->
      <div class="card">
        <div class="card-header">
          <div class="ch-icon green"><i data-lucide="message-circle" width="14" height="14"></i></div>
          <h2>WhatsApp — Notificações (Fonnte)</h2>
        </div>
        <div class="card-body">
          <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:9px;padding:12px 14px;margin-bottom:14px;font-size:12.5px;color:#166534;line-height:1.6">
            <strong>Como configurar:</strong> Acesse <strong>fonnte.com</strong>, conecte o WhatsApp do estabelecimento e cole o token abaixo. Você receberá uma notificação a cada novo pedido.
          </div>
          <form id="form-whatsapp" onsubmit="saveSecrets(event)">
            <div class="form-grid" style="margin-bottom:14px">
              <div class="field form-full">
                <label>Token Fonnte (API key)</label>
                <input type="text" id="s-fonnte-token" placeholder="Cole aqui o token da Fonnte" autocomplete="off">
                <span class="field-hint">Encontrado em fonnte.com › Dispositivos › seu número › Token</span>
              </div>
              <div class="field form-full">
                <label>WhatsApp que receberá notificações</label>
                <input type="tel" id="s-store-whatsapp" placeholder="5541999998888">
                <span class="field-hint">Número com código do país, sem espaços. Ex: 5541999998888</span>
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Salvar WhatsApp</button>
          </form>
        </div>
      </div>

      <!-- Pagamento Stone -->
      <div class="card">
        <div class="card-header">
          <div class="ch-icon blue"><i data-lucide="credit-card" width="14" height="14"></i></div>
          <h2>Pagamento — Stone</h2>
        </div>
        <div class="card-body">
          <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:9px;padding:12px 14px;margin-bottom:14px;font-size:12.5px;color:#1D4ED8;line-height:1.6">
            Configure as credenciais Stone para aceitar PIX e cartão no app. Acesse <strong>stone.com.br</strong> › Área do lojista › Integrações.
          </div>
          <form id="form-stone" onsubmit="saveStone(event)">
            <div class="form-grid" style="margin-bottom:14px">
              <div class="field form-full">
                <label>Stone Client ID</label>
                <input type="text" id="s-stone-client-id" placeholder="stone_client_id" autocomplete="off">
              </div>
              <div class="field form-full">
                <label>Stone Client Secret</label>
                <input type="password" id="s-stone-client-secret" placeholder="••••••••••••••••" autocomplete="off">
              </div>
              <div class="field form-full">
                <div class="toggle-wrap">
                  <label class="toggle"><input type="checkbox" id="s-stone-sandbox"><span class="toggle-slider"></span></label>
                  <div>
                    <div style="font-size:13px;font-weight:600">Modo sandbox (testes)</div>
                    <div style="font-size:11.5px;color:var(--fg-3)">Desative ao colocar em produção</div>
                  </div>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Salvar Stone</button>
          </form>
        </div>
      </div>

      <!-- Alterar senha -->
      <div class="card">
        <div class="card-header">
          <div class="ch-icon gray"><i data-lucide="lock" width="14" height="14"></i></div>
          <h2>Segurança — Alterar senha</h2>
        </div>
        <div class="card-body">
          <form id="form-password" onsubmit="changePassword(event)">
            <div class="form-grid" style="margin-bottom:14px">
              <div class="field form-full">
                <label>Senha atual</label>
                <input type="password" id="s-pass-current" required autocomplete="current-password">
              </div>
              <div class="field">
                <label>Nova senha</label>
                <input type="password" id="s-pass-new" required minlength="8" autocomplete="new-password">
              </div>
              <div class="field">
                <label>Confirmar nova senha</label>
                <input type="password" id="s-pass-confirm" required autocomplete="new-password">
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Alterar senha</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="assets/app.js"></script>
<script>
async function loadSettings() {
  try {
    const data = await api('/settings');
    document.getElementById('s-store-name').value = data.store_name || '';
    document.getElementById('s-min-order').value = data.min_order || '';
    document.getElementById('s-delivery-fee').value = data.delivery_fee || '';
    document.getElementById('s-time-min').value = data.delivery_time_min || '';
    document.getElementById('s-time-max').value = data.delivery_time_max || '';
    document.getElementById('s-open').checked = data.store_open === '1' || data.store_open === 1;
    document.getElementById('s-stone-sandbox').checked = data.stone_sandbox === '1' || data.stone_sandbox === 1;
    if (data.fonnte_token) document.getElementById('s-fonnte-token').placeholder = '(configurado)';
    if (data.store_whatsapp) document.getElementById('s-store-whatsapp').value = data.store_whatsapp;
    if (data.stone_client_id) document.getElementById('s-stone-client-id').placeholder = '(configurado)';
  } catch (e) { toast(e.message, 'error'); }
}

async function saveStore(e) {
  e.preventDefault();
  const btn = e.submitter; btn.disabled = true; btn.textContent = 'Salvando…';
  try {
    await api('/settings', { method: 'PUT', body: JSON.stringify({
      store_name: document.getElementById('s-store-name').value,
      min_order: document.getElementById('s-min-order').value,
      delivery_fee: document.getElementById('s-delivery-fee').value,
      delivery_time_min: document.getElementById('s-time-min').value,
      delivery_time_max: document.getElementById('s-time-max').value,
      store_open: document.getElementById('s-open').checked ? '1' : '0',
    }) });
    toast('Configurações salvas');
  } catch (e) { toast(e.message, 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Salvar configurações'; }
}

async function saveSecrets(e) {
  e.preventDefault();
  const btn = e.submitter; btn.disabled = true; btn.textContent = 'Salvando…';
  const body = { store_whatsapp: document.getElementById('s-store-whatsapp').value };
  const token = document.getElementById('s-fonnte-token').value;
  if (token) body.fonnte_token = token;
  try {
    await api('/settings/secrets', { method: 'PUT', body: JSON.stringify(body) });
    toast('WhatsApp salvo');
    if (token) { document.getElementById('s-fonnte-token').value = ''; document.getElementById('s-fonnte-token').placeholder = '(configurado)'; }
  } catch (e) { toast(e.message, 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Salvar WhatsApp'; }
}

async function saveStone(e) {
  e.preventDefault();
  const btn = e.submitter; btn.disabled = true; btn.textContent = 'Salvando…';
  const body = { stone_sandbox: document.getElementById('s-stone-sandbox').checked ? '1' : '0' };
  const cid = document.getElementById('s-stone-client-id').value;
  const csec = document.getElementById('s-stone-client-secret').value;
  if (cid) body.stone_client_id = cid;
  if (csec) body.stone_client_secret = csec;
  try {
    await api('/settings/secrets', { method: 'PUT', body: JSON.stringify(body) });
    toast('Credenciais Stone salvas');
    if (cid) { document.getElementById('s-stone-client-id').value = ''; document.getElementById('s-stone-client-id').placeholder = '(configurado)'; }
    if (csec) document.getElementById('s-stone-client-secret').value = '';
  } catch (e) { toast(e.message, 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Salvar Stone'; }
}

async function changePassword(e) {
  e.preventDefault();
  const npass = document.getElementById('s-pass-new').value;
  const nconf = document.getElementById('s-pass-confirm').value;
  if (npass !== nconf) { toast('As senhas não coincidem', 'error'); return; }
  const btn = e.submitter; btn.disabled = true; btn.textContent = 'Alterando…';
  try {
    await api('/auth/change-password', { method: 'POST', body: JSON.stringify({
      current_password: document.getElementById('s-pass-current').value,
      new_password: npass,
    }) });
    toast('Senha alterada com sucesso');
    document.getElementById('form-password').reset();
  } catch (e) { toast(e.message, 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Alterar senha'; }
}

loadSettings();
</script>
</body>
</html>
