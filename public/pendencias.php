<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
topo('Pendências de entrega', ['rota','balcao'], 'Itens que ficaram faltando em entregas já feitas');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $pid = (int)($_POST['pedido_id'] ?? 0);
    q('UPDATE pendencias SET resolvida = 1 WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    if (!(int)q('SELECT COUNT(*) FROM pendencias WHERE pedido_id = ? AND resolvida = 0', [$pid])->fetchColumn()) {
        q("UPDATE pedidos SET status = 'entregue' WHERE id = ? AND status = 'com_pendencia'", [$pid]);
        q('UPDATE itens_pedido SET conferido = 1 WHERE pedido_id = ?', [$pid]);
    }
    header('Location: pendencias.php'); exit;
}
$rows = q('SELECT pe.id, pe.pedido_id, pe.observacao, pe.criada_em, i.quantidade, i.unidade, i.descricao, c.nome, c.endereco, c.bairro, c.telefone
 FROM pendencias pe JOIN itens_pedido i ON i.id = pe.item_id JOIN pedidos p ON p.id = pe.pedido_id JOIN clientes c ON c.id = p.cliente_id
 WHERE pe.resolvida = 0 ORDER BY pe.id')->fetchAll();
if (!$rows): ?><div class="vazio"><p>Nenhuma pendência em aberto. Tudo entregue.</p><a class="btn sec" href="index.php">Voltar ao painel</a></div><?php endif; ?>
<div class="cards"><?php foreach ($rows as $r): ?><div class="card"><div class="pedido-cab"><strong>Pedido #<?= $r['pedido_id'] ?> · <?= h($r['nome']) ?></strong> <span class="tag pend">Pendente</span></div>
<p class="meta"><?= h($r['endereco']) ?> – <?= h($r['bairro']) ?><?= $r['telefone'] ? '<br>Tel.: ' . h($r['telefone']) : '' ?></p>
<p><strong>Falta: <?= qtd_fmt($r['quantidade']) ?> <?= h($r['unidade']) ?> – <?= h($r['descricao']) ?></strong></p><?= $r['observacao'] ? '<p class="meta">Obs.: ' . h($r['observacao']) . '</p>' : '' ?>
<form method="post" class="no-print"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="pedido_id" value="<?= $r['pedido_id'] ?>"><button class="peq">Marcar como entregue</button></form></div>
<?php endforeach; ?></div><?php rodape();
