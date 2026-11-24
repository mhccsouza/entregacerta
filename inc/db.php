<?php
require_once __DIR__ . '/config.php';
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            error_log('EntregaCerta: falha ao conectar ao banco: ' . $e->getMessage());
            exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;max-width:520px;margin:40px auto;padding:0 16px"><h1>Sem conexão com o banco de dados</h1><p>Confira o arquivo <code>inc/config.local.php</code> (nome do banco, usuário e senha do hPanel). Para ver o motivo exato, abra <code>diagnostico.php</code> no navegador e apague-o depois.</p></body>');
        }
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
// Entrega "a combinar com o cliente": usa uma data-marca no fim do calendario (sem alterar o banco).
const A_COMBINAR = '9999-12-31';
function data_br(?string $d): string { return !$d ? '' : ($d === A_COMBINAR ? 'A combinar' : date('d/m/Y', strtotime($d))); }
// Prioridade pelo prazo: [texto, classe CSS]. Pedidos entregues/cancelados nao tem prazo.
function prazo(string $d, string $status): array {
    if (in_array($status, ['entregue','cancelado'], true)) { return ['', '']; }
    if ($d === A_COMBINAR) { return ['A combinar', 'p-combinar']; }
    $dias = (int)round((strtotime($d) - strtotime(date('Y-m-d'))) / 86400);
    if ($dias < 0) { return ['Atrasado ' . (-$dias) . ($dias === -1 ? ' dia' : ' dias'), 'p-atrasado']; }
    if ($dias === 0) { return ['Hoje', 'p-hoje']; }
    if ($dias === 1) { return ['Amanhã', 'p-amanha']; }
    if ($dias <= 7) { return ['Em ' . $dias . ' dias', 'p-semana']; }
    return ['', ''];
}
function qtd_fmt($n): string { $n = (float)$n; return rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ','); }
function status_nome(string $s): string {
    return ['aguardando'=>'Aguardando separação','separado'=>'Separado','em_rota'=>'Em rota','entregue'=>'Entregue','com_pendencia'=>'Com pendência','cancelado'=>'Cancelado'][$s] ?? $s;
}

// Data padrão das telas: a mais antiga com pedido pendente (ou hoje, se não houver).
function data_padrao(string $condicao): string {
    $d = q("SELECT MIN(data_entrega) FROM pedidos WHERE data_entrega <> ? AND ($condicao)", [A_COMBINAR])->fetchColumn();
    return $d ?: date('Y-m-d');
}
