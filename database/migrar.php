<?php
/**
 * Migração aditiva para bancos criados a partir de database/gymcontrol.sql antigo.
 * Execute apenas via CLI, depois de um backup: php database/migrar.php
 * Nunca limpa tabelas nem reinicializa IDs. DDL do MySQL possui commits implícitos.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execução permitida somente no terminal.');
}
require_once __DIR__ . '/../config/conexao.php';

function colunasGc(PDO $pdo, string $tabela): array
{
    $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$tabela]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Verifique a variante do esquema ANTES de qualquer mudança.
$requisitos = [
    'usuarios' => ['id', 'nome', 'email', 'senha', 'nivel_acesso', 'tipo', 'status', 'criado_em'],
    'alunos' => ['usuario_id', 'professor_id', 'status_matricula'],
    'treinos' => ['id', 'aluno_id', 'professor_id', 'titulo', 'descricao', 'data_criacao'],
    'exercicios' => ['id', 'treino_id', 'nome_exercicio', 'series', 'repeticoes'],
    'mensagens' => ['remetente_id', 'destinatario_id', 'data_envio', 'lida'],
];
foreach ($requisitos as $tabela => $campos) {
    $faltando = array_diff($campos, colunasGc($pdo, $tabela));
    if ($faltando) {
        fwrite(STDERR, "Esquema incompatível na tabela {$tabela}: " . implode(', ', $faltando) . " ausente(s). Nenhuma alteração foi executada.\n" .
            "O outro Gymcontrol.sql antigo utiliza tabelas e vínculos distintos; sua conversão exige mapeamento manual para preservar os dados.\n");
        exit(1);
    }
}

// A conexão com o banco já foi testada; criar somente o que falta.
$estrutura = file_get_contents(__DIR__ . '/estrutura.sql');
$estrutura = preg_replace('/^\s*--[^\r\n]*$/m', '', $estrutura);
$instrucoes = explode(';', $estrutura);
try {
    // A academia padrão é necessária antes da atribuição de academia_id aos usuários antigos.
    foreach ($instrucoes as $instrucao) {
        $instrucao = trim($instrucao);
        if (preg_match('/^CREATE TABLE IF NOT EXISTS academias\s*\(/i', $instrucao)) {
            $pdo->exec($instrucao);
        } elseif (preg_match('/^INSERT INTO academias\b/i', $instrucao)) {
            // Bases antigas ainda sem `codigo` recebem a primeira academia sem um campo
            // que só será acrescentado em migrar_credenciais_academias.php.
            if (in_array('codigo', colunasGc($pdo, 'academias'), true)) {
                $pdo->exec($instrucao);
            } else {
                $pdo->exec("INSERT INTO academias (id,nome,status) SELECT 1,'GymControl','ativa' WHERE NOT EXISTS (SELECT 1 FROM academias WHERE id=1)");
            }
        }
    }
    foreach (['usuarios', 'alunos'] as $tabela) {
        if (!in_array('academia_id', colunasGc($pdo, $tabela), true)) {
            // A base antiga era de uma academia só; conservar todos os registros existentes na academia 1.
            $pdo->exec("ALTER TABLE {$tabela} ADD COLUMN academia_id INT NOT NULL DEFAULT 1");
            echo "Coluna {$tabela}.academia_id criada; dados existentes preservados.\n";
        }
    }
    // Converter unicidade GLOBAL de e-mail, matrícula e CPF para unicidade por academia.
    // Não excluir dados, nem alterar os valores já cadastrados.
    foreach (['usuarios' => ['email', 'matricula'], 'alunos' => ['cpf']] as $tabela => $colunas) {
        foreach ($colunas as $coluna) {
            $indices = $pdo->query("SHOW INDEX FROM `{$tabela}`")->fetchAll(PDO::FETCH_ASSOC);
            $porNome = [];
            foreach ($indices as $indice) $porNome[$indice['Key_name']][] = $indice;
            foreach ($porNome as $nomeIndice => $partes) {
                if (count($partes) !== 1 || $partes[0]['Non_unique'] != 0 || $partes[0]['Column_name'] !== $coluna || $nomeIndice === 'PRIMARY') continue;
                if (!preg_match('/^[A-Za-z0-9_]+$/', $nomeIndice)) throw new RuntimeException('Índice inesperado: ' . $nomeIndice);
                $pdo->exec("ALTER TABLE `{$tabela}` DROP INDEX `{$nomeIndice}`");
            }
        }
    }
    foreach ([
        'usuarios' => ['uq_usuario_email_academia'=>'academia_id,email', 'uq_usuario_matricula_academia'=>'academia_id,matricula'],
        'alunos' => ['uq_aluno_cpf_academia'=>'academia_id,cpf']
    ] as $tabela => $novosIndices) {
        $existentes = array_column($pdo->query("SHOW INDEX FROM `{$tabela}`")->fetchAll(PDO::FETCH_ASSOC), 'Key_name');
        foreach ($novosIndices as $nome => $colunas) {
            if (!in_array($nome,$existentes,true)) $pdo->exec("ALTER TABLE `{$tabela}` ADD UNIQUE INDEX `{$nome}` ({$colunas})");
        }
    }
    foreach ($instrucoes as $instrucao) {
        $instrucao = trim($instrucao);
        if (preg_match('/^CREATE TABLE IF NOT EXISTS\b/i', $instrucao) ||
            preg_match('/^INSERT IGNORE INTO exercicios_catalogo\b/i', $instrucao)) {
            $pdo->exec($instrucao);
        }
    }
    // Só reparar as 5 contas demonstrativas quando a senha ainda possui o hash antigo inválido.
    $hashAntigo = '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm';
    $hashNovo = password_hash('123456', PASSWORD_DEFAULT);
    $corrigir = $pdo->prepare('UPDATE usuarios SET senha = :nova WHERE email = :email AND senha = :antiga');
    foreach (['admin@gymcontrol.com', 'professor@gymcontrol.com', 'aluno@gymcontrol.com',
              'recepcao@gymcontrol.com', 'loja@gymcontrol.com'] as $email) {
        $corrigir->execute([':nova' => $hashNovo, ':email' => $email, ':antiga' => $hashAntigo]);
    }
    echo "Migração incremental finalizada. Nenhum registro excluído.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Falha na migração: {$e->getMessage()}\nFaça backup e revise o esquema antes de tentar novamente.\n");
    exit(1);
}
