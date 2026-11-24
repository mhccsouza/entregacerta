<?php
require_once __DIR__ . '/auth.php';
function stag(string $s): string { return '<span class="tag s-' . h($s) . '">' . h(status_nome($s)) . '</span>'; }
function topo(string $titulo, array $perfis = [], string $sub = ''): array {
    $u = exige_login($perfis);
    $menu = [
        'index.php' => ['Painel', ['admin','balcao','deposito','rota']],
        'pedido_novo.php' => ['Novo pedido', ['admin','balcao']],
        'pedidos.php' => ['Pedidos', ['admin','balcao','deposito','rota']],
        'separacao.php' => ['Separação', ['admin','deposito']],
        'rota.php' => ['Rota do dia', ['admin','rota']],
        'pendencias.php' => ['Pendências', ['admin','rota','balcao']],
        'usuarios.php' => ['Usuários', ['admin']],
    ];
    $perfil_nome = ['admin'=>'Administrador','balcao'=>'Atendimento','deposito'=>'Depósito','rota'=>'Rotas'];
    $atual = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $agrupa = ['entrega.php' => 'rota.php'];
    $atual = $agrupa[$atual] ?? $atual;
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0f4a24">';
    echo '<title>' . h($titulo) . ' – ' . h(NOME_SISTEMA) . ' · ' . h(NOME_LOJA) . '</title><link rel="stylesheet" href="style.css"></head><body>';
    echo '<header class="topo no-print"><div class="topo-in"><a class="marca" href="index.php"><span class="logo" aria-hidden="true"></span><span>' . h(NOME_SISTEMA) . '<small>' . h(NOME_LOJA) . '</small></span></a><nav class="nav" aria-label="Menu principal">';
    foreach ($menu as $arq => [$nome, $ok]) {
        if ($u['perfil'] === 'admin' || in_array($u['perfil'], $ok, true)) {
            echo '<a href="' . $arq . '"' . ($arq === $atual ? ' class="on" aria-current="page"' : '') . '>' . h($nome) . '</a>';
        }
    }
    echo '</nav><div class="usuario"><span class="quem">' . h($u['nome']) . '<small>' . h($perfil_nome[$u['perfil']] ?? $u['perfil']) . '</small></span><a href="logout.php">Sair</a></div></div></header><main>';
    echo '<div class="cabeca"><div><h1>' . h($titulo) . '</h1>' . ($sub !== '' ? '<p class="sub">' . h($sub) . '</p>' : '') . '</div></div>';
    return $u;
}
function rodape(): void { echo '</main></body></html>'; }
