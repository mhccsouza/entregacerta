<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
topo('Conferência da entrega', ['rota'], 'Confira os itens com o cliente antes de ele assinar o talão');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = q('SELECT p.*, c.nome, c.endereco, c.bairro FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.id = ?', [$id])->fetch();
if (!$p) { echo '<p class="erro">Pedido não encontrado.</p>'; rodape(); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $ok = array_map('intval', $_POST['ok'] ?? []);
    $falta = 0;
    foreach (q('SELECT id FROM itens_pedido WHERE pedido_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN) as $iid) {
        $entregue = in_array((int)$iid, $ok, true);
        q('UPDATE itens_pedido SET conferido = ? WHERE id = ?', [$entregue ? 1 : 0, $iid]);
        $existe = (int)q('SELECT COUNT(*) FROM pendencias WHERE item_id = ? AND resolvida = 0', [$iid])->fetchColumn();
        if (!$entregue) { $falta++; if (!$existe) { q('INSERT INTO pendencias (pedido_id, item_id, observacao) VALUES (?,?,?)', [$id, $iid, trim($_POST['obs'] ?? '')]); } }
        elseif ($existe) { q('UPDATE pendencias SET resolvida = 1 WHERE item_id = ?', [$iid]); }
    }
    q('UPDATE pedidos SET status = ? WHERE id = ?', [$falta ? 'com_pendencia' : 'entregue', $id]);
    header('Location: ' . ($falta ? 'pendencias.php' : 'rota.php?data=' . $p['data_entrega'])); exit;
}
$itens = q('SELECT * FROM itens_pedido WHERE pedido_id = ?', [$id])->fetchAll();
?>
<div class="card"><div class="pedido-cab"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong> <?= stag($p['status']) ?></div><p class="meta"><?= h($p['endereco']) ?> – <?= h($p['bairro']) ?></p></div>
<form method="post" class="card sep"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
<h2>Itens entregues</h2><p class="nota">Marque o que o cliente conferiu. O que ficar sem marca vira pendência.</p>
<div class="progresso" aria-hidden="true"><i></i></div><div class="prog-txt"></div>
<div class="checklist"><?php foreach ($itens as $i): ?><div class="check <?= $i['conferido'] ? 'feito' : '' ?>"><input type="checkbox" name="ok[]" value="<?= $i['id'] ?>" id="i<?= $i['id'] ?>" <?= $i['conferido']?'checked':'' ?>>
<span class="qtd"><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></span><label for="i<?= $i['id'] ?>" class="txt"><?= h($i['descricao']) ?></label></div><?php endforeach; ?></div>
<label for="obs">Observação sobre a pendência (opcional)</label><input id="obs" name="obs" placeholder="Ex.: faltou 1 saco, cliente aguarda reposição">
<div class="barra"><button>Registrar conferência</button><a class="btn sec" href="comprovante.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">Comprovante A4</a><a class="btn sec" href="rota.php?data=<?= h($p['data_entrega']) ?>">Voltar</a></div></form>
<script>
(function(){var c=document.querySelector('.sep'),cks=c.querySelectorAll('input[type=checkbox]'),bar=c.querySelector('.progresso i'),tx=c.querySelector('.prog-txt');
function at(){var n=0;cks.forEach(function(k){if(k.checked)n++;k.closest('.check').classList.toggle('feito',k.checked)});bar.style.width=(100*n/cks.length)+'%';tx.textContent=n+' de '+cks.length+' itens conferidos'}
c.addEventListener('change',at);at();})();
</script>
<?php rodape();
