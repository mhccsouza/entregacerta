<?php
require_once __DIR__ . '/../inc/layout.php';
topo('Pedidos');
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'] ?? '') ? $_GET['data'] : date('Y-m-d');
$ps = q('SELECT p.*, c.nome, c.endereco, c.bairro, c.telefone FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.data_entrega = ? ORDER BY p.id', [$data])->fetchAll();
if (!empty($_GET['ok'])): ?><p class="ok">Pedido #<?= (int)$_GET['ok'] ?> salvo.</p><?php endif; ?>
<form method="get" class="no-print"><label for="data">Data da entrega</label><input id="data" type="date" name="data" value="<?= h($data) ?>" onchange="this.form.submit()"></form>
<?php if (!$ps): ?><p>Nenhum pedido para esta data.</p><?php endif; ?>
<div class="cards"><?php foreach ($ps as $p): ?>
<div class="card"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong> <span class="tag <?= $p['status']==='com_pendencia'?'pend':'' ?>"><?= h(status_nome($p['status'])) ?></span><br>
<?= h($p['endereco']) ?><?= $p['bairro'] ? ' – ' . h($p['bairro']) : '' ?> · Tel.: <?= h($p['telefone']) ?> · Vendedor: <?= h($p['vendedor']) ?>
<table class="itens"><?php foreach (q('SELECT * FROM itens_pedido WHERE pedido_id = ?', [$p['id']])->fetchAll() as $i): ?>
<tr><td><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></td><td><?= h($i['descricao']) ?></td></tr><?php endforeach; ?></table>
<?php if (in_array($p['status'], ['em_rota','com_pendencia'], true)): ?><p class="no-print"><a class="btn sec" href="entrega.php?id=<?= $p['id'] ?>">Conferência da entrega</a></p><?php endif; ?></div>
<?php endforeach; ?></div><?php rodape();
