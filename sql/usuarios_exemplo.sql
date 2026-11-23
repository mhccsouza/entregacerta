-- Gere o hash localmente (substitua SENHA):  php -r "echo password_hash('SENHA', PASSWORD_DEFAULT);"
-- Depois cole o hash abaixo e importe pelo phpMyAdmin. Perfis: admin, balcao, deposito, rota.
INSERT INTO usuarios (nome, login, senha_hash, perfil) VALUES
('Administrador', 'admin',    'COLE_O_HASH_AQUI', 'admin'),
('Atendimento',   'balcao',   'COLE_O_HASH_AQUI', 'balcao'),
('Depósito',      'deposito', 'COLE_O_HASH_AQUI', 'deposito'),
('Rotas',         'rota',     'COLE_O_HASH_AQUI', 'rota');
