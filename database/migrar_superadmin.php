<?php
// Execute no terminal: php database/migrar_superadmin.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Apenas CLI.'); }
require_once __DIR__ . '/../config/conexao.php';
function haTabela(PDO $pdo, string $nome): bool {
    $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$nome]); return (bool)$q->fetchColumn();
}
function haColuna(PDO $pdo, string $tabela, string $coluna): bool {
    $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$tabela,$coluna]); return (bool)$q->fetchColumn();
}
try {
    foreach (['academias','usuarios','contatos_interesse'] as $tabela) {
        if (!haTabela($pdo,$tabela)) throw new RuntimeException('Tabela existente obrigatória ausente: '.$tabela.'. Aplique a migração principal antes.');
    }
    $requisitos=['academias'=>['id','nome','status'], 'usuarios'=>['id','academia_id','nome','email','senha','nivel_acesso','tipo','status'], 'contatos_interesse'=>['id','nome_academia','responsavel','email','telefone','plano_interesse','estimativa_alunos']];
    foreach ($requisitos as $tabela=>$colunas) foreach ($colunas as $coluna) {
        if (!haColuna($pdo,$tabela,$coluna)) throw new RuntimeException('Esquema incompatível: '.$tabela.'.'.$coluna.' ausente. Nenhuma conversão automática desses campos.');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS superadmins (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(120) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, senha VARCHAR(255) NOT NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS superadmin_auditoria (id BIGINT AUTO_INCREMENT PRIMARY KEY, superadmin_id INT NOT NULL, acao VARCHAR(60) NOT NULL, academia_id INT NULL, solicitacao_id INT NULL, detalhes VARCHAR(255) NULL, criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_auditoria_data(criado_em), CONSTRAINT fk_audit_super FOREIGN KEY(superadmin_id) REFERENCES superadmins(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $extras=[
        'contatos_interesse'=>[
            'status'=>"VARCHAR(20) NOT NULL DEFAULT 'pendente'",
            'academia_id'=>'INT NULL',
            'analisado_por'=>'INT NULL',
            'analisado_em'=>'DATETIME NULL',
            'observacao'=>'VARCHAR(500) NULL',
        ],
        'academias'=>[
            'plano'=>'VARCHAR(60) NOT NULL DEFAULT \'Não informado\'',
            'contato_email'=>'VARCHAR(150) NULL',
            'contato_whatsapp'=>'VARCHAR(30) NULL',
            'gestor_nome'=>'VARCHAR(120) NULL',
        ],
        'usuarios'=>[
            'trocar_senha'=>'TINYINT(1) NOT NULL DEFAULT 0',
        ]
    ];
    foreach ($extras as $tabela=>$campos) foreach ($campos as $coluna=>$definicao) {
        if (!haColuna($pdo,$tabela,$coluna)) {
            $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
            echo "Adicionada coluna {$tabela}.{$coluna}\n";
        }
    }
    echo "Migração concluída. Dados anteriores preservados. Agora execute php superadmin/criar_admin.php.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Falha: {$e->getMessage()}\nRevise backup/esquema antes de repetir.\n"); exit(1);
}
