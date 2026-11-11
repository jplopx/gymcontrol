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
