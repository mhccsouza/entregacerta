<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
$u = topo('Painel', [], 'Resumo das entregas gratuitas da ' . NOME_LOJA);
$hoje = date('Y-m-d');
$hojeC = q("SELECT status, COUNT(*) n FROM pedidos WHERE data_entrega = ? AND status <> 'cancelado' GROUP BY status", [$hoje])->fetchAll(PDO::FETCH_KEY_PAIR);
$abertoC = q("SELECT status, COUNT(*) n FROM pedidos WHERE status NOT IN ('entregue','cancelado') GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pend = (int)q('SELECT COUNT(*) FROM pendencias WHERE resolvida = 0')->fetchColumn();
$prox = q("SELECT MIN(data_entrega) FROM pedidos WHERE status NOT IN ('entregue','cancelado')")->fetchColumn();
$p = $u['perfil'];
function card_stat(string $num, string $rotulo, string $cor, string $href = ''): string {
    $z = ((int)$num === 0) ? ' zero' : '';
    $in = '<strong class="num">' . h($num) . '</strong><span>' . h($rotulo) . '</span>';
    return $href ? '<a class="stat' . $z . '" style="--cor:var(--' . $cor . ')" href="' . h($href) . '">' . $in . '</a>'
                 : '<div class="stat' . $z . '" style="--cor:var(--' . $cor . ')">' . $in . '</div>';
}
?>
<div class="acoes no-print">
<?php if (in_array($p, ['admin','balcao'], true)): ?><a class="btn" href="pedido_novo.php">+ Novo pedido</a><?php endif; ?>
<?php if (in_array($p, ['admin','deposito'], true)): ?><a class="btn sec" href="separacao.php">Lista de separação</a><?php endif; ?>
<?php if (in_array($p, ['admin','rota'], true)): ?><a class="btn sec" href="rota.php">Rota do dia</a><?php endif; ?>
<a class="btn sec" href="pedidos.php">Ver pedidos</a>
</div>
<h2>Em aberto, de todas as datas</h2>
<?php if ($prox): ?><p class="prox">Próxima entrega pendente: <a href="pedidos.php?data=<?= h($prox) ?>"><strong><?= data_br($prox) ?></strong></a></p><?php endif; ?>
<div class="stats">
<?= card_stat((string)(int)($abertoC['aguardando'] ?? 0), 'Aguardando separação', 'aguardando', 'separacao.php') ?>
<?= card_stat((string)(int)($abertoC['separado'] ?? 0), 'Separados', 'separado', 'rota.php') ?>
<?= card_stat((string)(int)($abertoC['em_rota'] ?? 0), 'Em rota', 'em_rota', 'rota.php') ?>
<?= card_stat((string)(int)($abertoC['com_pendencia'] ?? 0), 'Com pendência', 'com_pendencia', 'pendencias.php') ?>
<?= card_stat((string)$pend, 'Itens pendentes', 'com_pendencia', 'pendencias.php') ?>
</div>
<h2>Entregas de hoje, <?= data_br($hoje) ?></h2>
<div class="stats">
<?php foreach (['aguardando','separado','em_rota','entregue','com_pendencia'] as $s): ?>
<?= card_stat((string)(int)($hojeC[$s] ?? 0), status_nome($s), $s) ?>
<?php endforeach; ?>
</div>
<?php rodape();
