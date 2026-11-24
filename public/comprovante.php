<?php
// Comprovante de entrega em A4, com campo de assinatura do cliente. ?id=PEDIDO ou ?rota=ROTA (um por página).
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc');
require_once INC . '/auth.php';
$u = exige_login(['rota', 'balcao']);
$rotaId = (int)($_GET['rota'] ?? 0); $id = (int)($_GET['id'] ?? 0);
$sql = 'SELECT p.*, c.nome, c.telefone, c.endereco, c.bairro, c.referencia, r.nome AS rota_nome FROM pedidos p JOIN clientes c ON c.id = p.cliente_id LEFT JOIN rotas r ON r.id = p.rota_id WHERE ';
$ps = $rotaId ? q($sql . 'p.rota_id = ? ORDER BY p.ordem_rota, p.id', [$rotaId])->fetchAll() : q($sql . 'p.id = ?', [$id])->fetchAll();
$itens = [];
if ($ps) { foreach (q('SELECT * FROM itens_pedido WHERE pedido_id IN (' . implode(',', array_map(fn($p) => (int)$p['id'], $ps)) . ') ORDER BY id')->fetchAll() as $i) { $itens[$i['pedido_id']][] = $i; } }
$volta = $rotaId && $ps ? 'rota.php?data=' . $ps[0]['data_entrega'] : ($ps ? 'entrega.php?id=' . (int)$ps[0]['id'] : 'rota.php');
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Comprovante de entrega</title>
<style>
@page{size:A4;margin:14mm}
*{box-sizing:border-box}
body{margin:0;background:#e5e7e3;font:14px/1.4 Arial,Helvetica,sans-serif;color:#111}
.ctl{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:center;padding:12px;background:#0f4a24}
.ctl a,.ctl button{font:inherit;min-height:42px;padding:0 16px;border-radius:8px;border:0;background:#fff;color:#0f4a24;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;cursor:pointer}
.ctl button{background:#22c55e;color:#06280f}
.pag{width:210mm;min-height:297mm;margin:14px auto;background:#fff;padding:14mm;page-break-after:always;break-after:page;position:relative}
.pag:last-of-type{page-break-after:auto;break-after:auto}
.cab{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:3px solid #0f4a24;padding-bottom:8px;margin-bottom:14px}
.cab h1{margin:0;font-size:22px}.cab .loja{font-size:12px;color:#444}.cab .num{text-align:right;font-size:20px;font-weight:700}
.cab .num small{display:block;font-size:12px;font-weight:400;color:#444}
dl{display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;margin:0 0 14px}
dl div{margin:0}dl .l{grid-column:1/-1}
dt{font-size:11px;color:#555;text-transform:none}dd{margin:1px 0 0;font-weight:700}
h2{font-size:15px;margin:14px 0 6px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #888;padding:7px 8px;text-align:left;vertical-align:top}
th{background:#eef2ec;font-size:12px}
.q{white-space:nowrap;width:70px}.cf{width:84px;text-align:center}
.cx{display:inline-block;width:16px;height:16px;border:1.5px solid #111;vertical-align:middle}
.oc{border:1px solid #888;min-height:54px;padding:6px 8px;margin-top:4px}
.decl{margin:16px 0 10px;font-size:13px}
.ass{display:grid;grid-template-columns:1.6fr 1fr;gap:26px;margin-top:38px}
.linha{border-top:1.5px solid #111;padding-top:4px;font-size:12px}
.ass2{display:grid;grid-template-columns:1fr 1fr 1fr;gap:22px;margin-top:30px}
.rod{position:absolute;left:14mm;right:14mm;bottom:10mm;font-size:11px;color:#666;display:flex;justify-content:space-between}
.vazio{max-width:420px;margin:30px auto;padding:20px;background:#fff;border-radius:10px;text-align:center}
@media print{body{background:#fff}.ctl{display:none}.pag{margin:0;width:auto;min-height:0;height:269mm;padding:0}.rod{left:0;right:0;bottom:0}}
</style></head><body>
<div class="ctl"><a href="<?= h($volta) ?>">‹ Voltar</a><button type="button" onclick="window.print()">Imprimir <?= $rotaId ? 'carregamento + ' : '' ?><?= count($ps) ?> <?= count($ps) === 1 ? 'comprovante' : 'comprovantes' ?> (A4)</button></div>
<?php if (!$ps): ?><div class="vazio"><p>Pedido ou rota não encontrado.</p></div><?php endif; ?>
<?php if ($rotaId && $ps): $rv = array_reverse($ps); $totalIt = 0; foreach ($ps as $x) { $totalIt += count($itens[$x['id']] ?? []); } ?>
<section class="pag">
<div class="cab"><div><h1>Carregamento do veículo</h1><span class="loja"><?= h(NOME_LOJA) ?> · <?= h(NOME_SISTEMA) ?></span></div><div class="num"><?= h($ps[0]['rota_nome'] ?? 'Rota') ?><small>Data: <?= data_br($ps[0]['data_entrega']) ?> · <?= count($ps) ?> entregas · <?= $totalIt ?> itens</small></div></div>
<p class="decl">Carregue na ordem abaixo: a última entrega da rota entra primeiro, para a primeira ficar mais perto da porta.</p>
<?php foreach ($rv as $x): ?>
<h2>Carregar <?= $loop = ($loop ?? 0) + 1 ?>º · Entrega <?= (int)$x['ordem_rota'] ?: '—' ?> · Pedido #<?= (int)$x['id'] ?> · <?= h($x['nome']) ?><?= $x['bairro'] ? ' (' . h($x['bairro']) . ')' : '' ?></h2>
<table><tbody>
<?php foreach ($itens[$x['id']] ?? [] as $i): ?><tr><td class="cf"><span class="cx"></span></td><td class="q"><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></td><td><?= h($i['descricao']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endforeach; ?>
<div class="ass"><div class="linha">Carregado por</div><div class="linha">Motorista / entregador</div></div>
<div class="rod"><span>Emitido em <?= date('d/m/Y H:i') ?></span><span>Folha de carregamento</span></div>
</section>
<?php endif; ?>
<?php foreach ($ps as $p): ?>
<section class="pag">
<div class="cab"><div><h1>Comprovante de entrega</h1><span class="loja"><?= h(NOME_LOJA) ?> · <?= h(NOME_SISTEMA) ?></span></div><div class="num">Pedido #<?= (int)$p['id'] ?><small>Entrega: <?= data_br($p['data_entrega']) ?></small></div></div>
<dl>
<div><dt>Cliente</dt><dd><?= h($p['nome']) ?></dd></div>
<div><dt>Telefone</dt><dd><?= h($p['telefone'] ?: '—') ?></dd></div>
<div class="l"><dt>Endereço de entrega</dt><dd><?= h($p['endereco']) ?><?= $p['bairro'] ? ' – ' . h($p['bairro']) : '' ?><?= $p['referencia'] ? ' (Ref.: ' . h($p['referencia']) . ')' : '' ?></dd></div>
<div><dt>Vendedor</dt><dd><?= h($p['vendedor']) ?></dd></div>
<div><dt>Rota</dt><dd><?= $p['rota_nome'] ? h($p['rota_nome']) . ($p['ordem_rota'] ? ' · parada ' . (int)$p['ordem_rota'] : '') : '—' ?></dd></div>
</dl>
<h2>Itens do pedido</h2>
<table><thead><tr><th class="q">Qtd.</th><th>Descrição</th><th class="cf">Conferido</th></tr></thead><tbody>
<?php foreach ($itens[$p['id']] ?? [] as $i): ?><tr><td class="q"><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></td><td><?= h($i['descricao']) ?></td><td class="cf"><span class="cx"></span></td></tr><?php endforeach; ?>
</tbody></table>
<?php if ($p['observacao']): ?><p><b>Observação:</b> <?= h($p['observacao']) ?></p><?php endif; ?>
<h2>Ocorrências ou itens não entregues</h2><div class="oc"></div>
<p class="decl">Declaro que conferi os itens acima no ato da entrega e os recebi conforme descrito, exceto o que estiver registrado em ocorrências.</p>
<div class="ass"><div class="linha">Assinatura do cliente</div><div class="linha">Nome legível</div></div>
<div class="ass2"><div class="linha">Data e hora da entrega</div><div class="linha">Documento (opcional)</div><div class="linha">Entregador</div></div>
<div class="rod"><span>Emitido em <?= date('d/m/Y H:i') ?></span><span><?= h(NOME_SISTEMA) ?> · Pedido #<?= (int)$p['id'] ?></span></div>
</section>
<?php endforeach; ?>
</body></html>
