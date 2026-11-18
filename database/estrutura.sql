-- GymControl | Estrutura única compatível com as páginas PHP (MySQL 8/MariaDB 10.4+).
-- USO: importar database/gymcontrol.sql em instalação NOVA.
-- BASE EXISTENTE: usar php database/migrar.php (CLI); NÃO reimportar os dados de demonstração.
-- Nenhum DROP TABLE, TRUNCATE ou exclusão de registros.
CREATE DATABASE IF NOT EXISTS gymcontrol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gymcontrol;

CREATE TABLE IF NOT EXISTS academias (
 id INT AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(8) NOT NULL UNIQUE,
 nome VARCHAR(150) NOT NULL,
 status ENUM('ativa','inativa') NOT NULL DEFAULT 'ativa',
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 plano VARCHAR(60) NOT NULL DEFAULT 'Não informado',
 contato_email VARCHAR(150) NULL,
 contato_whatsapp VARCHAR(30) NULL,
 gestor_nome VARCHAR(120) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO academias(id,nome,status,codigo) VALUES (1,'GymControl','ativa',CAST(FLOOR(10000000 + RAND() * 90000000) AS CHAR))
 ON DUPLICATE KEY UPDATE id=VALUES(id);

CREATE TABLE IF NOT EXISTS usuarios (
 id INT AUTO_INCREMENT PRIMARY KEY,
 academia_id INT NOT NULL,
 nome VARCHAR(100) NOT NULL,
 email VARCHAR(100) NOT NULL,
 matricula VARCHAR(30) NULL,
 senha VARCHAR(255) NOT NULL,
 nivel_acesso ENUM('aluno','recepcao','loja','professor','admin') NOT NULL DEFAULT 'aluno',
 tipo ENUM('aluno','recepcao','loja','professor','admin') NOT NULL DEFAULT 'aluno',
 status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
 trocar_senha TINYINT(1) NOT NULL DEFAULT 0,
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_usuario_email_academia(academia_id,email),
 UNIQUE KEY uq_usuario_matricula_academia(academia_id,matricula),
 KEY idx_usuario_academia(academia_id),
 CONSTRAINT fk_gym_usuario_academia FOREIGN KEY(academia_id) REFERENCES academias(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS alunos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 academia_id INT NOT NULL,
 usuario_id INT NOT NULL UNIQUE,
 professor_id INT NULL,
 cpf VARCHAR(14) NULL,
 UNIQUE KEY uq_aluno_cpf_academia(academia_id,cpf),
 data_nascimento DATE NULL,
 telefone VARCHAR(20) NULL,
 status_matricula ENUM('ativa','pendente','bloqueada') NOT NULL DEFAULT 'ativa',
 CONSTRAINT fk_gym_aluno_academia FOREIGN KEY(academia_id) REFERENCES academias(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gym_aluno_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gym_aluno_professor FOREIGN KEY(professor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS treinos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 aluno_id INT NOT NULL,
 professor_id INT NOT NULL,
 titulo VARCHAR(100) NOT NULL,
 descricao TEXT NULL,
 data_criacao DATE NOT NULL,
 KEY idx_treinos_aluno(aluno_id),
 CONSTRAINT fk_gym_treino_aluno FOREIGN KEY(aluno_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gym_treino_professor FOREIGN KEY(professor_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exercicios (
 id INT AUTO_INCREMENT PRIMARY KEY,
 treino_id INT NOT NULL,
 nome_exercicio VARCHAR(100) NOT NULL,
 series INT NOT NULL DEFAULT 3,
 repeticoes VARCHAR(20) NOT NULL DEFAULT '10 a 12',
 carga VARCHAR(20) NULL,
 observacoes VARCHAR(255) NULL,
 CONSTRAINT fk_gym_exercicio_treino FOREIGN KEY(treino_id) REFERENCES treinos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exercicios_catalogo (
 id INT AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(100) NOT NULL,
 grupo_muscular VARCHAR(50) NOT NULL,
 equipamento VARCHAR(80) DEFAULT NULL,
 UNIQUE KEY uq_exercicio_catalogo(nome,grupo_muscular)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- GymControl: modelos reutilizáveis de ficha. Não altera nem remove fichas/alunos existentes.
-- Execute no banco gymcontrol após realizar backup.
CREATE TABLE IF NOT EXISTS treino_modelos (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    academia_id INT NOT NULL,
    professor_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_modelos_prof_academia (professor_id, academia_id),
    CONSTRAINT fk_gc_modelos_academia FOREIGN KEY (academia_id) REFERENCES academias(id) ON DELETE RESTRICT,
    CONSTRAINT fk_gc_modelos_professor FOREIGN KEY (professor_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS treino_modelo_exercicios (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    modelo_id INT NOT NULL,
    posicao SMALLINT UNSIGNED NOT NULL,
    nome_exercicio VARCHAR(100) NOT NULL,
    series INT NOT NULL,
    repeticoes VARCHAR(20) NOT NULL,
    carga VARCHAR(20) NULL,
    observacoes VARCHAR(255) NULL,
    KEY idx_modelo_ordem (modelo_id, posicao),
    CONSTRAINT fk_gc_modelo_exercicios FOREIGN KEY (modelo_id) REFERENCES treino_modelos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mensagens (
 id INT AUTO_INCREMENT PRIMARY KEY,
 remetente_id INT NOT NULL,
 destinatario_id INT NOT NULL,
 mensagem TEXT NOT NULL,
 data_envio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 lida TINYINT(1) NOT NULL DEFAULT 0,
 KEY idx_conversa(remetente_id,destinatario_id,data_envio),
 CONSTRAINT fk_gym_msg_remetente FOREIGN KEY(remetente_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gym_msg_destinatario FOREIGN KEY(destinatario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agendamentos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 aluno_id INT NOT NULL,
 professor_id INT NOT NULL,
 titulo VARCHAR(150) NOT NULL,
 tipo ENUM('aula','avaliacao','acompanhamento','outro') NOT NULL DEFAULT 'aula',
 data_agendamento DATE NOT NULL,
 hora_inicio TIME NOT NULL,
 hora_fim TIME NOT NULL,
 observacoes TEXT NULL,
 status ENUM('agendado','cancelado','concluido') NOT NULL DEFAULT 'agendado',
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_agenda_professor(professor_id,data_agendamento,hora_inicio),
 CONSTRAINT fk_gym_agenda_aluno FOREIGN KEY(aluno_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gym_agenda_professor FOREIGN KEY(professor_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS avaliacoes_fisicas (
 id INT AUTO_INCREMENT PRIMARY KEY,
 aluno_id INT NOT NULL,
 professor_id INT NOT NULL,
 data_avaliacao DATE NOT NULL,
 peso DECIMAL(6,2) NULL,
 altura DECIMAL(4,2) NULL,
 peito DECIMAL(6,2) NULL,
 cintura DECIMAL(6,2) NULL,
 abdomen DECIMAL(6,2) NULL,
 quadril DECIMAL(6,2) NULL,
 braco_direito DECIMAL(6,2) NULL,
 braco_esquerdo DECIMAL(6,2) NULL,
 coxa_direita DECIMAL(6,2) NULL,
 coxa_esquerda DECIMAL(6,2) NULL,
 percentual_gordura DECIMAL(5,2) NULL,
 observacoes TEXT NULL,
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_avaliacao_aluno(aluno_id,data_avaliacao),
 CONSTRAINT fk_gym_aval_aluno FOREIGN KEY(aluno_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gym_aval_professor FOREIGN KEY(professor_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS progresso_exercicios (
 id INT AUTO_INCREMENT PRIMARY KEY,
 aluno_id INT NOT NULL,
 exercicio_id INT NOT NULL,
 data_treino DATE NOT NULL,
 concluido TINYINT(1) NOT NULL DEFAULT 0,
 atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_progresso(aluno_id,exercicio_id,data_treino),
 CONSTRAINT fk_gym_progresso_aluno FOREIGN KEY(aluno_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gym_progresso_exercicio FOREIGN KEY(exercicio_id) REFERENCES exercicios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gamificacao_treinos_concluidos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 aluno_id INT NOT NULL,
 treino_id INT NOT NULL,
 data_treino DATE NOT NULL,
 xp INT UNSIGNED NOT NULL DEFAULT 100,
 UNIQUE KEY uq_treino_dia(aluno_id,treino_id,data_treino),
 CONSTRAINT fk_gym_game_aluno FOREIGN KEY(aluno_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gym_game_treino FOREIGN KEY(treino_id) REFERENCES treinos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gamificacao_desafios (
 id INT AUTO_INCREMENT PRIMARY KEY,
 professor_id INT NOT NULL,
 aluno_id INT NOT NULL,
 titulo VARCHAR(150) NOT NULL,
 descricao TEXT NULL,
 tipo ENUM('fichas','dias') NOT NULL,
 meta INT UNSIGNED NOT NULL,
 xp_recompensa INT UNSIGNED NOT NULL,
 data_inicio DATE NOT NULL,
 data_fim DATE NOT NULL,
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_desafio_aluno(aluno_id),
 CONSTRAINT fk_gym_desafio_prof FOREIGN KEY(professor_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gym_desafio_aluno FOREIGN KEY(aluno_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gamificacao_desafios_recompensas (
 desafio_id INT PRIMARY KEY,
 aluno_id INT NOT NULL,
 xp INT UNSIGNED NOT NULL,
 concedido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_bonus_aluno(aluno_id),
 CONSTRAINT fk_gym_bonus_desafio FOREIGN KEY(desafio_id) REFERENCES gamificacao_desafios(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gym_bonus_aluno FOREIGN KEY(aluno_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gamificacao_ranking_participacao (
 aluno_id INT PRIMARY KEY,
 participa TINYINT(1) NOT NULL DEFAULT 0,
 CONSTRAINT fk_gym_ranking_aluno FOREIGN KEY(aluno_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS recepcao_matriculas (
 id INT AUTO_INCREMENT PRIMARY KEY,
 academia_id INT NOT NULL,
 aluno_id INT NOT NULL UNIQUE,
 plano VARCHAR(30) NOT NULL,
 forma_pagamento_prevista VARCHAR(20) NOT NULL,
 registrado_por INT NOT NULL,
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_gc_recepcao_academia FOREIGN KEY(academia_id) REFERENCES academias(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gc_recepcao_aluno FOREIGN KEY(aluno_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gc_recepcao_usuario FOREIGN KEY(registrado_por) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS loja_produtos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 academia_id INT NOT NULL,
 nome VARCHAR(150) NOT NULL,
 marca VARCHAR(100) NULL,
 gramatura_g INT UNSIGNED NULL,
 foto VARCHAR(255) NULL,
 preco DECIMAL(10,2) NOT NULL,
 estoque INT UNSIGNED NOT NULL DEFAULT 0,
 ativo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_loja_produtos(academia_id,nome),
 CONSTRAINT fk_gc_loja_produto_academia FOREIGN KEY(academia_id) REFERENCES academias(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loja_vendas (
 id INT AUTO_INCREMENT PRIMARY KEY,
 academia_id INT NOT NULL,
 vendedor_id INT NOT NULL,
 aluno_id INT NULL,
 forma_pagamento VARCHAR(20) NOT NULL,
 total DECIMAL(12,2) NOT NULL,
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_gc_loja_venda_academia FOREIGN KEY(academia_id) REFERENCES academias(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gc_loja_venda_vendedor FOREIGN KEY(vendedor_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gc_loja_venda_aluno FOREIGN KEY(aluno_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loja_itens_venda (
 id INT AUTO_INCREMENT PRIMARY KEY,
 venda_id INT NOT NULL,
 produto_id INT NOT NULL,
 quantidade INT UNSIGNED NOT NULL,
 preco_unitario DECIMAL(10,2) NOT NULL,
 CONSTRAINT fk_gc_loja_item_venda FOREIGN KEY(venda_id) REFERENCES loja_vendas(id) ON DELETE RESTRICT,
 CONSTRAINT fk_gc_loja_item_produto FOREIGN KEY(produto_id) REFERENCES loja_produtos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Autoatendimento: estruturas necessárias também em instalações novas.
CREATE TABLE IF NOT EXISTS loja_totens (
  academia_id INT NOT NULL PRIMARY KEY,
  token_hash CHAR(64) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_totem_token (token_hash),
  CONSTRAINT fk_loja_totem_academia FOREIGN KEY (academia_id) REFERENCES academias(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loja_pedidos_totem (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academia_id INT NOT NULL,
  status ENUM('pendente','concluido','cancelado') NOT NULL DEFAULT 'pendente',
  total DECIMAL(12,2) NOT NULL,
  venda_id INT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pedidos_academia (academia_id,status,criado_em),
  CONSTRAINT fk_pedido_academia FOREIGN KEY (academia_id) REFERENCES academias(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pedido_venda FOREIGN KEY (venda_id) REFERENCES loja_vendas(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loja_pedidos_itens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id BIGINT UNSIGNED NOT NULL,
  produto_id INT NOT NULL,
  quantidade INT UNSIGNED NOT NULL,
  preco_unitario DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_pedido_itens_pedido FOREIGN KEY (pedido_id) REFERENCES loja_pedidos_totem(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pedido_itens_produto FOREIGN KEY (produto_id) REFERENCES loja_produtos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cada academia cadastra seus produtos pelo PDV; não inserir estoque de demonstração.
CREATE TABLE IF NOT EXISTS contatos_interesse (
 id INT AUTO_INCREMENT PRIMARY KEY,
 nome_academia VARCHAR(150) NOT NULL,
 responsavel VARCHAR(120) NOT NULL,
 email VARCHAR(150) NOT NULL,
 telefone VARCHAR(30) NOT NULL,
 plano_interesse VARCHAR(60) NOT NULL,
 estimativa_alunos VARCHAR(30) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pendente',
 academia_id INT NULL,
 analisado_por INT NULL,
 analisado_em DATETIME NULL,
 observacao VARCHAR(500) NULL,
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Catálogo global de exercícios (não contém dados pessoais).
INSERT IGNORE INTO exercicios_catalogo
(nome, grupo_muscular, equipamento)
VALUES
('Supino Reto com Barra', 'Peito', 'Barra'),
('Supino Inclinado com Halteres', 'Peito', 'Halteres'),
('Supino Declinado', 'Peito', 'Barra'),
('Crucifixo Reto', 'Peito', 'Halteres'),
('Crucifixo Inclinado', 'Peito', 'Halteres'),
('Crossover na Polia', 'Peito', 'Polia'),
('Peck Deck', 'Peito', 'Máquina'),
('Flexão de Braços', 'Peito', 'Peso corporal'),
('Chest Press', 'Peito', 'Máquina'),
('Pullover com Halter', 'Peito', 'Halter');

INSERT IGNORE INTO exercicios_catalogo
(nome, grupo_muscular, equipamento)
VALUES
-- COSTAS
('Puxada Frontal', 'Costas', 'Polia'),
('Puxada Supinada', 'Costas', 'Polia'),
('Puxada Neutra', 'Costas', 'Polia'),
('Puxada Unilateral', 'Costas', 'Polia'),
('Barra Fixa Pronada', 'Costas', 'Barra fixa'),
('Barra Fixa Supinada', 'Costas', 'Barra fixa'),
('Remada Curvada', 'Costas', 'Barra'),
('Remada Cavalinho', 'Costas', 'Máquina'),
('Remada Baixa', 'Costas', 'Polia'),
('Remada Unilateral', 'Costas', 'Halter'),
('Remada Articulada', 'Costas', 'Máquina'),
('Remada Serrote', 'Costas', 'Halter'),
('Pulldown', 'Costas', 'Polia'),
('Pullover na Polia', 'Costas', 'Polia'),
('Levantamento Terra', 'Costas', 'Barra'),

-- QUADRÍCEPS
('Agachamento Livre', 'Quadríceps', 'Barra'),
('Agachamento Smith', 'Quadríceps', 'Smith'),
('Agachamento Frontal', 'Quadríceps', 'Barra'),
('Agachamento Goblet', 'Quadríceps', 'Halter'),
('Leg Press 45', 'Quadríceps', 'Máquina'),
('Leg Press Horizontal', 'Quadríceps', 'Máquina'),
('Cadeira Extensora', 'Quadríceps', 'Máquina'),
('Afundo com Halteres', 'Quadríceps', 'Halteres'),
('Passada com Halteres', 'Quadríceps', 'Halteres'),
('Agachamento Hack', 'Quadríceps', 'Máquina'),
('Agachamento Búlgaro', 'Quadríceps', 'Halteres'),
('Step Up', 'Quadríceps', 'Banco'),
('Sissy Squat', 'Quadríceps', 'Peso corporal'),

-- POSTERIOR DE COXA
('Mesa Flexora', 'Posterior de Coxa', 'Máquina'),
('Cadeira Flexora', 'Posterior de Coxa', 'Máquina'),
('Flexora em Pé', 'Posterior de Coxa', 'Máquina'),
('Stiff com Barra', 'Posterior de Coxa', 'Barra'),
('Stiff com Halteres', 'Posterior de Coxa', 'Halteres'),
('Terra Romeno', 'Posterior de Coxa', 'Barra'),
('Nordic Curl', 'Posterior de Coxa', 'Peso corporal'),
('Good Morning', 'Posterior de Coxa', 'Barra'),

-- GLÚTEOS
('Elevação Pélvica', 'Glúteos', 'Barra'),
('Glúteo na Polia', 'Glúteos', 'Polia'),
('Glúteo na Máquina', 'Glúteos', 'Máquina'),
('Cadeira Abdutora', 'Glúteos', 'Máquina'),
('Abdução com Elástico', 'Glúteos', 'Elástico'),
('Ponte de Glúteos', 'Glúteos', 'Peso corporal'),
('Coice de Glúteos', 'Glúteos', 'Peso corporal'),
('Agachamento Sumô', 'Glúteos', 'Halter'),
('Afundo Reverso', 'Glúteos', 'Halteres'),

-- PANTURRILHAS
('Panturrilha em Pé', 'Panturrilhas', 'Máquina'),
('Panturrilha Sentado', 'Panturrilhas', 'Máquina'),
('Panturrilha no Leg Press', 'Panturrilhas', 'Máquina'),
('Panturrilha no Smith', 'Panturrilhas', 'Smith'),
('Panturrilha Unilateral', 'Panturrilhas', 'Peso corporal'),
('Panturrilha no Degrau', 'Panturrilhas', 'Peso corporal'),

-- OMBROS
('Desenvolvimento com Halteres', 'Ombros', 'Halteres'),
('Desenvolvimento com Barra', 'Ombros', 'Barra'),
('Desenvolvimento Arnold', 'Ombros', 'Halteres'),
('Desenvolvimento na Máquina', 'Ombros', 'Máquina'),
('Elevação Lateral', 'Ombros', 'Halteres'),
('Elevação Frontal', 'Ombros', 'Halteres'),
('Elevação Lateral na Polia', 'Ombros', 'Polia'),
('Crucifixo Inverso', 'Ombros', 'Halteres'),
('Crucifixo Inverso na Máquina', 'Ombros', 'Máquina'),
('Face Pull', 'Ombros', 'Polia'),
('Remada Alta', 'Ombros', 'Barra'),
('Elevação Frontal na Polia', 'Ombros', 'Polia'),

-- BÍCEPS
('Rosca Direta', 'Bíceps', 'Barra'),
('Rosca Alternada', 'Bíceps', 'Halteres'),
('Rosca Martelo', 'Bíceps', 'Halteres'),
('Rosca Concentrada', 'Bíceps', 'Halter'),
('Rosca Scott', 'Bíceps', 'Máquina'),
('Rosca Scott com Barra', 'Bíceps', 'Barra'),
('Rosca na Polia', 'Bíceps', 'Polia'),
('Rosca Inclinada', 'Bíceps', 'Halteres'),
('Rosca Spider', 'Bíceps', 'Halteres'),
('Rosca 21', 'Bíceps', 'Barra'),

-- TRÍCEPS
('Tríceps Corda', 'Tríceps', 'Polia'),
('Tríceps Barra', 'Tríceps', 'Polia'),
('Tríceps Francês', 'Tríceps', 'Halter'),
('Tríceps Testa', 'Tríceps', 'Barra'),
('Tríceps Coice', 'Tríceps', 'Halter'),
('Tríceps Unilateral na Polia', 'Tríceps', 'Polia'),
('Tríceps Banco', 'Tríceps', 'Banco'),
('Tríceps Máquina', 'Tríceps', 'Máquina'),
('Tríceps Acima da Cabeça', 'Tríceps', 'Polia'),
('Mergulho nas Paralelas', 'Tríceps', 'Paralelas'),

-- ABDÔMEN
('Abdominal Supra', 'Abdômen', 'Peso corporal'),
('Abdominal Infra', 'Abdômen', 'Peso corporal'),
('Abdominal Bicicleta', 'Abdômen', 'Peso corporal'),
('Abdominal Oblíquo', 'Abdômen', 'Peso corporal'),
('Abdominal na Polia', 'Abdômen', 'Polia'),
('Abdominal na Máquina', 'Abdômen', 'Máquina'),
('Elevação de Pernas', 'Abdômen', 'Barra fixa'),
('Prancha Isométrica', 'Abdômen', 'Peso corporal'),
('Prancha Lateral', 'Abdômen', 'Peso corporal'),
('Abdominal Canivete', 'Abdômen', 'Peso corporal'),
('Abdominal Remador', 'Abdômen', 'Peso corporal'),
('Abdominal com Roda', 'Abdômen', 'Roda abdominal'),

-- ANTEBRAÇOS
('Rosca Punho', 'Antebraços', 'Barra'),
('Rosca Punho Inversa', 'Antebraços', 'Barra'),
('Rosca Inversa', 'Antebraços', 'Barra'),
('Farmer Walk', 'Antebraços', 'Halteres'),
('Suspensão na Barra', 'Antebraços', 'Barra fixa'),

-- ADUTORES
('Cadeira Adutora', 'Adutores', 'Máquina'),
('Adução na Polia', 'Adutores', 'Polia'),
('Adução com Bola', 'Adutores', 'Bola');

-- ATUALIZAÇÃO ADITIVA PARA GESTÃO GLOBAL | executar preferencialmente: php database/migrar_superadmin.php
-- Estes comandos CREATE TABLE são seguros para reexecução. As colunas adicionais são aplicadas pelo migrador PHP.
CREATE TABLE IF NOT EXISTS superadmins (
 id INT AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(120) NOT NULL,
 email VARCHAR(150) NOT NULL UNIQUE,
 senha VARCHAR(255) NOT NULL,
 ativo TINYINT(1) NOT NULL DEFAULT 1,
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS superadmin_auditoria (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 superadmin_id INT NOT NULL,
 acao VARCHAR(60) NOT NULL,
 academia_id INT NULL,
 solicitacao_id INT NULL,
 detalhes VARCHAR(255) NULL,
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_auditoria_data (criado_em),
 CONSTRAINT fk_audit_super FOREIGN KEY (superadmin_id) REFERENCES superadmins(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ATUALIZAÇÃO ADITIVA PARA GESTÃO GLOBAL | executar preferencialmente: php database/migrar_superadmin.php
-- Estes comandos CREATE TABLE são seguros para reexecução. As colunas adicionais são aplicadas pelo migrador PHP.
CREATE TABLE IF NOT EXISTS superadmins (
 id INT AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(120) NOT NULL,
 email VARCHAR(150) NOT NULL UNIQUE,
 senha VARCHAR(255) NOT NULL,
 ativo TINYINT(1) NOT NULL DEFAULT 1,
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS superadmin_auditoria (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 superadmin_id INT NOT NULL,
 acao VARCHAR(60) NOT NULL,
 academia_id INT NULL,
 solicitacao_id INT NULL,
 detalhes VARCHAR(255) NULL,
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_auditoria_data (criado_em),
 CONSTRAINT fk_audit_super FOREIGN KEY (superadmin_id) REFERENCES superadmins(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
