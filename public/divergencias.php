<?php
define('INC', is_dir(__DIR__ . '/inc') ? __DIR__ . '/inc' : __DIR__ . '/../inc'); // funciona com inc/ ao lado ou acima
require_once INC . '/layout.php';
$u0 = exige_login(['balcao']);
divergencia_garante(); pendencia_qtd_garante();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ok();
    $d = q('SELECT * FROM divergencias WHERE id = ? AND resolvida = 0', [(int)($_POST['id'] ?? 0)])->fetch();
    if ($d) {
        $corrige = ($_POST['acao'] ?? '') === 'corrigir';
        $motivo = mb_substr(trim((string)($_POST['motivo'] ?? '')), 0, 250);
        if (!$corrige && $motivo === '') { header('Location: divergencias.php?sem_motivo=' . (int)$d['id']); exit; } // manter exige explicar
        if ($corrige) { q('UPDATE itens_pedido SET quantidade = ? WHERE id = ?', [$d['qtd_real'], $d['item_id']]); }
        else { // verificado: a quantidade certa era a cadastrada. Refaz a pendência em aberto (se houver) sobre ela.
            $pe = q('SELECT id, qtd_falta FROM pendencias WHERE item_id = ? AND resolvida = 0', [$d['item_id']])->fetch();
            if ($pe) {
                $entregue = $pe['qtd_falta'] !== null ? max(0.0, (float)$d['qtd_real'] - (float)$pe['qtd_falta']) : 0.0;
                $falta = round((float)$d['qtd_pedido'] - $entregue, 2);
                if ($falta > 0) { q('UPDATE pendencias SET qtd_falta = ? WHERE id = ?', [$falta, $pe['id']]); }
                else {
                    q('UPDATE pendencias SET resolvida = 1 WHERE id = ?', [$pe['id']]);
                    q('UPDATE itens_pedido SET conferido = 1 WHERE id = ?', [$d['item_id']]);
                    if (!(int)q('SELECT COUNT(*) FROM pendencias WHERE pedido_id = ? AND resolvida = 0', [$d['pedido_id']])->fetchColumn()) {
                        q("UPDATE pedidos SET status = 'entregue' WHERE id = ? AND status = 'com_pendencia'", [$d['pedido_id']]);
                    }
                }
            }
        }
        q('UPDATE divergencias SET resolvida = 1, resolucao = ?, resolucao_obs = ?, resolvida_por = ?, resolvida_em = ' . (strpos(DB_DSN, 'sqlite') === 0 ? 'CURRENT_TIMESTAMP' : 'NOW()') . ' WHERE id = ?', [$corrige ? 'corrigido' : 'mantido', $motivo, mb_substr((string)($u0['nome'] ?? ''), 0, 60), $d['id']]);
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
<div class="motivo-div"><label for="m<?= (int)$r['id'] ?>">Motivo da decisão (obrigatório para manter)</label><input id="m<?= (int)$r['id'] ?>" name="motivo" maxlength="250" autocomplete="off" placeholder="Ex.: conferi o talão e eram 2 sc mesmo" <?= (int)($_GET['sem_motivo'] ?? 0) === (int)$r['id'] ? 'class="campo-erro" autofocus' : '' ?>><?= (int)($_GET['sem_motivo'] ?? 0) === (int)$r['id'] ? '<p class="erro" role="alert">Explique o motivo para manter a quantidade cadastrada.</p>' : '' ?></div>
<button name="acao" value="corrigir" class="peq">Corrigir pedido para <?= qtd_fmt($r['qtd_real']) ?> <?= h($r['unidade']) ?></button>
<button name="acao" value="manter" class="peq sec" onclick="var i=this.form.motivo;if(!i.value.trim()){i.setCustomValidity('Explique o motivo para manter.');i.reportValidity();i.addEventListener('input',function(){i.setCustomValidity('')},{once:true});return false}">Manter <?= qtd_fmt($r['qtd_pedido']) ?> <?= h($r['unidade']) ?></button>
<a class="btn sec peq" href="pedido_ver.php?id=<?= (int)$r['pedido_id'] ?>">Ver pedido / fotos</a></form></div>
<?php endforeach; ?></div><?php rodape();
