<?php
// Busca clientes já cadastrados (por nome ou telefone) para preencher o Novo pedido.
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc');
require_once INC . '/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$u = usuario();
if (!$u || !in_array($u['perfil'], ['admin', 'balcao'], true)) { http_response_code($u ? 403 : 401); echo '[]'; exit; }
session_write_close();
$t = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($t) < 2) { echo '[]'; exit; }
$esc = fn($s) => str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s); // '!' como escape: funciona igual no MySQL e no SQLite
$dig = preg_replace('/\D+/', '', $t);
$sql = "SELECT id, nome, telefone, endereco, bairro, referencia FROM clientes WHERE nome LIKE ? ESCAPE '!'";
$par = ['%' . $esc($t) . '%'];
if (strlen($dig) >= 3) { $sql .= " OR REPLACE(REPLACE(REPLACE(REPLACE(telefone,'(',''),')',''),'-',''),' ','') LIKE ? ESCAPE '!'"; $par[] = '%' . $dig . '%'; }
$sql .= ' ORDER BY id DESC LIMIT 40';
$out = []; $vistos = [];
foreach (q($sql, $par)->fetchAll() as $c) {
    $chave = mb_strtolower($c['nome']) . '|' . $c['telefone'] . '|' . mb_strtolower($c['endereco']);
    if (isset($vistos[$chave])) { continue; } $vistos[$chave] = 1;
    $rua = $c['endereco']; $num = ''; $comp = '';
    if (preg_match('/^(.*?),\s*(\d[\w\/-]*|s\/?n)(?:\s+-\s+(.+))?$/iu', $c['endereco'], $m)) { $rua = trim($m[1]); $num = $m[2]; $comp = $m[3] ?? ''; }
    $out[] = ['nome' => $c['nome'], 'telefone' => (string)$c['telefone'], 'rua' => $rua, 'numero' => $num, 'complemento' => $comp, 'bairro' => (string)$c['bairro'], 'referencia' => (string)$c['referencia']];
    if (count($out) >= 8) { break; }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
