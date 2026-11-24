<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/auth.php';
exige_login(['balcao']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: pedidos.php'); exit; }
csrf_ok();
$id = (int)($_POST['id'] ?? 0);
$p = q('SELECT status FROM pedidos WHERE id = ?', [$id])->fetch();
$sep = (int)q('SELECT COUNT(*) FROM itens_pedido WHERE pedido_id = ? AND separado = 1', [$id])->fetchColumn();
if ($p && $p['status'] === 'aguardando' && $sep === 0) {
    q('DELETE FROM pedidos WHERE id = ?', [$id]); // itens e pendências saem junto (ON DELETE CASCADE)
    header('Location: pedidos.php?excluido=' . $id); exit;
}
header('Location: pedidos.php?bloqueado=' . $id);
