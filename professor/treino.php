<?php
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['professor', 'admin']);

require_once __DIR__ . '/../config/conexao.php';

$professor_id = (int) $_SESSION['usuario_id'];
$aluno_id = filter_input(INPUT_GET, 'aluno_id', FILTER_VALIDATE_INT);

if (!$aluno_id) {
    header('Location: index.php');
    exit;
}

// Confirma que o aluno pertence ao professor logado.
$stmt = $pdo->prepare("
    SELECT u.id, u.nome
    FROM usuarios u
    INNER JOIN alunos a ON a.usuario_id = u.id
    WHERE u.id = :aluno_id
      AND a.professor_id = :professor_id
      AND a.academia_id = :academia_id AND u.academia_id = a.academia_id
      AND u.status = 'ativo'
    LIMIT 1
");

$stmt->execute([
    ':aluno_id' => $aluno_id,
    ':professor_id' => $professor_id,
    ':academia_id' => (int) $_SESSION['academia_id']
]);

$aluno = $stmt->fetch();

if (!$aluno) {
    http_response_code(403);
    exit('Aluno não encontrado ou não vinculado a este professor.');
}

$erro = '';
$sucesso = '';
if (empty($_SESSION['csrf_treino'])) {
    $_SESSION['csrf_treino'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_treino'];

// Modelos do próprio professor, limitados à academia da sessão.
// O cadastro manual de fichas continua funcional mesmo sem a migração dos modelos.
$modelosDisponiveis = [];
$modelosAviso = '';
try {
    $buscaModelos = $pdo->prepare('SELECT m.id, m.titulo,
        (SELECT COUNT(*) FROM treino_modelo_exercicios e WHERE e.modelo_id = m.id) AS exercicios_total
        FROM treino_modelos m
        WHERE m.academia_id = ? AND m.professor_id = ? ORDER BY m.titulo, m.id');
    $buscaModelos->execute([(int) $_SESSION['academia_id'], $professor_id]);
    $modelosDisponiveis = $buscaModelos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('GymControl professor/treino [modelos]: ' . $e->getMessage());
    $modelosAviso = 'Para utilizar modelos, instale a migração ATUALIZACAO_MODELOS_TREINO.sql.';
}

// Todas as alterações exigem POST, token CSRF e vínculo com o professor logado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $acao = (string) ($_POST['acao'] ?? 'criar');
    $treinoId = filter_var($_POST['treino_id'] ?? null, FILTER_VALIDATE_INT);

    if (!hash_equals($csrf, $token)) {
        $erro = 'Sessão do formulário inválida. Atualize a página e tente novamente.';
    } elseif (!in_array($acao, ['criar', 'criar_modelo', 'editar', 'excluir'], true)) {
        $erro = 'Operação inválida.';
    } elseif (!in_array($acao, ['criar', 'criar_modelo'], true) && !$treinoId) {
        $erro = 'Ficha inválida.';
    } else {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        if (!in_array($acao, ['excluir', 'criar_modelo'], true) && ($titulo === '' || mb_strlen($titulo) > 100)) {
            $erro = 'Informe um título de até 100 caracteres.';
        } elseif (!in_array($acao, ['excluir', 'criar_modelo'], true) && mb_strlen($descricao) > 5000) {
            $erro = 'As orientações devem ter no máximo 5000 caracteres.';
        } else {
            try {
                if ($acao === 'criar_modelo') {
                    $modeloId = filter_var($_POST['modelo_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
                    if (!$modeloId) throw new RuntimeException('Selecione um modelo de ficha válido.');

                    // A ficha e seus exercícios são criados juntos; em caso de falha nada fica incompleto.
                    $pdo->beginTransaction();
                    $modeloStmt = $pdo->prepare('SELECT id, titulo, descricao FROM treino_modelos
                        WHERE id = ? AND professor_id = ? AND academia_id = ? LIMIT 1');
                    $modeloStmt->execute([$modeloId, $professor_id, (int) $_SESSION['academia_id']]);
                    $modelo = $modeloStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$modelo) throw new RuntimeException('Modelo indisponível para este professor ou academia.');
                    $itemsStmt = $pdo->prepare('SELECT nome_exercicio, series, repeticoes, carga, observacoes
                        FROM treino_modelo_exercicios WHERE modelo_id = ? ORDER BY posicao, id');
                    $itemsStmt->execute([$modeloId]);
                    $itensModelo = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!$itensModelo) throw new RuntimeException('O modelo está vazio. Edite-o e inclua um exercício.');

                    $criarFicha = $pdo->prepare('INSERT INTO treinos
                        (aluno_id, professor_id, titulo, descricao, data_criacao)
                        VALUES (?, ?, ?, ?, CURDATE())');
                    $criarFicha->execute([$aluno_id, $professor_id, $modelo['titulo'], $modelo['descricao']]);
                    $novaFichaId = (int) $pdo->lastInsertId();
                    $criarExercicio = $pdo->prepare('INSERT INTO exercicios
                        (treino_id, nome_exercicio, series, repeticoes, carga, observacoes)
                        VALUES (?, ?, ?, ?, ?, ?)');
                    foreach ($itensModelo as $item) {
                        $criarExercicio->execute([$novaFichaId, $item['nome_exercicio'], $item['series'],
                            $item['repeticoes'], $item['carga'], $item['observacoes']]);
                    }
                    $pdo->commit();
                } elseif ($acao === 'criar') {
                    $stmt = $pdo->prepare("INSERT INTO treinos (aluno_id, professor_id, titulo, descricao, data_criacao)
                                           VALUES (:aluno, :professor, :titulo, :descricao, CURDATE())");
                    $stmt->execute([':aluno' => $aluno_id, ':professor' => $professor_id,
                                    ':titulo' => $titulo, ':descricao' => $descricao]);
                } elseif ($acao === 'editar') {
                    $stmt = $pdo->prepare("UPDATE treinos SET titulo = :titulo, descricao = :descricao
                                           WHERE id = :id AND aluno_id = :aluno AND professor_id = :professor");
                    $stmt->execute([':titulo' => $titulo, ':descricao' => $descricao, ':id' => $treinoId,
                                    ':aluno' => $aluno_id, ':professor' => $professor_id]);
                    // rowCount pode ser zero quando não houve mudança: verificar existência.
                    $verifica = $pdo->prepare('SELECT id FROM treinos WHERE id = ? AND aluno_id = ? AND professor_id = ?');
                    $verifica->execute([$treinoId, $aluno_id, $professor_id]);
                    if (!$verifica->fetchColumn()) {
                        throw new RuntimeException('Ficha não encontrada ou sem permissão.');
                    }
                } else {
                    // Não apaga exercícios/histórico automaticamente. A exclusão só acontece
                    // quando as regras de integridade do banco permitirem.
                    $stmt = $pdo->prepare('DELETE FROM treinos WHERE id = ? AND aluno_id = ? AND professor_id = ?');
                    $stmt->execute([$treinoId, $aluno_id, $professor_id]);
                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('Ficha não encontrada ou sem permissão.');
                    }
                }
                header('Location: treino.php?aluno_id=' . $aluno_id . '&resultado=' . $acao);
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('GymControl professor/treino: ' . $e->getMessage());
                $erro = $acao === 'criar_modelo' ? 'Falha ao aplicar o modelo de ficha. Confira a migração e tente novamente.' : ($acao === 'excluir'
                    ? 'Não foi possível excluir. A ficha pode possuir exercícios ou registros vinculados. Verifique os vínculos antes de removê-la.'
                    : 'Não foi possível salvar a ficha.');
            } catch (RuntimeException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $erro = $e->getMessage();
            }
        }
    }
}

$mensagens = ['criar' => 'Ficha cadastrada com sucesso!',
              'editar' => 'Ficha atualizada com sucesso!',
              'excluir' => 'Ficha excluída com sucesso!',
              'criar_modelo' => 'Modelo aplicado! A ficha e seus exercícios foram copiados para o aluno.'];
$resultado = (string) ($_GET['resultado'] ?? '');
if (isset($mensagens[$resultado])) {
    $sucesso = $mensagens[$resultado];
} elseif (isset($_GET['criado'])) {
    $sucesso = 'Ficha cadastrada com sucesso!';
}

// Busca as fichas cadastradas para o aluno.
$stmt = $pdo->prepare("
    SELECT id, titulo, descricao, data_criacao
    FROM treinos
    WHERE aluno_id = :aluno_id
      AND professor_id = :professor_id
    ORDER BY id DESC
");

$stmt->execute([
    ':aluno_id' => $aluno_id,
    ':professor_id' => $professor_id
]);

$treinos = $stmt->fetchAll();

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="gc-section-banner" style="margin-bottom: 28px;">
    <a href="index.php"
       style="color: var(--cor-principal);">
        ← Voltar aos alunos
    </a>

    <h1 style="color: #fff; margin-top: 16px;">
        Fichas de Treino
    </h1>

    <p style="color: var(--text-muted);">
        Aluno:
        <?= htmlspecialchars($aluno['nome']) ?>
    </p>
</div>

<?php if ($erro): ?>
    <div class="panel-card"
         style="color: #f87171; margin-bottom: 20px;">
        <?= htmlspecialchars($erro) ?>
    </div>
<?php endif; ?>

<?php if ($sucesso): ?>
    <div class="panel-card"
         style="color: #10b981; margin-bottom: 20px;">
        <?= htmlspecialchars($sucesso) ?>
    </div>
<?php endif; ?>

<div class="panel-card" style="margin-bottom: 24px;">
    <span class="gc-form-heading">Biblioteca de treino</span>
    <h2 style="color:#fff;margin-bottom:10px">Criar ficha a partir de um modelo</h2>
    <p style="color:#a7bad1;font-size:13px;margin-bottom:18px">Escolha um modelo predefinido. A ficha e seus exercícios serão copiados para este aluno e poderão ser personalizados depois, sem alterar o modelo original.</p>
    <?php if ($modelosAviso): ?><p style="color:#fbbf24;margin-bottom:14px"><?= htmlspecialchars($modelosAviso, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if (!$modelosDisponiveis): ?>
        <p style="color:#a7bad1;margin-bottom:15px">Nenhum modelo disponível para aplicar.</p>
        <a class="btn btn-accent" href="modelos.php"><i class="fa-solid fa-layer-group"></i> Criar primeiro modelo</a>
    <?php else: ?>
    <form method="post" class="gc-template-picker">
        <input type="hidden" name="acao" value="criar_modelo">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <div>
            <label for="modelo_id">Modelo de ficha disponível</label>
            <select name="modelo_id" id="modelo_id" required>
                <option value="">Selecione um modelo</option>
                <?php foreach ($modelosDisponiveis as $modeloOpcao): ?>
                <option value="<?= (int) $modeloOpcao['id'] ?>" <?= (int)$modeloOpcao['exercicios_total'] < 1 ? 'disabled' : '' ?>><?= htmlspecialchars($modeloOpcao['titulo'], ENT_QUOTES, 'UTF-8') ?> · <?= (int)$modeloOpcao['exercicios_total'] ?> exercício(s)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-copy"></i> Aplicar modelo ao aluno</button>
    </form>
    <a href="modelos.php" style="color:#7dd3fc;font-size:13px;display:inline-block;margin-top:16px">Gerenciar meus modelos →</a>
    <?php endif; ?>
</div>

<div class="panel-card" style="margin-bottom: 24px;">
    <span class="gc-form-heading">Criação livre</span>
    <h2 style="color: #fff; margin-bottom: 20px;">
        Nova Ficha de Treino
    </h2>

    <form method="POST">
        <input type="hidden" name="acao" value="criar">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label for="titulo"
               style="display: block; margin-bottom: 8px;">
            Nome do treino
        </label>

        <input
            type="text"
            id="titulo"
            name="titulo"
            maxlength="100"
            required
            placeholder="Ex.: Treino A - Peito e Tríceps"
            style="width: 100%; padding: 12px;
                   margin-bottom: 18px;"
        >

        <label for="descricao"
               style="display: block; margin-bottom: 8px;">
            Orientações gerais
        </label>

        <textarea
            id="descricao"
            name="descricao"
            rows="4"
            placeholder="Ex.: Descansar 60 segundos entre séries."
            style="width: 100%; padding: 12px;
                   margin-bottom: 18px;"
        ></textarea>

        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i>
            Cadastrar Ficha
        </button>
    </form>
</div>

<div class="panel-card">
    <h2 style="color: #fff; margin-bottom: 20px;">
        Fichas Cadastradas
    </h2>

    <?php if (empty($treinos)): ?>
        <p style="color: var(--text-muted);">
            Nenhuma ficha cadastrada para este aluno.
        </p>
    <?php else: ?>
        <?php foreach ($treinos as $treino): ?>
            <div style="padding: 18px 0;
                        border-bottom: 1px solid
                        var(--border-color);">
                <h3 style="color: #fff;">
                    <?= htmlspecialchars($treino['titulo']) ?>
                </h3>

                <p style="color: var(--text-muted);
                          margin: 10px 0;">
                    <?= nl2br(htmlspecialchars(
                        $treino['descricao'] ?? ''
                    )) ?>
                </p>

                <small style="color: var(--text-muted);">
                    Criado em:
                    <?= date(
                        'd/m/Y',
                        strtotime($treino['data_criacao'])
                    ) ?>
                </small>

<div style="margin-top: 16px;">
    <a
        href="exercicios.php?treino_id=<?= (int) $treino['id'] ?>"
        class="btn btn-primary"
    >
        <i class="fa-solid fa-dumbbell"></i>
        Gerenciar Exercícios
    </a>
</div>

                <details style="margin-top: 14px;">
                    <summary style="cursor: pointer; color: var(--cor-principal);">Editar ficha</summary>
                    <form method="POST" style="margin-top: 12px;">
                        <input type="hidden" name="acao" value="editar">
                        <input type="hidden" name="treino_id" value="<?= (int) $treino['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <label for="titulo-<?= (int) $treino['id'] ?>">Nome do treino</label>
                        <input id="titulo-<?= (int) $treino['id'] ?>" name="titulo" type="text" maxlength="100" required
                               value="<?= htmlspecialchars($treino['titulo'], ENT_QUOTES, 'UTF-8') ?>"
                               style="display:block; width:100%; padding:12px; margin:8px 0 14px;">
                        <label for="descricao-<?= (int) $treino['id'] ?>">Orientações gerais</label>
                        <textarea id="descricao-<?= (int) $treino['id'] ?>" name="descricao" rows="4" maxlength="5000"
                                  style="display:block; width:100%; padding:12px; margin:8px 0 14px;"><?= htmlspecialchars($treino['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <button class="btn btn-primary" type="submit">Salvar alterações</button>
                    </form>
                </details>
                <form method="POST" style="margin-top: 14px;"
                      onsubmit="return confirm('Excluir esta ficha? Esta ação não poderá ser desfeita.');">
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="treino_id" value="<?= (int) $treino['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn" style="background:#7f1d1d; color:#fff;">Excluir ficha</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
