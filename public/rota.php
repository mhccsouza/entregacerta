<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
topo('Rota do dia', ['rota'], 'Monte a sequência de entregas e confira cada parada');
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'] ?? $_POST['data'] ?? '') ? ($_GET['data'] ?? $_POST['data']) : data_padrao("status IN ('aguardando','separado','em_rota','com_pendencia') AND status <> 'entregue'");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $sel = array_map('intval', $_POST['pedido'] ?? []);
    if ($sel) {
        $n = (int)q('SELECT COUNT(*) FROM rotas WHERE data_rota = ?', [$data])->fetchColumn() + 1;
        q('INSERT INTO rotas (data_rota, nome) VALUES (?, ?)', [$data, 'Rota ' . $n . ' – ' . data_br($data)]);
        $rid = (int)db()->lastInsertId();
        $ordens = $_POST['ordem'] ?? [];
        $lista = [];
        foreach ($sel as $pid) { $lista[] = [(int)($ordens[$pid] ?? 99), $pid]; }
        sort($lista);
        foreach ($lista as $pos => [$_, $pid]) {
            q("UPDATE pedidos SET rota_id = ?, ordem_rota = ?, status = 'em_rota' WHERE id = ? AND status IN ('aguardando','separado')", [$rid, $pos + 1, $pid]);
        }
    }
    header('Location: rota.php?data=' . $data); exit;
}
$livres = q("SELECT p.*, c.nome, c.endereco, c.bairro FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.data_entrega = ? AND p.rota_id IS NULL AND p.status IN ('aguardando','separado') ORDER BY c.bairro, p.id", [$data])->fetchAll();
$rotas = q('SELECT * FROM rotas WHERE data_rota = ? ORDER BY id', [$data])->fetchAll();
?>
<form method="get" class="no-print card"><label for="data" style="margin-top:0">Data</label><input id="data" type="date" name="data" value="<?= h($data) ?>" onchange="this.form.submit()"></form>
<?php if ($livres): ?><form method="post" class="no-print"><?= csrf_campo() ?><input type="hidden" name="data" value="<?= h($data) ?>">
<h2>Pedidos sem rota</h2><p class="nota" style="margin:-4px 0 10px">Marque os pedidos desta saída e, se quiser, indique a ordem das paradas (1, 2, 3…).</p>
<table class="resp rota"><tr><th>Incluir</th><th>Ordem</th><th>Pedido</th><th>Endereço</th><th>Separação</th></tr>
<?php foreach ($livres as $p): $falta = (int)q('SELECT COUNT(*) FROM itens_pedido WHERE pedido_id = ? AND separado = 0', [$p['id']])->fetchColumn(); ?>
<tr><td data-label="Incluir na rota" style="width:70px"><input type="checkbox" name="pedido[]" value="<?= $p['id'] ?>" aria-label="Incluir pedido <?= $p['id'] ?>"></td>
<td data-label="Ordem na rota" style="width:100px"><input name="ordem[<?= $p['id'] ?>]" inputmode="numeric" value="" aria-label="Ordem do pedido <?= $p['id'] ?>"></td>
<td data-label="Pedido"><strong>#<?= $p['id'] ?></strong> · <?= h($p['nome']) ?></td><td data-label="Endereço"><?= h($p['endereco']) ?> – <?= h($p['bairro']) ?></td>
<td data-label="Separação"><?= $falta ? '<span class="tag s-aguardando">' . $falta . ' item(ns) por separar</span>' : '<span class="tag s-separado">Separado</span>' ?></td></tr>
<?php endforeach; ?></table><div class="barra"><button>Montar rota com os pedidos marcados</button></div></form>
<?php else: ?><div class="vazio no-print"><p>Nenhum pedido sem rota nesta data.</p></div><?php endif; ?>
<?php foreach ($rotas as $r): ?><h2><?= h($r['nome']) ?></h2><div class="cards">
<?php foreach (q('SELECT p.*, c.nome, c.endereco, c.bairro, c.referencia, c.telefone FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.rota_id = ? ORDER BY p.ordem_rota', [$r['id']])->fetchAll() as $p): ?>
<div class="card"><div class="pedido-cab"><strong><?= (int)$p['ordem_rota'] ?>. <?= h($p['nome']) ?></strong> <?= stag($p['status']) ?></div>
<p class="meta"><?= h($p['endereco']) ?> – <?= h($p['bairro']) ?><?= $p['referencia'] ? '<br>Ref.: ' . h($p['referencia']) : '' ?><?= $p['telefone'] ? '<br>Tel.: ' . h($p['telefone']) : '' ?><?= $p['observacao'] ? '<br>Obs.: ' . h($p['observacao']) : '' ?></p>
<table class="itens"><?php foreach (q('SELECT * FROM itens_pedido WHERE pedido_id = ?', [$p['id']])->fetchAll() as $i): ?><tr><td>☐ <?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></td><td><?= h($i['descricao']) ?></td></tr><?php endforeach; ?></table>
<div class="acoes-card no-print"><a class="btn peq" href="entrega.php?id=<?= $p['id'] ?>">Conferir entrega</a></div></div>
<?php endforeach; ?></div><?php endforeach; ?>
<?php if ($rotas): ?><div class="barra no-print"><button type="button" class="sec" onclick="window.print()">Imprimir rota</button></div><?php endif; rodape();
