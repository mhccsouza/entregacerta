<?php
// Responde se algo mudou nos pedidos (usado pelo aviso "Há novidades" das telas).
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc');
require_once INC . '/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!usuario()) { http_response_code(401); echo '{"ok":false}'; exit; }
session_write_close(); // não trava outras abas do mesmo usuário
$a = q("SELECT COUNT(*) c, COALESCE(MAX(id),0) m, COALESCE(SUM(id * (CASE status WHEN 'aguardando' THEN 1 WHEN 'separado' THEN 2 WHEN 'em_rota' THEN 3 WHEN 'entregue' THEN 4 WHEN 'com_pendencia' THEN 5 ELSE 6 END)),0) s FROM pedidos")->fetch();
$b = q('SELECT COALESCE(SUM(separado),0) s, COALESCE(SUM(conferido),0) c FROM itens_pedido')->fetch();
$c = (int)q('SELECT COUNT(*) FROM pendencias WHERE resolvida = 0')->fetchColumn();
echo json_encode(['ok' => true, 'v' => implode('-', [$a['c'], $a['m'], $a['s'], $b['s'], $b['c'], $c])]);
