<?php
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['professor', 'admin']);

require_once __DIR__ . '/../config/conexao.php';

$professor_id = (int) $_SESSION['usuario_id'];

$treino_id = filter_input(
    INPUT_GET,
    'treino_id',
    FILTER_VALIDATE_INT
);

if (!$treino_id || $treino_id < 1) {
    header('Location: index.php');
    exit;
}

// Verifica se a ficha pertence ao professor logado
// e se o aluno continua vinculado a ele.
$stmt = $pdo->prepare("
    SELECT t.id, t.titulo, t.aluno_id,
           u.nome AS nome_aluno
    FROM treinos t
    INNER JOIN alunos a
        ON a.usuario_id = t.aluno_id
    INNER JOIN usuarios u
        ON u.id = t.aluno_id
  WHERE t.id = :treino_id
  AND t.professor_id = :professor_treino
  AND a.professor_id = :professor_aluno
  AND a.academia_id = :academia_id
  AND u.academia_id = a.academia_id
  AND u.status = 'ativo'
LIMIT 1
");


$stmt->execute([
    ':treino_id' => $treino_id,
    ':professor_treino' => $professor_id,
    ':professor_aluno' => $professor_id,
    ':academia_id' => (int) $_SESSION['academia_id']
]);

$treino = $stmt->fetch();

if (!$treino) {
    http_response_code(403);
    exit('Ficha não encontrada ou acesso não autorizado.');
}

$erro = '';
$sucesso = '';

if (empty($_SESSION['csrf_exercicios'])) {
    $_SESSION['csrf_exercicios'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_exercicios'];

// Catálogo de exercícios: consulta separada, sem alterar os exercícios das fichas.
$catalogo = $pdo->query('SELECT id, nome, grupo_muscular FROM exercicios_catalogo ORDER BY grupo_muscular, nome')->fetchAll(PDO::FETCH_ASSOC);
$grupos = array_values(array_unique(array_column($catalogo, 'grupo_muscular')));


// Todas as alterações são feitas via POST e exigem token de sessão.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'criar';
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Sessão inválida. Atualize a página e tente novamente.');
    }

    $exercicio_id = filter_var($_POST['exercicio_id'] ?? null, FILTER_VALIDATE_INT);
    if ($acao === 'editar' || $acao === 'excluir') {
        $stmt = $pdo->prepare('SELECT id FROM exercicios WHERE id = :id AND treino_id = :treino');
        $stmt->execute([':id' => $exercicio_id ?: 0, ':treino' => $treino_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            exit('Exercício não encontrado nesta ficha.');
        }
    }

    if ($acao === 'excluir') {
        try {
            // Preserva histórico: o banco impede exclusão se já existir progresso registrado.
            $stmt = $pdo->prepare('DELETE FROM exercicios WHERE id = :id AND treino_id = :treino');
            $stmt->execute([':id' => $exercicio_id, ':treino' => $treino_id]);
            header('Location: exercicios.php?treino_id=' . $treino_id . '&excluido=1');
            exit;
        } catch (PDOException $e) {
            error_log('Exclusão de exercício: ' . $e->getMessage());
            $erro = 'Este exercício possui registros vinculados e não pode ser excluído sem perder o histórico.';
        }
    }

    if ($acao === 'criar' || $acao === 'editar') {
        $nome = trim($_POST['nome_exercicio'] ?? '');
        // Apenas a criação aceita selecionar um item do catálogo.
        // O nome é recuperado do banco, nunca confiado ao navegador.
        $origem = $_POST['origem_exercicio'] ?? 'manual';
        if ($acao === 'criar' && $origem === 'catalogo') {
            $catalogo_id = filter_var($_POST['catalogo_id'] ?? null, FILTER_VALIDATE_INT);
            $stmtCatalogo = $pdo->prepare('SELECT nome FROM exercicios_catalogo WHERE id = :id LIMIT 1');
            $stmtCatalogo->execute([':id' => $catalogo_id ?: 0]);
            $nomeCatalogo = $stmtCatalogo->fetchColumn();
            $nome = $nomeCatalogo !== false ? $nomeCatalogo : '';
        } elseif ($acao === 'criar' && $origem !== 'manual') {
            $nome = '';
        }
        $series = filter_var($_POST['series'] ?? null, FILTER_VALIDATE_INT);
        $repeticoes = trim($_POST['repeticoes'] ?? '');
        $carga = trim($_POST['carga'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');

        if ($nome === '' || mb_strlen($nome) > 100) {
            $erro = 'Informe um nome de até 100 caracteres.';
        } elseif ($series === false || $series < 1) {
            $erro = 'Informe uma quantidade válida de séries.';
        } elseif ($repeticoes === '' || mb_strlen($repeticoes) > 20) {
            $erro = 'Informe até 20 caracteres para repetições.';
        } elseif (mb_strlen($carga) > 20) {
            $erro = 'A carga deve ter até 20 caracteres.';
        } elseif (mb_strlen($observacoes) > 255) {
            $erro = 'As observações devem ter até 255 caracteres.';
        } else {
            try {
                $dados = [
                    ':nome' => $nome,
                    ':series' => $series,
                    ':repeticoes' => $repeticoes,
                    ':carga' => $carga !== '' ? $carga : null,
                    ':observacoes' => $observacoes !== '' ? $observacoes : null,
                ];
                if ($acao === 'editar') {
                    $stmt = $pdo->prepare('UPDATE exercicios SET nome_exercicio = :nome,
                        series = :series, repeticoes = :repeticoes, carga = :carga,
                        observacoes = :observacoes WHERE id = :id AND treino_id = :treino');
                    $dados[':id'] = $exercicio_id;
                    $dados[':treino'] = $treino_id;
                } else {
                    $stmt = $pdo->prepare('INSERT INTO exercicios
                        (treino_id, nome_exercicio, series, repeticoes, carga, observacoes)
                        VALUES (:treino, :nome, :series, :repeticoes, :carga, :observacoes)');
                    $dados[':treino'] = $treino_id;
                }
                $stmt->execute($dados);
                header('Location: exercicios.php?treino_id=' . $treino_id .
                    ($acao === 'editar' ? '&editado=1' : '&criado=1'));
                exit;
            } catch (PDOException $e) {
                $erro = 'Não foi possível salvar o exercício.';
            }
        }
    } else {
        $erro = 'Operação inválida.';
    }
}

if (isset($_GET['criado'])) $sucesso = 'Exercício cadastrado com sucesso!';
if (isset($_GET['editado'])) $sucesso = 'Exercício atualizado com sucesso!';
if (isset($_GET['excluido'])) $sucesso = 'Exercício excluído com sucesso!';

// Lista os exercícios da ficha
$stmt = $pdo->prepare("
    SELECT id, nome_exercicio, series,
           repeticoes, carga, observacoes
    FROM exercicios
    WHERE treino_id = :treino_id
    ORDER BY id ASC
");

$stmt->execute([':treino_id' => $treino_id]);
$exercicios = $stmt->fetchAll();

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="gc-section-banner" style="margin-bottom: 28px;">
    <a
        href="treino.php?aluno_id=<?= (int) $treino['aluno_id'] ?>"
        style="color: var(--cor-principal);"
    >
        ← Voltar às fichas
    </a>

    <h1 style="color: #fff; margin-top: 16px;">
        Gerenciar Exercícios
    </h1>

    <p style="color: var(--text-muted);">
        <?= htmlspecialchars($treino['titulo']) ?>
        — <?= htmlspecialchars($treino['nome_aluno']) ?>
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
    <h2 style="color: #fff; margin-bottom: 20px;">
        Adicionar Exercício
    </h2>

    <form method="POST">
        <input type="hidden" name="acao" value="criar">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <div class="origem-exercicio">
            <label for="origem_exercicio">Como deseja adicionar?</label>
            <select id="origem_exercicio" name="origem_exercicio">
                <option value="catalogo">Selecionar do catálogo</option>
                <option value="manual">Digitar manualmente</option>
            </select>
        </div>

        <div id="campos-catalogo">
            <div class="campos-catalogo-grid">
                <div>
                    <label for="grupo_muscular">Grupo muscular</label>
                    <select id="grupo_muscular">
                        <option value="">Selecione o grupo muscular</option>
                        <?php foreach ($grupos as $grupo): ?>
                            <option value="<?= htmlspecialchars($grupo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($grupo, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="catalogo_id">Exercício do catálogo</label>
                    <select id="catalogo_id" name="catalogo_id" required disabled>
                        <option value="">Selecione primeiro um grupo</option>
                        <?php foreach ($catalogo as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" data-grupo="<?= htmlspecialchars($item['grupo_muscular'], ENT_QUOTES, 'UTF-8') ?>" hidden disabled><?= htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div id="campo-manual" hidden>
        <label for="nome_exercicio">Nome do exercício</label>
        <input
            id="nome_exercicio"
            name="nome_exercicio"
            type="text"
            maxlength="100"
            required
            placeholder="Ex.: Supino Reto com Barra"
        >
        </div>

        <div class="campos-exercicio">
            <div>
                <label for="series">Séries</label>
                <input
                    id="series"
                    name="series"
                    type="number"
                    min="1"
                    required
                    value="3"
                >
            </div>

            <div>
                <label for="repeticoes">Repetições</label>
                <input
                    id="repeticoes"
                    name="repeticoes"
                    type="text"
                    maxlength="20"
                    required
                    placeholder="Ex.: 10 a 12"
                >
            </div>

            <div>
                <label for="carga">Carga</label>
                <input
                    id="carga"
                    name="carga"
                    type="text"
                    maxlength="20"
                    placeholder="Ex.: 30 kg"
                >
            </div>
        </div>

        <label for="observacoes">Observações</label>
        <textarea
            id="observacoes"
            name="observacoes"
            rows="3"
            maxlength="255"
            placeholder="Orientações para executar o exercício"
        ></textarea>

        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i>
            Adicionar Exercício
        </button>
    </form>
</div>

<div class="panel-card">
    <h2 style="color: #fff; margin-bottom: 20px;">
        Exercícios da Ficha
    </h2>

    <?php if (empty($exercicios)): ?>
        <p style="color: var(--text-muted);">
            Nenhum exercício cadastrado nesta ficha.
        </p>
    <?php else: ?>
        <?php foreach ($exercicios as $ex): ?>
            <div class="exercicio-item">
                <h3>
                    <?= htmlspecialchars($ex['nome_exercicio']) ?>
                </h3>

                <div class="exercicio-detalhes">
                    <span>
                        Séries:
                        <strong><?= (int) $ex['series'] ?></strong>
                    </span>

                    <span>
                        Repetições:
                        <strong>
                            <?= htmlspecialchars($ex['repeticoes']) ?>
                        </strong>
                    </span>

                    <span>
                        Carga:
                        <strong>
                            <?= htmlspecialchars($ex['carga'] ?? 'Não informada') ?>
                        </strong>
                    </span>
                </div>

                <div class="acoes-exercicio">
                    <button type="button" class="btn btn-primary"
                        onclick="document.getElementById('editar-<?= (int) $ex['id'] ?>').hidden = !document.getElementById('editar-<?= (int) $ex['id'] ?>').hidden">
                        Editar
                    </button>
                    <form method="POST" onsubmit="return confirm('Excluir este exercício? Esta ação não pode ser desfeita.');">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="exercicio_id" value="<?= (int) $ex['id'] ?>">
                        <button type="submit" class="btn btn-excluir">Excluir</button>
                    </form>
                </div>

                <form id="editar-<?= (int) $ex['id'] ?>" class="form-editar" method="POST" hidden>
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="exercicio_id" value="<?= (int) $ex['id'] ?>">
                    <label>Nome do exercício</label>
                    <input name="nome_exercicio" maxlength="100" required value="<?= htmlspecialchars($ex['nome_exercicio'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="campos-exercicio">
                        <div><label>Séries</label><input name="series" type="number" min="1" required value="<?= (int) $ex['series'] ?>"></div>
                        <div><label>Repetições</label><input name="repeticoes" maxlength="20" required value="<?= htmlspecialchars($ex['repeticoes'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div><label>Carga</label><input name="carga" maxlength="20" value="<?= htmlspecialchars($ex['carga'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                    </div>
                    <label>Observações</label>
                    <textarea name="observacoes" maxlength="255" rows="3"><?= htmlspecialchars($ex['observacoes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <button type="submit" class="btn btn-primary">Salvar alterações</button>
                </form>

                <?php if (!empty($ex['observacoes'])): ?>
                    <p class="exercicio-observacoes">
                        <?= htmlspecialchars($ex['observacoes']) ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    const origem = document.getElementById('origem_exercicio');
    const blocoCatalogo = document.getElementById('campos-catalogo');
    const blocoManual = document.getElementById('campo-manual');
    const grupo = document.getElementById('grupo_muscular');
    const seletor = document.getElementById('catalogo_id');
    const manual = document.getElementById('nome_exercicio');
    const opcoes = Array.from(seletor.options).slice(1);

    function filtrar() {
        seletor.value = '';
        for (const opcao of opcoes) {
            const visivel = grupo.value !== '' && opcao.dataset.grupo === grupo.value;
            opcao.hidden = !visivel;
            opcao.disabled = !visivel;
        }
        seletor.disabled = !grupo.value || origem.value !== 'catalogo';
    }
    function alternar() {
        const usarCatalogo = origem.value === 'catalogo';
        blocoCatalogo.hidden = !usarCatalogo;
        blocoManual.hidden = usarCatalogo;
        grupo.required = usarCatalogo;
        manual.required = !usarCatalogo;
        manual.disabled = usarCatalogo;
        seletor.required = usarCatalogo;
        if (usarCatalogo) filtrar();
        else seletor.disabled = true;
    }
    grupo.addEventListener('change', filtrar);
    origem.addEventListener('change', alternar);
    alternar();
})();
</script>

<style>
#campos-catalogo[hidden], #campo-manual[hidden] { display: none !important; }
.campos-catalogo-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.panel-card form select { width: 100%; padding: 12px; margin-bottom: 18px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #fff; font: inherit; }
.panel-card form select:focus { outline: none; border-color: #38bdf8; }
@media (max-width: 640px) { .campos-catalogo-grid { grid-template-columns: 1fr; } }
.acoes-exercicio { display: flex; gap: 10px; align-items: center; margin-top: 14px; }
.acoes-exercicio form { margin: 0; }
.btn-excluir { background: #7f1d1d; color: #fff; border: 1px solid #b91c1c; padding: 9px 14px; border-radius: 8px; cursor: pointer; }
.form-editar { margin-top: 16px; padding: 18px; border: 1px solid #334155; border-radius: 8px; }
.form-editar[hidden] { display: none !important; }

.campos-exercicio {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.panel-card form label {
    display: block;
    color: #cbd5e1;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 8px;
}

.panel-card form input,
.panel-card form textarea {
    width: 100%;
    padding: 12px;
    margin-bottom: 18px;
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 8px;
    color: #fff;
    font: inherit;
}

.panel-card form input:focus,
.panel-card form textarea:focus {
    outline: none;
    border-color: #38bdf8;
}

.exercicio-item {
    padding: 18px 0;
    border-bottom: 1px solid var(--border-color);
}

.exercicio-item h3 {
    color: #fff;
    margin-bottom: 12px;
}

.exercicio-detalhes {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    color: var(--text-muted);
    font-size: 13px;
}

.exercicio-detalhes strong {
    color: #fff;
}

.exercicio-observacoes {
    color: var(--text-muted);
    font-size: 13px;
    margin-top: 12px;
}

@media (max-width: 640px) {
    .campos-exercicio {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
