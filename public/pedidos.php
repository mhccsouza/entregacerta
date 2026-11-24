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
$validos = ['aguardando','separado','em_rota','com_pendencia','entregue','cancelado','a_combinar'];
if (!in_array($status, $validos, true)) { $status = ''; }
$vend = trim((string)($_GET['vendedor'] ?? ''));
$busca = trim((string)($_GET['busca'] ?? ''));
$pag = max(1, (int)($_GET['pag'] ?? 1)); $por = 30;
$w = []; $pr = [];
if ($de)  { $w[] = 'p.data_entrega >= ?'; $pr[] = $de; }
if ($ate) { $w[] = 'p.data_entrega <= ?'; $pr[] = $ate; }
if ($status === 'a_combinar') { $w[] = "p.status NOT IN ('entregue','cancelado') AND p.data_entrega = ?"; $pr[] = A_COMBINAR; }
elseif ($status) { $w[] = 'p.status = ?'; $pr[] = $status; }
elseif (!$de && !$ate) { $w[] = "p.status <> 'cancelado' AND (p.data_entrega >= ? OR p.status <> 'entregue')"; $pr[] = $hoje; }
if ($vend !== '') { $w[] = 'p.vendedor = ?'; $pr[] = $vend; }
if ($busca !== '') {
    $w[] = '(c.nome LIKE ? OR c.endereco LIKE ? OR c.bairro LIKE ? OR p.id = ?)';
    $l = '%' . $busca . '%'; array_push($pr, $l, $l, $l, (int)ltrim($busca, '#'));
}
$onde = $w ? 'WHERE ' . implode(' AND ', $w) : '';
$total = (int)q("SELECT COUNT(*) FROM pedidos p JOIN clientes c ON c.id = p.cliente_id $onde", $pr)->fetchColumn();
$paginas = max(1, (int)ceil($total / $por)); $pag = min($pag, $paginas);
$ordem = in_array($status, ['entregue','cancelado'], true) ? 'p.data_entrega DESC, p.id DESC' : 'p.data_entrega, p.id';
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
$nomesVend = q('SELECT nome FROM vendedores UNION SELECT DISTINCT vendedor FROM pedidos ORDER BY 1')->fetchAll(PDO::FETCH_COLUMN);
$filtrado = $de || $ate || $status || $vend !== '' || $busca !== '';
if (!empty($_GET['ok'])): ?><p class="ok">Pedido #<?= (int)$_GET['ok'] ?> salvo.</p><?php endif;
if (!empty($_GET['excluido'])): ?><p class="ok">Pedido #<?= (int)$_GET['excluido'] ?> excluído.</p><?php endif;
if (!empty($_GET['cancelado'])): ?><p class="ok">Pedido #<?= (int)$_GET['cancelado'] ?> cancelado. Avise o depósito para devolver os itens ao estoque, se já foram separados.</p><?php endif;
if (!empty($_GET['naocancelado'])): ?><p class="erro">Não foi possível cancelar o pedido #<?= (int)$_GET['naocancelado'] ?>. Informe o motivo; só pedidos ainda não entregues podem ser cancelados.</p><?php endif;
if (!empty($_GET['bloqueado'])): ?><p class="erro">O pedido #<?= (int)$_GET['bloqueado'] ?> já foi separado ou entrou em rota e só o administrador pode excluí-lo.</p><?php endif; ?>
<form method="get" class="no-print card filtros"><div class="grid">
<div class="f-larga"><label for="busca" style="margin-top:0">Buscar</label><input id="busca" type="search" name="busca" value="<?= h($busca) ?>" placeholder="Cliente, endereço, bairro ou nº"></div>
<div><label for="vendedor" style="margin-top:0">Vendedor</label><select id="vendedor" name="vendedor" onchange="this.form.submit()"><option value="">Todos</option><?php foreach ($nomesVend as $nv): ?><option value="<?= h($nv) ?>" <?= $vend === $nv ? 'selected' : '' ?>><?= h($nv) ?></option><?php endforeach; ?></select></div>
<div><label for="status" style="margin-top:0">Situação</label><select id="status" name="status" onchange="this.form.submit()"><option value="">Em aberto</option>
<?php foreach (['a_combinar'=>'Data a combinar','aguardando'=>'Aguardando separação','separado'=>'Separado','em_rota'=>'Em rota','com_pendencia'=>'Com pendência','entregue'=>'Entregue','cancelado'=>'Cancelado'] as $k => $n): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></div>
<div><label for="de" style="margin-top:0">Entrega de</label><input id="de" type="date" name="de" value="<?= h($de) ?>"></div>
<div><label for="ate" style="margin-top:0">até</label><input id="ate" type="date" name="ate" value="<?= h($ate) ?>"></div></div>
<div class="chips"><?php $atalhos = ['Hoje' => [$hoje, $hoje], 'Amanhã' => [date('Y-m-d', strtotime('+1 day')), date('Y-m-d', strtotime('+1 day'))], 'Próximos 7 dias' => [$hoje, date('Y-m-d', strtotime('+7 day'))], 'Esta semana' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))]];
foreach ($atalhos as $nome => [$d1, $d2]): $on = $de === $d1 && $ate === $d2; ?><a class="chip<?= $on ? ' on' : '' ?>" href="<?= h('pedidos.php?' . http_build_query(array_filter(['de' => $d1, 'ate' => $d2, 'vendedor' => $vend, 'status' => $status, 'busca' => $busca]))) ?>"><?= h($nome) ?></a><?php endforeach; ?></div>
<div class="barra"><button class="peq">Filtrar</button><?php if ($filtrado): ?><a class="btn sec peq" href="pedidos.php">Limpar filtros</a><?php endif; ?>
<span class="nota" style="margin:0"><?= $total ?> <?= $total === 1 ? 'pedido' : 'pedidos' ?><?= $vend !== '' ? ' de ' . h($vend) : '' ?></span></div></form>
<?php if (!$ps): ?><div class="vazio"><p>Nenhum pedido encontrado.</p><?php if (in_array($u['perfil'], ['admin','balcao'], true)): ?><a class="btn" href="pedido_novo.php">+ Cadastrar pedido</a><?php endif; ?></div><?php endif; ?>
<?php if ($ps): $grupoAtual = null; $agrupar = !in_array($status, ['entregue','cancelado'], true) && !$de && !$ate; ?>
<div class="cards compactos">
<?php foreach ($ps as $p): [$pz, $pzc] = prazo($p['data_entrega'], $p['status']); $its = $itens[$p['id']] ?? [];
$g = $agrupar ? grupo_prazo($p['data_entrega'], $p['status']) : '';
if ($g !== $grupoAtual): $grupoAtual = $g; if ($g): ?><h2 class="grupo-tit <?= h($pzc ?: 'p-mais') ?>"><?= h($g) ?></h2><?php endif; endif; ?>
<div class="card ped-card <?= $pzc ?>">
<div class="pedido-cab"><span class="c-data"><b><?= data_br($p['data_entrega']) ?></b><?= $pz && $p['data_entrega'] !== A_COMBINAR ? ' <em class="prazo ' . $pzc . '">' . h($pz) . '</em>' : '' ?></span><?= stag($p['status']) ?></div>
<p class="ped-nome"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong></p>
<p class="meta"><?= h($p['bairro'] ?: 'Sem bairro') ?> · <b><?= h($p['vendedor']) ?></b></p>
<p class="resumo-it"><?= $its ? qtd_fmt($its[0]['quantidade']) . ' ' . h($its[0]['unidade']) . ' ' . h($its[0]['descricao']) : 'Sem itens' ?><?= count($its) > 1 ? ' <span class="mais-n">+' . (count($its) - 1) . '</span>' : '' ?></p>
<details class="mais"><summary>Detalhes e ações</summary>
<p class="meta"><?= h($p['endereco']) ?><?= $p['bairro'] ? ' – ' . h($p['bairro']) : '' ?><?= $p['referencia'] ? '<br>Ref.: ' . h($p['referencia']) : '' ?><?= $p['telefone'] ? '<br>Tel.: ' . h($p['telefone']) : '' ?></p>
<table class="itens"><?php foreach ($its as $i): ?><tr><td><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></td><td><?= h($i['descricao']) ?></td></tr><?php endforeach; ?></table>
<?php if ($p['observacao']): ?><p class="meta">Obs.: <?= h($p['observacao']) ?></p><?php endif; ?>
<?php $elegivel = $p['status'] === 'aguardando' && empty($sepCont[$p['id']]);
$pode_ed = in_array($u['perfil'], ['admin','balcao'], true) && $elegivel;
$pode_conf = in_array($p['status'], ['em_rota','com_pendencia'], true);
$pode_canc = $u['perfil'] === 'admin' && in_array($p['status'], ['aguardando','separado','em_rota'], true);
$pode_exc_adm = $u['perfil'] === 'admin' && !$elegivel; ?>
<?php if ($pode_ed || $pode_conf): ?><div class="acoes-card no-print">
<?php if ($pode_conf): ?><a class="btn peq" href="entrega.php?id=<?= $p['id'] ?>">Conferir entrega</a><?php endif; ?>
<?php if ($pode_ed): ?><a class="btn sec peq" href="pedido_novo.php?id=<?= $p['id'] ?>"><?= $p['data_entrega'] === A_COMBINAR ? 'Definir data / editar' : 'Editar' ?></a>
<form method="post" action="pedido_excluir.php" onsubmit="return confirm('Excluir o pedido #<?= $p['id'] ?>? Não é possível desfazer.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="perigo peq">Excluir</button></form><?php endif; ?>
</div><?php endif; ?>
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
<p class="nota" style="text-align:center">Página <?= $pag ?> de <?= $paginas ?> · mostrando <?= ($pag - 1) * $por + 1 ?> a <?= min($total, $pag * $por) ?> de <?= $total ?> pedidos</p>
<?php endif; ?>
<?php endif; rodape();
