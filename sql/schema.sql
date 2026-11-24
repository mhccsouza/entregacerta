-- Central do Piso – MVP de organização de entregas (MySQL 5.7+/MariaDB)
SET NAMES utf8mb4;
CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  login VARCHAR(40) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  perfil ENUM('admin','balcao','deposito','rota') NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  telefone VARCHAR(30) NULL,
  endereco VARCHAR(200) NOT NULL,
  bairro VARCHAR(80) NULL,
  referencia VARCHAR(150) NULL,
  INDEX (telefone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rotas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data_rota DATE NOT NULL,
  nome VARCHAR(60) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (data_rota)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vendedores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  vendedor VARCHAR(60) NOT NULL,
  data_entrega DATE NOT NULL,
  observacao VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'aguardando', -- aguardando, separado, em_rota, entregue, com_pendencia
  rota_id INT NULL,
  ordem_rota INT NULL,
  criado_por INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (data_entrega), INDEX (status),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (rota_id) REFERENCES rotas(id),
  FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE itens_pedido (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  quantidade DECIMAL(10,2) NOT NULL,
  unidade VARCHAR(10) NOT NULL,
  descricao VARCHAR(150) NOT NULL,
  separado TINYINT(1) NOT NULL DEFAULT 0,
  conferido TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pendencias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  item_id INT NOT NULL,
  observacao VARCHAR(255) NULL,
  qtd_falta DECIMAL(10,2) NULL, -- quanto faltou entregar (NULL = item inteiro)
  resolvida TINYINT(1) NOT NULL DEFAULT 0,
  criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES itens_pedido(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Criadas sozinhas pelo sistema na primeira vez que forem usadas (nao precisa importar):
-- divergencias (quantidade cadastrada x informada na entrega) e fotos_pedido (foto do talao assinado, arquivos em inc/fotos/).
