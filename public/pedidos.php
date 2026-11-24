<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
topo('Pedidos');
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'] ?? '') ? $_GET['data'] : null;
if ($data) {
    $ps = q('SELECT p.*, c.nome, c.endereco, c.bairro, c.telefone FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.data_entrega = ? ORDER BY p.id', [$data])->fetchAll();
} else { // sem data: todos os pedidos de hoje em diante e os ainda não entregues
    $ps = q("SELECT p.*, c.nome, c.endereco, c.bairro, c.telefone FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.data_entrega >= ? OR p.status <> 'entregue' ORDER BY p.data_entrega, p.id", [date('Y-m-d')])->fetchAll();
}
if (!empty($_GET['ok'])): ?><p class="ok">Pedido #<?= (int)$_GET['ok'] ?> salvo.</p><?php endif; ?>
<form method="get" class="no-print"><label for="data">Filtrar por data da entrega (vazio = próximos pedidos)</label><input id="data" type="date" name="data" value="<?= h($data ?? '') ?>" onchange="this.form.submit()">
<?php if ($data): ?><p><a class="btn sec" href="pedidos.php">Ver todos os próximos</a></p><?php endif; ?></form>
<?php if (!$ps): ?><p>Nenhum pedido encontrado.</p><?php endif; ?>
<div class="cards"><?php foreach ($ps as $p): ?>
<div class="card"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong> <span class="tag <?= $p['status']==='com_pendencia'?'pend':'' ?>"><?= h(status_nome($p['status'])) ?></span><br>
Entrega: <strong><?= data_br($p['data_entrega']) ?></strong><br>
<?= h($p['endereco']) ?><?= $p['bairro'] ? ' – ' . h($p['bairro']) : '' ?> · Tel.: <?= h($p['telefone']) ?> · Vendedor: <?= h($p['vendedor']) ?>
<table class="itens"><?php foreach (q('SELECT * FROM itens_pedido WHERE pedido_id = ?', [$p['id']])->fetchAll() as $i): ?>
<tr><td><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></td><td><?= h($i['descricao']) ?></td></tr><?php endforeach; ?></table>
<?php if (in_array($p['status'], ['em_rota','com_pendencia'], true)): ?><p class="no-print"><a class="btn sec" href="entrega.php?id=<?= $p['id'] ?>">Conferência da entrega</a></p><?php endif; ?></div>
<?php endforeach; ?></div><?php rodape();
