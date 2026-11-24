<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/auth.php';
$u = exige_login(['balcao']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: pedidos.php'); exit; }
csrf_ok();
$id = (int)($_POST['id'] ?? 0);
$p = q('SELECT status FROM pedidos WHERE id = ?', [$id])->fetch();
$sep = (int)q('SELECT COUNT(*) FROM itens_pedido WHERE pedido_id = ? AND separado = 1', [$id])->fetchColumn();
// Atendimento: só pedido ainda não separado. Administrador: qualquer pedido (limpeza de testes).
$pode = $p && ($u['perfil'] === 'admin' || ($p['status'] === 'aguardando' && $sep === 0));
if ($pode) {
    q('DELETE FROM pedidos WHERE id = ?', [$id]); // itens e pendências saem junto (ON DELETE CASCADE)
    q('DELETE FROM rotas WHERE id NOT IN (SELECT rota_id FROM pedidos WHERE rota_id IS NOT NULL)');          // rota que ficou vazia
    q('DELETE FROM clientes WHERE id NOT IN (SELECT cliente_id FROM pedidos)');                              // cliente sem pedidos (minimização de dados)
    header('Location: pedidos.php?excluido=' . $id); exit;
}
header('Location: pedidos.php?bloqueado=' . $id);
