<?php
require_once __DIR__ . '/../inc/layout.php';
topo('Pendências de entrega', ['rota','balcao']);
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
if (!$rows): ?><p>Nenhuma pendência em aberto.</p><?php endif;
foreach ($rows as $r): ?><div class="card"><strong>Pedido #<?= $r['pedido_id'] ?> · <?= h($r['nome']) ?></strong><br><?= h($r['endereco']) ?> – <?= h($r['bairro']) ?> · Tel.: <?= h($r['telefone']) ?><br>
<span class="tag pend">Falta: <?= qtd_fmt($r['quantidade']) ?> <?= h($r['unidade']) ?> – <?= h($r['descricao']) ?></span><?= $r['observacao'] ? '<br>Obs.: ' . h($r['observacao']) : '' ?>
<form method="post" style="margin-top:8px"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="pedido_id" value="<?= $r['pedido_id'] ?>"><button>Marcar como resolvida (item entregue)</button></form></div>
<?php endforeach; rodape();
