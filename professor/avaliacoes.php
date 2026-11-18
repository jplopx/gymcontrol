<?php
// professor/avaliacoes.php - Cadastro e histórico de avaliações físicas
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['professor', 'admin']);
require_once __DIR__ . '/../config/conexao.php';

$professorId = (int) ($_SESSION['usuario_id'] ?? 0);
$alunoId = filter_input(INPUT_GET, 'aluno_id', FILTER_VALIDATE_INT);
$aluno = null;
$historico = [];
$erro = '';
$sucesso = !empty($_SESSION['avaliacao_sucesso']);
unset($_SESSION['avaliacao_sucesso']);

if (empty($_SESSION['csrf_avaliacoes'])) {
    $_SESSION['csrf_avaliacoes'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_avaliacoes'];

function escaparAvaliacao($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$campos = [
    'peso' => ['Peso (kg)', 0.01, 500],
    'altura' => ['Altura (m)', 0.5, 2.7],
    'peito' => ['Peito (cm)', 1, 300],
    'cintura' => ['Cintura (cm)', 1, 300],
    'abdomen' => ['Abdômen (cm)', 1, 300],
    'quadril' => ['Quadril (cm)', 1, 300],
    'braco_direito' => ['Braço direito (cm)', 1, 200],
    'braco_esquerdo' => ['Braço esquerdo (cm)', 1, 200],
    'coxa_direita' => ['Coxa direita (cm)', 1, 200],
    'coxa_esquerda' => ['Coxa esquerda (cm)', 1, 200],
    'percentual_gordura' => ['Gordura corporal (%)', 0, 100],
];

try {
    if (!$alunoId) {
        $erro = 'Selecione um aluno a partir do painel Meus Alunos.';
    } else {
        $stmt = $pdo->prepare("SELECT u.id, u.nome, u.email FROM usuarios u
            INNER JOIN alunos a ON a.usuario_id = u.id
            WHERE u.id = :aluno AND a.professor_id = :professor
              AND a.academia_id = :academia AND u.academia_id = a.academia_id
              AND u.status = 'ativo' AND u.nivel_acesso = 'aluno'
            LIMIT 1");
        $stmt->execute([':aluno' => $alunoId, ':professor' => $professorId, ':academia' => (int) $_SESSION['academia_id']]);
        $aluno = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$aluno) {
            $erro = 'Aluno não encontrado ou não vinculado a você.';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            $data = $_POST['data_avaliacao'] ?? '';
            $observacoes = trim((string) ($_POST['observacoes'] ?? ''));
            $valores = [];
            if (!is_string($token) || !hash_equals($csrf, $token)) {
                $erro = 'Sessão inválida. Atualize a página.';
            } else {
                $dt = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $data);
                if (!$dt || $dt->format('Y-m-d') !== $data || $data > date('Y-m-d')) {
                    $erro = 'Informe uma data válida, não futura.';
                }
                foreach ($campos as $campo => $config) {
                    $bruto = trim((string) ($_POST[$campo] ?? ''));
                    if ($bruto === '') {
                        $valores[$campo] = null;
                        continue;
                    }
                    $bruto = str_replace(',', '.', $bruto);
                    if (!is_numeric($bruto) || !is_finite((float) $bruto)
                        || (float) $bruto < $config[1] || (float) $bruto > $config[2]) {
                        $erro = 'Confira o valor de ' . $config[0] . '.';
                        break;
                    }
                    $valores[$campo] = round((float) $bruto, 2);
                }
                if (mb_strlen($observacoes, 'UTF-8') > 5000) {
                    $erro = 'Observações devem ter até 5000 caracteres.';
                }
                if ($erro === '') {
                    $colunas = array_keys($campos);
                    $sql = 'INSERT INTO avaliacoes_fisicas (aluno_id, professor_id, data_avaliacao, '
                        . implode(', ', $colunas) . ', observacoes) VALUES (:aluno, :professor, :data, '
                        . implode(', ', array_map(fn($c) => ':' . $c, $colunas)) . ', :observacoes)';
                    $params = [':aluno' => $alunoId, ':professor' => $professorId,
                        ':data' => $data, ':observacoes' => $observacoes ?: null];
                    foreach ($valores as $campo => $valor) $params[':' . $campo] = $valor;
                    $pdo->prepare($sql)->execute($params);
                    $_SESSION['avaliacao_sucesso'] = true;
                    header('Location: avaliacoes.php?aluno_id=' . $alunoId);
                    exit;
                }
            }
        }
        if ($aluno) {
            $stmt = $pdo->prepare('SELECT * FROM avaliacoes_fisicas WHERE aluno_id = :aluno AND professor_id = :professor ORDER BY data_avaliacao DESC, id DESC');
            $stmt->execute([':aluno' => $alunoId, ':professor' => $professorId]);
            $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    error_log('Avaliações físicas: ' . $e->getMessage());
    $erro = 'Erro ao acessar as avaliações. Confira a conexão e a tabela avaliacoes_fisicas.';
}

require_once __DIR__ . '/../layouts/header.php';
?>
<style>
.avaliacao-grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-top:18px}
.avaliacao-campo label{display:block;color:#e2e8f0;font-size:13px;margin-bottom:7px}
.avaliacao-campo input,.avaliacao-campo textarea{box-sizing:border-box;width:100%;padding:12px;border:1px solid var(--border-color);border-radius:9px;background:#0b1220;color:#fff;font:inherit}
.avaliacao-campo textarea{min-height:100px;resize:vertical}
.avaliacao-aviso{padding:12px 16px;border-radius:9px;margin-bottom:16px;background:#3f2025;color:#fecaca}
.avaliacao-aviso.ok{background:#12382e;color:#86efac}
.avaliacao-tabela{width:100%;border-collapse:collapse;text-align:left;font-size:13px;color:#e2e8f0}
.avaliacao-tabela th,.avaliacao-tabela td{padding:12px;border-bottom:1px solid var(--border-color)}
.avaliacao-tabela th{color:var(--text-muted)}
.avaliacao-grafico{display:flex;align-items:end;gap:12px;min-height:160px;overflow-x:auto;padding:18px 4px 4px}
.avaliacao-barra{display:flex;flex-direction:column;align-items:center;justify-content:end;min-width:68px;gap:6px;font-size:11px;color:var(--text-muted)}
.avaliacao-barra .barra{width:40px;background:#38bdf8;border-radius:6px 6px 0 0;min-height:4px}
</style>
<div class="gc-section-banner" style="margin-bottom: 28px;">
  <a href="index.php" style="color:var(--cor-principal);text-decoration:none"><i class="fa-solid fa-arrow-left"></i> Voltar aos alunos</a>
  <h1 style="color:#fff;font-size:27px;margin:12px 0 5px">Avaliação Física</h1>
  <p style="color:var(--text-muted)"><?= $aluno ? escaparAvaliacao($aluno['nome']) : 'Cadastro e acompanhamento de avaliações' ?></p>
</div>
<?php if ($erro): ?><div class="avaliacao-aviso" role="alert"><?= escaparAvaliacao($erro) ?></div><?php endif; ?>
<?php if ($sucesso): ?><div class="avaliacao-aviso ok">Avaliação salva com sucesso!</div><?php endif; ?>
<?php if ($aluno): ?>
<?php $comparativoAvaliacoes = $historico; require __DIR__ . '/../includes/comparativo_avaliacoes.php'; ?>
<div class="panel-card">
  <h3 style="color:#fff"><i class="fa-solid fa-clipboard-list"></i> Nova avaliação</h3>
  <p style="color:var(--text-muted);font-size:13px">Preencha os dados disponíveis. O IMC será calculado a partir do peso e da altura.</p>
  <form method="post" action="avaliacoes.php?aluno_id=<?= (int) $alunoId ?>">
    <input type="hidden" name="csrf_token" value="<?= escaparAvaliacao($csrf) ?>">
    <div class="avaliacao-grid">
      <div class="avaliacao-campo"><label for="data_avaliacao">Data da avaliação *</label><input type="date" id="data_avaliacao" name="data_avaliacao" max="<?= date('Y-m-d') ?>" value="<?= escaparAvaliacao($_POST['data_avaliacao'] ?? date('Y-m-d')) ?>" required></div>
      <?php foreach ($campos as $campo => $config): ?>
      <?php $unidade = $campo === 'peso' ? 'kg' : ($campo === 'altura' ? 'm' : ($campo === 'percentual_gordura' ? '%' : 'cm'));
            $exemplo = $campo === 'peso' ? '70,5' : ($campo === 'altura' ? '1,75' : ($campo === 'percentual_gordura' ? '18,5' : '85,5')); ?>
      <div class="avaliacao-campo"><label for="<?= escaparAvaliacao($campo) ?>"><?= escaparAvaliacao($config[0]) ?></label>
        <div class="gc-unit-field"><input type="text" inputmode="decimal" autocomplete="off" pattern="[0-9]+([,.][0-9]{1,2})?" data-medida data-campo="<?= escaparAvaliacao($campo) ?>" data-max="<?= escaparAvaliacao($config[2]) ?>" maxlength="8" placeholder="Ex.: <?= escaparAvaliacao($exemplo) ?>" id="<?= escaparAvaliacao($campo) ?>" name="<?= escaparAvaliacao($campo) ?>" value="<?= escaparAvaliacao($_POST[$campo] ?? '') ?>" aria-describedby="unidade-<?= escaparAvaliacao($campo) ?>"><span class="gc-unit" id="unidade-<?= escaparAvaliacao($campo) ?>"><?= escaparAvaliacao($unidade) ?></span></div></div>
      <?php endforeach; ?>
    </div>
    <p id="avaliacao-imc" style="color:#38bdf8;font-weight:700;margin-top:16px">IMC: informe peso e altura</p>
    <div class="avaliacao-campo" style="margin-top:16px"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" maxlength="5000" placeholder="Observações sobre a avaliação..."><?= escaparAvaliacao($_POST['observacoes'] ?? '') ?></textarea></div>
    <button class="btn btn-primary" type="submit" style="margin-top:18px"><i class="fa-solid fa-floppy-disk"></i> Salvar avaliação</button>
  </form>
</div>
<div class="panel-card">
  <h3 style="color:#fff"><i class="fa-solid fa-chart-line"></i> Evolução do peso</h3>
  <?php $pesos = array_values(array_filter(array_reverse($historico), fn($r) => $r['peso'] !== null)); ?>
  <?php if ($pesos): ?>
  <?php $maxPeso = max(array_map(fn($r) => (float)$r['peso'], $pesos)); ?>
  <div class="avaliacao-grafico">
    <?php foreach ($pesos as $registro): ?>
    <div class="avaliacao-barra"><strong style="color:#fff"><?= escaparAvaliacao(number_format((float)$registro['peso'], 1, ',', '.')) ?> kg</strong><div class="barra" style="height:<?= max(5, round((float)$registro['peso'] / max($maxPeso, 1) * 110)) ?>px"></div><span><?= escaparAvaliacao(date('d/m/y', strtotime($registro['data_avaliacao']))) ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php else: ?><p style="color:var(--text-muted)">Registre uma avaliação com peso para visualizar a evolução.</p><?php endif; ?>
</div>
<div class="panel-card">
  <h3 style="color:#fff"><i class="fa-solid fa-clock-rotate-left"></i> Histórico de avaliações</h3>
  <?php if (!$historico): ?><p style="color:var(--text-muted)">Nenhuma avaliação cadastrada para este aluno.</p>
  <?php else: ?><div style="overflow-x:auto"><table class="avaliacao-tabela"><thead><tr><th>Data</th><th>Peso</th><th>Altura</th><th>IMC</th><th>Cintura</th><th>Gordura</th><th>Observações</th></tr></thead><tbody>
  <?php foreach ($historico as $registro): ?>
  <?php $imc = ($registro['peso'] !== null && (float)$registro['altura'] > 0) ? (float)$registro['peso'] / ((float)$registro['altura'] ** 2) : null; ?>
  <tr><td><?= escaparAvaliacao(date('d/m/Y', strtotime($registro['data_avaliacao']))) ?></td>
    <td><?= $registro['peso'] === null ? '—' : escaparAvaliacao($registro['peso']) . ' kg' ?></td>
    <td><?= $registro['altura'] === null ? '—' : escaparAvaliacao($registro['altura']) . ' m' ?></td>
    <td><?= $imc === null ? '—' : escaparAvaliacao(number_format($imc, 1, ',', '.')) ?></td>
    <td><?= $registro['cintura'] === null ? '—' : escaparAvaliacao($registro['cintura']) . ' cm' ?></td>
    <td><?= $registro['percentual_gordura'] === null ? '—' : escaparAvaliacao($registro['percentual_gordura']) . '%' ?></td>
    <td style="white-space:pre-wrap;max-width:280px"><?= escaparAvaliacao($registro['observacoes'] ?: '—') ?></td>
  </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<?php endif; ?>
<script src="../assets/avaliacao-medidas.js?v=20261118-1" defer></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
