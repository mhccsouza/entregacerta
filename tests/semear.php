<?php // Cria os 4 usuários do teste (senha: senha12345) no banco SQLite usado por tests/sql.php.
$pdo = new PDO('sqlite:/tmp/t.db'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ([['Cristiane','cris','balcao'], ['Deposito','dep','deposito'], ['Emerson','emerson','rota'], ['Admin','admin','admin']] as [$nome, $login, $perfil]) {
    $pdo->prepare('INSERT INTO usuarios (nome, login, senha_hash, perfil) VALUES (?,?,?,?)')->execute([$nome, $login, password_hash('senha12345', PASSWORD_DEFAULT), $perfil]);
}
echo "Usuários de teste criados.\n";
