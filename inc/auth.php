<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
}
function usuario(): ?array { return $_SESSION['u'] ?? null; }
// Cabeçalho do sistema (logo, menu conforme o perfil e usuário à direita). Usado em todas as telas, inclusive no Acesso negado.
function cabecalho_site(array $u): void {
    $menu = [
        'index.php' => ['Painel', ['admin','balcao','deposito','rota']],
        'pedido_novo.php' => ['Novo pedido', ['admin','balcao']],
        'pedidos.php' => ['Pedidos', ['admin','balcao','deposito','rota']],
        'separacao.php' => ['Separação', ['admin','deposito']],
        'rota.php' => ['Rota do dia', ['admin','rota']],
        'pendencias.php' => ['Pendências', ['admin','rota','balcao']],
        'divergencias.php' => ['Divergências', ['admin','balcao']],
        'vendedores.php' => ['Vendedores', ['admin']],
        'usuarios.php' => ['Usuários', ['admin']],
    ];
    $perfil_nome = ['admin'=>'Administrador','balcao'=>'Atendimento','deposito'=>'Depósito','rota'=>'Rotas'];
    $atual = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $agrupa = ['entrega.php' => 'rota.php', 'historico.php' => 'pedidos.php', 'pedido_ver.php' => 'pedidos.php'];
    $atual = $agrupa[$atual] ?? $atual;
    echo '<header class="topo no-print"><div class="topo-in"><a class="marca" href="index.php"><span class="logo" aria-hidden="true"></span><span>' . h(NOME_SISTEMA) . '<small>' . h(NOME_LOJA) . '</small></span></a><nav class="nav" aria-label="Menu principal">';
    foreach ($menu as $arq => [$nome, $ok]) {
        if ($u['perfil'] === 'admin' || in_array($u['perfil'], $ok, true)) {
            $n = '';
            if ($arq === 'divergencias.php') { divergencia_garante(); $dn = (int)q('SELECT COUNT(*) FROM divergencias WHERE resolvida = 0')->fetchColumn(); $n = $dn ? ' <span class="nav-n">' . $dn . '</span>' : ''; }
            echo '<a href="' . $arq . '"' . ($arq === $atual ? ' class="on" aria-current="page"' : '') . '>' . h($nome) . $n . '</a>';
        }
    }
    echo '</nav><div class="usuario"><span class="quem">' . h($u['nome']) . (mb_strtolower($u['nome']) !== mb_strtolower($perfil_nome[$u['perfil']] ?? $u['perfil']) ? '<small>' . h($perfil_nome[$u['perfil']] ?? $u['perfil']) . '</small>' : '') . '</span><a href="logout.php">Sair</a></div></div></header>';
}
function acesso_negado(array $u): void {
    http_response_code(403);
    $nomes = ['admin'=>'Administrador','balcao'=>'Atendimento','deposito'=>'Depósito','rota'=>'Rotas'];
    $css = 'style.css?v=' . (int)@filemtime(dirname($_SERVER['SCRIPT_FILENAME']) . '/style.css');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0f4a24"><title>Acesso negado – ' . h(NOME_SISTEMA) . '</title><link rel="stylesheet" href="' . h($css) . '"></head><body>';
    cabecalho_site($u);
    echo '<main><div class="card acesso-negado" role="alert"><span class="an-icone" aria-hidden="true">!</span><h1>Acesso negado</h1><p>Você não tem permissão para abrir esta tela com o perfil <strong>' . h($nomes[$u['perfil']] ?? $u['perfil']) . '</strong>.</p><p class="nota">Se precisar desse acesso, peça ao administrador do sistema.</p><div class="acoes"><a class="btn" href="index.php">Voltar ao painel</a><a class="btn sec" href="logout.php">Sair</a></div></div></main></body></html>';
    exit;
}
function exige_login(array $perfis = []): array {
    $u = usuario();
    if (!$u) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') { $_SESSION['volta'] = basename($_SERVER['SCRIPT_NAME'] ?? '') . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''); }
        header('Location: login.php'); exit;
    }
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
