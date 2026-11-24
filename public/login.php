<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/auth.php';
$erro = '';
$login = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $login = trim($_POST['login'] ?? '');
    $u = q('SELECT * FROM usuarios WHERE login = ? AND ativo = 1', [$login])->fetch();
    if ($u && password_verify($_POST['senha'] ?? '', $u['senha_hash'])) {
        session_regenerate_id(true);
        $_SESSION['u'] = ['id' => (int)$u['id'], 'nome' => $u['nome'], 'perfil' => $u['perfil']];
        header('Location: index.php'); exit;
    }
    $erro = 'Usuário ou senha incorretos. Confira os dados e tente de novo.';
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0f4a24"><title>Entrar – <?= h(NOME_SISTEMA) ?></title><link rel="stylesheet" href="style.css?v=<?= (int)@filemtime(__DIR__ . '/style.css') ?>"></head>
<body class="login">
<aside class="login-lado" aria-hidden="true">
  <h2>Do talão de papel ao pedido organizado</h2>
  <p>Cadastro, separação e rota das entregas gratuitas da <?= h(NOME_LOJA) ?> em um só lugar.</p>
  <ul><li>Pedido cadastrado uma única vez</li><li>Depósito separa pela lista do dia</li><li>Rota montada e conferida na entrega</li></ul>
</aside>
<div class="login-box"><div class="login-in">
  <span class="logo" aria-hidden="true"></span>
  <h1><?= h(NOME_SISTEMA) ?></h1>
  <p class="sub"><?= h(NOME_LOJA) ?> · Organização de entregas</p>
  <form method="post" class="card"><?= csrf_campo() ?>
    <?php if ($erro): ?><p class="erro" role="alert"><?= h($erro) ?></p><?php endif; ?>
    <label for="login">Usuário</label>
    <input id="login" name="login" value="<?= h($login) ?>" autocomplete="username" autocapitalize="none" autocorrect="off" required <?= $erro ? '' : 'autofocus' ?>>
    <label for="senha">Senha</label>
    <div class="senha-w"><input id="senha" name="senha" type="password" autocomplete="current-password" required <?= $erro ? 'autofocus' : '' ?>><button type="button" class="sec" id="ver" aria-pressed="false">Mostrar</button></div>
    <button>Entrar</button>
  </form>
  <p class="pe">Esqueceu a senha? Peça ao administrador do sistema.</p>
</div></div>
<script>
var v=document.getElementById('ver'),s=document.getElementById('senha');
v.onclick=function(){var m=s.type==='password';s.type=m?'text':'password';v.textContent=m?'Ocultar':'Mostrar';v.setAttribute('aria-pressed',m)};
</script>
</body></html>
