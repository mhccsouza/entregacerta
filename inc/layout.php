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
        'divergencias.php' => ['Divergências', ['admin','balcao']],
        'vendedores.php' => ['Vendedores', ['admin']],
        'usuarios.php' => ['Usuários', ['admin']],
    ];
    $perfil_nome = ['admin'=>'Administrador','balcao'=>'Atendimento','deposito'=>'Depósito','rota'=>'Rotas'];
    $atual = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $agrupa = ['entrega.php' => 'rota.php', 'historico.php' => 'pedidos.php', 'pedido_ver.php' => 'pedidos.php'];
    $atual = $agrupa[$atual] ?? $atual;
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0f4a24">';
    echo '<title>' . h($titulo) . ' – ' . h(NOME_SISTEMA) . ' · ' . h(NOME_LOJA) . '</title><link rel="stylesheet" href="style.css?v=' . (int)@filemtime(dirname($_SERVER["SCRIPT_FILENAME"]) . "/style.css") . '"></head><body>';
    echo '<header class="topo no-print"><div class="topo-in"><a class="marca" href="index.php"><span class="logo" aria-hidden="true"></span><span>' . h(NOME_SISTEMA) . '<small>' . h(NOME_LOJA) . '</small></span></a><nav class="nav" aria-label="Menu principal">';
    foreach ($menu as $arq => [$nome, $ok]) {
        if ($u['perfil'] === 'admin' || in_array($u['perfil'], $ok, true)) {
            $n = '';
            if ($arq === 'divergencias.php') { divergencia_garante(); $dn = (int)q('SELECT COUNT(*) FROM divergencias WHERE resolvida = 0')->fetchColumn(); $n = $dn ? ' <span class="nav-n">' . $dn . '</span>' : ''; }
            echo '<a href="' . $arq . '"' . ($arq === $atual ? ' class="on" aria-current="page"' : '') . '>' . h($nome) . $n . '</a>';
        }
    }
    echo '</nav><div class="usuario"><span class="quem">' . h($u['nome']) . '<small>' . h($perfil_nome[$u['perfil']] ?? $u['perfil']) . '</small></span><a href="logout.php">Sair</a></div></div></header><main>';
    echo '<div class="cabeca"><div><h1>' . h($titulo) . '</h1>' . ($sub !== '' ? '<p class="sub">' . h($sub) . '</p>' : '') . '</div></div>';
    return $u;
}
function rodape(): void {
    $arq = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (usuario() && !in_array($arq, ['pedido_novo.php', 'entrega.php', 'instalar.php'], true)) {
        echo '<div id="aviso-novo" class="aviso-novo no-print" role="status" aria-live="polite" hidden><span>Há pedidos novos ou alterados por outro usuário.</span><button type="button" onclick="location.reload()">Atualizar</button></div>';
        $vivo = in_array($arq, ['index.php', 'pedidos.php', 'historico.php'], true) ? 'true' : 'false';
        echo '<script>(function(){var v=null,vivo=' . $vivo . ';function troca(){var a=document.activeElement;if(a&&/^(INPUT|SELECT|TEXTAREA)$/.test(a.tagName))return false;fetch(location.href,{credentials:"same-origin",cache:"no-store"}).then(function(r){return r.text()}).then(function(t){var n=new DOMParser().parseFromString(t,"text/html").querySelector("main"),m=document.querySelector("main");if(n&&m){var y=window.scrollY;m.innerHTML=n.innerHTML;window.scrollTo(0,y)}}).catch(function(){document.getElementById("aviso-novo").hidden=false});return true}function chk(){if(document.hidden)return;fetch("novidades.php",{credentials:"same-origin",cache:"no-store"}).then(function(r){if(!r.ok)throw 0;return r.json()}).then(function(d){if(!d||!d.ok)return;if(v===null){v=d.v}else if(d.v!==v){v=d.v;if(!(vivo&&troca()))document.getElementById("aviso-novo").hidden=false}}).catch(function(){})}chk();setInterval(chk,20000);document.addEventListener("visibilitychange",function(){if(!document.hidden)chk()});document.addEventListener("submit",function(e){var f=e.target,b=f.querySelector("button:not([type]),button[type=submit]");if(b&&!e.defaultPrevented){setTimeout(function(){b.disabled=true;b.dataset.t=b.textContent;b.textContent="Salvando…"},0)}});window.addEventListener("pageshow",function(e){if(e.persisted)location.reload()})})();</script>';
    }
    echo '</main></body></html>';
}
