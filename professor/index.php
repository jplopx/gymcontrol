<?php
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['professor', 'admin']);
require_once __DIR__ . '/../config/conexao.php';

$professor_id = (int) $_SESSION['usuario_id'];
$academia_id = (int) $_SESSION['academia_id'];
$meusAlunos = [];

// A lista de alunos não depende das consultas de indicadores.
try {
    $stmt = $pdo->prepare("SELECT u.id, u.nome, u.email, a.status_matricula
        FROM usuarios u
        JOIN alunos a ON u.id = a.usuario_id
        WHERE a.professor_id = ? AND a.academia_id = ?
          AND u.academia_id = a.academia_id AND u.status = 'ativo'");
    $stmt->execute([$professor_id, $academia_id]);
    $meusAlunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('GymControl professor/dashboard [lista_alunos]: ' . $e->getMessage());
}

// Uma falha de tabela/coluna em um indicador não pode derrubar os demais.
// null = indicador indisponível; zero = consulta executada e nenhum registro encontrado.
$indicadores = ['alunos'=>null, 'ativos'=>null, 'treinos'=>null,
                'avaliacoes'=>null, 'agendamentos'=>null];
$proximosHorarios = [];
$alertasAlunos = [];
$falhasDashboard = [];

$consultarDashboard = static function (string $parte, string $sql, array $parametros, string $formato = 'coluna') use ($pdo, $academia_id, &$falhasDashboard) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($parametros);
        if ($formato === 'linha') {
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
        if ($formato === 'linhas') {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        $falhasDashboard[$parte] = true;
        // Diagnóstico somente no log do servidor, sem expor SQL ao professor.
        error_log('GymControl professor/dashboard [' . $parte . '] academia=' . $academia_id
            . ': ' . $e->getMessage());
        return null;
    }
};

// Alunos e matrículas são calculados sem depender de treinos, avaliações ou agenda.
$resumo = $consultarDashboard('resumo', "SELECT COUNT(*) AS alunos,
    COALESCE(SUM(CASE WHEN a.status_matricula = 'ativa' THEN 1 ELSE 0 END), 0) AS ativos
    FROM alunos a JOIN usuarios u ON u.id = a.usuario_id
    WHERE a.professor_id = ? AND a.academia_id = ?
      AND u.academia_id = a.academia_id AND u.status = 'ativo'",
    [$professor_id, $academia_id], 'linha');
if ($resumo !== null) {
    $indicadores['alunos'] = (int) ($resumo['alunos'] ?? 0);
    $indicadores['ativos'] = (int) ($resumo['ativos'] ?? 0);
}

$contagens = [
    'treinos' => "SELECT COUNT(*) FROM treinos t
        JOIN alunos a ON a.usuario_id = t.aluno_id
        JOIN usuarios u ON u.id = a.usuario_id
        WHERE a.professor_id = ? AND a.academia_id = ?
          AND u.academia_id = a.academia_id AND u.status = 'ativo'
          AND t.professor_id = ?",
    'avaliacoes' => "SELECT COUNT(*) FROM avaliacoes_fisicas f
        JOIN alunos a ON a.usuario_id = f.aluno_id
        JOIN usuarios u ON u.id = a.usuario_id
        WHERE a.professor_id = ? AND a.academia_id = ?
          AND u.academia_id = a.academia_id AND u.status = 'ativo'
          AND f.professor_id = ?",
    'agendamentos' => "SELECT COUNT(*) FROM agendamentos g
        JOIN alunos a ON a.usuario_id = g.aluno_id
        JOIN usuarios u ON u.id = a.usuario_id
        WHERE a.professor_id = ? AND a.academia_id = ?
          AND u.academia_id = a.academia_id AND u.status = 'ativo'
          AND g.professor_id = ? AND g.status = 'agendado'
          AND (g.data_agendamento > CURDATE()
               OR (g.data_agendamento = CURDATE() AND g.hora_fim >= CURTIME()))"
];
foreach ($contagens as $campo => $sql) {
    $valor = $consultarDashboard($campo, $sql, [$professor_id, $academia_id, $professor_id]);
    if ($valor !== null) {
        $indicadores[$campo] = (int) $valor;
    }
}

$horarios = $consultarDashboard('proximos_horarios', "SELECT g.id, g.aluno_id,
    u.nome AS aluno_nome, g.titulo, g.tipo, g.data_agendamento, g.hora_inicio, g.hora_fim
    FROM agendamentos g
    JOIN alunos a ON a.usuario_id = g.aluno_id
    JOIN usuarios u ON u.id = g.aluno_id
    WHERE a.professor_id = ? AND a.academia_id = ?
      AND u.academia_id = a.academia_id AND u.status = 'ativo'
      AND g.professor_id = ? AND g.status = 'agendado'
      AND (g.data_agendamento > CURDATE()
           OR (g.data_agendamento = CURDATE() AND g.hora_fim >= CURTIME()))
    ORDER BY g.data_agendamento ASC, g.hora_inicio ASC LIMIT 5",
    [$professor_id, $academia_id, $professor_id], 'linhas');
if ($horarios !== null) {
    $proximosHorarios = $horarios;
}

$avisos = $consultarDashboard('alertas_alunos', "SELECT u.id, u.nome,
    (SELECT COUNT(*) FROM treinos t WHERE t.aluno_id = u.id AND t.professor_id = ?) AS total_treinos,
    (SELECT MAX(f.data_avaliacao) FROM avaliacoes_fisicas f
        WHERE f.aluno_id = u.id AND f.professor_id = ?) AS ultima_avaliacao
    FROM alunos a JOIN usuarios u ON u.id = a.usuario_id
    WHERE a.professor_id = ? AND a.academia_id = ?
      AND u.academia_id = a.academia_id AND u.status = 'ativo'
    ORDER BY u.nome LIMIT 100",
    [$professor_id, $professor_id, $professor_id, $academia_id], 'linhas');
if ($avisos !== null) {
    foreach ($avisos as $item) {
        $avisosAluno = [];
        if ((int) $item['total_treinos'] === 0) {
            $avisosAluno[] = 'Sem ficha de treino';
        }
        if (!$item['ultima_avaliacao']) {
            $avisosAluno[] = 'Sem avaliação física';
        } elseif ($item['ultima_avaliacao'] < date('Y-m-d', strtotime('-90 days'))) {
            $avisosAluno[] = 'Avaliação há mais de 90 dias';
        }
        if ($avisosAluno) {
            $alertasAlunos[] = ['id'=>(int) $item['id'], 'nome'=>$item['nome'], 'avisos'=>$avisosAluno];
        }
    }
}
$erroDashboard = !empty($falhasDashboard);

require_once __DIR__ . '/../layouts/header.php';
?>

<?php renderizarAvisoPermissao(); ?>

<style>
.prof-dashboard-hero{background:linear-gradient(115deg,#173b59,#13243b 75%);border:1px solid #29405b;border-radius:15px;padding:26px;margin:0 0 22px;display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap}
.prof-dashboard-hero h1{font-size:27px;font-weight:800;color:#fff;margin:0 0 6px}
.prof-dashboard-hero p,.prof-muted{color:#9fb5ce;font-size:14px;margin:0}
.prof-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px}
.prof-metric{background:#202c40;border:1px solid #33445e;border-radius:13px;padding:20px}
.prof-metric span{font-size:13px;color:#9ebcdb;display:block;margin-bottom:10px}
.prof-metric strong{font-size:29px;color:#fff;display:block}
.prof-dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:18px;margin-bottom:28px}
.prof-dashboard-box{background:#202c40;border:1px solid #33445e;border-radius:13px;padding:22px;min-width:0}
.prof-dashboard-box h3{color:#fff;font-size:18px;margin:0 0 18px}
.prof-dashboard-entry{padding:12px 0;border-bottom:1px solid #33445e;color:#fff;font-size:14px}
.prof-dashboard-entry:last-child{border-bottom:0}
.prof-dashboard-entry small{display:block;color:#9ebcdb;margin-top:6px;font-size:13px}
.prof-dashboard-entry a{color:#38bdf8;text-decoration:none}
.prof-tag{display:inline-block;margin:6px 6px 0 0;border:1px solid #8d6830;border-radius:7px;padding:4px 7px;color:#facc73;font-size:12px}
.prof-dashboard-warning{padding:12px 15px;border:1px solid #9b6b3d;border-radius:9px;color:#fbbf77;margin-bottom:18px}
</style>
<section class="prof-dashboard-hero gc-dash-hero">
 <div><h1><i class="fa-solid fa-chart-line" style="color:#38bdf8"></i> Dashboard do Professor</h1><p>Acompanhe seus alunos, atendimentos e prioridades em um só lugar.</p></div>
 <div class="gc-model-actions"><a href="modelos.php" class="btn btn-accent"><i class="fa-solid fa-layer-group"></i> Modelos de ficha</a><a href="#lista-alunos" class="btn btn-primary"><i class="fa-solid fa-users"></i> Gerenciar alunos</a></div>
</section>
<?php if ($erroDashboard): ?><div class="prof-dashboard-warning">Alguns indicadores estão indisponíveis. A equipe técnica pode identificar a consulta com falha no log do PHP; a lista de alunos permanece disponível.</div><?php endif; ?>
<div class="prof-metrics gc-dash-stats">
 <div class="prof-metric gc-dash-stat"><span><i class="fa-solid fa-users"></i> Meus alunos</span><strong><?= $indicadores['alunos'] === null ? '—' : (int) $indicadores['alunos'] ?></strong><small class="prof-muted"><?= $indicadores['ativos'] === null ? '—' : (int) $indicadores['ativos'] ?> matrícula(s) ativa(s)</small></div>
 <div class="prof-metric gc-dash-stat"><span><i class="fa-solid fa-dumbbell"></i> Fichas de treino</span><strong><?= $indicadores['treinos'] === null ? '—' : (int) $indicadores['treinos'] ?></strong><small class="prof-muted">Treinos cadastrados</small></div>
 <div class="prof-metric gc-dash-stat"><span><i class="fa-solid fa-heart-pulse"></i> Avaliações físicas</span><strong><?= $indicadores['avaliacoes'] === null ? '—' : (int) $indicadores['avaliacoes'] ?></strong><small class="prof-muted">Avaliações registradas</small></div>
 <div class="prof-metric gc-dash-stat"><span><i class="fa-solid fa-calendar-check"></i> Próximos horários</span><strong><?= $indicadores['agendamentos'] === null ? '—' : (int) $indicadores['agendamentos'] ?></strong><small class="prof-muted">Atendimentos agendados</small></div>
</div>
<div class="prof-dashboard-grid gc-dash-columns">
 <section class="prof-dashboard-box gc-dash-panel"><h3><i class="fa-solid fa-calendar-days" style="color:#38bdf8"></i> Próximos agendamentos</h3>
 <?php if (isset($falhasDashboard['proximos_horarios'])): ?><p class="prof-muted">Agendamentos indisponíveis no momento.</p><?php elseif (!$proximosHorarios): ?><p class="prof-muted">Nenhum agendamento futuro encontrado.</p><?php else: ?>
 <?php foreach ($proximosHorarios as $evento): ?>
 <div class="prof-dashboard-entry"><strong><?= htmlspecialchars($evento['titulo'],ENT_QUOTES,'UTF-8') ?></strong>
 <small><?= htmlspecialchars($evento['aluno_nome'],ENT_QUOTES,'UTF-8') ?> · <?= date('d/m/Y',strtotime($evento['data_agendamento'])) ?> · <?= substr($evento['hora_inicio'],0,5) ?>–<?= substr($evento['hora_fim'],0,5) ?></small>
 <small><a href="agendamentos.php?aluno_id=<?= (int)$evento['aluno_id'] ?>">Ver agendamento →</a></small></div>
 <?php endforeach; ?><?php endif; ?></section>
 <section class="prof-dashboard-box gc-dash-panel"><h3><i class="fa-solid fa-triangle-exclamation" style="color:#fbbf24"></i> Alunos que precisam de atenção</h3>
 <?php if (isset($falhasDashboard['alertas_alunos'])): ?><p class="prof-muted">Alertas indisponíveis no momento.</p><?php elseif (!$alertasAlunos): ?><p class="prof-muted">Nenhum alerta de treino ou avaliação entre os alunos consultados.</p><?php else: ?>
 <?php foreach (array_slice($alertasAlunos,0,6) as $alerta): ?>
 <div class="prof-dashboard-entry"><strong><?= htmlspecialchars($alerta['nome'],ENT_QUOTES,'UTF-8') ?></strong><div><?php foreach ($alerta['avisos'] as $aviso): ?><span class="prof-tag"><?= htmlspecialchars($aviso,ENT_QUOTES,'UTF-8') ?></span><?php endforeach; ?></div>
 <small><a href="treino.php?aluno_id=<?= $alerta['id'] ?>">Treino</a> · <a href="avaliacoes.php?aluno_id=<?= $alerta['id'] ?>">Avaliações</a></small></div>
 <?php endforeach; ?><?php if (count($alertasAlunos)>6): ?><p class="prof-muted" style="margin-top:10px">Mais <?= count($alertasAlunos)-6 ?> aluno(s) com alertas.</p><?php endif; ?><?php endif; ?></section>
</div>


<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
    <div>
        <h1 style="font-size: 28px; font-weight: 800; color: #fff; letter-spacing: -0.5px; margin-bottom: 4px;">Meus Alunos</h1>
        <p style="color: var(--text-muted); font-size: 15px;">Gerencie os treinos e acompanhe a evolução dos alunos sob sua responsabilidade.</p>
    </div>
</div>

<div class="panel-card" id="lista-alunos">
    <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #fff;">
        <i class="fa-solid fa-users" style="color: var(--cor-principal);"></i> Alunos Matriculados
    </h3>
    
    <?php if (count($meusAlunos) > 0): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted);">
                        <th style="padding: 14px 16px;">Nome do Aluno</th>
                        <th style="padding: 14px 16px;">E-mail</th>
                        <th style="padding: 14px 16px;">Status da Matrícula</th>
                        <th style="padding: 14px 16px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meusAlunos as $aluno): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 14px 16px; font-weight: 600; color: var(--text-main);">
                                <?php echo htmlspecialchars($aluno['nome']); ?>
                            </td>
                            <td style="padding: 14px 16px; color: var(--text-muted);">
                                <?php echo htmlspecialchars($aluno['email']); ?>
                            </td>
                            <td style="padding: 14px 16px;">
                                <?php if ($aluno['status_matricula'] === 'ativa'): ?>
                                    <span style="color: #10b981; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                                        <i class="fa-solid fa-circle-check"></i> Ativa
                                    </span>
                                <?php else: ?>
                                    <span style="color: #f59e0b; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Pendente/Bloqueada
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 16px; display: flex; gap: 10px; flex-wrap: wrap;">
                                <a href="treino.php?aluno_id=<?php echo $aluno['id']; ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                    <i class="fa-solid fa-dumbbell"></i> Ficha de Treino
                                </a>
                                <a href="avaliacoes.php?aluno_id=<?php echo (int) $aluno['id']; ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                    <i class="fa-solid fa-weight-scale"></i> Avaliação Física
                                </a>
                                <a href="desafios.php?aluno_id=<?php echo (int) $aluno['id']; ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-bullseye"></i> Desafios</a>
                                <a href="agendamentos.php?aluno_id=<?php echo (int) $aluno['id']; ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-calendar-days"></i> Agendamentos</a>
                                <a href="mensagens.php?aluno_id=<?php echo $aluno['id']; ?>" class="btn btn-accent" style="padding: 6px 12px; font-size: 12px;">
                                    <i class="fa-solid fa-comment"></i> Mensagem
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="color: var(--text-muted); font-size: 14px;">Você ainda não tem alunos vinculados. Peça ao administrador para fazer a associação.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>