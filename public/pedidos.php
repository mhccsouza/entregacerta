<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
$u = topo('Pedidos', [], 'Lista por prioridade de entrega. Toque em um pedido para ver os itens e as ações');
$hoje = date('Y-m-d');
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'] ?? '') ? $_GET['data'] : null;
$status = (string)($_GET['status'] ?? '');
$validos = ['aguardando','separado','em_rota','com_pendencia','entregue','cancelado','a_combinar'];
if (!in_array($status, $validos, true)) { $status = ''; }
$busca = trim((string)($_GET['busca'] ?? ''));
$pag = max(1, (int)($_GET['pag'] ?? 1)); $por = 25;
$w = []; $pr = [];
if ($data) { $w[] = 'p.data_entrega = ?'; $pr[] = $data; }
if ($status === 'a_combinar') { $w[] = "p.status NOT IN ('entregue','cancelado') AND p.data_entrega = ?"; $pr[] = A_COMBINAR; }
elseif ($status) { $w[] = 'p.status = ?'; $pr[] = $status; }
elseif (!$data) { $w[] = "p.status <> 'cancelado' AND (p.data_entrega >= ? OR p.status <> 'entregue')"; $pr[] = $hoje; }
if ($busca !== '') {
    $w[] = '(c.nome LIKE ? OR c.endereco LIKE ? OR c.bairro LIKE ? OR p.vendedor LIKE ? OR p.id = ?)';
    $l = '%' . $busca . '%'; array_push($pr, $l, $l, $l, $l, (int)ltrim($busca, '#'));
}
$onde = $w ? 'WHERE ' . implode(' AND ', $w) : '';
$total = (int)q("SELECT COUNT(*) FROM pedidos p JOIN clientes c ON c.id = p.cliente_id $onde", $pr)->fetchColumn();
$paginas = max(1, (int)ceil($total / $por)); $pag = min($pag, $paginas);
$ordem = in_array($status, ['entregue','cancelado'], true) ? 'p.data_entrega DESC, p.id DESC' : 'p.data_entrega, p.id';
$ps = q("SELECT p.*, c.nome, c.endereco, c.bairro, c.telefone FROM pedidos p JOIN clientes c ON c.id = p.cliente_id $onde ORDER BY $ordem LIMIT $por OFFSET " . (($pag - 1) * $por), $pr)->fetchAll();
// itens de todos os pedidos da pagina em UMA consulta (evita uma consulta por pedido)
$itens = []; $sepCont = [];
if ($ps) {
    $ids = array_map(fn($p) => (int)$p['id'], $ps);
    foreach (q('SELECT * FROM itens_pedido WHERE pedido_id IN (' . implode(',', $ids) . ') ORDER BY id')->fetchAll() as $i) {
        $itens[$i['pedido_id']][] = $i;
        if ($i['separado']) { $sepCont[$i['pedido_id']] = ($sepCont[$i['pedido_id']] ?? 0) + 1; }
    }
}
function link_pag(int $n): string { $g = $_GET; $g['pag'] = $n; return 'pedidos.php?' . http_build_query($g); }
if (!empty($_GET['ok'])): ?><p class="ok">Pedido #<?= (int)$_GET['ok'] ?> salvo.</p><?php endif;
if (!empty($_GET['excluido'])): ?><p class="ok">Pedido #<?= (int)$_GET['excluido'] ?> excluído.</p><?php endif;
if (!empty($_GET['cancelado'])): ?><p class="ok">Pedido #<?= (int)$_GET['cancelado'] ?> cancelado. Avise o depósito para devolver os itens ao estoque, se já foram separados.</p><?php endif;
if (!empty($_GET['naocancelado'])): ?><p class="erro">Não foi possível cancelar o pedido #<?= (int)$_GET['naocancelado'] ?>. Informe o motivo; só pedidos ainda não entregues podem ser cancelados.</p><?php endif;
if (!empty($_GET['bloqueado'])): ?><p class="erro">O pedido #<?= (int)$_GET['bloqueado'] ?> já foi separado ou entrou em rota e só o administrador pode excluí-lo.</p><?php endif; ?>
<form method="get" class="no-print card filtros"><div class="grid">
<div><label for="busca" style="margin-top:0">Buscar</label><input id="busca" type="search" name="busca" value="<?= h($busca) ?>" placeholder="Cliente, endereço, bairro, vendedor ou nº"></div>
<div><label for="status" style="margin-top:0">Situação</label><select id="status" name="status" onchange="this.form.submit()"><option value="">Em aberto e próximos</option>
<?php foreach (['a_combinar'=>'Data a combinar','aguardando'=>'Aguardando separação','separado'=>'Separado','em_rota'=>'Em rota','com_pendencia'=>'Com pendência','entregue'=>'Entregue','cancelado'=>'Cancelado'] as $k => $n): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></div>
<div><label for="data" style="margin-top:0">Data da entrega</label><input id="data" type="date" name="data" value="<?= h($data && $data !== A_COMBINAR ? $data : '') ?>" onchange="this.form.submit()"></div></div>
<div class="barra"><button class="peq">Filtrar</button><?php if ($data || $status || $busca !== ''): ?><a class="btn sec peq" href="pedidos.php">Limpar filtros</a><?php endif; ?>
<span class="nota" style="margin:0"><?= $total ?> <?= $total === 1 ? 'pedido' : 'pedidos' ?> · ordenados por prazo: atrasados, hoje, próximos dias, a combinar.</span></div></form>
<?php if (!$ps): ?><div class="vazio"><p>Nenhum pedido encontrado.</p><?php if (in_array($u['perfil'], ['admin','balcao'], true)): ?><a class="btn" href="pedido_novo.php">+ Cadastrar pedido</a><?php endif; ?></div><?php endif; ?>
<?php if ($ps): ?><div class="lista-ped"><div class="lista-cab" aria-hidden="true"><span>Entrega</span><span>Pedido</span><span>Bairro</span><span>Itens</span><span>Situação</span></div>
<?php foreach ($ps as $p): [$pz, $pzc] = prazo($p['data_entrega'], $p['status']); $its = $itens[$p['id']] ?? []; ?>
<details class="ped <?= $pzc ?>"><summary>
<span class="c-data"><b><?= data_br($p['data_entrega']) ?></b><?= $pz && $p['data_entrega'] !== A_COMBINAR ? '<em class="prazo ' . $pzc . '">' . h($pz) . '</em>' : '' ?></span>
<span class="c-nome"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong></span>
<span class="c-bairro"><?= h($p['bairro'] ?: '—') ?></span>
<span class="c-itens"><?= count($its) ?> <?= count($its) === 1 ? 'item' : 'itens' ?><?= ($sepCont[$p['id']] ?? 0) ? ' · ' . $sepCont[$p['id']] . ' sep.' : '' ?></span>
<span class="c-st"><?= stag($p['status']) ?></span></summary>
<div class="det"><p class="meta">Vendedor: <b><?= h($p['vendedor']) ?></b> · <?= h($p['endereco']) ?><?= $p['bairro'] ? ' – ' . h($p['bairro']) : '' ?><?= $p['telefone'] ? ' · Tel.: ' . h($p['telefone']) : '' ?></p>
<table class="itens"><?php foreach ($its as $i): ?><tr><td><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></td><td><?= h($i['descricao']) ?></td></tr><?php endforeach; ?></table>
<?php if ($p['observacao']): ?><p class="meta">Obs.: <?= h($p['observacao']) ?></p><?php endif; ?>
<?php $elegivel = $p['status'] === 'aguardando' && empty($sepCont[$p['id']]);
$pode_ed = in_array($u['perfil'], ['admin','balcao'], true) && $elegivel;
$pode_conf = in_array($p['status'], ['em_rota','com_pendencia'], true);
$pode_canc = $u['perfil'] === 'admin' && in_array($p['status'], ['aguardando','separado','em_rota'], true);
$pode_exc_adm = $u['perfil'] === 'admin' && !$elegivel; ?>
<?php if ($pode_ed || $pode_conf): ?><div class="acoes-card no-print">
<?php if ($pode_conf): ?><a class="btn peq" href="entrega.php?id=<?= $p['id'] ?>">Conferir entrega</a><?php endif; ?>
<?php if ($pode_ed): ?><a class="btn sec peq" href="pedido_novo.php?id=<?= $p['id'] ?>"><?= $p['data_entrega'] === A_COMBINAR ? 'Editar / definir data' : 'Editar' ?></a>
<form method="post" action="pedido_excluir.php" onsubmit="return confirm('Excluir o pedido #<?= $p['id'] ?>? Não é possível desfazer.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="perigo peq">Excluir</button></form><?php endif; ?>
</div><?php endif; ?>
<?php if ($pode_canc || $pode_exc_adm): ?><details class="admin no-print"><summary>Opções do administrador</summary>
<?php if ($pode_canc): ?><form method="post" action="pedido_cancelar.php" onsubmit="return confirm('Cancelar o pedido #<?= $p['id'] ?>? Ele sairá da separação e da rota.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
<label for="m<?= $p['id'] ?>">Cancelar pedido (desistência) – motivo</label><input id="m<?= $p['id'] ?>" name="motivo" required maxlength="120" placeholder="Ex.: cliente desistiu da compra"><p><button class="sec peq">Cancelar pedido</button></p></form><?php endif; ?>
<?php if ($pode_exc_adm): ?><form method="post" action="pedido_excluir.php" onsubmit="return confirm('EXCLUIR DEFINITIVAMENTE o pedido #<?= $p['id'] ?>, com itens e pendências? Use para pedidos de teste. Não é possível desfazer.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="perigo peq">Excluir definitivamente</button></form><?php endif; ?>
</details><?php endif; ?></div></details>
<?php endforeach; ?></div>
<?php if ($paginas > 1): ?><nav class="paginas no-print" aria-label="Páginas"><?php if ($pag > 1): ?><a class="btn sec peq" href="<?= h(link_pag($pag - 1)) ?>">Anteriores</a><?php endif; ?><span>Página <?= $pag ?> de <?= $paginas ?></span><?php if ($pag < $paginas): ?><a class="btn sec peq" href="<?= h(link_pag($pag + 1)) ?>">Próximos</a><?php endif; ?></nav><?php endif; ?>
<?php endif; rodape();
