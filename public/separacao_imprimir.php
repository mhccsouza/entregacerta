<?php
// Impressão da separação em bobina térmica (58 mm ou 80 mm): um cupom por pedido, só com o necessário.
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc');
require_once INC . '/auth.php';
$u = exige_login(['deposito']);
$larg = (int)($_GET['larg'] ?? 80); $larg = in_array($larg, [58, 80, 210], true) ? $larg : 80; $a4 = $larg === 210;
$hoje = date('Y-m-d'); $amanha = date('Y-m-d', strtotime('+1 day'));
$ids = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['ped'] ?? '')))));
$ver = (string)($_GET['ver'] ?? 'todos');
$w = "p.status IN ('aguardando','separado') AND p.rota_id IS NULL"; $pr = [];
if ($ids) { $w .= ' AND p.id IN (' . implode(',', $ids) . ')'; }
elseif ($ver === 'hoje') { $w .= ' AND p.data_entrega <= ?'; $pr[] = $hoje; }
elseif ($ver === 'amanha') { $w .= ' AND p.data_entrega = ?'; $pr[] = $amanha; }
$ps = q("SELECT p.*, c.nome, c.bairro FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE $w ORDER BY CASE WHEN p.data_entrega = '" . A_COMBINAR . "' THEN 1 ELSE 0 END, p.data_entrega, p.id", $pr)->fetchAll();
$itens = [];
if ($ps) { foreach (q('SELECT * FROM itens_pedido WHERE pedido_id IN (' . implode(',', array_map(fn($p) => (int)$p['id'], $ps)) . ') ORDER BY id')->fetchAll() as $i) { $itens[$i['pedido_id']][] = $i; } }
$volta = 'separacao.php' . ($ids ? '' : '?ver=' . urlencode($ver));
$css = 'style.css?v=' . (int)@filemtime(__DIR__ . '/style.css');
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Separação – cupom <?= $larg ?> mm</title>
<style>
@page{size:<?= $a4 ? 'A4' : $larg . 'mm auto' ?>;margin:<?= $a4 ? '12mm' : '2mm' ?>}
*{box-sizing:border-box}
body{margin:0;background:#e5e7e3;font:14px/1.3 Arial,Helvetica,sans-serif;color:#000}
.ctl{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:center;padding:12px;background:#0f4a24;color:#fff}
.ctl a,.ctl button,.ctl select{font:inherit;min-height:42px;padding:0 14px;border-radius:8px;border:0;background:#fff;color:#0f4a24;font-weight:700;text-decoration:none;display:inline-flex;align-items:center}
.ctl button{background:#22c55e;color:#06280f}
.cupom{width:<?= $a4 ? 120 : $larg - 4 ?>mm;max-width:100%;margin:14px auto;background:#fff;padding:2mm;<?= $a4 ? 'break-inside:avoid;border-bottom:1px dashed #000' : 'page-break-after:always;break-after:page' ?>}
.cupom:last-of-type{page-break-after:auto;break-after:auto}
.sel{max-width:560px;margin:12px auto;background:#fff;border-radius:10px;padding:10px 14px}.sel h2{font-size:1em;margin:0 0 6px}.sel label{display:flex;gap:8px;align-items:center;padding:8px 0;border-top:1px solid #ddd;min-height:44px}.sel input{width:22px;height:22px;flex:none}.sel .todos{font-weight:700;border-top:0}
.ctl .pp{color:#fff;font-size:.9em}
h1{font-size:1.15em;margin:0 0 2px;text-align:center;border-bottom:2px solid #000;padding-bottom:3px}
.cl{font-weight:700;font-size:1.1em;margin:5px 0 0;overflow-wrap:anywhere}
.m{margin:1px 0;overflow-wrap:anywhere}
.tracejado{border-top:1px dashed #000;margin:6px 0}
.it{display:flex;gap:6px;margin:5px 0;align-items:flex-start}
.cx{flex:none;width:5mm;height:5mm;border:1.5px solid #000;margin-top:1px}
.q{font-weight:700;flex:none}
.d{overflow-wrap:anywhere}
.rod{margin-top:6px;font-size:.85em}
.vazio{max-width:420px;margin:30px auto;padding:20px;background:#fff;border-radius:10px;text-align:center}
@media print{body{background:#fff}.ctl,.sel{display:none}.cupom{margin:0;width:auto;padding:0}}
</style></head><body>
<div class="ctl">
<a href="<?= h($volta) ?>">‹ Voltar</a>
<label>Papel da impressora&nbsp;<select id="larg" onchange="var q=new URLSearchParams(location.search);q.set('larg',this.value);try{localStorage.setItem('bobina',this.value)}catch(e){}location.search=q.toString()"><option value="80" <?= $larg === 80 ? 'selected' : '' ?>>80 mm</option><option value="58" <?= $larg === 58 ? 'selected' : '' ?>>58 mm</option><option value="210" <?= $a4 ? 'selected' : '' ?>>Folha A4</option></select></label>
<button type="button" id="bimp" onclick="window.print()">Imprimir <?= count($ps) ?> <?= count($ps) === 1 ? 'cupom' : 'cupons' ?></button>
</div>
<?php if (count($ps) > 1): ?><div class="sel"><h2>O que imprimir</h2>
<label class="todos"><input type="checkbox" id="todos" checked> Marcar / desmarcar todos</label>
<?php foreach ($ps as $p): ?><label><input type="checkbox" class="esc" value="<?= (int)$p['id'] ?>" checked> #<?= (int)$p['id'] ?> · <?= h($p['nome']) ?> · <?= data_br($p['data_entrega']) ?></label><?php endforeach; ?></div><?php endif; ?>
<script>try{var b=localStorage.getItem('bobina');if(b&&!/[?&]larg=/.test(location.search)&&(b==='58'||b==='80'||b==='210')&&b!=='<?= $larg ?>'){var q=new URLSearchParams(location.search);q.set('larg',b);location.replace('?'+q.toString())}}catch(e){}</script>
<?php if (!$ps): ?><div class="vazio"><p>Nenhum pedido para imprimir.</p></div><?php endif; ?>
<?php foreach ($ps as $p): [$pz] = prazo($p['data_entrega'], $p['status']); ?>
<section class="cupom" data-id="<?= (int)$p['id'] ?>">
<h1>SEPARAÇÃO #<?= (int)$p['id'] ?></h1>
<p class="cl"><?= h($p['nome']) ?></p>
<p class="m"><b>Entrega: <?= data_br($p['data_entrega']) ?></b><?= $pz && $p['data_entrega'] !== A_COMBINAR ? ' (' . h($pz) . ')' : '' ?></p>
<?php if ($p['bairro']): ?><p class="m">Bairro: <?= h($p['bairro']) ?></p><?php endif; ?>
<?php if ($p['observacao']): ?><p class="m">Obs.: <?= h($p['observacao']) ?></p><?php endif; ?>
<div class="tracejado"></div>
<?php foreach ($itens[$p['id']] ?? [] as $i): ?><div class="it"><span class="cx"></span><span class="q"><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></span><span class="d"><?= h($i['descricao']) ?></span></div><?php endforeach; ?>
<div class="tracejado"></div>
<p class="rod">Separado por: ____________<br>Impresso em <?= date('d/m/Y H:i') ?></p>
</section>
<?php endforeach; ?>
<script>(function(){var cs=[].slice.call(document.querySelectorAll('.esc')),t=document.getElementById('todos'),b=document.getElementById('bimp');function at(){var n=0;cs.forEach(function(c){var el=document.querySelector('.cupom[data-id="'+c.value+'"]');el.hidden=!c.checked;if(c.checked)n++});if(cs.length){b.disabled=!n;b.textContent=n?'Imprimir '+n+(n===1?' cupom':' cupons'):'Escolha ao menos um';t.checked=n===cs.length}}cs.forEach(function(c){c.onchange=at});if(t)t.onchange=function(){cs.forEach(function(c){c.checked=t.checked});at()}})();</script>
</body></html>
