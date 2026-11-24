<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
$u = topo('Pedidos', [], 'Entregas cadastradas, da mais próxima para a mais distante');
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
<form method="get" class="no-print card"><div class="barra" style="margin:0"><div style="flex:1;min-width:200px"><label for="data" style="margin-top:0">Data da entrega</label><input id="data" type="date" name="data" value="<?= h($data ?? '') ?>" onchange="this.form.submit()"></div>
<?php if ($data): ?><a class="btn sec" href="pedidos.php" style="align-self:flex-end">Ver todos os próximos</a><?php endif; ?></div>
<p class="nota"><?= $data ? 'Mostrando só a data escolhida.' : 'Sem data escolhida: aparecem todos os próximos pedidos e os ainda não entregues.' ?> <?= count($ps) ?> <?= count($ps) === 1 ? 'pedido' : 'pedidos' ?>.</p></form>
<?php if (!$ps): ?><div class="vazio"><p>Nenhum pedido encontrado.</p><?php if (in_array($u['perfil'], ['admin','balcao'], true)): ?><a class="btn" href="pedido_novo.php">+ Cadastrar pedido</a><?php endif; ?></div><?php endif; ?>
<div class="cards"><?php foreach ($ps as $p): ?>
<div class="card"><div class="pedido-cab"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong> <?= stag($p['status']) ?></div>
<p class="meta">Entrega <span class="data-dest"><?= data_br($p['data_entrega']) ?></span> · Vendedor: <b><?= h($p['vendedor']) ?></b></p>
<p class="meta"><?= h($p['endereco']) ?><?= $p['bairro'] ? ' – ' . h($p['bairro']) : '' ?><?= $p['telefone'] ? '<br>Tel.: ' . h($p['telefone']) : '' ?></p>
<table class="itens"><?php foreach (q('SELECT * FROM itens_pedido WHERE pedido_id = ?', [$p['id']])->fetchAll() as $i): ?>
<tr><td><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></td><td><?= h($i['descricao']) ?></td></tr><?php endforeach; ?></table>
<?php if ($p['observacao']): ?><p class="meta">Obs.: <?= h($p['observacao']) ?></p><?php endif; ?>
<?php $elegivel = $p['status'] === 'aguardando' && !(int)q('SELECT COUNT(*) FROM itens_pedido WHERE pedido_id = ? AND separado = 1', [$p['id']])->fetchColumn();
$pode_ed = in_array($u['perfil'], ['admin','balcao'], true) && $elegivel;
$pode_conf = in_array($p['status'], ['em_rota','com_pendencia'], true);
$pode_canc = $u['perfil'] === 'admin' && in_array($p['status'], ['aguardando','separado','em_rota'], true);
$pode_exc_adm = $u['perfil'] === 'admin' && !$elegivel; ?>
<?php if ($pode_ed || $pode_conf): ?><div class="acoes-card no-print">
<?php if ($pode_conf): ?><a class="btn peq" href="entrega.php?id=<?= $p['id'] ?>">Conferir entrega</a><?php endif; ?>
<?php if ($pode_ed): ?><a class="btn sec peq" href="pedido_novo.php?id=<?= $p['id'] ?>">Editar</a>
<form method="post" action="pedido_excluir.php" onsubmit="return confirm('Excluir o pedido #<?= $p['id'] ?>? Não é possível desfazer.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="perigo peq">Excluir</button></form><?php endif; ?>
</div><?php endif; ?>
<?php if ($pode_canc || $pode_exc_adm): ?><details class="admin no-print"><summary>Opções do administrador</summary>
<?php if ($pode_canc): ?><form method="post" action="pedido_cancelar.php" onsubmit="return confirm('Cancelar o pedido #<?= $p['id'] ?>? Ele sairá da separação e da rota.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
<label for="m<?= $p['id'] ?>">Cancelar pedido (desistência) – motivo</label><input id="m<?= $p['id'] ?>" name="motivo" required maxlength="120" placeholder="Ex.: cliente desistiu da compra"><p><button class="sec peq">Cancelar pedido</button></p></form><?php endif; ?>
<?php if ($pode_exc_adm): ?><form method="post" action="pedido_excluir.php" onsubmit="return confirm('EXCLUIR DEFINITIVAMENTE o pedido #<?= $p['id'] ?>, com itens e pendências? Use para pedidos de teste. Não é possível desfazer.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="perigo peq">Excluir definitivamente</button></form><?php endif; ?>
</details><?php endif; ?></div>
<?php endforeach; ?></div><?php rodape();
