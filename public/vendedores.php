<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
$u = topo('Vendedores', [], 'Cadastre quem vende. A lista aparece no novo pedido e nos filtros.');
if ($u['perfil'] !== 'admin') { http_response_code(403); echo '<p class="erro">Somente o administrador acessa esta tela.</p>'; rodape(); exit; }
$msg = ''; $erro = '';
vendedores(); // garante a tabela
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $acao = $_POST['acao'] ?? ''; $id = (int)($_POST['id'] ?? 0); $nome = trim((string)($_POST['nome'] ?? ''));
    $nome = mb_substr($nome, 0, 60);
    if ($acao === 'novo') {
        if ($nome === '') { $erro = 'Informe o nome do vendedor.'; }
        elseif (q('SELECT COUNT(*) FROM vendedores WHERE LOWER(nome) = LOWER(?)', [$nome])->fetchColumn()) { $erro = 'Já existe um vendedor com esse nome.'; }
        else { q('INSERT INTO vendedores (nome) VALUES (?)', [$nome]); $msg = 'Vendedor ' . $nome . ' cadastrado.'; }
    } elseif ($acao === 'editar') {
        $atual = q('SELECT nome FROM vendedores WHERE id = ?', [$id])->fetchColumn();
        if (!$atual) { $erro = 'Vendedor não encontrado.'; }
        elseif ($nome === '') { $erro = 'Informe o nome do vendedor.'; }
        elseif (q('SELECT COUNT(*) FROM vendedores WHERE LOWER(nome) = LOWER(?) AND id <> ?', [$nome, $id])->fetchColumn()) { $erro = 'Já existe um vendedor com esse nome.'; }
        else {
            $pdo = db(); $pdo->beginTransaction();
            q('UPDATE vendedores SET nome = ? WHERE id = ?', [$nome, $id]);
            q('UPDATE pedidos SET vendedor = ? WHERE vendedor = ?', [$nome, $atual]); // mantém o histórico com o nome novo
            $pdo->commit(); $msg = 'Nome alterado de ' . $atual . ' para ' . $nome . '.';
        }
    } elseif ($acao === 'excluir') {
        $atual = q('SELECT nome FROM vendedores WHERE id = ?', [$id])->fetchColumn();
        if ($atual) { q('DELETE FROM vendedores WHERE id = ?', [$id]); $msg = 'Vendedor ' . $atual . ' removido da lista. Os pedidos antigos continuam com o nome dele.'; }
        else { $erro = 'Vendedor não encontrado.'; }
    }
}
if ($msg): ?><p class="ok" role="status"><?= h($msg) ?></p><?php endif; if ($erro): ?><p class="erro" role="alert"><?= h($erro) ?></p><?php endif; ?>
<form method="post" class="card estreito um-campo"><?= csrf_campo() ?><input type="hidden" name="acao" value="novo">
<h2>Novo vendedor</h2><label for="nv">Nome</label><input id="nv" name="nome" maxlength="60" required autocomplete="off" placeholder="Ex.: Carla">
<div class="barra"><button>Cadastrar vendedor</button></div></form>
<h2>Equipe de vendas</h2>
<?php $lista = q('SELECT v.id, v.nome, (SELECT COUNT(*) FROM pedidos p WHERE p.vendedor = v.nome) AS n FROM vendedores v ORDER BY v.nome')->fetchAll();
if (!$lista): ?><div class="vazio"><p>Nenhum vendedor cadastrado.</p></div><?php endif; ?>
<div class="cards"><?php foreach ($lista as $r): ?>
<div class="card"><div class="pedido-cab"><strong><?= h($r['nome']) ?></strong><span class="tag"><?= (int)$r['n'] ?> <?= (int)$r['n'] === 1 ? 'pedido' : 'pedidos' ?></span></div>
<form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="editar"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<label for="n<?= (int)$r['id'] ?>">Editar nome</label><input id="n<?= (int)$r['id'] ?>" name="nome" maxlength="60" value="<?= h($r['nome']) ?>" required autocomplete="off">
<div class="barra"><button class="sec peq">Salvar nome</button></div></form>
<form method="post" onsubmit="return confirm('Remover <?= h(addslashes($r['nome'])) ?> da lista de vendedores? Os pedidos antigos continuam com o nome dele.')"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<div class="barra"><button class="perigo peq">Excluir vendedor</button></div></form></div>
<?php endforeach; ?></div><?php rodape();
