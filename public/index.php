<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
topo('Painel');
$hoje = date('Y-m-d');
$st = ['aguardando','separado','em_rota','entregue','com_pendencia'];
$hojeC = q("SELECT status, COUNT(*) n FROM pedidos WHERE data_entrega = ? GROUP BY status", [$hoje])->fetchAll(PDO::FETCH_KEY_PAIR);
$abertoC = q("SELECT status, COUNT(*) n FROM pedidos WHERE status <> 'entregue' GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pend = (int)q('SELECT COUNT(*) FROM pendencias WHERE resolvida = 0')->fetchColumn();
$prox = q("SELECT MIN(data_entrega) FROM pedidos WHERE status <> 'entregue'")->fetchColumn();
?>
<h2>Pedidos em aberto (todas as datas)</h2>
<?php if ($prox): ?><p>Próxima data com entrega pendente: <a href="pedidos.php?data=<?= h($prox) ?>"><strong><?= data_br($prox) ?></strong></a></p><?php endif; ?>
<div class="grid">
<?php foreach (['aguardando','separado','em_rota','com_pendencia'] as $s): ?>
  <div class="card"><strong style="font-size:1.8rem"><?= (int)($abertoC[$s] ?? 0) ?></strong><br><?= h(status_nome($s)) ?></div>
<?php endforeach; ?>
  <div class="card"><strong style="font-size:1.8rem"><?= $pend ?></strong><br>Pendências em aberto</div>
</div>
<h2>Entregas de hoje (<?= data_br($hoje) ?>)</h2>
<div class="grid">
<?php foreach ($st as $s): ?>
  <div class="card"><strong style="font-size:1.8rem"><?= (int)($hojeC[$s] ?? 0) ?></strong><br><?= h(status_nome($s)) ?></div>
<?php endforeach; ?>
</div>
<p><a class="btn" href="pedido_novo.php">+ Novo pedido</a> <a class="btn sec" href="pedidos.php">Ver pedidos</a> <a class="btn sec" href="separacao.php">Lista de separação</a> <a class="btn sec" href="rota.php">Rota do dia</a></p>
<?php rodape();
