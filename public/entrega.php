<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
topo('Conferência da entrega', ['rota'], 'Confira os itens com o cliente antes de ele assinar o talão');
pendencia_qtd_garante();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = q('SELECT p.*, c.nome, c.endereco, c.bairro FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.id = ?', [$id])->fetch();
if (!$p) { echo '<p class="erro">Pedido não encontrado.</p>'; rodape(); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $ok = array_map('intval', $_POST['ok'] ?? []);
    $qtds = (array)($_POST['qtd'] ?? []);
    $obss = (array)($_POST['obs'] ?? []);
    $falta = 0;
    foreach (q('SELECT id, quantidade FROM itens_pedido WHERE pedido_id = ?', [$id])->fetchAll() as $it) {
        $iid = (int)$it['id']; $total = (float)$it['quantidade'];
        // marcado = entregue por completo; sem marca = vale a quantidade informada (vazio = nada entregue)
        $entregue = in_array($iid, $ok, true) ? $total : max(0.0, min($total, (float)str_replace(',', '.', trim((string)($qtds[$iid] ?? '0')))));
        $faltou = round($total - $entregue, 2);
        $obs = mb_substr(trim((string)($obss[$iid] ?? '')), 0, 250);
        q('UPDATE itens_pedido SET conferido = ? WHERE id = ?', [$faltou <= 0 ? 1 : 0, $iid]);
        $aberta = q('SELECT id FROM pendencias WHERE item_id = ? AND resolvida = 0', [$iid])->fetchColumn();
        if ($faltou > 0) {
            $falta++;
            if ($aberta) { q('UPDATE pendencias SET qtd_falta = ?, observacao = ? WHERE id = ?', [$faltou, $obs, $aberta]); }
            else { q('INSERT INTO pendencias (pedido_id, item_id, observacao, qtd_falta) VALUES (?,?,?,?)', [$id, $iid, $obs, $faltou]); }
        } elseif ($aberta) { q('UPDATE pendencias SET resolvida = 1 WHERE item_id = ?', [$iid]); }
    }
    q('UPDATE pedidos SET status = ? WHERE id = ?', [$falta ? 'com_pendencia' : 'entregue', $id]);
    header('Location: ' . ($falta ? 'pendencias.php' : 'rota.php?data=' . $p['data_entrega'])); exit;
}
$itens = q('SELECT * FROM itens_pedido WHERE pedido_id = ?', [$id])->fetchAll();
$abertas = []; foreach (q('SELECT * FROM pendencias WHERE pedido_id = ? AND resolvida = 0', [$id])->fetchAll() as $x) { $abertas[$x['item_id']] = $x; }
?>
<div class="card"><div class="pedido-cab"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong> <?= stag($p['status']) ?></div><p class="meta"><?= h($p['endereco']) ?> – <?= h($p['bairro']) ?></p></div>
<form method="post" class="card sep"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
<h2>Itens entregues</h2><p class="nota">Marque o que o cliente recebeu por completo. Se faltou parte ou nada foi entregue, abra <strong>Parcial ou não entregue</strong> e informe a quantidade entregue.</p>
<div class="progresso" aria-hidden="true"><i></i></div><div class="prog-txt"></div>
<div class="checklist"><?php foreach ($itens as $i): $a = $abertas[$i['id']] ?? null;
    $falt = $a ? ($a['qtd_falta'] !== null ? (float)$a['qtd_falta'] : (float)$i['quantidade']) : 0;
    $ent = $a ? max(0, (float)$i['quantidade'] - $falt) : 0; // sem marca e sem pendência = nada entregue ainda ?>
<div class="item-conf" data-id="<?= $i['id'] ?>"><div class="check <?= $i['conferido'] ? 'feito' : '' ?>"><input type="checkbox" name="ok[]" value="<?= $i['id'] ?>" id="i<?= $i['id'] ?>" <?= $i['conferido']?'checked':'' ?>>
<span class="qtd"><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></span><label for="i<?= $i['id'] ?>" class="txt"><?= h($i['descricao']) ?></label></div>
<details class="parcial" <?= $a ? 'open' : '' ?>><summary>Parcial ou não entregue<?= $a ? ' · falta ' . qtd_fmt($falt) . ' ' . h($i['unidade']) : '' ?></summary>
<div class="parcial-campos"><div><label for="q<?= $i['id'] ?>">Quantidade entregue (de <?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?>)</label><input type="text" inputmode="decimal" id="q<?= $i['id'] ?>" name="qtd[<?= $i['id'] ?>]" value="<?= h(qtd_fmt($ent)) ?>" autocomplete="off"></div>
<div><label for="o<?= $i['id'] ?>">Motivo ou observação (opcional)</label><input id="o<?= $i['id'] ?>" name="obs[<?= $i['id'] ?>]" maxlength="250" value="<?= h($a['observacao'] ?? '') ?>" placeholder="Ex.: faltou 5 sacos, cliente aguarda reposição"></div></div></details></div>
<?php endforeach; ?></div>
<div class="barra"><button>Registrar conferência</button><a class="btn sec" href="comprovante.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">Comprovante A4</a><a class="btn sec" href="rota.php?data=<?= h($p['data_entrega']) ?>">Voltar</a></div></form>
<script>
(function(){var c=document.querySelector('.sep'),cks=c.querySelectorAll('input[type=checkbox]'),bar=c.querySelector('.progresso i'),tx=c.querySelector('.prog-txt');
function at(){var n=0;cks.forEach(function(k){var it=k.closest('.item-conf');if(k.checked)n++;k.closest('.check').classList.toggle('feito',k.checked);it.querySelector('.parcial').hidden=k.checked});bar.style.width=(100*n/cks.length)+'%';tx.textContent=n+' de '+cks.length+' itens conferidos por completo'}
c.addEventListener('change',at);at();})();
</script>
<?php rodape();
