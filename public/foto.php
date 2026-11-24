<?php
// Entrega a foto do talão só para quem está logado (as imagens ficam em inc/fotos/, sem acesso direto pela internet).
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc');
require_once INC . '/auth.php';
$u = usuario();
if (!$u || !in_array($u['perfil'], ['admin', 'balcao', 'rota'], true)) { http_response_code(403); exit('Acesso negado.'); }
fotos_garante();
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // remover (só administrador)
    csrf_ok();
    if ($u['perfil'] !== 'admin') { http_response_code(403); exit('Acesso negado.'); }
    $f = q('SELECT * FROM fotos_pedido WHERE id = ?', [(int)($_POST['id'] ?? 0)])->fetch();
    if ($f) { @unlink(INC . '/fotos/' . basename($f['arquivo'])); q('DELETE FROM fotos_pedido WHERE id = ?', [$f['id']]); }
    header('Location: pedido_ver.php?id=' . (int)($f['pedido_id'] ?? 0)); exit;
}
$f = q('SELECT arquivo FROM fotos_pedido WHERE id = ?', [(int)($_GET['id'] ?? 0)])->fetch();
$path = $f ? INC . '/fotos/' . basename($f['arquivo']) : '';
if (!$f || !is_file($path)) { http_response_code(404); exit('Foto não encontrada.'); }
header('Content-Type: image/jpeg'); header('Cache-Control: private, max-age=3600'); header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path)); readfile($path);
