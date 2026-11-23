<?php
require_once __DIR__ . '/../inc/layout.php';
topo('Conferência da entrega', ['rota']);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = q('SELECT p.*, c.nome, c.endereco, c.bairro FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.id = ?', [$id])->fetch();
if (!$p) { echo '<p class="erro">Pedido não encontrado.</p>'; rodape(); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $ok = array_map('intval', $_POST['ok'] ?? []);
    $falta = 0;
    foreach (q('SELECT id FROM itens_pedido WHERE pedido_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN) as $iid) {
        $entregue = in_array((int)$iid, $ok, true);
        q('UPDATE itens_pedido SET conferido = ? WHERE id = ?', [$entregue ? 1 : 0, $iid]);
        $existe = (int)q('SELECT COUNT(*) FROM pendencias WHERE item_id = ? AND resolvida = 0', [$iid])->fetchColumn();
        if (!$entregue) { $falta++; if (!$existe) { q('INSERT INTO pendencias (pedido_id, item_id, observacao) VALUES (?,?,?)', [$id, $iid, trim($_POST['obs'] ?? '')]); } }
        elseif ($existe) { q('UPDATE pendencias SET resolvida = 1 WHERE item_id = ?', [$iid]); }
    }
    q('UPDATE pedidos SET status = ? WHERE id = ?', [$falta ? 'com_pendencia' : 'entregue', $id]);
    header('Location: ' . ($falta ? 'pendencias.php' : 'rota.php?data=' . $p['data_entrega'])); exit;
}
$itens = q('SELECT * FROM itens_pedido WHERE pedido_id = ?', [$id])->fetchAll();
?>
<div class="card"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong><br><?= h($p['endereco']) ?> – <?= h($p['bairro']) ?></div>
<form method="post"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
<p>Marque os itens conferidos com o cliente. Os não marcados viram pendência.</p>
<table class="itens"><?php foreach ($itens as $i): ?><tr><td style="width:40px"><input type="checkbox" name="ok[]" value="<?= $i['id'] ?>" id="i<?= $i['id'] ?>" <?= $i['conferido']?'checked':'' ?>></td>
<td><label for="i<?= $i['id'] ?>" style="font-weight:normal;margin:0"><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?> – <?= h($i['descricao']) ?></label></td></tr><?php endforeach; ?></table>
<label for="obs">Observação sobre a pendência (opcional)</label><input id="obs" name="obs">
<p><button>Registrar conferência</button></p></form>
<?php rodape();
