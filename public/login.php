<?php
require_once __DIR__ . '/../inc/auth.php';
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $u = q('SELECT * FROM usuarios WHERE login = ? AND ativo = 1', [trim($_POST['login'] ?? '')])->fetch();
    if ($u && password_verify($_POST['senha'] ?? '', $u['senha_hash'])) {
        session_regenerate_id(true);
        $_SESSION['u'] = ['id' => (int)$u['id'], 'nome' => $u['nome'], 'perfil' => $u['perfil']];
        header('Location: index.php'); exit;
    }
    $erro = 'Usuário ou senha incorretos.';
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Entrar – <?= h(NOME_SISTEMA) ?></title><link rel="stylesheet" href="style.css"></head>
<body><main style="max-width:380px"><h1><?= h(NOME_SISTEMA) ?><br><small><?= h(NOME_LOJA) ?> · Organização de entregas</small></h1>
<?php if ($erro): ?><p class="erro"><?= h($erro) ?></p><?php endif; ?>
<form method="post" class="card"><?= csrf_campo() ?>
<label for="login">Usuário</label><input id="login" name="login" autocomplete="username" required autofocus>
<label for="senha">Senha</label><input id="senha" name="senha" type="password" autocomplete="current-password" required>
<p><button>Entrar</button></p></form></main></body></html>
