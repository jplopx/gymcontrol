CREATE DATABASE gymcontrol
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
USE gymcontrol;
SELECT DATABASE();

CREATE TABLE academias(
	id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR (150) not null,
    cnpj VARCHAR(18),
    telefone VARCHAR(20),
    email VARCHAR(150),
    endereco VARCHAR (255),
    cidade VARCHAR (100),
    estado VARCHAR (2),
    logo VARCHAR (255),
    cor_principal VARCHAR(20),
    cor_secundaria VARCHAR(20),
    usar_tema_padrao BOOLEAN default true,
    status ENUM ('ATIVA', 'INATIVA') DEFAULT 'ATIVA',
    created_at TIMESTAMP default current_timestamp,
    updated_at TIMESTAMP default current_timestamp on update current_timestamp
    );
    SHOW TABLES;
    
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academia_id INT,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('SUPER_ADMIN', 'ADMIN', 'PROFESSOR', 'RECEPCAO', 'ALUNO') NOT NULL,
    status ENUM('ATIVO', 'INATIVO') DEFAULT 'ATIVO',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_usuario_academia
        FOREIGN KEY (academia_id)
        REFERENCES academias(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

SHOW TABLES;
DESCRIBE usuarios;

CREATE TABLE alunos (
id INT AUTO_INCREMENT PRIMARY KEY,
academia_id INT not null, 
usuario_id INT unique, 

nome VARCHAR (150) not null,
cpf VARCHAR(14) not null, 
data_nascimento DATE, 
telefone VARCHAR(20),
email VARCHAR(150),

endereco VARCHAR(255),
cidade VARCHAR(100),
estado VARCHAR(2),

contato_emergencia_nome VARCHAR(150),
contato_emergencia_telefone VARCHAR(20),

foto VARCHAR(255),

status ENUM('ATIVO', 'INATIVO') DEFAULT 'ATIVO',

created_at timestamp default current_timestamp,
updated_at timestamp default current_timestamp on update current_timestamp,

CONSTRAINT fk_aluno_academia
	FOREIGN KEY (academia_id)
    REFERENCES academias(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
    
    CONSTRAINT fk_aluno_usuario
		FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
        );
        
        SHOW TABLES;
        DESCRIBE alunos;
        
CREATE TABLE professores (
		id INT auto_increment PRIMARY KEY,
		academia_id INT NOT NULL,
		usuario_id INT UNIQUE,
            
		nome VARCHAR(150) not null,
		cpf VARCHAR(14) not null,
		cref VARCHAR(30),
		data_nascimento DATE,
		telefone VARCHAR(20),
		email VARCHAR(150),
            
		especialidade VARCHAR(150),
		foto VARCHAR(255),
            
		status ENUM('ATIVO', 'INATIVO') DEFAULT 'ATIVO',
            
		created_at timestamp default current_timestamp,
		updated_at timestamp default current_timestamp on update current_timestamp,
            
		constraint fk_professor_academia
			foreign key (academia_id)
			references academias(id)
			ON DELETE CASCADE
			ON UPDATE CASCADE,
                
			CONSTRAINT fk_professor_usuario
				foreign key (usuario_id)
				references usuarios(id)
				ON DELETE SET NULL
				ON UPDATE CASCADE
);
                    
SHOW TABLES;
DESCRIBE professores;

CREATE TABLE planos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academia_id INT NOT NULL,

    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    valor DECIMAL(10,2) NOT NULL,
    
    tipo ENUM(
        'MENSAL',
        'TRIMESTRAL',
        'SEMESTRAL',
        'ANUAL',
        'DIARIA',
        'CONVENIO'
    ) NOT NULL DEFAULT 'MENSAL',

    status ENUM('ATIVO', 'INATIVO') DEFAULT 'ATIVO',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_plano_academia
        FOREIGN KEY (academia_id)
        REFERENCES academias(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
				
SHOW TABLES;
DESCRIBE planos;

CREATE TABLE matriculas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academia_id INT NOT NULL,
    aluno_id INT NOT NULL,
    plano_id INT NOT NULL,

    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,

    valor_contratado DECIMAL(10,2) NOT NULL,

    status ENUM(
        'ATIVA',
        'PENDENTE',
        'VENCIDA',
        'SUSPENSA',
        'CANCELADA'
    ) DEFAULT 'ATIVA',

    observacoes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_matricula_academia
        FOREIGN KEY (academia_id)
        REFERENCES academias(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_matricula_aluno
        FOREIGN KEY (aluno_id)
        REFERENCES alunos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_matricula_plano
        FOREIGN KEY (plano_id)
        REFERENCES planos(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);
SHOW TABLES;

CREATE TABLE pagamentos (
	id INT auto_increment primary key,
    academia_id INT not null,
    matricula_id INT not null,
    aluno_id INT not null,
    
    descricao VARCHAR(255),
    valor DECIMAL(10,2) not null,
    
    data_vencimento DATE not null,
    data_pagamento DATE,
    
    status ENUM(
		'PENDENTE',
        'PAGO',
        'VENCIDO',
        'CANCELADO'
	) DEFAULT 'PENDENTE',
    
	forma_pagamento ENUM(
		'DINHEIRO',
        'PIX',
        'CARTAO_CREDITO',
        'CARTAO_DEBITO',
        'BOLETO',
        'CONVENIO',
        'OUTRO'
	),
    
	observacoes TEXT,
    
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    
    CONSTRAINT fk_pagamento_academia
		FOREIGN KEY (academia_id)
        REFERENCES academias(id)
        ON DELETE CASCADE	
        ON UPDATE CASCADE,
        
	CONSTRAINT fk_pagamentos_matricula
		FOREIGN KEY (matricula_id)
        REFERENCES matriculas(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
        
	CONSTRAINT fk_pagamento_aluno
		FOREIGN KEY (aluno_id)
        REFERENCES alunos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
	);

SHOW TABLES;
DESCRIBE pagamentos;

USE gymcontrol;

CREATE TABLE avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academia_id INT NOT NULL,
    aluno_id INT NOT NULL,
    professor_id INT NOT NULL,

    data_avaliacao DATE NOT NULL,

    peso DECIMAL(5,2),
    altura DECIMAL(4,2),
    imc DECIMAL(5,2),
    percentual_gordura DECIMAL(5,2),

    peito DECIMAL(5,2),
    cintura DECIMAL(5,2),
    quadril DECIMAL(5,2),
    braco DECIMAL(5,2),
    coxa DECIMAL(5,2),
    panturrilha DECIMAL(5,2),

    observacoes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_avaliacao_academia
        FOREIGN KEY (academia_id)
        REFERENCES academias(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_avaliacao_aluno
        FOREIGN KEY (aluno_id)
        REFERENCES alunos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_avaliacao_professor
        FOREIGN KEY (professor_id)
        REFERENCES professores(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

SHOW TABLES;
DESCRIBE avaliacoes;

USE gymcontrol;

CREATE TABLE exercicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academia_id INT NOT NULL,

    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    grupo_muscular VARCHAR(100),
    equipamento VARCHAR(100),
    video_url VARCHAR(255),

    status ENUM('ATIVO', 'INATIVO') DEFAULT 'ATIVO',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_exercicio_academia
        FOREIGN KEY (academia_id)
        REFERENCES academias(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE treinos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academia_id INT NOT NULL,
    aluno_id INT NOT NULL,
    professor_id INT NOT NULL,

    nome VARCHAR(150) NOT NULL,
    objetivo VARCHAR(255),
    observacoes TEXT,

    data_inicio DATE,
    data_fim DATE,

    status ENUM('ATIVO', 'INATIVO') DEFAULT 'ATIVO',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_treino_academia
        FOREIGN KEY (academia_id)
        REFERENCES academias(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_treino_aluno
        FOREIGN KEY (aluno_id)
        REFERENCES alunos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_treino_professor
        FOREIGN KEY (professor_id)
        REFERENCES professores(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE TABLE treino_exercicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    treino_id INT NOT NULL,
    exercicio_id INT NOT NULL,

    ordem INT NOT NULL DEFAULT 1,

    series INT,
    repeticoes INT,
    carga DECIMAL(6,2),
    descanso_segundos INT,

    observacoes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_treino_exercicio_treino
        FOREIGN KEY (treino_id)
        REFERENCES treinos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_treino_exercicio_exercicio
        FOREIGN KEY (exercicio_id)
        REFERENCES exercicios(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

SHOW TABLES;
DESCRIBE exercicios;

USE gymcontrol;

CREATE TABLE agendamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academia_id INT NOT NULL,
    aluno_id INT NOT NULL,
    professor_id INT,

    titulo VARCHAR(150) NOT NULL,
    descricao TEXT,

    data_agendamento DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME,

    status ENUM(
        'AGENDADO',
        'CONFIRMADO',
        'REALIZADO',
        'CANCELADO'
    ) DEFAULT 'AGENDADO',

    observacoes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_agendamento_academia
        FOREIGN KEY (academia_id)
        REFERENCES academias(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_agendamento_aluno
        FOREIGN KEY (aluno_id)
        REFERENCES alunos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_agendamento_professor
        FOREIGN KEY (professor_id)
        REFERENCES professores(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

SHOW TABLES;
describe agendamentos;

USE gymcontrol;

CREATE TABLE acessos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academia_id INT NOT NULL,
    aluno_id INT,

    data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    tipo_verificacao ENUM(
        'FACIAL',
        'DIGITAL',
        'QR_CODE',
        'MANUAL'
    ) NOT NULL,

    resultado ENUM(
        'LIBERADO',
        'NEGADO'
    ) NOT NULL,

    motivo VARCHAR(255),

    observacoes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_acesso_academia
        FOREIGN KEY (academia_id)
        REFERENCES academias(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_acesso_aluno
        FOREIGN KEY (aluno_id)
        REFERENCES alunos(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

SHOW TABLES;
DESCRIBE acessos;

USE gymcontrol;

CREATE TABLE configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academia_id INT NOT NULL,

    nome_sistema VARCHAR(150),
    nome_exibicao VARCHAR(150),

    logo_url VARCHAR(255),
    favicon_url VARCHAR(255),

    cor_principal VARCHAR(20),
    cor_secundaria VARCHAR(20),
    cor_destaque VARCHAR(20),

    tema ENUM(
        'PADRAO',
        'CLARO',
        'ESCURO',
        'PERSONALIZADO'
    ) DEFAULT 'PADRAO',

    mostrar_marca_gymcontrol BOOLEAN DEFAULT TRUE,

    texto_rodape VARCHAR(255),

    timezone VARCHAR(50) DEFAULT 'America/Sao_Paulo',
    moeda VARCHAR(10) DEFAULT 'BRL',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_configuracao_academia
        FOREIGN KEY (academia_id)
        REFERENCES academias(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uk_configuracao_academia
        UNIQUE (academia_id)
);
SHOW TABLES;
DESCRIBE configuracoes;

INSERT INTO academias (
    nome,
    cnpj,
    telefone,
    email,
    endereco,
    cidade,
    estado,
    cor_principal,
    cor_secundaria
) VALUES (
    'Academia Gym Power',
    '12.345.678/0001-90',
    '(21) 99999-9999',
    'contato@gympower.com',
    'Rua das Flores, 100',
    'Rio de Janeiro',
    'RJ',
    '#111111',
    '#E63946'
);
SELECT * FROM academias;

INSERT INTO usuarios (
    academia_id,
    nome,
    email,
    senha,
    tipo
) VALUES (
    1,
    'Administrador',
    'admin@gympower.com',
    'TEMPORARIA',
    'ADMIN'
);

INSERT INTO usuarios (
    academia_id,
    nome,
    email,
    senha,
    tipo
) VALUES (
    1,
    'Carlos Silva',
    'carlos@gympower.com',
    'TEMPORARIA',
    'PROFESSOR'
);
SELECT * FROM usuarios;
INSERT INTO professores (
    academia_id,
    usuario_id,
    nome,
    cpf,
    cref,
    telefone,
    email,
    especialidade
) VALUES (
    1,
    2,
    'Carlos Silva',
    '123.456.789-00',
    '123456-G/RJ',
    '(21) 98888-8888',
    'carlos@gympower.com',
    'Musculação'
);

INSERT INTO usuarios (
    academia_id,
    nome,
    email,
    senha,
    tipo
) VALUES (
    1,
    'João Santos',
    'joao@gmail.com',
    'TEMPORARIA',
    'ALUNO'
);
SELECT * FROM usuarios;

INSERT INTO alunos (
    academia_id,
    usuario_id,
    nome,
    cpf,
    data_nascimento,
    telefone,
    email,
    endereco,
    cidade,
    estado,
    contato_emergencia_nome,
    contato_emergencia_telefone
) VALUES (
    1,
    3,
    'João Santos',
    '987.654.321-00',
    '2002-05-15',
    '(21) 97777-7777',
    'joao@gmail.com',
    'Rua Central, 50',
    'Rio de Janeiro',
    'RJ',
    'Maria Santos',
    '(21) 96666-6666'
);

INSERT INTO planos (
    academia_id,
    nome,
    descricao,
    valor,
    tipo
) VALUES (
    1,
    'Musculação',
    'Acesso à área de musculação',
    99.90,
    'MENSAL'
);
SELECT * FROM planos;

INSERT INTO matriculas (
    academia_id,
    aluno_id,
    plano_id,
    data_inicio,
    data_fim,
    valor_contratado,
    status
) VALUES (
    1,
    1,
    1,
    '2026-09-01',
    '2026-10-01',
    99.90,
    'ATIVA'
);

INSERT INTO pagamentos (
    academia_id,
    matricula_id,
    aluno_id,
    descricao,
    valor,
    data_vencimento,
    data_pagamento,
    status,
    forma_pagamento
) VALUES (
    1,
    1,
    1,
    'Mensalidade Setembro',
    99.90,
    '2026-09-05',
    '2026-09-04',
    'PAGO',
    'PIX'
);
SELECT
    a.nome AS aluno,
    p.nome AS plano,
    m.data_inicio,
    m.data_fim,
    pg.valor,
    pg.status,
    pg.forma_pagamento
FROM matriculas m
INNER JOIN alunos a
    ON m.aluno_id = a.id
INNER JOIN planos p
    ON m.plano_id = p.id
INNER JOIN pagamentos pg
    ON m.id = pg.matricula_id;
    
    USE gymcontrol;

SELECT
    a.nome AS aluno,
    m.status AS status_matricula,
    m.data_inicio,
    m.data_fim,
    pg.status AS status_pagamento,
    pg.data_vencimento,
    pg.data_pagamento
FROM alunos a
INNER JOIN matriculas m
    ON a.id = m.aluno_id
INNER JOIN pagamentos pg
    ON m.id = pg.matricula_id
WHERE a.id = 1;

SELECT
    a.nome AS aluno,

    CASE
        WHEN m.status = 'ATIVA'
             AND CURDATE() BETWEEN m.data_inicio AND m.data_fim
             AND pg.status = 'PAGO'
        THEN 'LIBERADO'

        ELSE 'NEGADO'
    END AS acesso

FROM alunos a

INNER JOIN matriculas m
    ON a.id = m.aluno_id

INNER JOIN pagamentos pg
    ON m.id = pg.matricula_id

WHERE a.id = 1;

UPDATE pagamentos
SET status = 'VENCIDO'
WHERE id = 1;

SELECT
    a.nome AS aluno,

    CASE
        WHEN m.status = 'ATIVA'
             AND CURDATE() BETWEEN m.data_inicio AND m.data_fim
             AND pg.status = 'PAGO'
        THEN 'LIBERADO'

        ELSE 'NEGADO'
    END AS acesso

FROM alunos a

INNER JOIN matriculas m
    ON a.id = m.aluno_id

INNER JOIN pagamentos pg
    ON m.id = pg.matricula_id

WHERE a.id = 1;




USE gymcontrol;

SELECT
    u.id,
    u.nome,
    u.email,
    u.tipo,
    u.status,
    p.id AS professor_id
FROM usuarios u
LEFT JOIN professores p
    ON p.usuario_id = u.id
WHERE u.tipo = 'PROFESSOR';


USE gymcontrol;

SELECT
    u.id AS usuario_id,
    u.nome AS nome_usuario,
    u.email,
    u.tipo,
    u.academia_id AS academia_usuario,
    p.id AS professor_id,
    p.nome AS nome_professor,
    p.usuario_id,
    p.academia_id AS academia_professor,
    p.status AS status_professor
FROM usuarios u
LEFT JOIN professores p ON p.usuario_id = u.id
WHERE u.tipo = 'PROFESSOR';

USE gymcontrol;

SELECT id, nome, usuario_id, academia_id, status
FROM professores;
                    
USE gymcontrol;

UPDATE professores
SET usuario_id = 2
WHERE id = 5
  AND academia_id = 1
  AND usuario_id IS NULL;
  
  SELECT id, nome, usuario_id, status
FROM professores
WHERE usuario_id = 2;

USE gymcontrol;

SELECT id, nome, grupo_muscular, equipamento, academia_id
FROM exercicios;


USE gymcontrol;

SELECT
    u.id AS usuario_id,
    u.nome AS nome_usuario,
    u.email,
    u.perfil,
    p.id AS professor_id,
    p.nome AS nome_professor,
    p.status,
    p.academia_id
FROM usuarios u
LEFT JOIN professores p
    ON p.usuario_id = u.id
WHERE u.perfil = 'PROFESSOR';


SELECT
    id,
    nome,
    email,
    papel,
    academia_id
FROM gymcontrol.usuarios
WHERE papel = 'PROFESSOR';


DESCRIBE gymcontrol.alunos;


