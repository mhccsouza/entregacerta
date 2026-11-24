<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
$u = topo('Pedidos');
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'] ?? '') ? $_GET['data'] : null;
if ($data) {
    $ps = q('SELECT p.*, c.nome, c.endereco, c.bairro, c.telefone FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.data_entrega = ? ORDER BY p.id', [$data])->fetchAll();
} else { // sem data: todos os pedidos de hoje em diante e os ainda não entregues
    $ps = q("SELECT p.*, c.nome, c.endereco, c.bairro, c.telefone FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.status <> 'cancelado' AND (p.data_entrega >= ? OR p.status <> 'entregue') ORDER BY p.data_entrega, p.id", [date('Y-m-d')])->fetchAll();
}
if (!empty($_GET['ok'])): ?><p class="ok">Pedido #<?= (int)$_GET['ok'] ?> salvo.</p><?php endif;
if (!empty($_GET['excluido'])): ?><p class="ok">Pedido #<?= (int)$_GET['excluido'] ?> excluído.</p><?php endif;
if (!empty($_GET['cancelado'])): ?><p class="ok">Pedido #<?= (int)$_GET['cancelado'] ?> cancelado. Avise o depósito para devolver os itens ao estoque, se já foram separados.</p><?php endif;
if (!empty($_GET['naocancelado'])): ?><p class="erro">Não foi possível cancelar o pedido #<?= (int)$_GET['naocancelado'] ?>. Informe o motivo; só pedidos ainda não entregues podem ser cancelados.</p><?php endif;
if (!empty($_GET['bloqueado'])): ?><p class="erro">O pedido #<?= (int)$_GET['bloqueado'] ?> já foi separado ou entrou em rota e só o administrador pode excluí-lo.</p><?php endif; ?>
<form method="get" class="no-print"><label for="data">Filtrar por data da entrega (vazio = próximos pedidos)</label><input id="data" type="date" name="data" value="<?= h($data ?? '') ?>" onchange="this.form.submit()">
<?php if ($data): ?><p><a class="btn sec" href="pedidos.php">Ver todos os próximos</a></p><?php endif; ?></form>
<?php if (!$ps): ?><p>Nenhum pedido encontrado.</p><?php endif; ?>
<div class="cards"><?php foreach ($ps as $p): ?>
<div class="card"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong> <span class="tag <?= $p['status']==='com_pendencia'?'pend':'' ?>"><?= h(status_nome($p['status'])) ?></span><br>
Entrega: <strong><?= data_br($p['data_entrega']) ?></strong><br>
<?= h($p['endereco']) ?><?= $p['bairro'] ? ' – ' . h($p['bairro']) : '' ?> · Tel.: <?= h($p['telefone']) ?> · Vendedor: <?= h($p['vendedor']) ?>
<table class="itens"><?php foreach (q('SELECT * FROM itens_pedido WHERE pedido_id = ?', [$p['id']])->fetchAll() as $i): ?>
<tr><td><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></td><td><?= h($i['descricao']) ?></td></tr><?php endforeach; ?></table>
<?php $elegivel = $p['status'] === 'aguardando' && !(int)q('SELECT COUNT(*) FROM itens_pedido WHERE pedido_id = ? AND separado = 1', [$p['id']])->fetchColumn(); ?>
<?php if ($u['perfil'] === 'admin' && !$elegivel): ?>
<form method="post" action="pedido_excluir.php" class="no-print" onsubmit="return confirm('EXCLUIR DEFINITIVAMENTE o pedido #<?= $p['id'] ?>, com itens e pendências? Use para pedidos de teste. Não é possível desfazer.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="sec">Excluir definitivamente (admin)</button></form>
<?php endif; ?>
<?php if (in_array($u['perfil'], ['admin','balcao'], true) && $elegivel): ?>
<form method="post" action="pedido_excluir.php" class="no-print" onsubmit="return confirm('Excluir o pedido #<?= $p['id'] ?>? Não é possível desfazer.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
<a class="btn sec" href="pedido_novo.php?id=<?= $p['id'] ?>">Editar</a> <button class="sec">Excluir</button></form><?php endif; ?>
<?php if ($u['perfil'] === 'admin' && in_array($p['status'], ['aguardando','separado','em_rota'], true)): ?>
<form method="post" action="pedido_cancelar.php" class="no-print" onsubmit="return confirm('Cancelar o pedido #<?= $p['id'] ?>? Ele sairá da separação e da rota.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
<label for="m<?= $p['id'] ?>">Cancelar pedido (desistência) – motivo</label><input id="m<?= $p['id'] ?>" name="motivo" required maxlength="120" placeholder="Ex.: cliente desistiu da compra"><p><button class="sec">Cancelar pedido</button></p></form><?php endif; ?>
<?php if (in_array($p['status'], ['em_rota','com_pendencia'], true)): ?><p class="no-print"><a class="btn sec" href="entrega.php?id=<?= $p['id'] ?>">Conferência da entrega</a></p><?php endif; ?></div>
<?php endforeach; ?></div><?php rodape();
