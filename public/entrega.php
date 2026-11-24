<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
topo('Conferência da entrega', ['rota'], 'Confira os itens com o cliente antes de ele assinar o talão');
pendencia_qtd_garante(); divergencia_garante(); fotos_garante();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = q('SELECT p.*, c.nome, c.endereco, c.bairro FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.id = ?', [$id])->fetch();
if (!$p) { echo '<p class="erro">Pedido não encontrado.</p>'; rodape(); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $ok = array_map('intval', $_POST['ok'] ?? []);
    $qtds = (array)($_POST['qtd'] ?? []);
    $obss = (array)($_POST['obs'] ?? []);
    $reais = (array)($_POST['real'] ?? []);
    $dobs = (array)($_POST['dobs'] ?? []);
    $falta = 0;
    foreach (q('SELECT id, quantidade FROM itens_pedido WHERE pedido_id = ?', [$id])->fetchAll() as $it) {
        $iid = (int)$it['id']; $total = (float)$it['quantidade'];
        // marcado = entregue por completo; sem marca = vale a quantidade informada (vazio = nada entregue)
        $entregue = in_array($iid, $ok, true) ? $total : max(0.0, min($total, (float)str_replace(',', '.', trim((string)($qtds[$iid] ?? '0')))));
        $faltou = round($total - $entregue, 2);
        $obs = mb_substr(trim((string)($obss[$iid] ?? '')), 0, 250);
        q('UPDATE itens_pedido SET conferido = ? WHERE id = ?', [$faltou <= 0 ? 1 : 0, $iid]);
        // divergencia: a quantidade cadastrada pode estar errada (o talão do cliente diz outra); sinaliza para verificação
        $realTxt = trim((string)($reais[$iid] ?? ''));
        $real = $realTxt === '' ? null : round(max(0.0, (float)str_replace(',', '.', $realTxt)), 2);
        $dab = q('SELECT id FROM divergencias WHERE item_id = ? AND resolvida = 0', [$iid])->fetchColumn();
        if ($real !== null && abs($real - $total) > 0.001) {
            $dob = mb_substr(trim((string)($dobs[$iid] ?? '')), 0, 250);
            if ($dab) { q('UPDATE divergencias SET qtd_pedido = ?, qtd_real = ?, observacao = ? WHERE id = ?', [$total, $real, $dob, $dab]); }
            else { q('INSERT INTO divergencias (pedido_id, item_id, qtd_pedido, qtd_real, observacao) VALUES (?,?,?,?,?)', [$id, $iid, $total, $real, $dob]); }
        } elseif ($dab) { q('DELETE FROM divergencias WHERE id = ?', [$dab]); }
        $aberta = q('SELECT id FROM pendencias WHERE item_id = ? AND resolvida = 0', [$iid])->fetchColumn();
        if ($faltou > 0) {
            $falta++;
            if ($aberta) { q('UPDATE pendencias SET qtd_falta = ?, observacao = ? WHERE id = ?', [$faltou, $obs, $aberta]); }
            else { q('INSERT INTO pendencias (pedido_id, item_id, observacao, qtd_falta) VALUES (?,?,?,?)', [$id, $iid, $obs, $faltou]); }
        } elseif ($aberta) { q('UPDATE pendencias SET resolvida = 1 WHERE item_id = ?', [$iid]); }
    }
    q('UPDATE pedidos SET status = ? WHERE id = ?', [$falta ? 'com_pendencia' : 'entregue', $id]);
    $erroFoto = [];
    $fs = $_FILES['fotos'] ?? null;
    if ($fs && is_array($fs['name'])) {
        foreach (array_keys($fs['name']) as $k) {
            $e = foto_salvar($id, ['name' => $fs['name'][$k], 'tmp_name' => $fs['tmp_name'][$k], 'error' => $fs['error'][$k], 'size' => $fs['size'][$k]], (int)(usuario()['id'] ?? 0) ?: null);
            if ($e) { $erroFoto[] = $e; }
        }
    }
    if ($erroFoto) { header('Location: entrega.php?id=' . $id . '&fe=' . urlencode($erroFoto[0])); exit; }
    header('Location: ' . ($falta ? 'pendencias.php' : 'rota.php?data=' . $p['data_entrega'])); exit;
}
$itens = q('SELECT * FROM itens_pedido WHERE pedido_id = ?', [$id])->fetchAll();
$divs = []; foreach (q('SELECT * FROM divergencias WHERE pedido_id = ? AND resolvida = 0', [$id])->fetchAll() as $x) { $divs[$x['item_id']] = $x; }
$fotos = q('SELECT id FROM fotos_pedido WHERE pedido_id = ? ORDER BY id', [$id])->fetchAll(PDO::FETCH_COLUMN);
$abertas = []; foreach (q('SELECT * FROM pendencias WHERE pedido_id = ? AND resolvida = 0', [$id])->fetchAll() as $x) { $abertas[$x['item_id']] = $x; }
?>
<div class="card"><div class="pedido-cab"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong> <?= stag($p['status']) ?></div><p class="meta"><?= h($p['endereco']) ?> – <?= h($p['bairro']) ?></p></div>
<?php if (!empty($_GET['fe'])): ?><p class="erro" role="alert">A conferência foi registrada, mas a foto não foi salva: <?= h($_GET['fe']) ?></p><?php endif; ?>
<form method="post" class="card sep" enctype="multipart/form-data"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
<h2>Itens entregues</h2><p class="nota">Marque o que o cliente recebeu por completo. Se faltou parte ou nada foi entregue, abra <strong>Parcial ou não entregue</strong> e informe a quantidade entregue.</p>
<div class="progresso" aria-hidden="true"><i></i></div><div class="prog-txt"></div>
<div class="checklist"><?php foreach ($itens as $i): $a = $abertas[$i['id']] ?? null;
    $falt = $a ? ($a['qtd_falta'] !== null ? (float)$a['qtd_falta'] : (float)$i['quantidade']) : 0;
    $ent = $a ? max(0, (float)$i['quantidade'] - $falt) : 0; // sem marca e sem pendência = nada entregue ainda ?>
