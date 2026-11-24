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
$v = ['nome'=>'','telefone'=>'','endereco'=>'','rua'=>'','numero'=>'','complemento'=>'','bairro'=>'','referencia'=>'','vendedor'=>'','data_entrega'=>date('Y-m-d', strtotime('+1 day')),'observacao'=>''];
$itens = [['qtd'=>'','un'=>'cx','desc'=>'']];
if ($id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    foreach (['nome','telefone','endereco','bairro','referencia','vendedor','data_entrega','observacao'] as $k) { $v[$k] = (string)($ped[$k] ?? ''); }
    // o banco guarda "Rua, número" em um campo só; aqui separamos de volta para a tela
    if (preg_match('/^(.*?),\s*(\d[\w\/-]*|s\/?n)(?:\s+-\s+(.+))?$/iu', $v['endereco'], $m)) { $v['rua'] = trim($m[1]); $v['numero'] = $m[2]; $v['complemento'] = $m[3] ?? ''; } else { $v['rua'] = $v['endereco']; }
    $itens = [];
    foreach (q('SELECT quantidade, unidade, descricao FROM itens_pedido WHERE pedido_id = ? ORDER BY id', [$id])->fetchAll() as $i) { $itens[] = ['qtd'=>qtd_fmt($i['quantidade']), 'un'=>$i['unidade'], 'desc'=>$i['descricao']]; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    foreach ($v as $k => $_) { $v[$k] = trim($_POST[$k] ?? ''); }
    if (!empty($_POST['a_combinar'])) { $v['data_entrega'] = A_COMBINAR; }
    $itens = [];
    foreach (($_POST['qtd'] ?? []) as $i => $qtd) {
        $qtd = str_replace(',', '.', trim($qtd)); $desc = trim($_POST['desc'][$i] ?? ''); $un = trim($_POST['un'][$i] ?? '');
        if ($qtd === '' && $desc === '') continue;
        $itens[] = ['qtd'=>$qtd, 'un'=>$un, 'desc'=>$desc];
    }
    if ($v['nome']==='') $erros[] = 'Informe o nome do cliente.';
    $v['endereco'] = $v['rua'] === '' ? '' : $v['rua'] . ($v['numero'] !== '' ? ', ' . $v['numero'] : '') . ($v['rua'] !== '' && $v['complemento'] !== '' ? ' - ' . $v['complemento'] : '');
    if ($v['rua']==='') $erros[] = 'Informe a rua.';
    if ($v['numero']==='') $erros[] = 'Informe o número (ou s/n se não houver).';
    if ($v['vendedor']==='') $erros[] = 'Informe o vendedor.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['data_entrega'])) $erros[] = 'Informe a data da entrega.';
    if (!$itens) $erros[] = 'Inclua ao menos um item.';
    foreach ($itens as $it) {
        if (!is_numeric($it['qtd']) || (float)$it['qtd'] <= 0 || $it['desc']==='' || $it['un']==='') { $erros[] = 'Cada item precisa de quantidade (maior que zero), unidade e descrição.'; break; }
    }
    if (!$erros) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            // Cliente: nunca altera o cadastro que outros pedidos já usam (o endereço de pedidos antigos não pode mudar).
            // - editando um pedido cujo cliente só tem ele: corrige o cadastro no lugar;
            // - senão: reaproveita o cliente só se TODOS os dados são iguais (nome, telefone, endereço, bairro e referência); qualquer mudança, mesmo pequena, cria um cadastro novo.
            $cid = null;
            $atual = $id ? (int)q('SELECT cliente_id FROM pedidos WHERE id = ?', [$id])->fetchColumn() : 0;
            if ($atual && (int)q('SELECT COUNT(*) FROM pedidos WHERE cliente_id = ?', [$atual])->fetchColumn() === 1) {
                $cid = $atual;
                q('UPDATE clientes SET nome=?, telefone=?, endereco=?, bairro=?, referencia=? WHERE id=?', [$v['nome'],$v['telefone'],$v['endereco'],$v['bairro'],$v['referencia'],$cid]);
            } else {
                $c = q("SELECT id FROM clientes WHERE nome = ? AND COALESCE(telefone, '') = ? AND endereco = ? AND COALESCE(bairro, '') = ? AND COALESCE(referencia, '') = ? ORDER BY id DESC LIMIT 1", [$v['nome'], $v['telefone'], $v['endereco'], $v['bairro'], $v['referencia']])->fetch();
                if ($c) { $cid = (int)$c['id']; } // nada mudou: reaproveita o cadastro
                else {
                    q('INSERT INTO clientes (nome, telefone, endereco, bairro, referencia) VALUES (?,?,?,?,?)', [$v['nome'],$v['telefone'],$v['endereco'],$v['bairro'],$v['referencia']]);
                    $cid = (int)$pdo->lastInsertId();
                }
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
            header('Location: pedidos.php?' . ($v['data_entrega'] === A_COMBINAR ? 'status=a_combinar' : 'data=' . urlencode($v['data_entrega'])) . '&ok=' . $pid); exit;
        } catch (Throwable $e) { $pdo->rollBack(); $erros[] = 'Não foi possível salvar o pedido. Tente novamente.'; }
    }
    if (!$itens) $itens = [['qtd'=>'','un'=>'cx','desc'=>'']];
}
$unidades = ['cx'=>'caixa(s)','sc'=>'saco(s)','kg'=>'kg','un'=>'unidade(s)','pc'=>'peça(s)','par'=>'par(es)','m2'=>'m²'];
?>
<?php foreach ($erros as $e): ?><p class="erro" role="alert"><?= h($e) ?></p><?php endforeach; ?>
<form method="post" id="form-pedido" novalidate><?= csrf_campo() ?><?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
<div class="card"><h2>Cliente e endereço</h2><div class="grid grid-end">
<div class="g-nome busca-cli"><label class="req" for="nome">Nome do cliente</label><input id="nome" name="nome" value="<?= h($v['nome']) ?>" autocomplete="off" required <?= $id ? '' : 'autofocus' ?>></div>
<div class="g-tel"><label for="telefone">Telefone / WhatsApp</label><input id="telefone" name="telefone" inputmode="tel" placeholder="(71) 99999-0000" maxlength="16" value="<?= h($v['telefone']) ?>"></div>
<div class="g-cep"><label for="cep">CEP <span class="nota sem-margem">(opcional)</span></label><input id="cep" inputmode="numeric" maxlength="9" placeholder="00000-000" autocomplete="off"><p class="nota sem-margem" id="cep-msg" aria-live="polite">Preenche rua e bairro.</p></div>
<div class="g-rua"><label class="req" for="rua">Rua</label><input id="rua" name="rua" value="<?= h($v['rua']) ?>" autocomplete="off" required></div>
<div class="g-num"><label class="req" for="numero">Número</label><input id="numero" name="numero" value="<?= h($v['numero']) ?>" maxlength="12" placeholder="s/n" autocomplete="off" required></div>
<div class="g-comp"><label for="complemento">Complemento <span class="nota sem-margem">(opcional)</span></label><input id="complemento" name="complemento" value="<?= h($v['complemento']) ?>" maxlength="60" placeholder="Ex.: apto 302, bloco B" autocomplete="off"></div>
<div class="g-bairro"><label for="bairro">Bairro</label><input id="bairro" name="bairro" list="bairros" value="<?= h($v['bairro']) ?>" autocomplete="off"><datalist id="bairros"><?php foreach (q("SELECT DISTINCT bairro FROM clientes WHERE bairro <> '' ORDER BY bairro")->fetchAll(PDO::FETCH_COLUMN) as $b): ?><option value="<?= h($b) ?>"><?php endforeach; ?></datalist></div>
<div class="g-ref"><label for="referencia">Ponto de referência</label><input id="referencia" name="referencia" value="<?= h($v['referencia']) ?>" placeholder="Ex.: portão azul" autocomplete="off"></div>
</div></div>

