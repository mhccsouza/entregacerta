<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
$u = topo('Painel', [], 'Resumo das entregas gratuitas da ' . NOME_LOJA);
$hoje = date('Y-m-d');
$hojeC = q("SELECT status, COUNT(*) n FROM pedidos WHERE data_entrega = ? AND status <> 'cancelado' GROUP BY status", [$hoje])->fetchAll(PDO::FETCH_KEY_PAIR);
$abertoC = q("SELECT status, COUNT(*) n FROM pedidos WHERE status NOT IN ('entregue','cancelado') GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pend = (int)q('SELECT COUNT(*) FROM pendencias WHERE resolvida = 0')->fetchColumn();
$prox = q("SELECT MIN(data_entrega) FROM pedidos WHERE status NOT IN ('entregue','cancelado') AND data_entrega <> ?", [A_COMBINAR])->fetchColumn();
$comb = (int)q("SELECT COUNT(*) FROM pedidos WHERE status NOT IN ('entregue','cancelado') AND data_entrega = ?", [A_COMBINAR])->fetchColumn();
$atras = (int)q("SELECT COUNT(*) FROM pedidos WHERE status NOT IN ('entregue','cancelado') AND data_entrega < ?", [$hoje])->fetchColumn();
$p = $u['perfil'];
// o administrador vê tudo; os demais veem só o que compete ao seu perfil
$ve = fn(array $perfis) => $p === 'admin' || in_array($p, $perfis, true);
function card_stat(string $num, string $rotulo, string $cor, string $href = ''): string {
    $z = ((int)$num === 0) ? ' zero' : '';
    $in = '<strong class="num">' . h($num) . '</strong><span>' . h($rotulo) . '</span>';
    return $href ? '<a class="stat' . $z . '" style="--cor:var(--' . $cor . ')" href="' . h($href) . '">' . $in . '</a>'
                 : '<div class="stat' . $z . '" style="--cor:var(--' . $cor . ')">' . $in . '</div>';
}
$n = fn(string $s) => (int)($abertoC[$s] ?? 0);
$hojeAberto = array_sum(array_diff_key($hojeC, ['entregue' => 1]));

// lista "Precisa de atenção": só entra o que tem pendência para o perfil
$alertas = [];
$al = function (bool $mostra, int $qtd, string $um, string $varios, string $href, string $cor) use (&$alertas) {
    if ($mostra && $qtd > 0) { $alertas[] = [$qtd, ($qtd === 1 ? $um : $varios), $href, $cor]; }
};
$al($ve(['rota', 'balcao']), $atras, 'pedido atrasado', 'pedidos atrasados', 'pedidos.php?prio=atrasado', 'com_pendencia');
$al($ve(['deposito']), $n('aguardando'), 'pedido aguardando separação', 'pedidos aguardando separação', 'separacao.php', 'aguardando');
$al($ve(['rota']), $n('separado'), 'pedido separado, pronto para a rota', 'pedidos separados, prontos para a rota', 'rota.php', 'separado');
$al($ve(['rota']), $n('em_rota'), 'pedido em rota para conferir', 'pedidos em rota para conferir', 'rota.php', 'em_rota');
$al($ve(['rota', 'balcao']), $pend, 'item pendente de entrega', 'itens pendentes de entrega', 'pendencias.php', 'com_pendencia');
$al($ve(['balcao']), $comb, 'pedido com data a combinar', 'pedidos com data a combinar', 'pedidos.php?status=a_combinar', 'cancelado');
$al($ve(['rota', 'balcao']), $hojeAberto, 'entrega de hoje ainda não concluída', 'entregas de hoje ainda não concluídas', 'pedidos.php?prio=hoje', 'aguardando');
?>
<div class="acoes no-print">
<?php if ($ve(['balcao'])): ?><a class="btn" href="pedido_novo.php">+ Novo pedido</a><?php endif; ?>
<?php if ($ve(['deposito'])): ?><a class="btn sec" href="separacao.php">Lista de separação</a><?php endif; ?>
<?php if ($ve(['rota'])): ?><a class="btn sec" href="rota.php">Rota do dia</a><?php endif; ?>
<a class="btn sec" href="pedidos.php">Ver pedidos</a>
</div>
<section class="alertas" aria-label="Precisa de atenção">
<h2>Precisa de atenção</h2>
<?php if ($alertas): ?><ul><?php foreach ($alertas as [$qtd, $txt, $href, $cor]): ?>
<li class="al"><a style="--cor:var(--<?= h($cor) ?>)" href="<?= h($href) ?>"><b><?= $qtd ?></b><span><?= h($txt) ?></span></a></li>
<?php endforeach; ?></ul><?php else: ?><p class="ok-tudo">Tudo em dia. Nada pendente para o seu perfil agora.</p><?php endif; ?>
</section>
<h2>Em aberto, de todas as datas</h2>
<?php if ($prox && $ve(['balcao', 'rota'])): ?><p class="prox">Próxima entrega pendente: <a href="pedidos.php?data=<?= h($prox) ?>"><strong><?= data_br($prox) ?></strong></a></p><?php endif; ?>
<div class="stats">
<?php if ($ve(['deposito', 'balcao'])): ?><?= card_stat((string)$n('aguardando'), 'Aguardando separação', 'aguardando', $ve(['deposito']) ? 'separacao.php' : 'pedidos.php?status=aguardando') ?><?php endif; ?>
<?php if ($ve(['deposito', 'rota', 'balcao'])): ?><?= card_stat((string)$n('separado'), 'Separados', 'separado', $ve(['rota']) ? 'rota.php' : 'pedidos.php?status=separado') ?><?php endif; ?>
<?php if ($ve(['rota', 'balcao'])): ?><?= card_stat((string)$n('em_rota'), 'Em rota', 'em_rota', $ve(['rota']) ? 'rota.php' : 'pedidos.php?status=em_rota') ?>
<?= card_stat((string)$n('com_pendencia'), 'Com pendência', 'com_pendencia', 'pedidos.php?status=com_pendencia') ?>
<?= card_stat((string)$pend, 'Itens pendentes', 'com_pendencia', 'pendencias.php') ?>
<?= card_stat((string)$atras, 'Atrasados', 'com_pendencia', 'pedidos.php?prio=atrasado') ?><?php endif; ?>
<?php if ($ve(['balcao'])): ?><?= card_stat((string)$comb, 'Data a combinar', 'cancelado', 'pedidos.php?status=a_combinar') ?><?php endif; ?>
</div>
<h2>Entregas de hoje, <?= data_br($hoje) ?></h2>
<div class="stats">
<?php $verHoje = $p === 'deposito' ? ['aguardando', 'separado'] : ['aguardando', 'separado', 'em_rota', 'entregue', 'com_pendencia'];
foreach ($verHoje as $s): ?>
<?= card_stat((string)(int)($hojeC[$s] ?? 0), status_nome($s), $s, 'pedidos.php?' . http_build_query(['data' => $hoje, 'status' => $s])) ?>
<?php endforeach; ?>
</div>
<?php rodape();
