<?php // uso: php tests/sql.php arquivo.sql | php tests/sql.php -q "SELECT ..."
$pdo = new PDO('sqlite:/tmp/t.db'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if ($argv[1] === '-q') { foreach ($pdo->query($argv[2]) as $r) { echo implode('|', array_filter($r, 'is_string', ARRAY_FILTER_USE_KEY)), "\n"; } }
else { $pdo->exec(file_get_contents($argv[1])); }
