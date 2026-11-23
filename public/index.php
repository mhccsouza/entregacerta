<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
topo('Painel');
$hoje = date('Y-m-d');
$cont = q("SELECT status, COUNT(*) n FROM pedidos WHERE data_entrega = ? GROUP BY status", [$hoje])->fetchAll(PDO::FETCH_KEY_PAIR);
$pend = (int)q('SELECT COUNT(*) FROM pendencias WHERE resolvida = 0')->fetchColumn();
?>
<p>Entregas de hoje (<?= data_br($hoje) ?>):</p>
<div class="grid">
<?php foreach (['aguardando','separado','em_rota','entregue','com_pendencia'] as $s): ?>
  <div class="card"><strong style="font-size:1.8rem"><?= (int)($cont[$s] ?? 0) ?></strong><br><?= h(status_nome($s)) ?></div>
<?php endforeach; ?>
  <div class="card"><strong style="font-size:1.8rem"><?= $pend ?></strong><br>Pendências em aberto</div>
</div>
<p><a class="btn" href="pedido_novo.php">+ Novo pedido</a> <a class="btn sec" href="separacao.php">Lista de separação</a> <a class="btn sec" href="rota.php">Rota do dia</a></p>
<?php rodape();
