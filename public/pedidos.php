<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
$u = topo('Pedidos', [], 'Ordenados por prioridade de entrega. Filtre por data, vendedor ou situação');
$hoje = date('Y-m-d');
$ehData = fn($x) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$x) === 1;
$data = $ehData($_GET['data'] ?? '') ? $_GET['data'] : null;          // data exata (links do painel)
$de  = $data ?: ($ehData($_GET['de'] ?? '') ? $_GET['de'] : '');
$ate = $data ?: ($ehData($_GET['ate'] ?? '') ? $_GET['ate'] : '');
if ($de && $ate && $de > $ate) { [$de, $ate] = [$ate, $de]; }
$status = (string)($_GET['status'] ?? '');
$validos = ['aguardando','separado','em_rota','com_pendencia','entregue','cancelado','a_combinar','todos'];
if (!in_array($status, $validos, true)) { $status = ''; }
$prios = ['atrasado'=>'Atrasados','hoje'=>'Hoje','amanha'=>'Amanhã','semana'=>'Próximos 7 dias','adiante'=>'Mais adiante','combinar'=>'A combinar'];
$prio = (string)($_GET['prio'] ?? ''); if (!isset($prios[$prio])) { $prio = ''; }
$d1 = date('Y-m-d', strtotime('+1 day')); $d2 = date('Y-m-d', strtotime('+2 day')); $d7 = date('Y-m-d', strtotime('+7 day'));
$vend = trim((string)($_GET['vendedor'] ?? ''));
$busca = trim((string)($_GET['busca'] ?? ''));
$pag = max(1, (int)($_GET['pag'] ?? 1)); $por = 12;
$w = []; $pr = [];
if ($de)  { $w[] = 'p.data_entrega >= ?'; $pr[] = $de; }
if ($ate) { $w[] = 'p.data_entrega <= ?'; $pr[] = $ate; }
if ($status === 'a_combinar') { $w[] = "p.status NOT IN ('entregue','cancelado') AND p.data_entrega = ?"; $pr[] = A_COMBINAR; }
elseif ($status === 'todos') { $w[] = "p.status <> 'cancelado'"; }
elseif ($status) { $w[] = 'p.status = ?'; $pr[] = $status; }
elseif (!$de && !$ate && !$prio) { $w[] = "p.status <> 'cancelado' AND (p.data_entrega >= ? OR p.status <> 'entregue')"; $pr[] = $hoje; }
if ($prio) {
    $w[] = "p.status NOT IN ('entregue','cancelado')";
    switch ($prio) {
        case 'atrasado': $w[] = 'p.data_entrega < ?'; $pr[] = $hoje; break;
        case 'hoje':     $w[] = 'p.data_entrega = ?'; $pr[] = $hoje; break;
        case 'amanha':   $w[] = 'p.data_entrega = ?'; $pr[] = $d1; break;
        case 'semana':   $w[] = 'p.data_entrega BETWEEN ? AND ?'; array_push($pr, $d2, $d7); break;
        case 'adiante':  $w[] = 'p.data_entrega > ? AND p.data_entrega <> ?'; array_push($pr, $d7, A_COMBINAR); break;
        case 'combinar': $w[] = 'p.data_entrega = ?'; $pr[] = A_COMBINAR; break;
    }
}
if ($vend !== '') { $w[] = 'p.vendedor = ?'; $pr[] = $vend; }
if ($busca !== '') {
    $w[] = '(c.nome LIKE ? OR c.endereco LIKE ? OR c.bairro LIKE ? OR p.id = ?)';
    $l = '%' . $busca . '%'; array_push($pr, $l, $l, $l, (int)ltrim($busca, '#'));
}
$onde = $w ? 'WHERE ' . implode(' AND ', $w) : '';
$total = (int)q("SELECT COUNT(*) FROM pedidos p JOIN clientes c ON c.id = p.cliente_id $onde", $pr)->fetchColumn();
$paginas = max(1, (int)ceil($total / $por)); $pag = min($pag, $paginas);
$ordem = in_array($status, ['entregue','cancelado'], true) ? 'p.data_entrega DESC, p.id DESC' : ($status === 'todos' ? "CASE WHEN p.status = 'entregue' THEN 1 ELSE 0 END, p.data_entrega, p.id" : 'p.data_entrega, p.id');
$ps = q("SELECT p.*, c.nome, c.endereco, c.bairro, c.telefone, c.referencia FROM pedidos p JOIN clientes c ON c.id = p.cliente_id $onde ORDER BY $ordem LIMIT $por OFFSET " . (($pag - 1) * $por), $pr)->fetchAll();
// itens de todos os pedidos da pagina em UMA consulta
$itens = []; $sepCont = [];
if ($ps) {
    $ids = array_map(fn($p) => (int)$p['id'], $ps);
    foreach (q('SELECT * FROM itens_pedido WHERE pedido_id IN (' . implode(',', $ids) . ') ORDER BY id')->fetchAll() as $i) {
        $itens[$i['pedido_id']][] = $i;
        if ($i['separado']) { $sepCont[$i['pedido_id']] = ($sepCont[$i['pedido_id']] ?? 0) + 1; }
    }
}
function grupo_prazo(string $d, string $status): string {
    [$t, $c] = prazo($d, $status);
    return ['p-atrasado'=>'Atrasados','p-hoje'=>'Hoje','p-amanha'=>'Amanhã','p-semana'=>'Próximos 7 dias','p-combinar'=>'Data a combinar'][$c] ?? 'Mais adiante';
}
function link_pag(int $n): string { $g = $_GET; $g['pag'] = $n; return 'pedidos.php?' . http_build_query($g); }
$nomesVend = array_column(vendedores(), 'nome'); // garante a tabela de vendedores
foreach (q("SELECT DISTINCT vendedor FROM pedidos WHERE vendedor <> ''")->fetchAll(PDO::FETCH_COLUMN) as $n) { if (!in_array($n, $nomesVend, true)) { $nomesVend[] = $n; } }
sort($nomesVend, SORT_NATURAL | SORT_FLAG_CASE);
$cont = q("SELECT SUM(CASE WHEN data_entrega < ? THEN 1 ELSE 0 END) a, SUM(CASE WHEN data_entrega = ? THEN 1 ELSE 0 END) h, SUM(CASE WHEN data_entrega = ? THEN 1 ELSE 0 END) m, SUM(CASE WHEN data_entrega BETWEEN ? AND ? THEN 1 ELSE 0 END) s, SUM(CASE WHEN data_entrega > ? AND data_entrega <> ? THEN 1 ELSE 0 END) f, SUM(CASE WHEN data_entrega = ? THEN 1 ELSE 0 END) c FROM pedidos WHERE status NOT IN ('entregue','cancelado')", [$hoje, $hoje, $d1, $d2, $d7, $d7, A_COMBINAR, A_COMBINAR])->fetch();
$contagem = ['atrasado'=>(int)$cont['a'],'hoje'=>(int)$cont['h'],'amanha'=>(int)$cont['m'],'semana'=>(int)$cont['s'],'adiante'=>(int)$cont['f'],'combinar'=>(int)$cont['c']];
$classe = ['atrasado'=>'p-atrasado','hoje'=>'p-hoje','amanha'=>'p-amanha','semana'=>'p-semana','adiante'=>'p-mais','combinar'=>'p-combinar'];
$entregues = (int)q("SELECT COUNT(*) FROM pedidos WHERE status = 'entregue'")->fetchColumn();
$filtrado = $prio || $de || $ate || $status || $vend !== '' || $busca !== '';
if (!empty($_GET['ok'])): ?><p class="ok">Pedido #<?= (int)$_GET['ok'] ?> salvo.</p><?php endif;
if (!empty($_GET['excluido'])): ?><p class="ok">Pedido #<?= (int)$_GET['excluido'] ?> excluído.</p><?php endif;
if (!empty($_GET['cancelado'])): ?><p class="ok">Pedido #<?= (int)$_GET['cancelado'] ?> cancelado. Avise o depósito para devolver os itens ao estoque, se já foram separados.</p><?php endif;
if (!empty($_GET['naocancelado'])): ?><p class="erro">Não foi possível cancelar o pedido #<?= (int)$_GET['naocancelado'] ?>. Informe o motivo; só pedidos ainda não entregues podem ser cancelados.</p><?php endif;
if (!empty($_GET['bloqueado'])): ?><p class="erro">O pedido #<?= (int)$_GET['bloqueado'] ?> já foi separado ou entrou em rota e só o administrador pode excluí-lo.</p><?php endif; ?>
<form method="get" class="no-print card filtros"><div class="grid">
<div class="f-larga"><label for="busca">Buscar</label><input id="busca" type="search" name="busca" value="<?= h($busca) ?>" placeholder="Cliente, endereço, bairro ou nº"></div>
<div><label for="vendedor">Vendedor</label><select id="vendedor" name="vendedor" onchange="this.form.submit()"><option value="">Todos</option><?php foreach ($nomesVend as $nv): ?><option value="<?= h($nv) ?>" <?= $vend === $nv ? 'selected' : '' ?>><?= h($nv) ?></option><?php endforeach; ?></select></div>
<div><label for="status">Situação</label><select id="status" name="status" onchange="this.form.submit()"><option value="">Em aberto</option><option value="todos" <?= $status === 'todos' ? 'selected' : '' ?>>Todos (com entregues)</option>
<?php foreach (['a_combinar'=>'Data a combinar','aguardando'=>'Aguardando separação','separado'=>'Separado','em_rota'=>'Em rota','com_pendencia'=>'Com pendência','entregue'=>'Entregue','cancelado'=>'Cancelado'] as $k => $n): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></div>
<div><label for="de">Entrega de</label><div class="data-w<?= $de ? '' : ' sem-data' ?>"><input id="de" type="date" name="de" value="<?= h($de) ?>"><span class="ph" aria-hidden="true">Escolher data</span></div></div>
<div><label for="ate">até</label><div class="data-w<?= $ate ? '' : ' sem-data' ?>"><input id="ate" type="date" name="ate" value="<?= h($ate) ?>"><span class="ph" aria-hidden="true">Escolher data</span></div></div></div>
<div class="prio-barra" role="group" aria-label="Filtrar por prioridade"><?php foreach ($prios as $k => $nome): $on = $prio === $k;
$q2 = array_filter(['prio' => $on ? '' : $k, 'vendedor' => $vend, 'status' => $status, 'busca' => $busca, 'de' => $de, 'ate' => $ate]); ?>
<a class="prio <?= $classe[$k] ?><?= $on ? ' on' : '' ?>" href="<?= h('pedidos.php' . ($q2 ? '?' . http_build_query($q2) : '')) ?>"><i></i><?= h($nome) ?><b><?= $contagem[$k] ?></b></a><?php endforeach; ?><a class="prio p-entregue" href="historico.php" title="Ver o histórico de pedidos entregues"><i></i>Entregues<b><?= $entregues ?></b></a></div>
<div class="barra"><button class="peq">Filtrar</button><?php if ($filtrado): ?><a class="btn sec peq" href="pedidos.php">Limpar filtros</a><?php endif; ?>
<span class="nota sem-margem"><?= $total ?> <?= $total === 1 ? 'pedido' : 'pedidos' ?><?= $vend !== '' ? ' de ' . h($vend) : '' ?></span></div></form>
<?php if (!$ps): ?><div class="vazio"><p>Nenhum pedido encontrado.</p><?php if (in_array($u['perfil'], ['admin','balcao'], true)): ?><a class="btn" href="pedido_novo.php">+ Cadastrar pedido</a><?php endif; ?></div><?php endif; ?>
<?php if ($ps): ?>
<div class="cards compactos">
<?php foreach ($ps as $p): [$pz, $pzc] = prazo($p['data_entrega'], $p['status']); $its = $itens[$p['id']] ?? [];
?>
<div class="card ped-card <?= $pzc ?: 'p-mais' ?>">
<div class="pedido-cab"><span class="c-data"><b><?= data_br($p['data_entrega']) ?></b></span><?= stag($p['status']) ?></div>
<div class="linha-prazo"><?= $pz && $p['data_entrega'] !== A_COMBINAR ? '<em class="prazo ' . $pzc . '">' . h($pz) . '</em>' : ($p['data_entrega'] === A_COMBINAR ? '<em class="prazo p-combinar">Aguardando definição</em>' : '') ?></div>
<p class="ped-nome"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong></p>
<p class="meta"><?= h($p['bairro'] ?: 'Sem bairro') ?> · <b><?= h($p['vendedor']) ?></b></p>
<p class="resumo-it"><?= $its ? qtd_fmt($its[0]['quantidade']) . ' ' . h($its[0]['unidade']) . ' ' . h($its[0]['descricao']) : 'Sem itens' ?><?= count($its) > 1 ? ' <span class="mais-n">+' . (count($its) - 1) . '</span>' : '' ?></p>
<details class="mais"><summary>Detalhes e ações</summary>
<p class="meta"><?= h($p['endereco']) ?><?= $p['bairro'] ? ' – ' . h($p['bairro']) : '' ?><?= $p['referencia'] ? '<br>Ref.: ' . h($p['referencia']) : '' ?><?= $p['telefone'] ? '<br>Tel.: ' . h($p['telefone']) : '' ?></p>
<table class="itens"><?php foreach ($its as $i): ?><tr><td><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></td><td><?= h($i['descricao']) ?></td></tr><?php endforeach; ?></table>
<?php if ($p['observacao']): ?><p class="meta">Obs.: <?= h($p['observacao']) ?></p><?php endif; ?>
<?php $elegivel = $p['status'] === 'aguardando' && empty($sepCont[$p['id']]);
$pode_ed = in_array($u['perfil'], ['admin','balcao'], true) && $elegivel;
$pode_conf = in_array($p['status'], ['em_rota','com_pendencia'], true) && in_array($u['perfil'], ['admin','rota'], true);
$pode_canc = $u['perfil'] === 'admin' && in_array($p['status'], ['aguardando','separado','em_rota'], true);
$pode_exc_adm = $u['perfil'] === 'admin' && !$elegivel; ?>
<div class="acoes-card no-print"><a class="btn sec peq" href="pedido_ver.php?id=<?= $p['id'] ?>">Ver / 2ª via</a>
<?php if ($pode_conf): ?><a class="btn peq" href="entrega.php?id=<?= $p['id'] ?>">Conferir entrega</a><?php endif; ?>
<?php if ($pode_ed): ?><a class="btn sec peq" href="pedido_novo.php?id=<?= $p['id'] ?>"><?= $p['data_entrega'] === A_COMBINAR ? 'Definir data / editar' : 'Editar' ?></a>
<form method="post" action="pedido_excluir.php" onsubmit="return confirm('Excluir o pedido #<?= $p['id'] ?>? Não é possível desfazer.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="perigo peq">Excluir</button></form><?php endif; ?>
</div>
<?php if ($pode_canc || $pode_exc_adm): ?><details class="admin no-print"><summary>Opções do administrador</summary>
<?php if ($pode_canc): ?><form method="post" action="pedido_cancelar.php" onsubmit="return confirm('Cancelar o pedido #<?= $p['id'] ?>? Ele sairá da separação e da rota.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
<label for="m<?= $p['id'] ?>">Cancelar pedido (desistência) – motivo</label><input id="m<?= $p['id'] ?>" name="motivo" required maxlength="120" placeholder="Ex.: cliente desistiu da compra"><p><button class="sec peq">Cancelar pedido</button></p></form><?php endif; ?>
<?php if ($pode_exc_adm): ?><form method="post" action="pedido_excluir.php" onsubmit="return confirm('EXCLUIR DEFINITIVAMENTE o pedido #<?= $p['id'] ?>, com itens e pendências? Use para pedidos de teste. Não é possível desfazer.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="perigo peq">Excluir definitivamente</button></form><?php endif; ?>
</details><?php endif; ?></details></div>
<?php endforeach; ?></div>
<?php if ($paginas > 1):
$mostrar = array_unique(array_filter(array_merge([1, $paginas], range(max(1, $pag - 2), min($paginas, $pag + 2))), fn($n) => $n >= 1 && $n <= $paginas)); sort($mostrar); ?>
<nav class="paginas no-print" aria-label="Páginas de pedidos">
<?php if ($pag > 1): ?><a class="pg" href="<?= h(link_pag($pag - 1)) ?>" aria-label="Página anterior">‹</a><?php endif; ?>
<?php $ant = 0; foreach ($mostrar as $n): if ($n - $ant > 1): ?><span class="pg-gap">…</span><?php endif; $ant = $n; ?>
<?php if ($n === $pag): ?><span class="pg on" aria-current="page"><?= $n ?></span><?php else: ?><a class="pg" href="<?= h(link_pag($n)) ?>"><?= $n ?></a><?php endif; ?>
<?php endforeach; ?>
<?php if ($pag < $paginas): ?><a class="pg" href="<?= h(link_pag($pag + 1)) ?>" aria-label="Próxima página">›</a><?php endif; ?>
</nav>
<p class="nota centro">Página <?= $pag ?> de <?= $paginas ?> · mostrando <?= ($pag - 1) * $por + 1 ?> a <?= min($total, $pag * $por) ?> de <?= $total ?> pedidos</p>
<?php endif; ?>
<?php endif; ?>
<script>
document.querySelectorAll('.data-w').forEach(function(w){var i=w.querySelector('input');
 function at(){w.classList.toggle('sem-data',!i.value)}i.addEventListener('input',at);i.addEventListener('change',at);at();});
</script>
<?php rodape();
