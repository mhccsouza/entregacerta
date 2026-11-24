<?php
require_once __DIR__ . '/config.php';
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            // o MySQL da hospedagem usa UTC: alinha o relógio do banco ao do sistema (America/Bahia) para a hora de cadastro sair certa
            if (strpos(DB_DSN, 'mysql') === 0) { $pdo->exec("SET time_zone = '" . date('P') . "'"); }
        } catch (Throwable $e) {
            http_response_code(500);
            error_log('EntregaCerta: falha ao conectar ao banco: ' . $e->getMessage());
            exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;max-width:520px;margin:40px auto;padding:0 16px"><h1>Sem conexão com o banco de dados</h1><p>Confira o arquivo <code>inc/config.local.php</code> (nome do banco, usuário e senha do hPanel). Para ver o motivo exato, abra <code>diagnostico.php</code> no navegador e apague-o depois.</p></body>');
        }
        if (strpos(DB_DSN, 'sqlite') === 0) { $pdo->exec('PRAGMA foreign_keys = ON'); }
    }
    return $pdo;
}
// Todas as consultas usam parâmetros (PDO prepared statements).
function q(string $sql, array $p = []): PDOStatement {
    $s = db()->prepare($sql);
    $s->execute($p);
    return $s;
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
// Entrega "a combinar com o cliente": usa uma data-marca no fim do calendario (sem alterar o banco).
const A_COMBINAR = '9999-12-31';
function data_br(?string $d): string { return !$d ? '' : ($d === A_COMBINAR ? 'A combinar' : date('d/m/Y', strtotime($d))); }
// Prioridade pelo prazo: [texto, classe CSS]. Pedidos entregues/cancelados nao tem prazo.
function prazo(string $d, string $status): array {
    if (in_array($status, ['entregue','cancelado'], true)) { return ['', '']; }
    if ($d === A_COMBINAR) { return ['A combinar', 'p-combinar']; }
    $dias = (int)round((strtotime($d) - strtotime(date('Y-m-d'))) / 86400);
    if ($dias < 0) { return ['Atrasado ' . (-$dias) . ($dias === -1 ? ' dia' : ' dias'), 'p-atrasado']; }
    if ($dias === 0) { return ['Hoje', 'p-hoje']; }
    if ($dias === 1) { return ['Amanhã', 'p-amanha']; }
    if ($dias <= 7) { return ['Em ' . $dias . ' dias', 'p-semana']; }
    return ['', ''];
}
function qtd_fmt($n): string { $n = (float)$n; return rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ','); }
function status_nome(string $s): string {
    return ['aguardando'=>'Aguardando separação','separado'=>'Separado','em_rota'=>'Em rota','entregue'=>'Entregue','com_pendencia'=>'Com pendência','cancelado'=>'Cancelado'][$s] ?? $s;
}

// Data padrão das telas: a mais antiga com pedido pendente (ou hoje, se não houver).
function data_padrao(string $condicao): string {
    $d = q("SELECT MIN(data_entrega) FROM pedidos WHERE data_entrega <> ? AND ($condicao)", [A_COMBINAR])->fetchColumn();
    return $d ?: date('Y-m-d');
}

// Pendencias parciais: a coluna qtd_falta e criada sozinha na primeira vez (nao precisa mexer no banco).
// Valor NULL = faltou o item inteiro (comportamento antigo).
function pendencia_qtd_garante(): void {
    static $ok = false;
    if ($ok) { return; }
    $ok = true;
    try { q('SELECT qtd_falta FROM pendencias LIMIT 1'); }
    catch (Throwable $e) { db()->exec('ALTER TABLE pendencias ADD COLUMN qtd_falta DECIMAL(10,2) NULL'); }
}


