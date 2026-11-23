<?php
require_once __DIR__ . '/auth.php';
function topo(string $titulo, array $perfis = []): array {
    $u = exige_login($perfis);
    $menu = [
        'index.php' => ['Painel', ['admin','balcao','deposito','rota']],
        'pedido_novo.php' => ['Novo pedido', ['admin','balcao']],
        'pedidos.php' => ['Pedidos', ['admin','balcao','deposito','rota']],
        'separacao.php' => ['Separação', ['admin','deposito']],
        'rota.php' => ['Rota do dia', ['admin','rota']],
        'pendencias.php' => ['Pendências', ['admin','rota','balcao']],
    ];
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($titulo) . ' – ' . h(NOME_SISTEMA) . ' · ' . h(NOME_LOJA) . '</title><link rel="stylesheet" href="style.css"></head><body>';
    echo '<header class="no-print"><strong>' . h(NOME_SISTEMA) . ' · ' . h(NOME_LOJA) . '</strong><nav>';
    foreach ($menu as $arq => [$nome, $ok]) {
        if ($u['perfil'] === 'admin' || in_array($u['perfil'], $ok, true)) { echo '<a href="' . $arq . '">' . h($nome) . '</a>'; }
    }
    echo '</nav><span>' . h($u['nome']) . ' · <a href="logout.php">Sair</a></span></header><main>';
    echo '<h1>' . h($titulo) . '</h1>';
    return $u;
}
function rodape(): void { echo '</main></body></html>'; }