<div class="card"><h2>Entrega e venda</h2><div class="grid">
<?php $comb = $v['data_entrega'] === A_COMBINAR; ?>
<div><label class="req" for="data_entrega">Data da entrega</label><input id="data_entrega" type="date" name="data_entrega" value="<?= h($comb ? date('Y-m-d', strtotime('+1 day')) : $v['data_entrega']) ?>" <?= $comb ? 'disabled' : 'required' ?>>
<div class="chips" id="atalhos-data" <?= $comb ? 'hidden' : '' ?>><button type="button" class="chip" data-d="0">Hoje</button><button type="button" class="chip" data-d="1">Amanhã</button><button type="button" class="chip" data-d="2">Depois de amanhã</button></div>
<label class="marcar" for="a_combinar"><input type="checkbox" id="a_combinar" name="a_combinar" value="1" <?= $comb ? 'checked' : '' ?>> A combinar com o cliente</label>
<p class="nota" id="nota-combinar" <?= $comb ? '' : 'hidden' ?>>O pedido fica na lista sem data. Quando o cliente definir o prazo, edite o pedido e escolha a data.</p>
</div>
<div><label class="req" for="vendedor">Vendedor</label><select id="vendedor" name="vendedor" required><option value="">Escolha o vendedor</option>
<?php $nomesV = array_column(vendedores(), 'nome'); if ($v['vendedor'] !== '' && !in_array($v['vendedor'], $nomesV, true)) { $nomesV[] = $v['vendedor']; } foreach ($nomesV as $nv): ?><option value="<?= h($nv) ?>" <?= $v['vendedor'] === $nv ? 'selected' : '' ?>><?= h($nv) ?></option><?php endforeach; ?></select>
<?php if (!$nomesV): ?><p class="nota">Nenhum vendedor cadastrado. O administrador cadastra em Vendedores.</p><?php endif; ?>
</div>
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
// CEP (opcional): preenche rua e bairro pelo ViaCEP; se falhar, o preenchimento manual continua valendo
var cep=document.getElementById('cep'),cepMsg=document.getElementById('cep-msg'),cepUlt='';
cep.addEventListener('input',function(){var d=cep.value.replace(/\D/g,'').slice(0,8);cep.value=d.length>5?d.slice(0,5)+'-'+d.slice(5):d;
 if(d.length<8){cepUlt='';return}if(d===cepUlt)return;cepUlt=d;cepMsg.textContent='Buscando o CEP...';
 var ctl=window.AbortController?new AbortController():null,tm=setTimeout(function(){if(ctl)ctl.abort()},6000);
 fetch('https://viacep.com.br/ws/'+d+'/json/',ctl?{signal:ctl.signal}:{}).then(function(r){return r.json()}).then(function(j){clearTimeout(tm);
  if(j.erro){cepMsg.textContent='CEP não encontrado. Preencha a rua manualmente.';return}
  var ru=document.getElementById('rua'),nu=document.getElementById('numero'),ba=document.getElementById('bairro');
  ru.value=j.logradouro||'';nu.value='';document.getElementById('complemento').value='';ba.value=j.bairro||'';ru.classList.remove('campo-erro');ba.classList.remove('campo-erro');
  cepMsg.textContent=j.logradouro?'Preenchido. Informe o número.':'CEP sem rua (CEP geral da cidade). Informe a rua.';(j.logradouro?nu:ru).focus()
 }).catch(function(){clearTimeout(tm);cepMsg.textContent='Não foi possível consultar o CEP agora. Preencha a rua manualmente.'})});
