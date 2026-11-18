<?php
/**
 * GymControl - Auditoria integral do esquema e atualização estritamente ADITIVA.
 *
 * Verificar (não altera dados): php database/auditar_e_atualizar.php --verificar
 * Aplicar (somente após backup): php database/auditar_e_atualizar.php --aplicar
 *
 * Não executa DELETE, DROP, TRUNCATE nem altera a associação de um usuário/aluno.
 * DDL do MySQL/MariaDB pode realizar COMMIT implícito. Tenha um backup real antes.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Uso exclusivo via terminal.'); }
$opcao = $argv[1] ?? '--verificar';
if (!in_array($opcao, ['--verificar', '--aplicar'], true)) {
    fwrite(STDERR, "Uso: php database/auditar_e_atualizar.php [--verificar|--aplicar]\n");
    exit(2);
}
require_once __DIR__ . '/../config/conexao.php';

function gcTabelaExiste(PDO $pdo, string $tabela): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $s->execute([$tabela]);
    return (bool) $s->fetchColumn();
}
function gcColunas(PDO $pdo, string $tabela): array {
    $s = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $s->execute([$tabela]);
    return $s->fetchAll(PDO::FETCH_COLUMN);
}
function gcCriacoes(string $caminho): array {
    $sql = file_get_contents($caminho);
    if ($sql === false) throw new RuntimeException('Não foi possível ler ' . $caminho);
    if (!preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-z_]+)`?\s*\(.*?\)\s*ENGINE\s*=\s*InnoDB[^;]*;/is', $sql, $partes, PREG_SET_ORDER)) {
        throw new RuntimeException('Nenhuma criação de tabela encontrada em ' . $caminho);
    }
    $resultado = [];
    foreach ($partes as $item) {
        // A estrutura histórica contém blocos repetidos; a primeira definição prevalece.
        if (!isset($resultado[$item[1]])) $resultado[$item[1]] = $item[0];
    }
    return $resultado;
}
function gcAdicionarColuna(PDO $pdo, string $tabela, string $coluna, string $tipo): bool {
    if (in_array($coluna, gcColunas($pdo,$tabela), true)) return false;
    // tabela, coluna e tipo são TODOS literais definidos neste script, não vêm do usuário.
    $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$tipo}");
    echo "[ADICIONADA] {$tabela}.{$coluna}\n";
    return true;
}
function gcAjustarCodigos(PDO $pdo): void {
    if (!in_array('codigo', gcColunas($pdo,'academias'), true)) {
        gcAdicionarColuna($pdo,'academias','codigo','VARCHAR(32) NULL');
    }
    $info = $pdo->query("SELECT DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='academias' AND COLUMN_NAME='codigo'")->fetch(PDO::FETCH_ASSOC);
    if (!$info || !in_array(strtolower((string)$info['DATA_TYPE']),['varchar','char'],true) || (int)$info['CHARACTER_MAXIMUM_LENGTH'] < 8) {
        throw new RuntimeException('Formato de academias.codigo incompatível. Corrija essa coluna manualmente após backup.');
    }
    $pdo->beginTransaction();
    try {
        $rows = $pdo->query('SELECT id,codigo FROM academias ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
        $reservados = []; $pendentes = [];
        foreach ($rows as $r) {
            $codigo = (string)($r['codigo'] ?? '');
            if (preg_match('/^[0-9]{1,8}$/D',$codigo) && !isset($reservados[$codigo])) $reservados[$codigo]=true;
            else $pendentes[]=(int)$r['id'];
        }
        $upd = $pdo->prepare('UPDATE academias SET codigo=? WHERE id=?');
        foreach ($pendentes as $id) {
            $novo = null;
            for ($i=0;$i<100;$i++) {
                $tentativa=(string)random_int(10000000,99999999);
                if (!isset($reservados[$tentativa])) { $novo=$tentativa; break; }
            }
            if ($novo === null) throw new RuntimeException('Sem credenciais disponíveis.');
            $upd->execute([$novo,$id]);
            $reservados[$novo]=true;
            echo "[CÓDIGO NOVO] Academia #{$id}: {$novo} (informe o novo código ao gestor)\n";
        }
        $pdo->commit();
    } catch(Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    // Executar DDL após o COMMIT. Os dados continuam intactos; exige backup.
    if (strtolower((string)$info['DATA_TYPE']) !== 'varchar'
        || (int)$info['CHARACTER_MAXIMUM_LENGTH'] !== 8
        || $info['IS_NULLABLE'] !== 'NO') {
        $pdo->exec('ALTER TABLE academias MODIFY COLUMN codigo VARCHAR(8) NOT NULL');
        echo "[COLUNA] academias.codigo ajustada para VARCHAR(8) NOT NULL\n";
    }
    $indices = $pdo->query('SHOW INDEX FROM academias')->fetchAll(PDO::FETCH_ASSOC);
    $grupos=[];
    foreach ($indices as $indice) $grupos[$indice['Key_name']][]=$indice;
    $unico=false;
    foreach ($grupos as $partes) {
        if (count($partes) === 1 && (int)$partes[0]['Non_unique'] === 0 && $partes[0]['Column_name'] === 'codigo') { $unico=true; break; }
    }
    if (!$unico) {
        $pdo->exec('ALTER TABLE academias ADD UNIQUE INDEX uq_academias_codigo (codigo)');
        echo "[ÍNDICE] academias.codigo único criado\n";
    }
}

$requeridas = [
    'academias'=>['id','codigo','nome','status','plano','contato_email','contato_whatsapp','gestor_nome'],
    'usuarios'=>['id','academia_id','nome','email','matricula','senha','nivel_acesso','tipo','status','trocar_senha','criado_em'],
    'alunos'=>['id','academia_id','usuario_id','professor_id','cpf','telefone','status_matricula'],
    'treinos'=>['id','aluno_id','professor_id','titulo','descricao','data_criacao'],
    'exercicios'=>['id','treino_id','nome_exercicio','series','repeticoes','carga','observacoes'],
    'exercicios_catalogo'=>['id','nome','grupo_muscular'],
    'treino_modelos'=>['id','academia_id','professor_id','titulo','descricao','criado_em','atualizado_em'],
    'treino_modelo_exercicios'=>['id','modelo_id','posicao','nome_exercicio','series','repeticoes','carga','observacoes'],
    'mensagens'=>['id','remetente_id','destinatario_id','mensagem','data_envio','lida'],
    'agendamentos'=>['id','aluno_id','professor_id','titulo','tipo','data_agendamento','hora_inicio','hora_fim','observacoes','status'],
    'avaliacoes_fisicas'=>['id','aluno_id','professor_id','data_avaliacao','peso','altura','percentual_gordura','observacoes'],
    'progresso_exercicios'=>['aluno_id','exercicio_id','data_treino','concluido'],
    'gamificacao_treinos_concluidos'=>['aluno_id','treino_id','data_treino','xp'],
    'gamificacao_desafios'=>['id','professor_id','aluno_id','titulo','tipo','meta','xp_recompensa','data_inicio','data_fim'],
    'gamificacao_desafios_recompensas'=>['desafio_id','aluno_id','xp'],
    'gamificacao_ranking_participacao'=>['aluno_id','participa'],
    'recepcao_matriculas'=>['id','academia_id','aluno_id','plano','forma_pagamento_prevista','registrado_por'],
    'loja_produtos'=>['id','academia_id','nome','marca','gramatura_g','foto','preco','estoque','ativo'],
    'loja_vendas'=>['id','academia_id','vendedor_id','aluno_id','forma_pagamento','total','criado_em'],
    'loja_itens_venda'=>['id','venda_id','produto_id','quantidade','preco_unitario'],
    'loja_totens'=>['academia_id','token_hash','ativo'],
    'loja_pedidos_totem'=>['id','academia_id','status','total','venda_id','criado_em'],
    'loja_pedidos_itens'=>['id','pedido_id','produto_id','quantidade','preco_unitario'],
    'contatos_interesse'=>['id','nome_academia','responsavel','email','telefone','plano_interesse','estimativa_alunos','status','academia_id','analisado_por','analisado_em','observacao'],
    'superadmins'=>['id','nome','email','senha','ativo'],
    'superadmin_auditoria'=>['id','superadmin_id','acao','academia_id','solicitacao_id','detalhes']
];
$extras = [
    'academias'=>[
        'status'=>"ENUM('ativa','inativa') NOT NULL DEFAULT 'ativa'",
        'plano'=>"VARCHAR(60) NOT NULL DEFAULT 'Não informado'",
        'contato_email'=>'VARCHAR(150) NULL',
        'contato_whatsapp'=>'VARCHAR(30) NULL',
        'gestor_nome'=>'VARCHAR(120) NULL',
    ],
    'usuarios'=>['trocar_senha'=>'TINYINT(1) NOT NULL DEFAULT 0'],
    'contatos_interesse'=>[
        'status'=>"VARCHAR(20) NOT NULL DEFAULT 'pendente'",
        'academia_id'=>'INT NULL',
        'analisado_por'=>'INT NULL',
        'analisado_em'=>'DATETIME NULL',
        'observacao'=>'VARCHAR(500) NULL',
    ],
    'loja_produtos'=>[
        'marca'=>'VARCHAR(100) NULL',
        'gramatura_g'=>'INT UNSIGNED NULL',
        'foto'=>'VARCHAR(255) NULL',
    ],
];

function gcDiagnostico(PDO $pdo, array $requeridas): int {
    $problemas=0;
    foreach ($requeridas as $nome=>$campos) {
        if (!gcTabelaExiste($pdo,$nome)) {
            echo "[AUSENTE] tabela {$nome}\n"; $problemas++; continue;
        }
        $faltantes=array_values(array_diff($campos,gcColunas($pdo,$nome)));
        if ($faltantes) { echo "[COLUNAS FALTANDO] {$nome}: ".implode(', ',$faltantes)."\n"; $problemas++; }
        else echo "[OK] {$nome}\n";
    }
    if (gcTabelaExiste($pdo,'academias') && in_array('codigo',gcColunas($pdo,'academias'),true)) {
        $codigosInvalidos = (int)$pdo->query("SELECT COUNT(*) FROM academias WHERE codigo IS NULL OR codigo NOT REGEXP '^[0-9]{1,8}$'")->fetchColumn();
        $codigosDuplicados = (int)$pdo->query("SELECT COUNT(*) FROM (SELECT codigo FROM academias WHERE codigo IS NOT NULL GROUP BY codigo HAVING COUNT(*)>1) duplicados")->fetchColumn();
        echo ($codigosInvalidos || $codigosDuplicados ? '[ATENÇÃO]' : '[OK]') . " Credenciais inválidas={$codigosInvalidos}, duplicadas={$codigosDuplicados}\n";
        if ($codigosInvalidos || $codigosDuplicados) $problemas++;
    }
    // Estas verificações NÃO alteram registros. Achados dependem dos dados reais.
    $cruzamentos = [
        'Usuários com academia inexistente'=>["SELECT COUNT(*) FROM usuarios u LEFT JOIN academias a ON a.id=u.academia_id WHERE a.id IS NULL",['usuarios','academias']],
        'Alunos vinculados a academia diferente da conta'=>["SELECT COUNT(*) FROM alunos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.academia_id<>u.academia_id",['alunos','usuarios']],
        'Professores associados a alunos de outra academia'=>["SELECT COUNT(*) FROM alunos a JOIN usuarios p ON p.id=a.professor_id WHERE p.academia_id<>a.academia_id",['alunos','usuarios']],
        'Treinos entre usuários de academias diferentes'=>["SELECT COUNT(*) FROM treinos t JOIN usuarios u ON u.id=t.aluno_id JOIN usuarios p ON p.id=t.professor_id WHERE u.academia_id<>p.academia_id",['treinos','usuarios']],
        'Modelos de fichas com professor de outra academia'=>["SELECT COUNT(*) FROM treino_modelos m JOIN usuarios p ON p.id=m.professor_id WHERE p.academia_id<>m.academia_id",['treino_modelos','usuarios']],
        'Agendamentos entre academias diferentes'=>["SELECT COUNT(*) FROM agendamentos g JOIN usuarios u ON u.id=g.aluno_id JOIN usuarios p ON p.id=g.professor_id WHERE u.academia_id<>p.academia_id",['agendamentos','usuarios']],
        'Avaliações físicas entre academias diferentes'=>["SELECT COUNT(*) FROM avaliacoes_fisicas f JOIN usuarios u ON u.id=f.aluno_id JOIN usuarios p ON p.id=f.professor_id WHERE u.academia_id<>p.academia_id",['avaliacoes_fisicas','usuarios']],
        'Vendas atribuídas a funcionários de outra academia'=>["SELECT COUNT(*) FROM loja_vendas v JOIN usuarios u ON u.id=v.vendedor_id WHERE v.academia_id<>u.academia_id",['loja_vendas','usuarios']],
        'Itens de venda com produto de outra academia'=>["SELECT COUNT(*) FROM loja_itens_venda i JOIN loja_vendas v ON v.id=i.venda_id JOIN loja_produtos p ON p.id=i.produto_id WHERE v.academia_id<>p.academia_id",['loja_itens_venda','loja_vendas','loja_produtos']],
        'Itens de pedidos de totem com produto de outra academia'=>["SELECT COUNT(*) FROM loja_pedidos_itens i JOIN loja_pedidos_totem p ON p.id=i.pedido_id JOIN loja_produtos x ON x.id=i.produto_id WHERE p.academia_id<>x.academia_id",['loja_pedidos_itens','loja_pedidos_totem','loja_produtos']],
        'Alunos com professor inexistente'=>["SELECT COUNT(*) FROM alunos a LEFT JOIN usuarios p ON p.id=a.professor_id WHERE a.professor_id IS NOT NULL AND p.id IS NULL",['alunos','usuarios']],
        'Pedidos concluídos com venda de outra academia'=>["SELECT COUNT(*) FROM loja_pedidos_totem p JOIN loja_vendas v ON v.id=p.venda_id WHERE p.academia_id<>v.academia_id",['loja_pedidos_totem','loja_vendas']],
    ];
    echo "\nVerificações de isolamento dos dados (somente leitura):\n";
    foreach ($cruzamentos as $rotulo=>[$sql,$tab]) {
        $tabelasDisponiveis = true;
        foreach ($tab as $dependencia) {
            if (!gcTabelaExiste($pdo, $dependencia)) { $tabelasDisponiveis = false; break; }
        }
        if (!$tabelasDisponiveis) { echo "[IGNORADO] {$rotulo}: tabela ausente\n"; continue; }
        try {
            $n=(int)$pdo->query($sql)->fetchColumn();
            echo ($n ? '[ATENÇÃO]' : '[OK]')." {$rotulo}: {$n}\n";
            if ($n) $problemas++;
        } catch (PDOException $e) { echo "[INCOMPATÍVEL] {$rotulo}: ".$e->getMessage()."\n"; $problemas++; }
    }
    echo "\nResumo: {$problemas} ausência(s), incompatibilidade(s) ou inconsistência(s). Nenhum registro foi apagado.\n";
    return $problemas;
}

try {
    echo "GymControl - ".($opcao==='--aplicar'?'MIGRAÇÃO ADITIVA':'AUDITORIA SEM MODIFICAÇÕES')." - banco ".$pdo->query('SELECT DATABASE()')->fetchColumn()."\n\n";
    if ($opcao==='--aplicar') {
        echo "Aplicando somente criações e colunas compatíveis. DDL pode confirmar mudanças parciais.\n";
        foreach (['academias'=>['id','nome'],'usuarios'=>['id','academia_id','nome','email','senha','nivel_acesso','tipo','status'],'alunos'=>['id','usuario_id','academia_id','professor_id']] as $t=>$minimos) {
            if (!gcTabelaExiste($pdo,$t)) throw new RuntimeException("Tabela central {$t} ausente. Para instalações novas use database/gymcontrol.sql, após backup. Nenhum reparo automático seguro.");
            $f=array_diff($minimos,gcColunas($pdo,$t));
            if ($f) throw new RuntimeException("Tabela central {$t} incompatível; faltam ".implode(', ',$f).". Conversão exige análise manual.");
        }
        // Corrigir os campos aditivos ANTES de criar dependências das tabelas.
        foreach ($extras as $t=>$colunas) {
            if (!gcTabelaExiste($pdo,$t)) continue;
            foreach ($colunas as $col=>$tipo) gcAdicionarColuna($pdo,$t,$col,$tipo);
        }
        gcAjustarCodigos($pdo);
        $criacao=gcCriacoes(__DIR__.'/estrutura.sql');
        // As três tabelas de autoatendimento também estão declaradas na estrutura consolidada.
        foreach ($criacao as $t=>$ddl) {
            if (!gcTabelaExiste($pdo,$t)) { $pdo->exec($ddl); echo "[CRIADA] {$t}\n"; }
        }
        // Reavaliar colunas quando as tabelas acabaram de ser criadas.
        foreach ($extras as $t=>$colunas) {
            if (!gcTabelaExiste($pdo,$t)) continue;
            foreach ($colunas as $col=>$tipo) gcAdicionarColuna($pdo,$t,$col,$tipo);
        }
        echo "\nMigração aditiva concluída. Executando diagnóstico atualizado:\n\n";
    }
    $falhas=gcDiagnostico($pdo,$requeridas);
    exit($falhas ? 1 : 0);
} catch(Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,"[ERRO] {$e->getMessage()}\nNão insista em alterações manuais. Confira backup e relatório.\n");
    exit(2);
}
