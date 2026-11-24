<?php
// Ferramenta temporária: mostra por que o sistema não conecta. APAGUE este arquivo do servidor depois de usar.
$inc = is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc';
$itens = []; // [ok(bool), título, detalhe]
$add = function ($ok, $t, $d = '') use (&$itens) { $itens[] = [$ok, $t, $d]; };

$add(true, 'Versão do PHP', PHP_VERSION);
$add(is_dir($inc), 'Pasta inc', is_dir($inc) ? 'Encontrada' : 'Não encontrada: envie a pasta inc/ ao lado do login.php');
$add(is_file("$inc/config.php"), 'inc/config.php', is_file("$inc/config.php") ? 'Encontrado' : 'Falta enviar este arquivo');
$add(is_file("$inc/config.local.php"), 'inc/config.local.php', is_file("$inc/config.local.php") ? 'Encontrado' : 'Falta: copie config.local.php.modelo para config.local.php e preencha os dados do banco');
$add(extension_loaded('pdo_mysql'), 'Extensão pdo_mysql', extension_loaded('pdo_mysql') ? 'Ativa' : 'Inativa: ative no hPanel (Avançado > Configuração do PHP)');

if (is_file("$inc/config.php")) {
    try {
        require "$inc/config.php";
        $add(true, 'Banco configurado', preg_replace('/.*dbname=([^;]*).*/', '$1', DB_DSN) . ' · usuário ' . DB_USER);
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $add(true, 'Conexão com o banco', 'OK');
        foreach (['usuarios', 'clientes', 'pedidos', 'itens_pedido', 'rotas', 'pendencias', 'vendedores'] as $t) {
            try { $n = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(); $add(true, "Tabela $t", "$n registro(s)"); }
            catch (Throwable $e) {
                $add($t === 'vendedores', "Tabela $t", $t === 'vendedores' ? 'Ainda não criada: é criada sozinha no primeiro acesso a Pedidos ou Vendedores' : 'Não existe: importe o schema.sql no phpMyAdmin');
            }
        }
    } catch (Throwable $e) { $add(false, 'Conexão com o banco', 'Falhou: ' . $e->getMessage()); }
}
$falhas = count(array_filter($itens, function ($i) { return !$i[0]; }));
function hh($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Diagnóstico – EntregaCerta</title><link rel="stylesheet" href="style.css"></head>
<body><main class="estreito">
<h1>Diagnóstico do sistema</h1>
<p class="sub">Verifica o servidor, os arquivos e o banco de dados.</p>
<p class="<?= $falhas ? 'erro' : 'ok' ?>" role="status"><?= $falhas ? "$falhas item(ns) precisam de atenção." : 'Tudo certo: o sistema está pronto para conectar.' ?></p>
<div class="card">
<?php foreach ($itens as $i): ?>
<p style="margin:0 0 10px"><strong><?= $i[0] ? '✔' : '✖' ?> <?= hh($i[1]) ?></strong><br><span class="<?= $i[0] ? '' : 'erro' ?>" style="<?= $i[0] ? 'color:#5b6b5f' : 'display:inline-block;padding:4px 8px;margin:4px 0 0' ?>"><?= hh($i[2]) ?></span></p>
<?php endforeach; ?>
</div>
<p class="erro" role="alert"><strong>Segurança:</strong> apague o arquivo <code>diagnostico.php</code> do servidor agora que terminou de usar.</p>
</main></body></html>
