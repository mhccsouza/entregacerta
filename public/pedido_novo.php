<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
exige_login(['balcao']);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$u = topo($id ? 'Editar pedido #' . $id : 'Novo pedido', ['balcao'], 'Copie do talão: dados da entrega e quantidades. Campos com * são obrigatórios.');
$erros = [];
$bloqueado = '';
if ($id) {
    $ped = q('SELECT p.*, c.nome, c.telefone, c.endereco, c.bairro, c.referencia FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.id = ?', [$id])->fetch();
    if (!$ped) { $bloqueado = 'Pedido não encontrado.'; }
    elseif ($ped['status'] !== 'aguardando' || (int)q('SELECT COUNT(*) FROM itens_pedido WHERE pedido_id = ? AND separado = 1', [$id])->fetchColumn() > 0) {
        $bloqueado = 'Este pedido já foi separado ou entrou em rota e não pode mais ser editado.';
    }
    if ($bloqueado) { echo '<p class="erro">' . h($bloqueado) . '</p><p><a class="btn sec" href="pedidos.php">Voltar</a></p>'; rodape(); exit; }
}
$v = ['nome'=>'','telefone'=>'','endereco'=>'','bairro'=>'','referencia'=>'','vendedor'=>'','data_entrega'=>date('Y-m-d', strtotime('+1 day')),'observacao'=>''];
$itens = [['qtd'=>'','un'=>'cx','desc'=>'']];
if ($id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    foreach (['nome','telefone','endereco','bairro','referencia','vendedor','data_entrega','observacao'] as $k) { $v[$k] = (string)($ped[$k] ?? ''); }
    $itens = [];
    foreach (q('SELECT quantidade, unidade, descricao FROM itens_pedido WHERE pedido_id = ? ORDER BY id', [$id])->fetchAll() as $i) { $itens[] = ['qtd'=>qtd_fmt($i['quantidade']), 'un'=>$i['unidade'], 'desc'=>$i['descricao']]; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    foreach ($v as $k => $_) { $v[$k] = trim($_POST[$k] ?? ''); }
    $itens = [];
    foreach (($_POST['qtd'] ?? []) as $i => $qtd) {
        $qtd = str_replace(',', '.', trim($qtd)); $desc = trim($_POST['desc'][$i] ?? ''); $un = trim($_POST['un'][$i] ?? '');
        if ($qtd === '' && $desc === '') continue;
        $itens[] = ['qtd'=>$qtd, 'un'=>$un, 'desc'=>$desc];
    }
    if ($v['nome']==='') $erros[] = 'Informe o nome do cliente.';
    if ($v['endereco']==='') $erros[] = 'Informe o endereço.';
    if ($v['vendedor']==='') $erros[] = 'Informe o vendedor.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['data_entrega'])) $erros[] = 'Informe a data da entrega.';
    if (!$itens) $erros[] = 'Inclua ao menos um item.';
    foreach ($itens as $it) {
        if (!is_numeric($it['qtd']) || (float)$it['qtd'] <= 0 || $it['desc']==='' || $it['un']==='') { $erros[] = 'Cada item precisa de quantidade (maior que zero), unidade e descrição.'; break; }
    }
    if (!$erros) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            $cid = null;
            if ($v['telefone'] !== '') {
                $c = q('SELECT id FROM clientes WHERE telefone = ? AND nome = ? ORDER BY id DESC LIMIT 1', [$v['telefone'], $v['nome']])->fetch();
                if ($c) { $cid = (int)$c['id']; q('UPDATE clientes SET endereco=?, bairro=?, referencia=? WHERE id=?', [$v['endereco'],$v['bairro'],$v['referencia'],$cid]); }
            }
            if (!$cid) {
                q('INSERT INTO clientes (nome, telefone, endereco, bairro, referencia) VALUES (?,?,?,?,?)', [$v['nome'],$v['telefone'],$v['endereco'],$v['bairro'],$v['referencia']]);
                $cid = (int)$pdo->lastInsertId();
            }
            if ($id) {
                q('UPDATE pedidos SET cliente_id = ?, vendedor = ?, data_entrega = ?, observacao = ? WHERE id = ?', [$cid,$v['vendedor'],$v['data_entrega'],$v['observacao'],$id]);
                q('DELETE FROM itens_pedido WHERE pedido_id = ?', [$id]);
                $pid = $id;
            } else {
                q('INSERT INTO pedidos (cliente_id, vendedor, data_entrega, observacao, criado_por) VALUES (?,?,?,?,?)', [$cid,$v['vendedor'],$v['data_entrega'],$v['observacao'],$u['id']]);
                $pid = (int)$pdo->lastInsertId();
            }
            foreach ($itens as $it) { q('INSERT INTO itens_pedido (pedido_id, quantidade, unidade, descricao) VALUES (?,?,?,?)', [$pid,(float)$it['qtd'],$it['un'],$it['desc']]); }
            $pdo->commit();
            header('Location: pedidos.php?data=' . urlencode($v['data_entrega']) . '&ok=' . $pid); exit;
        } catch (Throwable $e) { $pdo->rollBack(); $erros[] = 'Não foi possível salvar o pedido. Tente novamente.'; }
    }
    if (!$itens) $itens = [['qtd'=>'','un'=>'cx','desc'=>'']];
}
$unidades = ['cx'=>'caixa(s)','sc'=>'saco(s)','kg'=>'kg','un'=>'unidade(s)','pc'=>'peça(s)','par'=>'par(es)','m2'=>'m²'];
?>
<?php foreach ($erros as $e): ?><p class="erro" role="alert"><?= h($e) ?></p><?php endforeach; ?>
<form method="post" id="form-pedido" novalidate><?= csrf_campo() ?><?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
<div class="card"><h2>Cliente e endereço</h2><div class="grid">
<div><label class="req" for="nome">Nome do cliente</label><input id="nome" name="nome" value="<?= h($v['nome']) ?>" autocomplete="off" required <?= $id ? '' : 'autofocus' ?>></div>
<div><label for="telefone">Telefone / WhatsApp</label><input id="telefone" name="telefone" inputmode="tel" placeholder="(71) 99999-0000" maxlength="16" value="<?= h($v['telefone']) ?>"></div>
<div><label class="req" for="endereco">Endereço (rua e número)</label><input id="endereco" name="endereco" value="<?= h($v['endereco']) ?>" autocomplete="off" required></div>
<div><label for="bairro">Bairro</label><input id="bairro" name="bairro" list="bairros" value="<?= h($v['bairro']) ?>" autocomplete="off"><datalist id="bairros"><?php foreach (q("SELECT DISTINCT bairro FROM clientes WHERE bairro <> '' ORDER BY bairro")->fetchAll(PDO::FETCH_COLUMN) as $b): ?><option value="<?= h($b) ?>"><?php endforeach; ?></datalist></div>
</div>
<label for="referencia">Ponto de referência</label><input id="referencia" name="referencia" value="<?= h($v['referencia']) ?>" placeholder="Ex.: ao lado da padaria, portão azul" autocomplete="off"></div>

