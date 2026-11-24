<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
}
function usuario(): ?array { return $_SESSION['u'] ?? null; }
function acesso_negado(array $u): void {
    http_response_code(403);
    $nomes = ['admin'=>'Administrador','balcao'=>'Atendimento','deposito'=>'Depósito','rota'=>'Rotas'];
    $css = 'style.css?v=' . (int)@filemtime(dirname($_SERVER['SCRIPT_FILENAME']) . '/style.css');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0f4a24"><title>Acesso negado – ' . h(NOME_SISTEMA) . '</title><link rel="stylesheet" href="' . h($css) . '"></head><body>';
    echo '<header class="topo"><div class="topo-in"><a class="marca" href="index.php"><span class="logo" aria-hidden="true"></span><span>' . h(NOME_SISTEMA) . '<small>' . h(NOME_LOJA) . '</small></span></a><div class="usuario"><span class="quem">' . h($u['nome']) . '<small>' . h($nomes[$u['perfil']] ?? $u['perfil']) . '</small></span><a href="logout.php">Sair</a></div></div></header>';
    echo '<main><div class="card acesso-negado" role="alert"><span class="an-icone" aria-hidden="true">!</span><h1>Acesso negado</h1><p>Você não tem permissão para abrir esta tela com o perfil <strong>' . h($nomes[$u['perfil']] ?? $u['perfil']) . '</strong>.</p><p class="nota">Se precisar desse acesso, peça ao administrador do sistema.</p><div class="acoes"><a class="btn" href="index.php">Voltar ao painel</a><a class="btn sec" href="logout.php">Sair</a></div></div></main></body></html>';
    exit;
}
function exige_login(array $perfis = []): array {
    $u = usuario();
    if (!$u) { header('Location: login.php'); exit; }
    if ($perfis && $u['perfil'] !== 'admin' && !in_array($u['perfil'], $perfis, true)) {
        acesso_negado($u);
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
