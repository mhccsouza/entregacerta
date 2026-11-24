<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
$u0 = exige_login([]); if ($u0['perfil'] !== 'admin') { acesso_negado($u0); }
$u = topo('Usuários e senhas', [], 'Troque a senha de qualquer usuário. Use ao menos 8 caracteres.');
$msg = ''; $erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $id = (int)($_POST['id'] ?? 0); $s1 = $_POST['senha'] ?? ''; $s2 = $_POST['senha2'] ?? '';
    if (strlen($s1) < 8) { $erro = 'A senha precisa ter ao menos 8 caracteres.'; }
    elseif ($s1 !== $s2) { $erro = 'As duas senhas não são iguais.'; }
    else {
        $n = q('UPDATE usuarios SET senha_hash = ? WHERE id = ? AND ativo = 1', [password_hash($s1, PASSWORD_DEFAULT), $id])->rowCount();
        $msg = $n ? 'Senha alterada.' : 'Usuário não encontrado.';
    }
}
if ($msg): ?><p class="ok" role="status"><?= h($msg) ?></p><?php endif; if ($erro): ?><p class="erro" role="alert"><?= h($erro) ?></p><?php endif;
$perfil_nome = ['admin'=>'Administrador','balcao'=>'Atendimento','deposito'=>'Depósito','rota'=>'Rotas'];
?><div class="cards">
<?php foreach (q('SELECT id, nome, login, perfil FROM usuarios WHERE ativo = 1 ORDER BY id')->fetchAll() as $r): ?>
<form method="post" class="card"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<div class="pedido-cab"><strong><?= h($r['nome']) ?></strong><span class="tag"><?= h($perfil_nome[$r['perfil']] ?? $r['perfil']) ?></span></div><p class="meta">Login: <b><?= h($r['login']) ?></b></p>
<div><label for="s<?= $r['id'] ?>">Nova senha</label><input id="s<?= $r['id'] ?>" name="senha" type="password" autocomplete="new-password" required></div>
<div><label for="t<?= $r['id'] ?>">Repita a nova senha</label><input id="t<?= $r['id'] ?>" name="senha2" type="password" autocomplete="new-password" required></div>
<div class="barra"><button class="peq">Trocar senha</button></div></form>
<?php endforeach; ?></div><?php rodape();
