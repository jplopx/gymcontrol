-- ============================================================================
-- GYMCONTROL - BANCO DE DADOS RELACIONAL MULTI-TENANT B2B
-- ARQUITETURA RBAC: aluno, recepcao, loja, professor, admin
-- ============================================================================

CREATE DATABASE IF NOT EXISTS gymcontrol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gymcontrol;

-- ----------------------------------------------------------------------------
-- 1. TABELA DE USUÁRIOS
-- Autenticação unificada com controle de acesso baseado em papéis (RBAC)
-- Suporte a login por E-mail (alunos) e Matrícula/ID (funcionários/admins)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    matricula VARCHAR(30) NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nivel_acesso ENUM('aluno', 'recepcao', 'loja', 'professor', 'admin') NOT NULL DEFAULT 'aluno',
    tipo ENUM('aluno', 'recepcao', 'loja', 'professor', 'admin') NOT NULL DEFAULT 'aluno',
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nivel_acesso (nivel_acesso),
    INDEX idx_matricula (matricula)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- SCRIPT DE MIGRAÇÃO (Para bases de dados já existentes)
-- ----------------------------------------------------------------------------
-- ALTER TABLE usuarios ADD COLUMN matricula VARCHAR(30) NULL UNIQUE AFTER email;
-- ALTER TABLE usuarios ADD COLUMN nivel_acesso ENUM('aluno', 'recepcao', 'loja', 'professor', 'admin') NOT NULL DEFAULT 'aluno' AFTER senha;
-- ALTER TABLE usuarios MODIFY COLUMN tipo ENUM('aluno', 'recepcao', 'loja', 'professor', 'admin') NOT NULL DEFAULT 'aluno';
-- CREATE INDEX idx_nivel_acesso ON usuarios (nivel_acesso);
-- CREATE INDEX idx_matricula ON usuarios (matricula);

-- ----------------------------------------------------------------------------
-- 2. TABELA DE ALUNOS (Dados complementares e vínculo pedagógico)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    professor_id INT NULL,
    cpf VARCHAR(14) UNIQUE,
    data_nascimento DATE,
    telefone VARCHAR(20),
    status_matricula ENUM('ativa', 'pendente', 'bloqueada') DEFAULT 'ativa',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 3. TABELA DE TREINOS (Fichas prescritas por professores)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS treinos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    professor_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL, -- Ex: "Treino A - Peito e Tríceps"
    descricao TEXT,
    data_criacao DATE NOT NULL,
    FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 4. TABELA DE EXERCÍCIOS VINCULADOS AO TREINO
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exercicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    treino_id INT NOT NULL,
    nome_exercicio VARCHAR(100) NOT NULL, -- Ex: "Supino Reto"
    series INT NOT NULL DEFAULT 3,
    repeticoes VARCHAR(20) NOT NULL DEFAULT '10 a 12',
    carga VARCHAR(20) NULL, -- Ex: "20kg cada lado"
    observacoes VARCHAR(255) NULL,
    FOREIGN KEY (treino_id) REFERENCES treinos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 5. TABELA DE MENSAGENS (Comunicação direta Professor <-> Aluno)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remetente_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    mensagem TEXT NOT NULL,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lida TINYINT(1) DEFAULT 0,
    FOREIGN KEY (remetente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- DADOS DE TESTE PARA TODAS AS ROLES RBAC
-- Senha de todos os utilizadores: 123456
-- Hash gerado via password_hash('123456', PASSWORD_DEFAULT)
-- ============================================================================
INSERT INTO usuarios (id, nome, email, matricula, senha, nivel_acesso, tipo, status) VALUES
(1, 'Administrador Master', 'admin@gymcontrol.com', 'ADM001', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'admin', 'admin', 'ativo'),
(2, 'Professor Carlos Personal', 'professor@gymcontrol.com', 'PROF001', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'professor', 'professor', 'ativo'),
(3, 'Aluno Lucas Silva', 'aluno@gymcontrol.com', 'ALU001', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'aluno', 'aluno', 'ativo'),
(4, 'Mariana Recepção', 'recepcao@gymcontrol.com', 'REC001', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'recepcao', 'recepcao', 'ativo'),
(5, 'Nicolas Loja e PDV', 'loja@gymcontrol.com', 'LOJA001', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'loja', 'loja', 'ativo')
ON DUPLICATE KEY UPDATE 
    nome=VALUES(nome),
    matricula=VALUES(matricula),
    nivel_acesso=VALUES(nivel_acesso),
    tipo=VALUES(tipo),
    status=VALUES(status);

-- Vincula o aluno Lucas (id 3) ao Professor Carlos (id 2)
INSERT INTO alunos (id, usuario_id, professor_id, cpf, status_matricula) VALUES
(1, 3, 2, '123.456.789-00', 'ativa')
ON DUPLICATE KEY UPDATE 
    usuario_id=VALUES(usuario_id),
    professor_id=VALUES(professor_id),
    cpf=VALUES(cpf),
    status_matricula=VALUES(status_matricula);
    
    USE gymcontrol;

USE gymcontrol;

SHOW CREATE TABLE treinos;

SHOW CREATE TABLE exercicios;

USE gymcontrol;

DESCRIBE treinos;


CREATE TABLE IF NOT EXISTS exercicios_catalogo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    grupo_muscular VARCHAR(50) NOT NULL,
    equipamento VARCHAR(80) DEFAULT NULL,
    UNIQUE KEY uq_exercicio_catalogo (nome, grupo_muscular)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


USE gymcontrol;

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


SELECT * FROM exercicios_catalogo;


USE gymcontrol;

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


SELECT grupo_muscular, COUNT(*) AS quantidade
FROM exercicios_catalogo
GROUP BY grupo_muscular
ORDER BY grupo_muscular;

SELECT COUNT(*) AS total_exercicios
FROM exercicios_catalogo;




