<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/auth.php';
$u = exige_login([]);                       // admin passa sempre; os demais perfis caem abaixo
if ($u['perfil'] !== 'admin') { http_response_code(403); exit('Somente o administrador cancela pedidos.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: pedidos.php'); exit; }
csrf_ok();
$id = (int)($_POST['id'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '');
$p = q('SELECT status, observacao FROM pedidos WHERE id = ?', [$id])->fetch();
if (!$p || !in_array($p['status'], ['aguardando', 'separado', 'em_rota'], true) || $motivo === '') {
    header('Location: pedidos.php?naocancelado=' . $id); exit;
}
$obs = trim(($p['observacao'] ?? '') . ' | Cancelado em ' . date('d/m/Y H:i') . ' por ' . $u['nome'] . ': ' . $motivo);
q("UPDATE pedidos SET status = 'cancelado', rota_id = NULL, ordem_rota = NULL, observacao = ? WHERE id = ?", [mb_substr($obs, 0, 255), $id]);
header('Location: pedidos.php?cancelado=' . $id);