// atalhos de data
var dt=document.getElementById('data_entrega');
function iso(x){var d=new Date();d.setDate(d.getDate()+x);return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}
function marcaData(){document.querySelectorAll('#atalhos-data .chip').forEach(function(c){c.classList.toggle('on',dt.value===iso(+c.dataset.d))})}
document.getElementById('atalhos-data').addEventListener('click',function(e){var c=e.target.closest('.chip');if(c){dt.value=iso(+c.dataset.d);dt.classList.remove('campo-erro');marcaData()}});
dt.addEventListener('input',marcaData);marcaData();
var ac=document.getElementById('a_combinar');
function combinar(){dt.disabled=ac.checked;dt.required=!ac.checked;document.getElementById('atalhos-data').hidden=ac.checked;document.getElementById('nota-combinar').hidden=!ac.checked;dt.classList.remove('campo-erro')}
ac.addEventListener('change',combinar);combinar();
// tira a borda vermelha assim que o campo é preenchido (digitando, escolhendo ou preenchido pelo CEP)
function limpaErro(e){var i=e.target;if(i.classList&&i.classList.contains('campo-erro')&&i.value.trim()){i.classList.remove('campo-erro')}}
document.getElementById('form-pedido').addEventListener('input',limpaErro);document.getElementById('form-pedido').addEventListener('change',limpaErro);
// validação amigável: foca no primeiro campo faltando
document.getElementById('form-pedido').addEventListener('submit',function(e){
 var f=['nome','rua','numero','vendedor'].concat(ac.checked?[]:['data_entrega']).map(function(i){return document.getElementById(i)});
 var falta=null;f.forEach(function(i){var vazio=!i.value.trim();i.classList.toggle('campo-erro',vazio);if(vazio&&!falta)falta=i});
 if(falta){e.preventDefault();falta.focus()}});
})();
</script>
<?php if (!$id): ?><script>
// Cliente já cadastrado: ao digitar o nome (ou telefone), sugere os clientes salvos e preenche o endereço.
(function(){
var nome=document.getElementById('nome'),tel=document.getElementById('telefone');if(!nome)return;
var box=document.createElement('ul');box.className='sugestoes';box.setAttribute('role','listbox');box.hidden=true;nome.parentNode.appendChild(box);
nome.setAttribute('autocomplete','off');var t=null,ult='';
function fecha(){box.hidden=true;box.innerHTML=''}
function ev(el){el.dispatchEvent(new Event('input',{bubbles:true}));el.dispatchEvent(new Event('change',{bubbles:true}))}
function escolhe(c){
  [['nome','nome'],['telefone','telefone'],['rua','rua'],['numero','numero'],['complemento','complemento'],['bairro','bairro'],['referencia','referencia']].forEach(function(p){var el=document.getElementById(p[0]);if(el){el.value=c[p[1]]||'';ev(el)}});
  fecha();var v=document.getElementById('vendedor');if(v)v.focus();
  var av=document.getElementById('aviso-cli');if(!av){av=document.createElement('p');av.id='aviso-cli';av.className='nota';av.setAttribute('role','status');box.parentNode.appendChild(av)}
  av.textContent='Cliente já cadastrado: dados preenchidos. Confira o endereço antes de salvar.';
}
function busca(q){
  fetch('clientes_busca.php?q='+encodeURIComponent(q),{credentials:'same-origin'}).then(function(r){return r.ok?r.json():[]}).then(function(l){
    if(q!==ult)return;box.innerHTML='';if(!l.length){box.hidden=true;return}
    l.forEach(function(c){var li=document.createElement('li');li.setAttribute('role','option');var b=document.createElement('button');b.type='button';
      var s1=document.createElement('strong');s1.textContent=c.nome;var s2=document.createElement('span');s2.textContent=[c.telefone,[c.rua,c.numero].filter(Boolean).join(', '),c.bairro].filter(Boolean).join(' · ');
      b.appendChild(s1);b.appendChild(s2);b.addEventListener('mousedown',function(e){e.preventDefault()});b.addEventListener('click',function(){escolhe(c)});li.appendChild(b);box.appendChild(li)});
    box.hidden=false}).catch(fecha)}
function digitou(el){var q=el.value.trim();ult=q;clearTimeout(t);if(q.length<2){fecha();return}t=setTimeout(function(){busca(q)},200)}
nome.addEventListener('input',function(){digitou(nome)});
if(tel)tel.addEventListener('input',function(){if(tel.value.replace(/\D/g,'').length>=3)digitou(tel);});
nome.addEventListener('keydown',function(e){if(e.key==='Escape')fecha();if(e.key==='ArrowDown'&&!box.hidden){var b=box.querySelector('button');if(b){e.preventDefault();b.focus()}}});
box.addEventListener('keydown',function(e){var b=document.activeElement;if(e.key==='ArrowDown'){var n=b.closest('li').nextElementSibling;if(n){e.preventDefault();n.querySelector('button').focus()}}else if(e.key==='ArrowUp'){var p=b.closest('li').previousElementSibling;e.preventDefault();(p?p.querySelector('button'):nome).focus()}else if(e.key==='Escape'){fecha();nome.focus()}});
document.addEventListener('click',function(e){if(!box.contains(e.target)&&e.target!==nome&&e.target!==tel)fecha()});
})();
</script><?php endif; ?>
<?php rodape();