<div class="item-conf" data-id="<?= $i['id'] ?>"><div class="check <?= $i['conferido'] ? 'feito' : '' ?>"><input type="checkbox" name="ok[]" value="<?= $i['id'] ?>" id="i<?= $i['id'] ?>" <?= $i['conferido']?'checked':'' ?>>
<span class="qtd"><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></span><label for="i<?= $i['id'] ?>" class="txt"><?= h($i['descricao']) ?></label></div>
<details class="parcial" <?= $a ? 'open' : '' ?>><summary>Parcial ou não entregue<?= $a ? ' · falta ' . qtd_fmt($falt) . ' ' . h($i['unidade']) : '' ?></summary>
<div class="parcial-campos"><div><label for="q<?= $i['id'] ?>">Quantidade entregue (de <?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?>)</label><input type="text" inputmode="decimal" id="q<?= $i['id'] ?>" name="qtd[<?= $i['id'] ?>]" value="<?= h(qtd_fmt($ent)) ?>" autocomplete="off"></div>
<div><label for="o<?= $i['id'] ?>">Motivo ou observação (opcional)</label><input id="o<?= $i['id'] ?>" name="obs[<?= $i['id'] ?>]" maxlength="250" value="<?= h($a['observacao'] ?? '') ?>" placeholder="Ex.: faltou 5 sacos, cliente aguarda reposição"></div></div></details>
<?php $dv = $divs[$i['id']] ?? null; ?><details class="diverge" <?= $dv ? 'open' : '' ?>><summary>Quantidade do pedido está diferente?<?= $dv ? ' · sinalizada: ' . qtd_fmt($dv['qtd_real']) . ' ' . h($i['unidade']) : '' ?></summary>
<div class="parcial-campos"><div><label for="r<?= $i['id'] ?>">Quantidade correta (cadastrado: <?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?>)</label><input type="text" inputmode="decimal" id="r<?= $i['id'] ?>" name="real[<?= $i['id'] ?>]" value="<?= $dv ? h(qtd_fmt($dv['qtd_real'])) : '' ?>" placeholder="Deixe em branco se está certo" autocomplete="off"></div>
<div><label for="d<?= $i['id'] ?>">O que foi percebido (opcional)</label><input id="d<?= $i['id'] ?>" name="dobs[<?= $i['id'] ?>]" maxlength="250" value="<?= h($dv['observacao'] ?? '') ?>" placeholder="Ex.: talão do cliente diz 25, foi cadastrado 20"></div></div>
<p class="nota">Isso não muda o pedido: avisa o atendimento para verificar e corrigir.</p></details></div>
<?php endforeach; ?></div>
<h3 class="foto-tit">Foto do talão assinado</h3><p class="nota">Opcional. Tire uma foto do talão com a assinatura do cliente (até 6 por pedido).</p>
<?php if ($fotos): ?><div class="fotos"><?php foreach ($fotos as $fid): ?><a href="foto.php?id=<?= (int)$fid ?>" target="_blank" rel="noopener"><img src="foto.php?id=<?= (int)$fid ?>" alt="Foto do talão" loading="lazy"></a><?php endforeach; ?></div><?php endif; ?>
<input type="file" name="fotos[]" accept="image/*" multiple aria-label="Adicionar foto do talão">
<div class="barra"><button>Registrar conferência</button><a class="btn sec" href="comprovante.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">Comprovante A4</a><a class="btn sec" href="rota.php?data=<?= h($p['data_entrega']) ?>">Voltar</a></div></form>
<script>
(function(){var c=document.querySelector('.sep'),cks=c.querySelectorAll('input[type=checkbox]'),bar=c.querySelector('.progresso i'),tx=c.querySelector('.prog-txt');
function at(){var n=0;cks.forEach(function(k){var it=k.closest('.item-conf');if(k.checked)n++;k.closest('.check').classList.toggle('feito',k.checked);it.querySelector('.parcial').hidden=k.checked});bar.style.width=(100*n/cks.length)+'%';tx.textContent=n+' de '+cks.length+' itens conferidos por completo'}
c.addEventListener('change',at);at();})();
</script>
<?php rodape();
