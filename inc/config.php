<?php
// Configuração. Em produção, crie inc/config.local.php (fora do Git) com as credenciais reais.
if (is_file(__DIR__ . '/config.local.php')) { require __DIR__ . '/config.local.php'; }
defined('DB_DSN')  || define('DB_DSN',  'mysql:host=localhost;dbname=SEU_BANCO;charset=utf8mb4');
defined('DB_USER') || define('DB_USER', 'SEU_USUARIO');
defined('DB_PASS') || define('DB_PASS', 'SUA_SENHA');
defined('NOME_SISTEMA') || define('NOME_SISTEMA', 'EntregaCerta');   // nome do produto
defined('NOME_LOJA')    || define('NOME_LOJA', 'Central do Piso');    // troque para outra loja
date_default_timezone_set('America/Bahia');

// Em vez de tela em branco, mostra um aviso e grava o motivo no error_log da hospedagem.
set_exception_handler(function (Throwable $e) {
    error_log('EntregaCerta: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { http_response_code(500); }
    echo '<div style="font-family:system-ui;max-width:560px;margin:30px auto;padding:16px;border:1px solid #fca5a5;background:#fef2f2;border-radius:10px;color:#7f1d1d"><strong>Não foi possível carregar esta tela.</strong><br>Atualize a página. Se continuar, avise o administrador e informe o horário: ' . date('d/m/Y H:i') . '.</div>';
});
