<?php
// Uso (linha de comando): php criar_usuarios.php login "Nome" perfil senha
// Perfis: admin, balcao, deposito, rota
if (PHP_SAPI !== 'cli') { exit('Somente linha de comando.'); }
require __DIR__ . '/inc/db.php';
[, $login, $nome, $perfil, $senha] = $argv + [null, null, null, null, null];
if (!$senha || !in_array($perfil, ['admin','balcao','deposito','rota'], true) || strlen($senha) < 8) { exit("Uso: php criar_usuarios.php login \"Nome\" perfil senha(>=8)\n"); }
q('INSERT INTO usuarios (nome, login, senha_hash, perfil) VALUES (?,?,?,?)', [$nome, $login, password_hash($senha, PASSWORD_DEFAULT), $perfil]);
echo "Usuário $login criado ($perfil).\n";