<div class="card"><h2>Entrega e venda</h2><div class="grid">
<div><label class="req" for="data_entrega">Data da entrega</label><input id="data_entrega" type="date" name="data_entrega" value="<?= h($v['data_entrega']) ?>" required>
<div class="chips" id="atalhos-data"><button type="button" class="chip" data-d="0">Hoje</button><button type="button" class="chip" data-d="1">Amanhã</button><button type="button" class="chip" data-d="2">Depois de amanhã</button></div></div>
<div><label class="req" for="vendedor">Vendedor</label><input id="vendedor" name="vendedor" value="<?= h($v['vendedor']) ?>" autocomplete="off" required>
<div class="chips" id="atalhos-vend"><button type="button" class="chip">Ione</button><button type="button" class="chip">Bruno</button></div></div>
</div>
<label for="observacao">Observação</label><input id="observacao" name="observacao" value="<?= h($v['observacao']) ?>" placeholder="Horário combinado, restrição de acesso…" autocomplete="off"></div>

<div class="card"><h2>Itens do talão</h2>
<div class="cab-itens" aria-hidden="true"><span>Quantidade</span><span>Unidade</span><span>Descrição do material</span><span></span></div>
<div id="itens">
<?php foreach ($itens as $it): ?>
<div class="linha-item"><input name="qtd[]" inputmode="decimal" placeholder="Qtd" aria-label="Quantidade" value="<?= h($it['qtd']) ?>">
<select name="un[]" aria-label="Unidade"><?php foreach ($unidades as $k => $n): ?><option value="<?= $k ?>" <?= $it['un']===$k?'selected':'' ?>><?= h($n) ?></option><?php endforeach; ?></select>
<input name="desc[]" placeholder="Ex.: Piso 58120 A, Arg. ACII" aria-label="Descrição" autocomplete="off" value="<?= h($it['desc']) ?>">
<button type="button" class="sec rem" aria-label="Remover item">×</button></div>
<?php endforeach; ?></div>
<div class="barra"><button type="button" class="sec" id="add">+ Adicionar item</button><span class="resumo-itens" id="resumo" aria-live="polite"></span></div>
<p class="nota">Dica: ao terminar a descrição, aperte Enter para abrir uma nova linha.</p></div>
<div class="barra barra-fixa"><button><?= $id ? 'Salvar alterações' : 'Salvar pedido' ?></button><a class="btn sec" href="pedidos.php">Cancelar</a></div></form>
<script>
(function(){
var itens=document.getElementById('itens'),resumo=document.getElementById('resumo');
function conta(){var n=0;itens.querySelectorAll('.linha-item').forEach(function(l){if(l.querySelector('[name="desc[]"]').value.trim()||l.querySelector('[name="qtd[]"]').value.trim())n++});
 resumo.textContent=n+(n===1?' item':' itens')+' no pedido';}
function novaLinha(){var n=itens.firstElementChild.cloneNode(true);n.querySelectorAll('input').forEach(function(i){i.value=''});n.querySelector('select').selectedIndex=0;itens.appendChild(n);n.querySelector('input').focus();return n}
document.getElementById('add').onclick=novaLinha;
itens.addEventListener('click',function(e){if(e.target.closest('.rem')){var l=e.target.closest('.linha-item');if(itens.children.length>1){l.remove()}else{l.querySelectorAll('input').forEach(function(i){i.value=''})}conta()}});
itens.addEventListener('input',conta);
itens.addEventListener('keydown',function(e){if(e.key==='Enter'&&e.target.name==='desc[]'){e.preventDefault();if(e.target.value.trim()){var ult=itens.lastElementChild.contains(e.target);if(ult){novaLinha()}else{e.target.closest('.linha-item').nextElementSibling.querySelector('input').focus()}}}});
conta();
// telefone: (71) 99999-0000
var tel=document.getElementById('telefone');
tel.addEventListener('input',function(){var d=tel.value.replace(/\D/g,'').slice(0,11),r=d;
 if(d.length>10){r='('+d.slice(0,2)+') '+d.slice(2,7)+'-'+d.slice(7)}else if(d.length>6){r='('+d.slice(0,2)+') '+d.slice(2,6)+'-'+d.slice(6)}else if(d.length>2){r='('+d.slice(0,2)+') '+d.slice(2)}else if(d.length){r='('+d}
 tel.value=r});
// atalhos de data
var dt=document.getElementById('data_entrega');
function iso(x){var d=new Date();d.setDate(d.getDate()+x);return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}
function marcaData(){document.querySelectorAll('#atalhos-data .chip').forEach(function(c){c.classList.toggle('on',dt.value===iso(+c.dataset.d))})}
document.getElementById('atalhos-data').addEventListener('click',function(e){var c=e.target.closest('.chip');if(c){dt.value=iso(+c.dataset.d);marcaData()}});
dt.addEventListener('input',marcaData);marcaData();
// vendedor
var vd=document.getElementById('vendedor');
function marcaVend(){document.querySelectorAll('#atalhos-vend .chip').forEach(function(c){c.classList.toggle('on',vd.value.trim().toLowerCase()===c.textContent.toLowerCase())})}
document.getElementById('atalhos-vend').addEventListener('click',function(e){var c=e.target.closest('.chip');if(c){vd.value=c.textContent;marcaVend()}});
vd.addEventListener('input',marcaVend);marcaVend();
// validação amigável: foca no primeiro campo faltando
document.getElementById('form-pedido').addEventListener('submit',function(e){
 var f=['nome','endereco','vendedor','data_entrega'].map(function(i){return document.getElementById(i)});
 var falta=null;f.forEach(function(i){var vazio=!i.value.trim();i.classList.toggle('campo-erro',vazio);if(vazio&&!falta)falta=i});
 if(falta){e.preventDefault();falta.focus()}});
})();
</script>
<?php rodape();
