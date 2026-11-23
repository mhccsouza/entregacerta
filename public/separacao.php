<?php
require_once __DIR__ . '/../inc/layout.php';
topo('Lista de separação', ['deposito']);
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'] ?? $_POST['data'] ?? '') ? ($_GET['data'] ?? $_POST['data']) : date('Y-m-d');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $marcados = array_map('intval', $_POST['sep'] ?? []);
    $pedidos = q("SELECT id FROM pedidos WHERE data_entrega = ? AND status IN ('aguardando','separado')", [$data])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($pedidos as $pid) {
        foreach (q('SELECT id FROM itens_pedido WHERE pedido_id = ?', [$pid])->fetchAll(PDO::FETCH_COLUMN) as $iid) {
            q('UPDATE itens_pedido SET separado = ? WHERE id = ?', [in_array((int)$iid, $marcados, true) ? 1 : 0, $iid]);
        }
        $falta = (int)q('SELECT COUNT(*) FROM itens_pedido WHERE pedido_id = ? AND separado = 0', [$pid])->fetchColumn();
        q('UPDATE pedidos SET status = ? WHERE id = ?', [$falta === 0 ? 'separado' : 'aguardando', $pid]);
    }
    header('Location: separacao.php?data=' . $data . '&salvo=1'); exit;
}
$ps = q("SELECT p.*, c.nome, c.bairro FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.data_entrega = ? AND p.status IN ('aguardando','separado') ORDER BY p.id", [$data])->fetchAll();
if (!empty($_GET['salvo'])): ?><p class="ok">Separação salva.</p><?php endif; ?>
<form method="get" class="no-print"><label for="data">Data da entrega</label><input id="data" type="date" name="data" value="<?= h($data) ?>" onchange="this.form.submit()"></form>
<?php if (!$ps): ?><p>Nenhum pedido para separar nesta data.</p><?php else: ?>
<form method="post"><?= csrf_campo() ?><input type="hidden" name="data" value="<?= h($data) ?>">
<?php foreach ($ps as $p): ?><div class="card"><strong>Pedido #<?= $p['id'] ?> · <?= h($p['nome']) ?></strong> <small>(<?= h($p['bairro']) ?>)</small> <span class="tag"><?= h(status_nome($p['status'])) ?></span>
<table class="itens"><?php foreach (q('SELECT * FROM itens_pedido WHERE pedido_id = ?', [$p['id']])->fetchAll() as $i): ?>
<tr><td style="width:40px"><input type="checkbox" name="sep[]" value="<?= $i['id'] ?>" id="i<?= $i['id'] ?>" <?= $i['separado']?'checked':'' ?>></td>
<td><label for="i<?= $i['id'] ?>" style="font-weight:normal;margin:0"><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?> – <?= h($i['descricao']) ?></label></td></tr><?php endforeach; ?></table>
<?php if ($p['observacao']): ?><small>Obs.: <?= h($p['observacao']) ?></small><?php endif; ?></div><?php endforeach; ?>
<p class="no-print"><button>Salvar separação</button> <button type="button" class="sec" onclick="window.print()">Imprimir lista</button></p></form>
<?php endif; rodape();
