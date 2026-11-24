<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
$u = topo('Entregues', [], 'Histórico dos pedidos já entregues, do mais recente ao mais antigo. Abra um pedido para consultar ou imprimir a 2ª via');
$ehData = fn($x) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$x) === 1;
$de  = $ehData($_GET['de'] ?? '') ? $_GET['de'] : '';
$ate = $ehData($_GET['ate'] ?? '') ? $_GET['ate'] : '';
if ($de && $ate && $de > $ate) { [$de, $ate] = [$ate, $de]; }
$vend  = trim((string)($_GET['vendedor'] ?? ''));
$busca = trim((string)($_GET['busca'] ?? ''));
$pag = max(1, (int)($_GET['pag'] ?? 1)); $por = 20;

$w = ["p.status = 'entregue'"]; $pr = [];
if ($de)  { $w[] = 'p.data_entrega >= ?'; $pr[] = $de; }
if ($ate) { $w[] = 'p.data_entrega <= ?'; $pr[] = $ate; }
if ($vend !== '') { $w[] = 'p.vendedor = ?'; $pr[] = $vend; }
if ($busca !== '') {
    $l = '%' . $busca . '%'; $dig = preg_replace('/\D+/', '', $busca);
    $cond = 'c.nome LIKE ? OR c.endereco LIKE ? OR c.bairro LIKE ? OR c.telefone LIKE ? OR p.id = ?';
    array_push($pr, $l, $l, $l, $l, (int)ltrim($busca, '#'));
    if (strlen($dig) >= 4) { // telefone digitado sem máscara
        $cond .= " OR REPLACE(REPLACE(REPLACE(REPLACE(c.telefone,'(',''),')',''),' ',''),'-','') LIKE ?"; $pr[] = '%' . $dig . '%';
    }
    $w[] = "($cond)";
}
$onde = 'WHERE ' . implode(' AND ', $w);
$total = (int)q("SELECT COUNT(*) FROM pedidos p JOIN clientes c ON c.id = p.cliente_id $onde", $pr)->fetchColumn();
$paginas = max(1, (int)ceil($total / $por)); $pag = min($pag, $paginas);
$ps = q("SELECT p.id, p.data_entrega, p.vendedor, c.nome, c.bairro FROM pedidos p JOIN clientes c ON c.id = p.cliente_id $onde ORDER BY p.data_entrega DESC, p.id DESC LIMIT $por OFFSET " . (($pag - 1) * $por), $pr)->fetchAll();
$qtd = [];
if ($ps) {
    $ids = array_map(fn($p) => (int)$p['id'], $ps);
    foreach (q('SELECT pedido_id, COUNT(*) n FROM itens_pedido WHERE pedido_id IN (' . implode(',', $ids) . ') GROUP BY pedido_id')->fetchAll() as $r) { $qtd[$r['pedido_id']] = (int)$r['n']; }
}
$nomesVend = array_column(vendedores(), 'nome');
foreach (q("SELECT DISTINCT vendedor FROM pedidos WHERE vendedor <> ''")->fetchAll(PDO::FETCH_COLUMN) as $n) { if (!in_array($n, $nomesVend, true)) { $nomesVend[] = $n; } }
sort($nomesVend, SORT_NATURAL | SORT_FLAG_CASE);
$filtrado = $de || $ate || $vend !== '' || $busca !== '';
function link_pag(int $n): string { $g = $_GET; $g['pag'] = $n; return 'historico.php?' . http_build_query($g); }
$meses = [1=>'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
?>
<form method="get" class="no-print card filtros"><div class="grid">
<div class="f-larga"><label for="busca">Buscar</label><input id="busca" type="search" name="busca" value="<?= h($busca) ?>" placeholder="Cliente, telefone, endereço ou nº"></div>
<div><label for="vendedor">Vendedor</label><select id="vendedor" name="vendedor" onchange="this.form.submit()"><option value="">Todos</option>
<?php foreach ($nomesVend as $n): ?><option value="<?= h($n) ?>" <?= $vend === $n ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></div>
<div><label for="de">Entregue de</label><div class="data-w<?= $de ? '' : ' sem-data' ?>"><input id="de" type="date" name="de" value="<?= h($de) ?>"><span class="ph" aria-hidden="true">Escolher data</span></div></div>
<div><label for="ate">até</label><div class="data-w<?= $ate ? '' : ' sem-data' ?>"><input id="ate" type="date" name="ate" value="<?= h($ate) ?>"><span class="ph" aria-hidden="true">Escolher data</span></div></div></div>
<div class="barra"><button class="peq">Buscar</button><?php if ($filtrado): ?><a class="btn sec peq" href="historico.php">Limpar filtros</a><?php endif; ?>
<a class="btn sec peq" href="pedidos.php">‹ Voltar aos pedidos</a>
<span class="nota sem-margem"><?= $total ?> <?= $total === 1 ? 'pedido entregue' : 'pedidos entregues' ?></span></div></form>
<?php if (!$ps): ?><div class="vazio"><p><?= $filtrado ? 'Nenhum pedido entregue encontrado com esses filtros.' : 'Ainda não há pedidos entregues.' ?></p></div><?php else: ?>
<ul class="hist">
<?php $mesAtual = ''; foreach ($ps as $p): $ts = strtotime($p['data_entrega']); $mes = $meses[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
if ($mes !== $mesAtual): $mesAtual = $mes; ?><li class="hist-mes"><?= h($mes) ?></li><?php endif; ?>
<li><a class="hist-i" href="pedido_ver.php?id=<?= (int)$p['id'] ?>&amp;de=hist">
<span class="h-data"><?= data_br($p['data_entrega']) ?></span>
<span class="h-cli"><strong>#<?= (int)$p['id'] ?> · <?= h($p['nome']) ?></strong><small><?= h($p['bairro'] ?: 'Sem bairro') ?> · <?= h($p['vendedor']) ?> · <?= $qtd[$p['id']] ?? 0 ?> <?= ($qtd[$p['id']] ?? 0) === 1 ? 'item' : 'itens' ?></small></span>
<span class="btn sec peq h-ver">Ver / 2ª via</span></a></li>
<?php endforeach; ?></ul>
<?php if ($paginas > 1):
$mostrar = array_unique(array_filter(array_merge([1, $paginas], range(max(1, $pag - 2), min($paginas, $pag + 2))), fn($n) => $n >= 1 && $n <= $paginas)); sort($mostrar); ?>
<nav class="paginas no-print" aria-label="Páginas do histórico">
<?php if ($pag > 1): ?><a class="pg" href="<?= h(link_pag($pag - 1)) ?>" aria-label="Página anterior">‹</a><?php endif; ?>
<?php $ant = 0; foreach ($mostrar as $n): if ($n - $ant > 1): ?><span class="pg-gap">…</span><?php endif; $ant = $n; ?>
<?php if ($n === $pag): ?><span class="pg on" aria-current="page"><?= $n ?></span><?php else: ?><a class="pg" href="<?= h(link_pag($n)) ?>"><?= $n ?></a><?php endif; ?>
<?php endforeach; ?>
<?php if ($pag < $paginas): ?><a class="pg" href="<?= h(link_pag($pag + 1)) ?>" aria-label="Próxima página">›</a><?php endif; ?>
</nav>
<p class="nota centro">Página <?= $pag ?> de <?= $paginas ?> · mostrando <?= ($pag - 1) * $por + 1 ?> a <?= min($total, $pag * $por) ?> de <?= $total ?> pedidos</p>
<?php endif; endif; ?>
<script>
document.querySelectorAll('.data-w').forEach(function(w){var i=w.querySelector('input');
 function at(){w.classList.toggle('sem-data',!i.value)}i.addEventListener('input',at);i.addEventListener('change',at);at();});
</script>
<?php rodape();
