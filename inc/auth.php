<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
}
function usuario(): ?array { return $_SESSION['u'] ?? null; }
function exige_login(array $perfis = []): array {
    $u = usuario();
    if (!$u) { header('Location: login.php'); exit; }
    if ($perfis && $u['perfil'] !== 'admin' && !in_array($u['perfil'], $perfis, true)) {
        http_response_code(403); echo 'Acesso negado para o seu perfil.'; exit;
    }
    return $u;
}
function csrf(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function csrf_campo(): string { return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">'; }
function csrf_ok(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Requisição inválida.'); }
}
