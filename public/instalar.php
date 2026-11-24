<?php
// Cria os usuários iniciais UMA vez. Depois de usar, APAGUE este arquivo do servidor.
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc');
require_once INC . '/auth.php';
$perfis = ['admin'=>'Administrador','balcao'=>'Atendimento','deposito'=>'Depósito','rota'=>'Rotas'];
$msg = ''; $feito = false;
if ((int)q('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) { $msg = 'Já instalado. Apague o arquivo instalar.php do servidor.'; $feito = true; }
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok(); $ok = true;
    foreach ($perfis as $k => $_) { if (strlen($_POST['s_' . $k] ?? '') < 8) { $ok = false; } }
    if (!$ok) { $msg = 'Cada senha precisa ter ao menos 8 caracteres.'; }
    else {
        foreach ($perfis as $k => $nome) { q('INSERT INTO usuarios (nome, login, senha_hash, perfil) VALUES (?,?,?,?)', [$nome, $k, password_hash($_POST['s_' . $k], PASSWORD_DEFAULT), $k]); }
        $msg = 'Usuários criados (logins: admin, balcao, deposito, rota). Agora APAGUE instalar.php do servidor.'; $feito = true;
    }
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalação</title><link rel="stylesheet" href="style.css"></head>
<body><main class="estreito"><h1>Instalação – usuários</h1>
<?php if ($msg): ?><p class="<?= $feito ? 'ok' : 'erro' ?>"><?= h($msg) ?></p><?php endif; ?>
<?php if (!$feito): ?><form method="post" class="card"><?= csrf_campo() ?>
<?php foreach ($perfis as $k => $nome): ?><label for="s_<?= $k ?>">Senha de <?= h($nome) ?> (login: <?= $k ?>)</label><input id="s_<?= $k ?>" name="s_<?= $k ?>" type="password" autocomplete="new-password" required><?php endforeach; ?>
<p><button>Criar usuários</button></p></form><?php endif; ?></main></body></html>