// Divergencias de quantidade e fotos do talao: as tabelas sao criadas sozinhas na primeira vez.
function divergencia_garante(): void {
    static $ok = false;
    if ($ok) { return; }
    $ok = true;
    try { q('SELECT resolucao_obs, resolvida_por FROM divergencias LIMIT 1'); return; } catch (Throwable $e) { /* cria ou completa abaixo */ }
    try { q('SELECT 1 FROM divergencias LIMIT 1'); // tabela da versao anterior: acrescenta as colunas do motivo
        db()->exec('ALTER TABLE divergencias ADD COLUMN resolucao_obs ' . (strpos(DB_DSN, 'sqlite') === 0 ? 'TEXT' : 'VARCHAR(255) NULL'));
        db()->exec('ALTER TABLE divergencias ADD COLUMN resolvida_por ' . (strpos(DB_DSN, 'sqlite') === 0 ? 'TEXT' : 'VARCHAR(60) NULL'));
    }
    catch (Throwable $e) {
        $sqlite = strpos(DB_DSN, 'sqlite') === 0;
        db()->exec($sqlite
            ? 'CREATE TABLE IF NOT EXISTS divergencias (id INTEGER PRIMARY KEY AUTOINCREMENT, pedido_id INTEGER NOT NULL, item_id INTEGER NOT NULL, qtd_pedido REAL NOT NULL, qtd_real REAL NOT NULL, observacao TEXT, resolvida INTEGER NOT NULL DEFAULT 0, resolucao TEXT, resolucao_obs TEXT, resolvida_por TEXT, criada_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, resolvida_em TEXT)'
            : 'CREATE TABLE IF NOT EXISTS divergencias (id INT AUTO_INCREMENT PRIMARY KEY, pedido_id INT NOT NULL, item_id INT NOT NULL, qtd_pedido DECIMAL(10,2) NOT NULL, qtd_real DECIMAL(10,2) NOT NULL, observacao VARCHAR(255) NULL, resolvida TINYINT(1) NOT NULL DEFAULT 0, resolucao VARCHAR(20) NULL, resolucao_obs VARCHAR(255) NULL, resolvida_por VARCHAR(60) NULL, criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, resolvida_em DATETIME NULL, INDEX (pedido_id), INDEX (resolvida)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}
function fotos_garante(): void {
    static $ok = false;
    if ($ok) { return; }
    $ok = true;
    try { q('SELECT 1 FROM fotos_pedido LIMIT 1'); }
    catch (Throwable $e) {
        $sqlite = strpos(DB_DSN, 'sqlite') === 0;
        db()->exec($sqlite
            ? 'CREATE TABLE IF NOT EXISTS fotos_pedido (id INTEGER PRIMARY KEY AUTOINCREMENT, pedido_id INTEGER NOT NULL, arquivo TEXT NOT NULL, enviada_por INTEGER, criada_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)'
            : 'CREATE TABLE IF NOT EXISTS fotos_pedido (id INT AUTO_INCREMENT PRIMARY KEY, pedido_id INT NOT NULL, arquivo VARCHAR(60) NOT NULL, enviada_por INT NULL, criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (pedido_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}
// Guarda uma foto enviada (JPG/PNG/WEBP) em inc/fotos/ (pasta bloqueada ao acesso direto), reduzida a 1600 px e sem dados de localizacao.
// Retorna null se deu certo (ou nao veio arquivo) ou a mensagem de erro.
function foto_salvar(int $pedido, array $f, ?int $uid): ?string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 15 * 1024 * 1024) { return 'a foto é grande demais ou não chegou inteira.'; }
    fotos_garante();
    if ((int)q('SELECT COUNT(*) FROM fotos_pedido WHERE pedido_id = ?', [$pedido])->fetchColumn() >= 6) { return 'este pedido já tem 6 fotos (o máximo).'; }
    $info = @getimagesize($f['tmp_name']);
    $tipos = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if (!$info || !isset($tipos[$info[2]])) { return 'use uma imagem JPG, PNG ou WEBP.'; }
    $dir = INC . '/fotos';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { return 'não foi possível criar a pasta das fotos no servidor.'; }
    $nome = bin2hex(random_bytes(12)) . '.jpg';
    $fn = $tipos[$info[2]];
    if (function_exists($fn) && function_exists('imagejpeg')) {
        $im = @$fn($f['tmp_name']);
        if (!$im) { return 'não foi possível ler a imagem.'; }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $ex = @exif_read_data($f['tmp_name']); $o = (int)($ex['Orientation'] ?? 1);
            $ang = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($ang) { $r = imagerotate($im, $ang, 0); if ($r) { $im = $r; } }
        }
        $w = imagesx($im); $hh = imagesy($im); $m = max($w, $hh);
        if ($m > 1600) { $im2 = imagescale($im, (int)round($w * 1600 / $m), (int)round($hh * 1600 / $m)); if ($im2) { $im = $im2; } }
        if (!imagejpeg($im, "$dir/$nome", 82)) { return 'não foi possível gravar a foto.'; }
    } elseif ($info[2] === IMAGETYPE_JPEG) {
        if (!move_uploaded_file($f['tmp_name'], "$dir/$nome") && !@copy($f['tmp_name'], "$dir/$nome")) { return 'não foi possível gravar a foto.'; }
    } else { return 'o servidor não consegue tratar esse formato; use JPG.'; }
    q('INSERT INTO fotos_pedido (pedido_id, arquivo, enviada_por) VALUES (?,?,?)', [$pedido, $nome, $uid]);
    return null;
}

// Vendedores: a tabela e criada sozinha na primeira vez (nao precisa mexer no banco).
function vendedores(): array {
    static $lista = null;
    if ($lista !== null) { return $lista; }
    try { $lista = q('SELECT id, nome FROM vendedores ORDER BY nome')->fetchAll(); }
    catch (Throwable $e) {
        $sqlite = strpos(DB_DSN, 'sqlite') === 0;
        db()->exec($sqlite
            ? 'CREATE TABLE IF NOT EXISTS vendedores (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL UNIQUE)'
            : 'CREATE TABLE IF NOT EXISTS vendedores (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(60) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $lista = [];
    }
    if (!$lista && !(int)q('SELECT COUNT(*) FROM vendedores')->fetchColumn()) {
        // primeira vez: cadastra os vendedores que ja aparecem nos pedidos (ou Ione e Bruno)
        $nomes = q("SELECT DISTINCT vendedor FROM pedidos WHERE vendedor <> '' ORDER BY vendedor")->fetchAll(PDO::FETCH_COLUMN) ?: ['Ione', 'Bruno'];
        foreach ($nomes as $n) { q('INSERT INTO vendedores (nome) VALUES (?)', [$n]); }
        $lista = q('SELECT id, nome FROM vendedores ORDER BY nome')->fetchAll();
    }
    return $lista;
}
