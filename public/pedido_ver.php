<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
$id = (int)($_GET['id'] ?? 0);
$p = q('SELECT p.*, c.nome, c.telefone, c.endereco, c.bairro, c.referencia, r.nome AS rota_nome, r.data_rota, uc.nome AS cadastrado_por
        FROM pedidos p JOIN clientes c ON c.id = p.cliente_id LEFT JOIN rotas r ON r.id = p.rota_id LEFT JOIN usuarios uc ON uc.id = p.criado_por WHERE p.id = ?', [$id])->fetch();
if (!$p) { $u = topo('Pedido não encontrado'); echo '<div class="vazio"><p>Esse pedido não existe ou foi excluído.</p><a class="btn" href="pedidos.php">Ver pedidos</a></div>'; rodape(); exit; }
pendencia_qtd_garante();
$u = topo('Pedido #' . $p['id'], [], '2ª via para consulta e impressão');
$voltar = ($_GET['de'] ?? '') === 'hist' ? ['historico.php', '‹ Voltar ao histórico'] : ['pedidos.php', '‹ Voltar aos pedidos'];
$its = q('SELECT * FROM itens_pedido WHERE pedido_id = ? ORDER BY id', [$id])->fetchAll();
$pend = q('SELECT * FROM pendencias WHERE pedido_id = ? ORDER BY id', [$id])->fetchAll();
$itById = []; foreach ($its as $i0) { $itById[$i0['id']] = $i0; }
$pendItem = []; foreach ($pend as $x) { if (!$x['resolvida']) { $pendItem[$x['item_id']] = $x; } }
$dt = fn($x) => $x ? date('d/m/Y H:i', strtotime($x)) : '';
?>
<style>@media print{.cabeca{display:none}}</style>
<div class="acoes-topo no-print"><a class="btn sec" href="<?= $voltar[0] ?>"><?= $voltar[1] ?></a><button type="button" onclick="window.print()">Imprimir 2ª via</button></div>
<article class="card via">
<div class="via-cab"><div><p class="via-loja"><?= h(NOME_LOJA) ?> · <?= h(NOME_SISTEMA) ?></p><h2>Pedido #<?= (int)$p['id'] ?> <small>2ª via</small></h2></div><?= stag($p['status']) ?></div>
<dl class="via-dados">
<div><dt>Cliente</dt><dd><?= h($p['nome']) ?></dd></div>
<?php if ($p['telefone']): ?><div><dt>Telefone</dt><dd><?= h($p['telefone']) ?></dd></div><?php endif; ?>
<div class="largo"><dt>Endereço</dt><dd><?= h($p['endereco']) ?><?= $p['bairro'] ? ' – ' . h($p['bairro']) : '' ?><?= $p['referencia'] ? '<br>Ref.: ' . h($p['referencia']) : '' ?></dd></div>
<div><dt>Data da entrega</dt><dd><?= data_br($p['data_entrega']) ?></dd></div>
<div><dt>Vendedor</dt><dd><?= h($p['vendedor']) ?></dd></div>
<?php if ($p['rota_id']): ?><div><dt>Rota</dt><dd><?= h($p['rota_nome']) ?><?= $p['ordem_rota'] ? ' · parada ' . (int)$p['ordem_rota'] : '' ?></dd></div><?php endif; ?>
<div><dt>Cadastrado em</dt><dd><?= $dt($p['criado_em']) ?><?= $p['cadastrado_por'] ? ' por ' . h($p['cadastrado_por']) : '' ?></dd></div>
</dl>
<h3>Itens</h3>
<table class="via-itens"><thead><tr><th>Qtd.</th><th>Descrição</th><th class="c">Situação</th></tr></thead><tbody>
<?php foreach ($its as $i): ?><tr><td><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></td><td><?= h($i['descricao']) ?></td>
<td class="c"><?= !empty($pendItem[$i['id']]) ? '<span class="tag s-com_pendencia">Pendente' . ($pendItem[$i['id']]['qtd_falta'] !== null ? ' · falta ' . qtd_fmt($pendItem[$i['id']]['qtd_falta']) . ' ' . h($i['unidade']) : '') . '</span>' : ($i['conferido'] ? 'Entregue' : ($i['separado'] ? 'Separado' : '–')) ?></td></tr><?php endforeach; ?>
<?php if (!$its): ?><tr><td colspan="3">Sem itens.</td></tr><?php endif; ?></tbody></table>
<?php if ($pend): ?><h3>Pendências</h3><ul class="via-pend"><?php foreach ($pend as $x): ?><li><?= $x['resolvida'] ? '✔ Resolvida' : '● Em aberto' ?><?php if (!empty($itById[$x['item_id']])): $it = $itById[$x['item_id']]; ?> – <?= $x['qtd_falta'] !== null ? 'falta ' . qtd_fmt($x['qtd_falta']) . ' ' . h($it['unidade']) . ' de ' . qtd_fmt($it['quantidade']) : 'falta ' . qtd_fmt($it['quantidade']) . ' ' . h($it['unidade']) ?> (<?= h($it['descricao']) ?>)<?php endif; ?><?= $x['observacao'] ? ' – ' . h($x['observacao']) : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if ($p['observacao']): ?><h3>Observação</h3><p><?= h($p['observacao']) ?></p><?php endif; ?>
<p class="via-rodape">2ª via emitida em <?= date('d/m/Y H:i') ?> por <?= h($u['nome']) ?>.</p>
</article>
<?php rodape();
