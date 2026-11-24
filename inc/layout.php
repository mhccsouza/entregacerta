<?php
require_once __DIR__ . '/auth.php';
function stag(string $s): string { return '<span class="tag s-' . h($s) . '">' . h(status_nome($s)) . '</span>'; }
function topo(string $titulo, array $perfis = [], string $sub = ''): array {
    $u = exige_login($perfis);
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0f4a24">';
    echo '<title>' . h($titulo) . ' – ' . h(NOME_SISTEMA) . ' · ' . h(NOME_LOJA) . '</title><link rel="stylesheet" href="style.css?v=' . (int)@filemtime(dirname($_SERVER["SCRIPT_FILENAME"]) . "/style.css") . '"></head><body>';
    cabecalho_site($u); echo '<main>';
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
