<?php
/**
 * Biblioteca particular de modelos de ficha do professor.
 * Modelos nunca alteram fichas já aplicadas: são copiados para cada aluno.
 */
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['professor', 'admin']);
require_once __DIR__ . '/../config/conexao.php';

$professorId = (int) $_SESSION['usuario_id'];
$academiaId = (int) $_SESSION['academia_id'];
if (empty($_SESSION['csrf_modelos_treino'])) {
    $_SESSION['csrf_modelos_treino'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_modelos_treino'];
function escModelo($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$erro = '';
$modelos = [];
$editando = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
$nomeModelo = '';
$descricaoModelo = '';
$exerciciosModelo = [];
$bancoDisponivel = true;

// Verifica a migração antes de exibir ou utilizar a nova funcionalidade.
try {
    $stmt = $pdo->prepare('SELECT m.id, m.titulo, m.descricao, m.atualizado_em,
        (SELECT COUNT(*) FROM treino_modelo_exercicios e WHERE e.modelo_id = m.id) AS total_exercicios
        FROM treino_modelos m WHERE m.professor_id = ? AND m.academia_id = ?
        ORDER BY m.atualizado_em DESC, m.id DESC');
    $stmt->execute([$professorId, $academiaId]);
    $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $bancoDisponivel = false;
    error_log('GymControl modelos (migração): ' . $e->getMessage());
    $erro = 'Os modelos de ficha ainda não estão disponíveis. Instale primeiro database/ATUALIZACAO_MODELOS_TREINO.sql.';
}

if ($bancoDisponivel && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $editando = filter_var($_POST['modelo_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $nomeModelo = trim((string) ($_POST['titulo'] ?? ''));
    $descricaoModelo = trim((string) ($_POST['descricao'] ?? ''));

    $nomes = $_POST['ex_nome'] ?? null;
    $series = $_POST['ex_series'] ?? null;
    $repeticoes = $_POST['ex_repeticoes'] ?? null;
    $cargas = $_POST['ex_carga'] ?? null;
    $observacoes = $_POST['ex_observacoes'] ?? null;
    $linhasValidas = [];

    if (!hash_equals($csrf, $token)) {
        $erro = 'Sessão do formulário inválida. Atualize a página e tente novamente.';
    } elseif ($nomeModelo === '' || mb_strlen($nomeModelo, 'UTF-8') > 100 || mb_strlen($descricaoModelo, 'UTF-8') > 5000) {
        $erro = 'Informe um nome de até 100 caracteres e orientações de até 5.000 caracteres.';
    } elseif (!is_array($nomes) || !is_array($series) || !is_array($repeticoes)
        || !is_array($cargas) || !is_array($observacoes)
        || count($nomes) < 1 || count($nomes) > 30
        || count($nomes) !== count($series) || count($nomes) !== count($repeticoes)
        || count($nomes) !== count($cargas) || count($nomes) !== count($observacoes)) {
        $erro = 'Inclua entre 1 e 30 exercícios e confira os dados enviados.';
    } else {
        foreach (array_values($nomes) as $i => $valor) {
            $nome = trim((string) $valor);
            $serie = filter_var(array_values($series)[$i], FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 50]]);
            $rep = trim((string) array_values($repeticoes)[$i]);
            $carga = trim((string) array_values($cargas)[$i]);
            $obs = trim((string) array_values($observacoes)[$i]);
            $exerciciosModelo[] = ['nome_exercicio' => $nome, 'series' => $serie === false ? '' : $serie,
                'repeticoes' => $rep, 'carga' => $carga, 'observacoes' => $obs];
            if ($nome === '' || mb_strlen($nome, 'UTF-8') > 100 || $serie === false
                || $rep === '' || mb_strlen($rep, 'UTF-8') > 20
                || mb_strlen($carga, 'UTF-8') > 20 || mb_strlen($obs, 'UTF-8') > 255) {
                $erro = 'Confira o exercício ' . ($i + 1) . ' (nome, 1–50 séries, repetições e textos).';
                break;
            }
            $linhasValidas[] = [$nome, $serie, $rep, $carga ?: null, $obs ?: null];
        }
    }

    if ($erro === '') {
        try {
            $pdo->beginTransaction();
            if ($editando > 0) {
                $confere = $pdo->prepare('SELECT id FROM treino_modelos
                    WHERE id = ? AND academia_id = ? AND professor_id = ? FOR UPDATE');
                $confere->execute([$editando, $academiaId, $professorId]);
                if (!$confere->fetchColumn()) {
                    throw new RuntimeException('Modelo inexistente ou sem permissão.');
                }
                $stmt = $pdo->prepare('UPDATE treino_modelos SET titulo = ?, descricao = ?
                    WHERE id = ? AND academia_id = ? AND professor_id = ?');
                $stmt->execute([$nomeModelo, $descricaoModelo ?: null, $editando, $academiaId, $professorId]);
                $stmt = $pdo->prepare('DELETE FROM treino_modelo_exercicios WHERE modelo_id = ?');
                $stmt->execute([$editando]);
                $modeloId = $editando;
            } else {
                $stmt = $pdo->prepare('INSERT INTO treino_modelos (academia_id, professor_id, titulo, descricao)
                    VALUES (?, ?, ?, ?)');
                $stmt->execute([$academiaId, $professorId, $nomeModelo, $descricaoModelo ?: null]);
                $modeloId = (int) $pdo->lastInsertId();
            }
            $stmt = $pdo->prepare('INSERT INTO treino_modelo_exercicios
                (modelo_id, posicao, nome_exercicio, series, repeticoes, carga, observacoes)
                VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($linhasValidas as $i => $linha) {
                $stmt->execute(array_merge([$modeloId, $i + 1], $linha));
            }
            $pdo->commit();
            header('Location: modelos.php?editar=' . $modeloId . '&salvo=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof PDOException) {
                error_log('GymControl modelos (salvar): ' . $e->getMessage());
                $erro = 'Falha ao salvar o modelo. Confira a migração e a conexão.';
            } else {
                $erro = $e->getMessage();
            }
        }
    }
}

if ($bancoDisponivel && $_SERVER['REQUEST_METHOD'] !== 'POST' && $editando > 0) {
    $stmt = $pdo->prepare('SELECT id, titulo, descricao FROM treino_modelos
        WHERE id = ? AND academia_id = ? AND professor_id = ? LIMIT 1');
    $stmt->execute([$editando, $academiaId, $professorId]);
    $modelo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$modelo) {
        http_response_code(404);
        $erro = 'Modelo não encontrado ou pertencente a outro professor.';
        $editando = 0;
    } else {
        $nomeModelo = $modelo['titulo'];
        $descricaoModelo = $modelo['descricao'] ?? '';
        $stmt = $pdo->prepare('SELECT nome_exercicio, series, repeticoes, carga, observacoes
            FROM treino_modelo_exercicios WHERE modelo_id = ? ORDER BY posicao, id');
        $stmt->execute([(int) $modelo['id']]);
        $exerciciosModelo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
if (!$exerciciosModelo) {
    $exerciciosModelo[] = ['nome_exercicio' => '', 'series' => 3, 'repeticoes' => '10 a 12',
        'carga' => '', 'observacoes' => ''];
}

require_once __DIR__ . '/../layouts/header.php';
?>
<section class="gc-section-banner">
  <span class="gc-form-heading">Biblioteca de treino</span>
  <h1><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Modelos de fichas</h1>
  <p>Crie uma ficha reutilizável e personalize seus exercícios. Ao aplicar um modelo a um aluno, o GymControl cria uma cópia independente.</p>
</section>
<?php if ($erro): ?><div class="panel-card" role="alert" style="border-color:rgba(248,113,113,.4);color:#fecaca"><?= escModelo($erro) ?></div><?php endif; ?>
<?php if (isset($_GET['salvo'])): ?><div class="panel-card" role="status" style="border-color:rgba(16,185,129,.4);color:#a7f3d0">Modelo salvo com sucesso! Ele já está disponível nas fichas de seus alunos.</div><?php endif; ?>

<?php if ($bancoDisponivel): ?>
<section class="panel-card" id="editar-modelo">
  <div class="gc-model-actions" style="justify-content:space-between;margin-bottom:19px">
    <div><span class="gc-form-heading">Ficha predefinida</span><h2 style="color:#fff;font-size:22px"><?= $editando ? 'Editar modelo' : 'Novo modelo de ficha' ?></h2></div>
    <?php if ($editando): ?><a href="modelos.php" class="btn btn-accent"><i class="fa-solid fa-plus"></i> Novo modelo</a><?php endif; ?>
  </div>
  <form method="post" id="form-modelo">
    <input type="hidden" name="csrf_token" value="<?= escModelo($csrf) ?>">
    <input type="hidden" name="modelo_id" value="<?= (int) $editando ?>">
    <div class="gc-model-form-grid">
      <div><label for="modelo-titulo">Nome do modelo *</label><input id="modelo-titulo" name="titulo" type="text" maxlength="100" required placeholder="Ex.: ABC · Hipertrofia" value="<?= escModelo($nomeModelo) ?>"></div>
      <div><label for="modelo-desc">Orientações gerais</label><textarea id="modelo-desc" name="descricao" rows="3" maxlength="5000" placeholder="Ex.: Descanso de 60 segundos entre séries."><?= escModelo($descricaoModelo) ?></textarea></div>
    </div>
    <div class="gc-model-actions" style="justify-content:space-between;margin-top:25px">
      <h3 style="font-size:19px;color:#fff">Exercícios do modelo <small id="contagem-modelo" style="font-size:12px;color:#a7bad1"></small></h3>
      <button type="button" class="btn btn-accent" id="adicionar-exercicio"><i class="fa-solid fa-plus"></i> Adicionar exercício</button>
    </div>
    <div id="linhas-modelo">
      <?php foreach ($exerciciosModelo as $i => $item): ?>
      <div class="gc-model-row" data-modelo-linha>
        <div class="gc-model-row-head"><strong>Exercício <span data-numero><?= $i + 1 ?></span></strong><button type="button" class="btn btn-danger" data-remover-exercicio aria-label="Remover exercício"><i class="fa-solid fa-trash"></i> Remover</button></div>
        <div class="gc-model-form-grid">
          <div><label>Nome do exercício *</label><input name="ex_nome[]" type="text" maxlength="100" required value="<?= escModelo($item['nome_exercicio']) ?>" placeholder="Ex.: Supino reto"></div>
          <div><label>Séries *</label><input name="ex_series[]" type="number" min="1" max="50" required value="<?= (int)($item['series'] ?: 3) ?>"></div>
          <div><label>Repetições *</label><input name="ex_repeticoes[]" type="text" maxlength="20" required value="<?= escModelo($item['repeticoes']) ?>" placeholder="Ex.: 10 a 12"></div>
          <div><label>Carga</label><input name="ex_carga[]" type="text" maxlength="20" value="<?= escModelo($item['carga'] ?? '') ?>" placeholder="Ex.: 15 kg"></div>
          <div style="grid-column:1/-1"><label>Observações</label><input name="ex_observacoes[]" type="text" maxlength="255" value="<?= escModelo($item['observacoes'] ?? '') ?>" placeholder="Ex.: Execução controlada"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="gc-model-toolbar">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= $editando ? 'Salvar alterações' : 'Criar modelo de ficha' ?></button>
      <span style="color:#a7bad1;font-size:13px">De 1 a 30 exercícios. Fichas já atribuídas aos alunos não serão modificadas.</span>
    </div>
  </form>
</section>

<section class="panel-card" id="meus-modelos">
  <span class="gc-form-heading">Biblioteca</span>
  <h2 style="font-size:21px;color:#fff;margin-bottom:16px">Meus modelos (<?= count($modelos) ?>)</h2>
  <?php if (!$modelos): ?><p style="color:#a7bad1">Nenhum modelo criado. Use o formulário acima para cadastrar o primeiro.</p>
  <?php else: ?><div class="gc-model-grid">
  <?php foreach ($modelos as $m): ?>
    <article class="gc-model-card">
      <h3><i class="fa-solid fa-dumbbell" style="color:#38bdf8" aria-hidden="true"></i> <?= escModelo($m['titulo']) ?></h3>
      <p><?= escModelo($m['descricao'] ?: 'Sem orientações gerais.') ?></p>
      <small style="color:#a7bad1"><?= (int) $m['total_exercicios'] ?> exercício(s) · Atualizado em <?= escModelo(date('d/m/Y', strtotime($m['atualizado_em']))) ?></small>
      <div class="gc-model-actions"><a class="btn btn-primary" href="modelos.php?editar=<?= (int) $m['id'] ?>#editar-modelo"><i class="fa-solid fa-pen"></i> Editar</a><a class="btn btn-accent" href="index.php#lista-alunos"><i class="fa-solid fa-user-plus"></i> Selecionar aluno</a></div>
    </article>
  <?php endforeach; ?>
  </div><?php endif; ?>
</section>
<?php endif; ?>
<script src="../assets/modelos-treino.js?v=20261118-1" defer></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
