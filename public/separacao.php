<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
topo('Lista de separação', ['deposito'], 'Marque o que já foi separado no estoque');
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'] ?? $_POST['data'] ?? '') ? ($_GET['data'] ?? $_POST['data']) : data_padrao("status IN ('aguardando','separado') AND rota_id IS NULL");
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
if (!empty($_GET['salvo'])): ?><p class="ok" role="status">Separação salva.</p><?php endif; ?>
<form method="get" class="no-print card"><label for="data" style="margin-top:0">Data da entrega</label><input id="data" type="date" name="data" value="<?= h($data) ?>" onchange="this.form.submit()"></form>
<?php if (!$ps): ?><div class="vazio"><p>Nenhum pedido para separar nesta data.</p><a class="btn sec" href="pedidos.php">Ver pedidos</a></div><?php else: ?>
<form method="post"><?= csrf_campo() ?><input type="hidden" name="data" value="<?= h($data) ?>">
<div class="cards"><?php foreach ($ps as $p): ?><div class="card sep"><div class="pedido-cab"><strong>Pedido #<?= $p['id'] ?> · <?= h($p['nome']) ?></strong> <?= stag($p['status']) ?></div>
<p class="meta"><?= h($p['bairro']) ?></p>
<div class="progresso" aria-hidden="true"><i></i></div><div class="prog-txt"></div>
<div class="checklist"><?php foreach (q('SELECT * FROM itens_pedido WHERE pedido_id = ?', [$p['id']])->fetchAll() as $i): ?>
<div class="check <?= $i['separado'] ? 'feito' : '' ?>"><input type="checkbox" name="sep[]" value="<?= $i['id'] ?>" id="i<?= $i['id'] ?>" <?= $i['separado']?'checked':'' ?>>
<span class="qtd"><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></span><label for="i<?= $i['id'] ?>" class="txt"><?= h($i['descricao']) ?></label></div><?php endforeach; ?></div>
<?php if ($p['observacao']): ?><p class="meta">Obs.: <?= h($p['observacao']) ?></p><?php endif; ?>
<button type="button" class="sec peq no-print todos">Marcar todos</button></div><?php endforeach; ?></div>
<div class="barra barra-fixa no-print"><button>Salvar separação</button><button type="button" class="sec" onclick="window.print()">Imprimir lista</button></div></form>
<script>
document.querySelectorAll('.sep').forEach(function(c){
 var cks=c.querySelectorAll('input[type=checkbox]'),bar=c.querySelector('.progresso i'),tx=c.querySelector('.prog-txt');
 function at(){var n=0;cks.forEach(function(k){if(k.checked)n++;k.closest('.check').classList.toggle('feito',k.checked)});
  bar.style.width=(100*n/cks.length)+'%';tx.textContent=n+' de '+cks.length+' itens separados'}
 c.addEventListener('change',at);
 c.querySelector('.todos').onclick=function(){var t=Array.prototype.some.call(cks,function(k){return !k.checked});cks.forEach(function(k){k.checked=t});at()};
 c.querySelectorAll('.check').forEach(function(r){r.addEventListener('click',function(e){if(e.target.tagName!=='INPUT'&&e.target.tagName!=='LABEL'){var k=r.querySelector('input');k.checked=!k.checked;at()}})});
 at();
});
</script>
<?php endif; rodape();
