<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Painel Admin · Plantão da Cerveja</title>
<link rel="icon" href="assets/favicon.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap">
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Nunito',sans-serif;background:#FFF8E1}
:root{--amber:#FFB800;--amber-dk:#D49800;--green:#22C55E;--red:#EF4444;--fg:#111827;--fg-2:#6B7280;--bg:#fff;--border:#E5E7EB}

/* ── Login Card ── */
.login-wrap{height:100vh;display:flex;align-items:center;justify-content:center;padding:16px}
.login-card{background:#fff;border-radius:20px;padding:40px 36px;width:100%;max-width:400px;box-shadow:0 8px 40px rgba(0,0,0,.12)}
.login-logo{display:flex;align-items:center;gap:12px;margin-bottom:28px}
.login-logo img{width:54px;height:54px;border-radius:50%;object-fit:cover}
.login-logo h1{font-size:18px;font-weight:900;color:var(--fg)}
.login-logo p{font-size:12px;color:var(--fg-2)}
.field{margin-bottom:16px}
label{display:block;font-size:12px;font-weight:800;color:var(--fg-2);margin-bottom:6px}
input[type=email],input[type=password]{
  width:100%;padding:13px 16px;border-radius:12px;
  border:2px solid var(--border);background:#F9FAFB;
  font:15px 'Nunito',sans-serif;color:var(--fg);outline:none;transition:border-color .15s
}
input:focus{border-color:var(--amber);background:#fff}
.btn-login{
  display:block;width:100%;padding:14px;border-radius:999px;border:none;
  background:var(--amber);color:#111;font:800 15px 'Nunito',sans-serif;
  cursor:pointer;margin-top:8px;transition:background .15s
}
.btn-login:hover{background:var(--amber-dk)}
.login-err{background:#FEE2E2;color:#991B1B;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:700;margin-bottom:12px;display:none}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <img src="assets/logo.jpg" alt="Plantão da Cerveja">
      <div>
        <h1>Plantão da Cerveja</h1>
        <p>Painel Administrativo</p>
      </div>
    </div>
    <div class="login-err" id="err">E-mail ou senha incorretos.</div>
    <div class="field">
      <label>E-mail</label>
      <input type="email" id="email" placeholder="admin@plantaodacerveja.com.br" autocomplete="username">
    </div>
    <div class="field">
      <label>Senha</label>
      <input type="password" id="pass" placeholder="••••••••" autocomplete="current-password">
    </div>
    <button class="btn-login" id="btn" onclick="login()">Entrar no painel</button>
  </div>
</div>
<script>
const API = 'https://api.plantaodacerveja.com.br';

document.getElementById('pass').addEventListener('keydown', e => { if(e.key==='Enter') login(); });

async function login() {
  const btn  = document.getElementById('btn');
  const err  = document.getElementById('err');
  const email = document.getElementById('email').value.trim();
  const pass  = document.getElementById('pass').value;
  err.style.display = 'none';
  btn.textContent = 'Entrando…'; btn.disabled = true;

  try {
    const res = await fetch(`${API}/auth/login`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({email, password: pass})
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Erro');
    localStorage.setItem('plantao_token', data.token);
    localStorage.setItem('plantao_user',  JSON.stringify(data.user));
    try {
      await fetch('set_session.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({token:data.token}),
        signal: AbortSignal.timeout(3000)
      });
    } catch {}
    window.location.href = 'dashboard.php';
  } catch (e) {
    err.textContent = e.message;
    err.style.display = 'block';
    btn.textContent = 'Entrar no painel'; btn.disabled = false;
  }
}
</script>
</body>
</html>
