<?php
require_once __DIR__ . '/config.php';
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        if (strpos(DB_DSN, 'sqlite') === 0) { $pdo->exec('PRAGMA foreign_keys = ON'); }
    }
    return $pdo;
}
// Todas as consultas usam parâmetros (PDO prepared statements).
function q(string $sql, array $p = []): PDOStatement {
    $s = db()->prepare($sql);
    $s->execute($p);
    return $s;
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function data_br(?string $d): string { return $d ? date('d/m/Y', strtotime($d)) : ''; }
function qtd_fmt($n): string { $n = (float)$n; return rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ','); }
function status_nome(string $s): string {
    return ['aguardando'=>'Aguardando separação','separado'=>'Separado','em_rota'=>'Em rota','entregue'=>'Entregue','com_pendencia'=>'Com pendência'][$s] ?? $s;
}

// Data padrão das telas: a mais antiga com pedido pendente (ou hoje, se não houver).
function data_padrao(string $condicao): string {
    $d = q("SELECT MIN(data_entrega) FROM pedidos WHERE $condicao")->fetchColumn();
    return $d ?: date('Y-m-d');
}
