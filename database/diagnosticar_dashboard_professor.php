<?php
/**
 * Diagnóstico somente para terminal:
 * C:\xampp\php\php.exe database\diagnosticar_dashboard_professor.php
 * Não modifica o banco.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este diagnóstico só pode ser executado no terminal.');
}
require_once __DIR__ . '/../config/conexao.php';

$requisitos = [
    'usuarios' => ['id','academia_id','nome','email','status'],
    'alunos' => ['usuario_id','academia_id','professor_id','status_matricula'],
    'treinos' => ['aluno_id','professor_id'],
    'avaliacoes_fisicas' => ['aluno_id','professor_id','data_avaliacao'],
    'agendamentos' => ['id','aluno_id','professor_id','titulo','tipo',
                      'data_agendamento','hora_inicio','hora_fim','status'],
];

$falhas = 0;
foreach ($requisitos as $tabela => $colunas) {
    try {
        // Identificadores são fixos, definidos no próprio código.
        $res = $pdo->query("SHOW COLUMNS FROM `{$tabela}`")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $falhas++;
        echo "[AUSENTE OU INACESSÍVEL] Tabela {$tabela}: {$e->getMessage()}\n";
        continue;
    }
    $existentes = array_column($res, 'Field');
    $faltantes = array_values(array_diff($colunas, $existentes));
    if ($faltantes) {
        $falhas++;
        echo "[INCOMPATÍVEL] {$tabela}: faltam " . implode(', ', $faltantes) . "\n";
    } else {
        echo "[OK] {$tabela}\n";
    }
}
if ($falhas) {
    echo "\nHá {$falhas} tabela(s) ausente(s) ou incompatível(is). "
       . "Crie apenas tabelas realmente ausentes com ATUALIZACAO_DASHBOARD_PROFESSOR.sql; "
       . "colunas ausentes exigem revisão individual.\n";
    exit(1);
}
echo "\nEstrutura mínima do dashboard encontrada. Caso ainda haja erro, confira o log do Apache.\n";
