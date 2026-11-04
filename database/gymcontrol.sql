-- Criação da Base de Dados
CREATE DATABASE IF NOT EXISTS gymcontrol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gymcontrol;

-- 1. Tabela de Utilizadores (Autenticação unificada para Admin, Professor e Aluno)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'professor', 'aluno') NOT NULL DEFAULT 'aluno',
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabela de Perfil do Aluno (Dados adicionais e vínculo com o Professor)
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
);

-- 3. Tabela de Treinos (Fichas criadas pelos Professores)
CREATE TABLE IF NOT EXISTS treinos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    professor_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL, -- Ex: "Treino A - Peito e Tríceps"
    descricao TEXT,
    data_criacao DATE NOT NULL,
    FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- 4. Tabela de Exercícios vinculados ao Treino
CREATE TABLE IF NOT EXISTS exercicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    treino_id INT NOT NULL,
    nome_exercicio VARCHAR(100) NOT NULL, -- Ex: "Supino Reto"
    series INT NOT NULL DEFAULT 3,
    repeticoes VARCHAR(20) NOT NULL DEFAULT '10 a 12',
    carga VARCHAR(20) NULL, -- Ex: "20kg cada lado"
    observacoes VARCHAR(255) NULL,
    FOREIGN KEY (treino_id) REFERENCES treinos(id) ON DELETE CASCADE
);

-- 5. Tabela de Mensagens (Comunicação direta entre Professor e Aluno)
CREATE TABLE IF NOT EXISTS mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remetente_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    mensagem TEXT NOT NULL,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lida TINYINT(1) DEFAULT 0,
    FOREIGN KEY (remetente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- INSERÇÃO DE DADOS DE TESTE (A senha de todos é: 123456)
-- O hash abaixo foi gerado usando password_hash('123456', PASSWORD_DEFAULT)
INSERT INTO usuarios (id, nome, email, senha, tipo, status) VALUES
(1, 'Administrador Master', 'admin@gymcontrol.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'admin', 'ativo'),
(2, 'Professor Carlos Personal', 'professor@gymcontrol.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'professor', 'ativo'),
(3, 'Aluno Lucas Silva', 'aluno@gymcontrol.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'aluno', 'ativo')
ON DUPLICATE KEY UPDATE id=id;

-- Vincula o aluno Lucas ao Professor Carlos
INSERT INTO alunos (id, usuario_id, professor_id, cpf, status_matricula) VALUES
(1, 3, 2, '123.456.789-00', 'ativa')
ON DUPLICATE KEY UPDATE id=id;