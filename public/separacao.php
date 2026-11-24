<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
topo('Lista de separação', ['deposito'], 'Todos os pedidos para separar, do mais urgente ao menos urgente. Dá para adiantar os de outros dias');
$ehData = fn($x) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$x) === 1;
$hoje = date('Y-m-d'); $amanha = date('Y-m-d', strtotime('+1 day'));
$ver = (string)($_GET['ver'] ?? $_POST['ver'] ?? 'todos'); if (!in_array($ver, ['todos', 'hoje', 'amanha'], true)) { $ver = 'todos'; }
$dia = $ehData($_GET['data'] ?? '') ? $_GET['data'] : ''; // links antigos com uma data específica
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $marcados = array_map('intval', $_POST['sep'] ?? []);
    foreach (array_unique(array_map('intval', $_POST['ped'] ?? [])) as $pid) {
        if (!q("SELECT 1 FROM pedidos WHERE id = ? AND status IN ('aguardando','separado')", [$pid])->fetch()) { continue; }
        foreach (q('SELECT id FROM itens_pedido WHERE pedido_id = ?', [$pid])->fetchAll(PDO::FETCH_COLUMN) as $iid) {
            q('UPDATE itens_pedido SET separado = ? WHERE id = ?', [in_array((int)$iid, $marcados, true) ? 1 : 0, $iid]);
        }
        $falta = (int)q('SELECT COUNT(*) FROM itens_pedido WHERE pedido_id = ? AND separado = 0', [$pid])->fetchColumn();
        q('UPDATE pedidos SET status = ? WHERE id = ?', [$falta === 0 ? 'separado' : 'aguardando', $pid]);
    }
    $foco = (int)($_POST['foco'] ?? 0);
    header('Location: separacao.php?ver=' . $ver . '&salvo=1' . ($foco ? '#p' . $foco : '')); exit;
}
$base = "p.status IN ('aguardando','separado') AND p.rota_id IS NULL";
$todosN = (int)q("SELECT COUNT(*) FROM pedidos p WHERE $base")->fetchColumn();
$hojeN  = (int)q("SELECT COUNT(*) FROM pedidos p WHERE $base AND p.data_entrega <= ?", [$hoje])->fetchColumn();
$amanhaN = (int)q("SELECT COUNT(*) FROM pedidos p WHERE $base AND p.data_entrega = ?", [$amanha])->fetchColumn();
$w = $base; $pr = [];
if ($dia) { $w .= ' AND p.data_entrega = ?'; $pr[] = $dia; }
elseif ($ver === 'hoje') { $w .= ' AND p.data_entrega <= ?'; $pr[] = $hoje; }
elseif ($ver === 'amanha') { $w .= ' AND p.data_entrega = ?'; $pr[] = $amanha; }
// mais urgentes primeiro; "a combinar" por último
$ps = q("SELECT p.*, c.nome, c.bairro FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE $w ORDER BY CASE WHEN p.data_entrega = '" . A_COMBINAR . "' THEN 1 ELSE 0 END, p.data_entrega, p.id", $pr)->fetchAll();
$grupos = ['p-atrasado' => 'Atrasados', 'p-hoje' => 'Hoje', 'p-amanha' => 'Amanhã', 'p-semana' => 'Próximos 7 dias', 'p-mais' => 'Mais adiante', 'p-combinar' => 'Data a combinar'];
$porGrupo = [];
foreach ($ps as $p) { [$pz, $pzc] = prazo($p['data_entrega'], $p['status']); $porGrupo[$pzc ?: 'p-mais'][] = $p; }
if (!empty($_GET['salvo'])): ?><p class="ok" role="status">Separação salva.</p><?php endif; ?>
<nav class="sep-abas no-print" aria-label="Quais pedidos mostrar">
<a class="aba<?= $ver === 'todos' && !$dia ? ' on' : '' ?>" href="separacao.php">Todos <b><?= $todosN ?></b></a>
<a class="aba<?= $ver === 'hoje' && !$dia ? ' on' : '' ?>" href="separacao.php?ver=hoje">Hoje e atrasados <b><?= $hojeN ?></b></a>
<a class="aba<?= $ver === 'amanha' && !$dia ? ' on' : '' ?>" href="separacao.php?ver=amanha">Amanhã <b><?= $amanhaN ?></b></a>
<?php if ($dia): ?><a class="aba on" href="separacao.php?data=<?= h($dia) ?>"><?= data_br($dia) ?></a><?php endif; ?>
</nav>
<?php if (!$ps): ?><div class="vazio"><p>Nenhum pedido para separar aqui.<?= $ver !== 'todos' || $dia ? ' Veja a aba "Todos".' : ' Todos os pedidos já foram separados.' ?></p><a class="btn sec" href="separacao.php">Ver todos</a></div><?php else: ?>
<form method="post"><?= csrf_campo() ?><input type="hidden" name="ver" value="<?= h($ver) ?>">
<?php foreach ($grupos as $cls => $titulo): if (empty($porGrupo[$cls])) { continue; } ?>
<h2 class="grupo-tit <?= $cls ?>"><?= h($titulo) ?> <span class="nota sem-margem">· <?= count($porGrupo[$cls]) ?> <?= count($porGrupo[$cls]) === 1 ? 'pedido' : 'pedidos' ?></span></h2>
<div class="cards"><?php foreach ($porGrupo[$cls] as $p): [$pz] = prazo($p['data_entrega'], $p['status']);
$its = q('SELECT * FROM itens_pedido WHERE pedido_id = ?', [$p['id']])->fetchAll();
$entrega = '<b>Entrega: ' . data_br($p['data_entrega']) . '</b>' . ($pz && $p['data_entrega'] !== A_COMBINAR ? ' (' . h($pz) . ')' : '') . ' · ' . h($p['bairro'] ?: 'Sem bairro'); ?>
<div class="sep-item" id="p<?= $p['id'] ?>">
<div class="card sep-resumo" role="button" tabindex="0" aria-haspopup="dialog" aria-label="Separar o pedido <?= $p['id'] ?>, <?= h($p['nome']) ?>">
<div class="pedido-cab"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong> <?= stag($p['status']) ?></div>
<p class="meta"><?= $entrega ?></p>
<div class="progresso" aria-hidden="true"><i></i></div><div class="prog-txt"></div>
<span class="abrir">Toque para separar ›</span></div>
<div class="sep-foco" role="dialog" aria-modal="true" aria-label="Separar o pedido <?= $p['id'] ?>">
<h2>Pedido #<?= $p['id'] ?> · <?= h($p['nome']) ?></h2>
<p class="meta"><?= $entrega ?></p>
<?php if ($p['observacao']): ?><p class="meta">Obs.: <?= h($p['observacao']) ?></p><?php endif; ?>
<div class="progresso" aria-hidden="true"><i></i></div><div class="prog-txt"></div>
<input type="hidden" name="ped[]" value="<?= $p['id'] ?>">
<div class="checklist"><?php foreach ($its as $i): ?>
<div class="check <?= $i['separado'] ? 'feito' : '' ?>"><input type="checkbox" name="sep[]" value="<?= $i['id'] ?>" id="i<?= $i['id'] ?>" <?= $i['separado'] ? 'checked' : '' ?>>
<span class="qtd"><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></span><label for="i<?= $i['id'] ?>" class="txt"><?= h($i['descricao']) ?></label></div><?php endforeach; ?></div>
<div class="foco-acoes no-print">
<button type="submit" name="foco" value="<?= $p['id'] ?>">Salvar e voltar à lista</button>
<button type="button" class="sec todos">Marcar todos</button>
<button type="button" class="sec fechar">Fechar sem salvar</button></div>
</div></div>
<?php endforeach; ?></div>
<?php endforeach; ?>
<div class="sep-fundo no-print" id="sep-fundo"></div>
<div class="barra barra-fixa no-print"><button>Salvar separação</button><a class="btn sec" href="separacao_imprimir.php?ver=<?= h($_GET['ver'] ?? 'todos') ?>" target="_blank" rel="noopener">Imprimir cupons (bobina)</a><span class="nao-salvo" id="nao-salvo" hidden>Há marcações ainda não salvas</span></div></form>
<script>
(function(){
 var fundo=document.getElementById('sep-fundo'),aviso=document.getElementById('nao-salvo'),aberto=null;
 function abre(it){aberto=it;it.querySelector('.sep-foco').classList.add('on');fundo.classList.add('on');document.body.classList.add('sem-rolagem');
  var k=it.querySelector('.sep-foco input[type=checkbox]');(k||it.querySelector('.sep-foco button')).focus()}
 function fecha(){if(!aberto)return;var it=aberto;it.querySelector('.sep-foco').classList.remove('on');fundo.classList.remove('on');document.body.classList.remove('sem-rolagem');aberto=null;it.querySelector('.sep-resumo').focus()}
 fundo.addEventListener('click',fecha);
 document.addEventListener('keydown',function(e){if(e.key==='Escape')fecha()});
 document.querySelectorAll('.sep-item').forEach(function(it){
  var cks=it.querySelectorAll('.sep-foco input[type=checkbox]'),res=it.querySelector('.sep-resumo');
  function at(){var n=0;cks.forEach(function(k){if(k.checked)n++;k.closest('.check').classList.toggle('feito',k.checked)});
   it.querySelectorAll('.progresso i').forEach(function(b){b.style.width=(100*n/cks.length)+'%'});
   it.querySelectorAll('.prog-txt').forEach(function(t){t.textContent=n+' de '+cks.length+' itens separados'});
   res.classList.toggle('completo',n===cks.length&&n>0)}
  res.addEventListener('click',function(){abre(it)});
  res.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();abre(it)}});
  it.addEventListener('change',function(){at();aviso.hidden=false});
  it.querySelector('.todos').onclick=function(){var t=Array.prototype.some.call(cks,function(k){return !k.checked});cks.forEach(function(k){k.checked=t});at();aviso.hidden=false};
  it.querySelector('.fechar').onclick=fecha;
  it.querySelectorAll('.check').forEach(function(r){r.addEventListener('click',function(e){if(e.target.tagName!=='INPUT'&&e.target.tagName!=='LABEL'){var k=r.querySelector('input');k.checked=!k.checked;at();aviso.hidden=false}})});
  at();
 });
})();
</script>
<?php endif; rodape();
