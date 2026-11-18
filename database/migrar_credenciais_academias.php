<?php
/**
 * Executar no terminal após backup:
 *   C:\xampp\php\php.exe database\migrar_credenciais_academias.php
 *
 * Migração idempotente: preserva academias, usuários e produtos existentes.
 * Não importa dumps nem apaga dados. Os códigos antigos não numéricos são substituídos.
 * Atenção: MySQL executa COMMIT implícito ao fazer ALTER TABLE.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Esta migração só pode ser executada no terminal.');
}
require_once __DIR__ . '/../config/conexao.php';

try {
    $tabela = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='academias'")->fetchColumn();
    if (!$tabela) throw new RuntimeException('Tabela academias não encontrada. Execute primeiro a instalação principal.');

    $stmt = $pdo->query("SELECT DATA_TYPE,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='academias' AND COLUMN_NAME='codigo'");
    $coluna = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$coluna) {
        // Sem default e temporariamente nula, para preencher com códigos exclusivos antes do NOT NULL.
        $pdo->exec('ALTER TABLE academias ADD COLUMN codigo VARCHAR(32) NULL');
        echo "Campo academias.codigo criado.\n";
    } elseif (!in_array(strtolower((string)$coluna['DATA_TYPE']), ['varchar','char'], true)
        || (int)$coluna['CHARACTER_MAXIMUM_LENGTH'] < 8) {
        $pdo->exec('ALTER TABLE academias MODIFY COLUMN codigo VARCHAR(32) NULL');
        echo "Campo academias.codigo adaptado para aceitar credenciais.\n";
    }

    $pdo->beginTransaction();
    // Lock impede troca concorrente dos códigos durante a migração.
    $dados = $pdo->query('SELECT id,codigo FROM academias ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $reservados = [];
    $pendentes = [];
    foreach ($dados as $academia) {
        $codigo = (string)($academia['codigo'] ?? '');
        // Códigos numéricos válidos continuam iguais, para não invalidar os já entregues.
        if (preg_match('/^[0-9]{1,8}$/D', $codigo) && !isset($reservados[$codigo])) {
            $reservados[$codigo] = true;
        } else {
            $pendentes[] = (int) $academia['id'];
        }
    }
    $alterar = $pdo->prepare('UPDATE academias SET codigo=? WHERE id=?');
    foreach ($pendentes as $id) {
        $novoCodigo = '';
        for ($n = 0; $n < 80; $n++) {
            $candidato = (string) random_int(10000000, 99999999);
            if (!isset($reservados[$candidato])) { $novoCodigo = $candidato; break; }
        }
        if ($novoCodigo === '') throw new RuntimeException('Não foi possível gerar credencial exclusiva.');
        $alterar->execute([$novoCodigo,$id]);
        $reservados[$novoCodigo] = true;
        echo "Credencial atribuída à academia #{$id}: {$novoCodigo}\n";
    }
    $pdo->commit();

    // Limite imposto no banco, inclusive contra inserções que ignorem o código PHP.
    $pdo->exec('ALTER TABLE academias MODIFY COLUMN codigo VARCHAR(8) NOT NULL');
    $indices = $pdo->query('SHOW INDEX FROM academias')->fetchAll(PDO::FETCH_ASSOC);
    $porIndice = [];
    foreach ($indices as $indice) $porIndice[$indice['Key_name']][] = $indice;
    $possuiIndiceUnico = false;
    foreach ($porIndice as $partes) {
        if (count($partes) === 1 && (int)$partes[0]['Non_unique'] === 0
            && $partes[0]['Column_name'] === 'codigo') $possuiIndiceUnico = true;
    }
    if (!$possuiIndiceUnico) {
        $pdo->exec('ALTER TABLE academias ADD UNIQUE INDEX uq_academias_codigo (codigo)');
        echo "Índice UNIQUE criado em academias.codigo.\n";
    }
    echo "Migração concluída: " . count($dados) . " academia(s), " . count($pendentes) . " credencial(is) nova(s).\n";
    echo "Consulte os códigos no painel Superadmin. Não execute o SQL de instalação sobre dados existentes.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Falha na migração de credenciais: ' . $e->getMessage() . "\nConfira o backup e a estrutura do banco antes de repetir.\n");
    exit(1);
}
