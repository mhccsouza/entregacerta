<?php
// Configuração. Em produção, crie inc/config.local.php (fora do Git) com as credenciais reais.
if (is_file(__DIR__ . '/config.local.php')) { require __DIR__ . '/config.local.php'; }
defined('DB_DSN')  || define('DB_DSN',  'mysql:host=localhost;dbname=SEU_BANCO;charset=utf8mb4');
defined('DB_USER') || define('DB_USER', 'SEU_USUARIO');
defined('DB_PASS') || define('DB_PASS', 'SUA_SENHA');
defined('NOME_SISTEMA') || define('NOME_SISTEMA', 'EntregaCerta');   // nome do produto
defined('NOME_LOJA')    || define('NOME_LOJA', 'Central do Piso');    // troque para outra loja
date_default_timezone_set('America/Bahia');
