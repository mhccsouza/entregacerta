<?php
// Ferramenta temporária: mostra por que o sistema não conecta. APAGUE este arquivo depois de usar.
header('Content-Type: text/plain; charset=utf-8');
$inc = is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc';
echo "PHP: " . PHP_VERSION . "\n";
echo "pasta inc: " . (is_dir($inc) ? 'encontrada' : 'NAO ENCONTRADA') . "\n";
echo "inc/config.php: " . (is_file("$inc/config.php") ? 'ok' : 'FALTA') . "\n";
echo "inc/config.local.php: " . (is_file("$inc/config.local.php") ? 'ok' : 'FALTA (copie o .modelo e preencha)') . "\n";
echo "extensao pdo_mysql: " . (extension_loaded('pdo_mysql') ? 'ok' : 'FALTA') . "\n";
if (is_file("$inc/config.php")) {
    require "$inc/config.php";
    echo "banco configurado: " . preg_replace('/.*dbname=([^;]*).*/', '$1', DB_DSN) . " | usuario: " . DB_USER . "\n";
    try {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "conexao: OK\n";
        foreach (['usuarios','clientes','pedidos','itens_pedido','rotas','pendencias'] as $t) {
            try { $n = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(); echo "tabela $t: $n registro(s)\n"; }
            catch (Throwable $e) { echo "tabela $t: NAO EXISTE (importe o schema.sql)\n"; }
        }
    } catch (Throwable $e) { echo "conexao: FALHOU\nmotivo: " . $e->getMessage() . "\n"; }
}
echo "\nApague este arquivo (diagnostico.php) agora.\n";
