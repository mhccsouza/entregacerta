<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
exige_login(['balcao']);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$u = topo($id ? 'Editar pedido #' . $id : 'Novo pedido (entrega gratuita)', ['balcao']);
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
<?php foreach ($erros as $e): ?><p class="erro"><?= h($e) ?></p><?php endforeach; ?>
<form method="post"><?= csrf_campo() ?><?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
<div class="card"><h2 style="margin-top:0">Entrega</h2><div class="grid">
<div><label for="nome">Cliente</label><input id="nome" name="nome" value="<?= h($v['nome']) ?>" required></div>
<div><label for="telefone">Telefone</label><input id="telefone" name="telefone" inputmode="tel" value="<?= h($v['telefone']) ?>"></div>
<div><label for="endereco">Endereço</label><input id="endereco" name="endereco" value="<?= h($v['endereco']) ?>" required></div>
<div><label for="bairro">Bairro</label><input id="bairro" name="bairro" value="<?= h($v['bairro']) ?>"></div>
<div><label for="referencia">Ponto de referência</label><input id="referencia" name="referencia" value="<?= h($v['referencia']) ?>"></div>
<div><label for="vendedor">Vendedor</label><input id="vendedor" name="vendedor" list="vend" value="<?= h($v['vendedor']) ?>" required><datalist id="vend"><option>Ione</option><option>Bruno</option></datalist></div>
<div><label for="data_entrega">Data da entrega</label><input id="data_entrega" type="date" name="data_entrega" value="<?= h($v['data_entrega']) ?>" required></div>
</div><label for="observacao">Observação (horário, restrições)</label><input id="observacao" name="observacao" value="<?= h($v['observacao']) ?>"></div>
<div class="card"><h2 style="margin-top:0">Itens</h2><div id="itens">
<?php foreach ($itens as $it): ?>
<div class="linha-item"><input name="qtd[]" inputmode="decimal" placeholder="Qtd" aria-label="Quantidade" value="<?= h($it['qtd']) ?>">
<select name="un[]" aria-label="Unidade"><?php foreach ($unidades as $k => $n): ?><option value="<?= $k ?>" <?= $it['un']===$k?'selected':'' ?>><?= h($n) ?></option><?php endforeach; ?></select>
<input name="desc[]" placeholder="Ex.: Piso 58120 A, Arg. ACII" aria-label="Descrição" value="<?= h($it['desc']) ?>">
<button type="button" class="sec" onclick="this.parentNode.remove()" aria-label="Remover item">×</button></div>
<?php endforeach; ?></div>
<button type="button" class="sec" id="add">+ Adicionar item</button></div>
<p><button><?= $id ? 'Salvar alterações' : 'Salvar pedido' ?></button></p></form>
<script>
document.getElementById('add').onclick=function(){var d=document.getElementById('itens'),n=d.firstElementChild.cloneNode(true);
n.querySelectorAll('input').forEach(function(i){i.value=''});d.appendChild(n);n.querySelector('input').focus();};
</script>
<?php rodape();
