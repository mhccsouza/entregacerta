<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
topo('Conferência da entrega', ['rota'], 'Para cada item, diga o que foi entregue ao cliente');
pendencia_qtd_garante(); divergencia_garante(); fotos_garante();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = q('SELECT p.*, c.nome, c.endereco, c.bairro FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.id = ?', [$id])->fetch();
if (!$p) { echo '<p class="erro">Pedido não encontrado.</p>'; rodape(); exit; }
$num = fn($v) => round(max(0.0, (float)str_replace(',', '.', trim((string)$v))), 2);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $modos = (array)($_POST['modo'] ?? []); $qtds = (array)($_POST['qtd'] ?? []); $obss = (array)($_POST['obs'] ?? []); $reais = (array)($_POST['real'] ?? []);
    $falta = 0;
    foreach (q('SELECT id, quantidade FROM itens_pedido WHERE pedido_id = ?', [$id])->fetchAll() as $it) {
        $iid = (int)$it['id']; $total = (float)$it['quantidade'];
        // quantidade do talão (se o cliente tem outra): vira a base da conferência e sinaliza divergência
        $realTxt = trim((string)($reais[$iid] ?? ''));
        $real = $realTxt === '' ? null : $num($realTxt);
        $temDiv = $real !== null && $real > 0 && abs($real - $total) > 0.001;
        $base = $temDiv ? $real : $total;
        $modo = (string)($modos[$iid] ?? 'nada');
        $entregue = $modo === 'todo' ? $base : ($modo === 'parte' ? min($base, $num($qtds[$iid] ?? 0)) : 0.0);
        $faltou = round($base - $entregue, 2);
        $obs = mb_substr(trim((string)($obss[$iid] ?? '')), 0, 250);
        q('UPDATE itens_pedido SET conferido = ? WHERE id = ?', [$faltou <= 0 ? 1 : 0, $iid]);
        $dab = q('SELECT id FROM divergencias WHERE item_id = ? AND resolvida = 0', [$iid])->fetchColumn();
        if ($temDiv) {
            if ($dab) { q('UPDATE divergencias SET qtd_pedido = ?, qtd_real = ?, observacao = ? WHERE id = ?', [$total, $real, $obs, $dab]); }
            else { q('INSERT INTO divergencias (pedido_id, item_id, qtd_pedido, qtd_real, observacao) VALUES (?,?,?,?,?)', [$id, $iid, $total, $real, $obs]); }
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
$itens = q('SELECT * FROM itens_pedido WHERE pedido_id = ? ORDER BY id', [$id])->fetchAll();
$divs = []; foreach (q('SELECT * FROM divergencias WHERE pedido_id = ? AND resolvida = 0', [$id])->fetchAll() as $x) { $divs[$x['item_id']] = $x; }
$abertas = []; foreach (q('SELECT * FROM pendencias WHERE pedido_id = ? AND resolvida = 0', [$id])->fetchAll() as $x) { $abertas[$x['item_id']] = $x; }
$fotos = q('SELECT id FROM fotos_pedido WHERE pedido_id = ? ORDER BY id', [$id])->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="card"><div class="pedido-cab"><strong>#<?= $p['id'] ?> · <?= h($p['nome']) ?></strong> <?= stag($p['status']) ?></div><p class="meta"><?= h($p['endereco']) ?> – <?= h($p['bairro']) ?></p></div>
<?php if (!empty($_GET['fe'])): ?><p class="erro" role="alert">A conferência foi registrada, mas a foto não foi salva: <?= h($_GET['fe']) ?></p><?php endif; ?>
<form method="post" class="card sep" enctype="multipart/form-data" id="conf"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
<h2>O que foi entregue?</h2>
<p class="nota">Escolha uma opção em cada item. <strong>Entregue</strong>: o cliente recebeu tudo. <strong>Parte</strong>: faltou alguma quantidade. <strong>Não entregue</strong>: nada foi entregue. O que faltar fica como pendência.</p>
<div class="checklist"><?php foreach ($itens as $i):
    $iid = (int)$i['id']; $a = $abertas[$iid] ?? null; $dv = $divs[$iid] ?? null;
    $base = $dv ? (float)$dv['qtd_real'] : (float)$i['quantidade'];
    $modo = ''; $qe = '';
    if ($a) { $f = $a['qtd_falta'] !== null ? (float)$a['qtd_falta'] : $base; if ($f >= $base) { $modo = 'nada'; } else { $modo = 'parte'; $qe = qtd_fmt($base - $f); } }
    elseif ($i['conferido']) { $modo = 'todo'; }
?>
<div class="item-conf" data-total="<?= h((string)(float)$i['quantidade']) ?>" data-desc="<?= h($i['descricao']) ?>" data-un="<?= h($i['unidade']) ?>">
<div class="ic-cab"><span class="qtd"><?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?></span><span class="txt"><?= h($i['descricao']) ?></span></div>
<p class="ic-base" hidden></p>
<div class="opcoes" role="radiogroup" aria-label="Situação de <?= h($i['descricao']) ?>">
<label class="op op-todo"><input type="radio" name="modo[<?= $iid ?>]" value="todo" required <?= $modo === 'todo' ? 'checked' : '' ?>><span>Entregue</span></label>
<label class="op op-parte"><input type="radio" name="modo[<?= $iid ?>]" value="parte" required <?= $modo === 'parte' ? 'checked' : '' ?>><span>Parte</span></label>
<label class="op op-nada"><input type="radio" name="modo[<?= $iid ?>]" value="nada" required <?= $modo === 'nada' ? 'checked' : '' ?>><span>Não entregue</span></label></div>
<div class="ic-parte" hidden><label for="q<?= $iid ?>">Quantas <?= h($i['unidade']) ?> foram entregues?</label><input type="text" inputmode="decimal" id="q<?= $iid ?>" name="qtd[<?= $iid ?>]" value="<?= h($qe) ?>" autocomplete="off" placeholder="Ex.: 15"><p class="ic-falta" aria-live="polite"></p></div>
<details class="diverge" <?= $dv ? 'open' : '' ?>><summary>O talão do cliente tem outra quantidade?</summary>
<label for="r<?= $iid ?>">Quantidade no talão (cadastrado: <?= qtd_fmt($i['quantidade']) ?> <?= h($i['unidade']) ?>)</label><input type="text" inputmode="decimal" id="r<?= $iid ?>" name="real[<?= $iid ?>]" value="<?= $dv ? h(qtd_fmt($dv['qtd_real'])) : '' ?>" placeholder="Deixe em branco se está certo" autocomplete="off">
<p class="nota">Não altera o pedido: o atendimento recebe um aviso para verificar. A conferência passa a usar a quantidade do talão.</p></details>
<div class="ic-obs" hidden><label for="o<?= $iid ?>">Observação (opcional)</label><input id="o<?= $iid ?>" name="obs[<?= $iid ?>]" maxlength="250" value="<?= h(($a['observacao'] ?? '') ?: ($dv['observacao'] ?? '')) ?>" placeholder="Ex.: faltaram 5 sacos; talão diz 25 e foi cadastrado 20"></div>
</div><?php endforeach; ?></div>
<div class="resumo-conf" id="resumo" role="status" aria-live="polite"></div>
<h3 class="foto-tit">Foto do talão assinado</h3><p class="nota">Opcional. Tire uma foto do talão com a assinatura do cliente (até 6 por pedido).</p>
<?php if ($fotos): ?><div class="fotos"><?php foreach ($fotos as $fid): ?><a href="foto.php?id=<?= (int)$fid ?>" target="_blank" rel="noopener"><img src="foto.php?id=<?= (int)$fid ?>" alt="Foto do talão" loading="lazy"></a><?php endforeach; ?></div><?php endif; ?>
<input type="file" name="fotos[]" accept="image/*" multiple aria-label="Adicionar foto do talão">
<div class="barra"><button>Registrar conferência</button><a class="btn sec" href="comprovante.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">Comprovante A4</a><a class="btn sec" href="rota.php?data=<?= h($p['data_entrega']) ?>">Voltar</a></div></form>
<script>
(function(){
var f=document.getElementById('conf'),its=f.querySelectorAll('.item-conf'),rs=document.getElementById('resumo');
function n(s){s=(s||'').toString().replace(',','.').trim();var v=parseFloat(s);return isNaN(v)?null:v}
function fm(v){return (Math.round(v*100)/100).toString().replace('.',',')}
function at(){
  var falt=[],sem=0;
  its.forEach(function(it){
    var tot=parseFloat(it.dataset.total),un=it.dataset.un,m=it.querySelector('input[type=radio]:checked'),modo=m?m.value:'';
    var real=n(it.querySelector('[name^="real"]').value),temDiv=real!==null&&real>0&&Math.abs(real-tot)>0.001,base=temDiv?real:tot;
    var b=it.querySelector('.ic-base');b.hidden=!temDiv;if(temDiv)b.textContent='Pedido: '+fm(tot)+' '+un+' · Talão: '+fm(real)+' '+un+' (a conferência usa '+fm(real)+')';
    it.querySelector('.ic-parte').hidden=modo!=='parte';
    it.querySelector('.ic-obs').hidden=!(modo==='parte'||modo==='nada'||temDiv);
    var fa=it.querySelector('.ic-falta'),faltou=0;
    if(modo==='todo')faltou=0;else if(modo==='nada')faltou=base;
    else if(modo==='parte'){var q=n(it.querySelector('[name^="qtd"]').value);
      if(q===null){fa.textContent='Informe quantas foram entregues.';fa.className='ic-falta aviso';faltou=null}
      else if(q>=base){fa.textContent='Isso é a quantidade toda: escolha "Entregue".';fa.className='ic-falta aviso';faltou=0}
      else{faltou=base-q;fa.textContent='Faltam '+fm(faltou)+' '+un+' (ficam como pendência).';fa.className='ic-falta'}}
    it.classList.toggle('feito',modo==='todo');
    if(!modo)sem++;else if(faltou===null)sem++;else if(faltou>0)falt.push(fm(faltou)+' '+un+' – '+it.dataset.desc);
  });
  if(sem){rs.className='resumo-conf';rs.textContent='Falta escolher a situação de '+sem+(sem===1?' item.':' itens.');}
  else if(falt.length){rs.className='resumo-conf pend';rs.textContent='O pedido ficará COM PENDÊNCIA: '+falt.join('; ')+'.';}
  else{rs.className='resumo-conf ok';rs.textContent='Tudo entregue. O pedido será concluído.';}
}
f.addEventListener('input',at);f.addEventListener('change',at);at();
})();
</script>
<?php rodape();
