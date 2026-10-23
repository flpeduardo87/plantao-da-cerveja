<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configurações · Plantão Admin</title>
<link rel="icon" href="assets/favicon.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div id="toast"></div>

<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo"><img src="assets/logo.jpg" alt=""><div><span>Plantão da Cerveja</span><small>Painel Admin</small></div></div>
    <nav>
      <div class="nav-section">Menu</div>
      <a class="nav-link" href="dashboard.php"><span class="icon">📊</span> Dashboard</a>
      <a class="nav-link" href="orders.php"><span class="icon">📦</span> Pedidos</a>
      <a class="nav-link" href="products.php"><span class="icon">🍺</span> Produtos</a>
      <a class="nav-link active" href="settings.php"><span class="icon">⚙️</span> Configurações</a>
    </nav>
    <div class="sidebar-footer"><button class="btn-logout" data-logout>🚪 Sair</button></div>
  </aside>

  <div class="main">
    <div class="topbar">
      <button class="hamburger" id="hamburger">☰</button>
      <h1>Configurações</h1>
      <div class="topbar-right">
        <button class="store-status closed" id="store-status-btn" data-open="0"><span class="dot"></span>Carregando…</button>
      </div>
    </div>

    <div class="page" style="max-width:720px;display:flex;flex-direction:column;gap:24px">

      <div class="card">
        <div class="card-header"><h2>🏪 Informações da loja</h2></div>
        <div class="card-body">
          <form id="form-store" onsubmit="saveStore(event)">
            <div class="form-grid" style="margin-bottom:16px">
              <div class="field form-full"><label>Nome da loja</label><input type="text" id="s-store-name" placeholder="Plantão da Cerveja"></div>
              <div class="field"><label>Pedido mínimo (R$)</label><input type="number" id="s-min-order" min="0" step="0.01"></div>
              <div class="field"><label>Taxa de entrega (R$)</label><input type="number" id="s-delivery-fee" min="0" step="0.01"></div>
              <div class="field"><label>Tempo mínimo de entrega (min)</label><input type="number" id="s-time-min" min="0" placeholder="15"></div>
              <div class="field"><label>Tempo máximo de entrega (min)</label><input type="number" id="s-time-max" min="0" placeholder="40"></div>
              <div class="field form-full" style="flex-direction:row;align-items:center;gap:10px">
                <label class="toggle"><input type="checkbox" id="s-open"><span class="toggle-slider"></span></label>
                <div><div style="font-size:13px;font-weight:700">Loja aberta</div><div style="font-size:12px;color:var(--fg-3)">Clientes conseguem fazer pedidos quando ativo</div></div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Salvar configurações</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>📲 WhatsApp (Fonnte)</h2></div>
        <div class="card-body">
          <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#166534">
            <strong>Como configurar:</strong><br>
            1. Acesse <strong>fonnte.com</strong> → crie uma conta gratuita<br>
            2. Conecte o WhatsApp do estabelecimento (escaneie o QR code)<br>
            3. Copie o token da API e cole abaixo<br>
            4. Informe o número do WhatsApp que receberá as notificações de pedido
          </div>
          <form id="form-whatsapp" onsubmit="saveSecrets(event)">
            <div class="form-grid" style="margin-bottom:16px">
              <div class="field form-full"><label>Token Fonnte (API key)</label><input type="text" id="s-fonnte-token" placeholder="Cole aqui o token da Fonnte" autocomplete="off"><span class="field-hint">Encontrado em fonnte.com → Dispositivos → seu número → Token</span></div>
              <div class="field form-full"><label>WhatsApp que receberá notificações</label><input type="tel" id="s-store-whatsapp" placeholder="5511999999999 (com código do país, sem +)"><span class="field-hint">Ex: 5541999998888 para (41) 99999-8888</span></div>
            </div>
            <button type="submit" class="btn btn-primary">Salvar WhatsApp</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>💳 Pagamento Stone</h2></div>
        <div class="card-body">
          <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#1D4ED8">
            <strong>Stone:</strong> Configure as credenciais da API Stone para aceitar pagamentos via PIX e cartão diretamente no app.<br>
            Acesse <strong>stone.com.br</strong> → Área do lojista → Integrações para obter as credenciais.
          </div>
          <form id="form-stone" onsubmit="saveStone(event)">
            <div class="form-grid" style="margin-bottom:16px">
              <div class="field form-full"><label>Stone Client ID</label><input type="text" id="s-stone-client-id" placeholder="stone_client_id" autocomplete="off"></div>
              <div class="field form-full"><label>Stone Client Secret</label><input type="password" id="s-stone-client-secret" placeholder="••••••••••••••••" autocomplete="off"></div>
              <div class="field form-full" style="flex-direction:row;align-items:center;gap:10px">
                <label class="toggle"><input type="checkbox" id="s-stone-sandbox"><span class="toggle-slider"></span></label>
                <div><div style="font-size:13px;font-weight:700">Modo sandbox (testes)</div><div style="font-size:12px;color:var(--fg-3)">Desative quando for ao ar em produção</div></div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Salvar Stone</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>🔒 Alterar senha</h2></div>
        <div class="card-body">
          <form id="form-password" onsubmit="changePassword(event)">
            <div class="form-grid" style="margin-bottom:16px">
              <div class="field form-full"><label>Senha atual</label><input type="password" id="s-pass-current" required autocomplete="current-password"></div>
              <div class="field"><label>Nova senha</label><input type="password" id="s-pass-new" required minlength="8" autocomplete="new-password"></div>
              <div class="field"><label>Confirmar nova senha</label><input type="password" id="s-pass-confirm" required autocomplete="new-password"></div>
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
    if (data.fonnte_token) document.getElementById('s-fonnte-token').placeholder = '(salvo)';
    if (data.store_whatsapp) document.getElementById('s-store-whatsapp').value = data.store_whatsapp;
    if (data.stone_client_id) document.getElementById('s-stone-client-id').placeholder = '(salvo)';
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
    toast('Configurações salvas!');
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
    toast('WhatsApp salvo!');
    document.getElementById('s-fonnte-token').value = '';
    document.getElementById('s-fonnte-token').placeholder = '(salvo)';
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
    toast('Credenciais Stone salvas!');
    if (cid) { document.getElementById('s-stone-client-id').value = ''; document.getElementById('s-stone-client-id').placeholder = '(salvo)'; }
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
    toast('Senha alterada com sucesso!');
    document.getElementById('form-password').reset();
  } catch (e) { toast(e.message, 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Alterar senha'; }
}

loadSettings();
</script>
</body>
</html>
