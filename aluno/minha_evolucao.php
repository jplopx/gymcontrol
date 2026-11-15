<?php
// aluno/minha_evolucao.php - Consulta das avaliações do aluno autenticado.
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['aluno']);
require_once __DIR__ . '/../config/conexao.php';

$alunoId = (int) ($_SESSION['usuario_id'] ?? 0);
$avaliacoes = [];
$erro = '';
try {
    $stmt = $pdo->prepare("
        SELECT af.*, u.nome AS nome_professor
        FROM avaliacoes_fisicas af
        LEFT JOIN usuarios u ON u.id = af.professor_id
        WHERE af.aluno_id = :aluno_id
        ORDER BY af.data_avaliacao DESC, af.id DESC
    ");
    $stmt->execute([':aluno_id' => $alunoId]);
    $avaliacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro ao consultar avaliações físicas: ' . $e->getMessage());
    $erro = 'Não foi possível carregar suas avaliações no momento.';
}

function escapar($valor): string {
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}
function numeroAvaliacao($valor, int $casas = 1): string {
    return $valor === null || $valor === '' ? '—' : number_format((float) $valor, $casas, ',', '.');
}
function imcAvaliacao($peso, $altura): ?float {
    if ($peso === null || $altura === null || (float) $peso <= 0 || (float) $altura <= 0) return null;
    return round((float) $peso / ((float) $altura * (float) $altura), 1);
}
$ultima = $avaliacoes[0] ?? null;
$imcAtual = $ultima ? imcAvaliacao($ultima['peso'], $ultima['altura']) : null;
$pesos = [];
foreach (array_reverse($avaliacoes) as $av) {
    if ($av['peso'] !== null && (float) $av['peso'] > 0) {
        $pesos[] = ['data' => date('d/m/Y', strtotime($av['data_avaliacao'])), 'peso' => (float) $av['peso']];
    }
}
$medidas = [
    'Peito' => 'peito', 'Cintura' => 'cintura', 'Abdômen' => 'abdomen',
    'Quadril' => 'quadril', 'Braço direito' => 'braco_direito',
    'Braço esquerdo' => 'braco_esquerdo', 'Coxa direita' => 'coxa_direita',
    'Coxa esquerda' => 'coxa_esquerda'
];
require_once __DIR__ . '/../layouts/header.php';
?>
<style>
.evo-title{color:#fff;font-size:27px;font-weight:800;margin:12px 0 6px}
.evo-sub{color:var(--text-muted);margin-bottom:26px;font-size:14px}
.evo-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px}
.evo-stat{background:#0b1220;border:1px solid var(--border-color);border-radius:12px;padding:17px}
.evo-stat small{display:block;color:var(--text-muted);margin-bottom:8px}
.evo-stat strong{color:#fff;font-size:23px}
.evo-card{margin-bottom:24px}
.evo-card h2{font-size:18px;color:#fff;margin:0 0 17px}
.evo-chart{display:flex;align-items:end;gap:12px;min-height:210px;overflow-x:auto;padding:20px 8px 5px;border-bottom:1px solid var(--border-color)}
.evo-bar-wrap{flex:1 0 55px;max-width:110px;display:flex;flex-direction:column;align-items:center;justify-content:end;gap:6px;height:190px}
.evo-bar{width:60%;min-height:3px;background:linear-gradient(180deg,#38bdf8,#0284c7);border-radius:7px 7px 0 0}
.evo-bar-wrap span{font-size:12px;color:#fff;font-weight:700}
.evo-bar-wrap small{font-size:11px;color:var(--text-muted);white-space:nowrap}
.evo-table{width:100%;border-collapse:collapse;font-size:13px}
.evo-table th,.evo-table td{padding:12px 13px;text-align:left;border-bottom:1px solid var(--border-color);color:#e2e8f0;white-space:nowrap}
.evo-table th{color:var(--text-muted)}
.evo-measures{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.evo-measure{background:#0b1220;border:1px solid var(--border-color);border-radius:10px;padding:13px;color:#e2e8f0}
.evo-measure small{display:block;color:var(--text-muted);margin-bottom:5px}
.evo-empty{color:var(--text-muted);font-size:14px;line-height:1.7}
</style>
<a href="index.php" style="color:#38bdf8;text-decoration:none;font-size:13px"><i class="fa-solid fa-arrow-left"></i> Voltar ao meu painel</a>
<h1 class="evo-title"><i class="fa-solid fa-heart-pulse" style="color:#34d399"></i> Minha Evolução Física</h1>
<p class="evo-sub">Acompanhe as avaliações registradas pelo seu professor.</p>
<?php if ($erro !== ''): ?>
    <div class="panel-card" style="color:#fca5a5"><?= escapar($erro) ?></div>
<?php elseif (!$avaliacoes): ?>
    <div class="panel-card"><p class="evo-empty">Você ainda não possui avaliações físicas cadastradas. Quando seu professor registrar a primeira, ela aparecerá aqui.</p></div>
<?php else: ?>
    <?php $comparativoAvaliacoes = $avaliacoes; require __DIR__ . '/../includes/comparativo_avaliacoes.php'; ?>
    <div class="evo-grid">
        <div class="evo-stat"><small>Último peso</small><strong><?= numeroAvaliacao($ultima['peso']) ?><?= $ultima['peso'] !== null ? ' kg' : '' ?></strong></div>
        <div class="evo-stat"><small>Altura</small><strong><?= numeroAvaliacao($ultima['altura'], 2) ?><?= $ultima['altura'] !== null ? ' m' : '' ?></strong></div>
        <div class="evo-stat"><small>IMC</small><strong><?= $imcAtual !== null ? numeroAvaliacao($imcAtual) : '—' ?></strong></div>
        <div class="evo-stat"><small>Avaliações registradas</small><strong><?= count($avaliacoes) ?></strong></div>
    </div>
    <div class="panel-card evo-card">
        <h2><i class="fa-solid fa-chart-column" style="color:#38bdf8"></i> Evolução do peso</h2>
        <?php if (!$pesos): ?>
            <p class="evo-empty">Ainda não há pesos registrados.</p>
        <?php else: ?>
            <?php
                $valores = array_column($pesos, 'peso');
                $min = min($valores);
                $max = max($valores);
                $base = max(0, $min - 5);
                $intervalo = max(1, $max - $base);
            ?>
            <div class="evo-chart" role="img" aria-label="Evolução do peso em quilogramas por data de avaliação">
                <?php foreach ($pesos as $p): ?>
                    <?php $alturaBarra = 25 + (($p['peso'] - $base) / $intervalo) * 115; ?>
                    <div class="evo-bar-wrap">
                        <span><?= numeroAvaliacao($p['peso']) ?> kg</span>
                        <div class="evo-bar" style="height:<?= (int) round($alturaBarra) ?>px" title="<?= escapar($p['data']) ?>: <?= numeroAvaliacao($p['peso']) ?> kg"></div>
                        <small><?= escapar($p['data']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <p style="color:var(--text-muted);font-size:12px;margin-top:12px">Barras mostram a evolução relativa dos pesos registrados.</p>
        <?php endif; ?>
    </div>
    <div class="panel-card evo-card">
        <h2><i class="fa-solid fa-ruler" style="color:#34d399"></i> Medidas da última avaliação</h2>
        <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px">Avaliação de <?= date('d/m/Y', strtotime($ultima['data_avaliacao'])) ?> · Professor: <?= escapar($ultima['nome_professor'] ?? 'Não informado') ?></p>
        <div class="evo-measures">
            <?php foreach ($medidas as $rotulo => $coluna): ?>
                <div class="evo-measure"><small><?= escapar($rotulo) ?></small><strong><?= numeroAvaliacao($ultima[$coluna]) ?><?= $ultima[$coluna] !== null ? ' cm' : '' ?></strong></div>
            <?php endforeach; ?>
            <div class="evo-measure"><small>Gordura corporal</small><strong><?= numeroAvaliacao($ultima['percentual_gordura']) ?><?= $ultima['percentual_gordura'] !== null ? '%' : '' ?></strong></div>
        </div>
        <?php if (!empty($ultima['observacoes'])): ?>
            <p style="color:#e2e8f0;margin-top:18px;white-space:pre-wrap;overflow-wrap:anywhere"><strong>Observações:</strong> <?= escapar($ultima['observacoes']) ?></p>
        <?php endif; ?>
    </div>
    <div class="panel-card evo-card">
        <h2><i class="fa-solid fa-clock-rotate-left" style="color:#38bdf8"></i> Histórico de avaliações</h2>
        <div style="overflow-x:auto">
            <table class="evo-table">
                <thead><tr><th>Data</th><th>Professor</th><th>Peso</th><th>Altura</th><th>IMC</th><th>Gordura</th></tr></thead>
                <tbody>
                <?php foreach ($avaliacoes as $av): ?>
                    <?php $imc = imcAvaliacao($av['peso'], $av['altura']); ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($av['data_avaliacao'])) ?></td>
                        <td><?= escapar($av['nome_professor'] ?? 'Não informado') ?></td>
                        <td><?= numeroAvaliacao($av['peso']) ?><?= $av['peso'] !== null ? ' kg' : '' ?></td>
                        <td><?= numeroAvaliacao($av['altura'], 2) ?><?= $av['altura'] !== null ? ' m' : '' ?></td>
                        <td><?= $imc !== null ? numeroAvaliacao($imc) : '—' ?></td>
                        <td><?= numeroAvaliacao($av['percentual_gordura']) ?><?= $av['percentual_gordura'] !== null ? '%' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
