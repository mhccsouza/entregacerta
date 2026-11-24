<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
$u0 = exige_login(['balcao']);
divergencia_garante();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $d = q('SELECT * FROM divergencias WHERE id = ? AND resolvida = 0', [(int)($_POST['id'] ?? 0)])->fetch();
    if ($d) {
        $corrige = ($_POST['acao'] ?? '') === 'corrigir';
        if ($corrige) { q('UPDATE itens_pedido SET quantidade = ? WHERE id = ?', [$d['qtd_real'], $d['item_id']]); }
        q('UPDATE divergencias SET resolvida = 1, resolucao = ?, resolvida_em = ' . (strpos(DB_DSN, 'sqlite') === 0 ? 'CURRENT_TIMESTAMP' : 'NOW()') . ' WHERE id = ?', [$corrige ? 'corrigido' : 'mantido', $d['id']]);
    }
    header('Location: divergencias.php'); exit;
}
topo('Divergências de quantidade', ['balcao'], 'Itens em que a quantidade cadastrada pode estar errada');
$rows = q('SELECT d.*, i.unidade, i.descricao, c.nome, p.data_entrega FROM divergencias d JOIN itens_pedido i ON i.id = d.item_id JOIN pedidos p ON p.id = d.pedido_id JOIN clientes c ON c.id = p.cliente_id WHERE d.resolvida = 0 ORDER BY d.id')->fetchAll();
if (!$rows): ?><div class="vazio"><p>Nenhuma divergência para verificar.</p><a class="btn sec" href="index.php">Voltar ao painel</a></div><?php endif; ?>
<div class="cards"><?php foreach ($rows as $r): ?><div class="card"><div class="pedido-cab"><strong>Pedido #<?= (int)$r['pedido_id'] ?> · <?= h($r['nome']) ?></strong> <span class="tag pend">Verificar</span></div>
<p class="meta">Entrega: <?= data_br($r['data_entrega']) ?></p>
<p><strong><?= h($r['descricao']) ?></strong><br>Cadastrado: <?= qtd_fmt($r['qtd_pedido']) ?> <?= h($r['unidade']) ?> · Informado na entrega: <strong><?= qtd_fmt($r['qtd_real']) ?> <?= h($r['unidade']) ?></strong></p>
<?= $r['observacao'] ? '<p class="meta">Obs.: ' . h($r['observacao']) . '</p>' : '' ?>
<form method="post" class="acoes-card no-print"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<button name="acao" value="corrigir" class="peq">Corrigir pedido para <?= qtd_fmt($r['qtd_real']) ?> <?= h($r['unidade']) ?></button>
<button name="acao" value="manter" class="peq sec">Manter <?= qtd_fmt($r['qtd_pedido']) ?> <?= h($r['unidade']) ?></button>
<a class="btn sec peq" href="pedido_ver.php?id=<?= (int)$r['pedido_id'] ?>">Ver pedido / fotos</a></form></div>
<?php endforeach; ?></div><?php rodape();
