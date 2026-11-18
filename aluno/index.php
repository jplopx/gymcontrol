<?php
// aluno/index.php - Painel Exclusivo do Aluno
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['aluno', 'admin']);
require_once __DIR__ . '/../config/conexao.php';

$usuario_id = $_SESSION['usuario_id'];
$dadosAluno = null;

try {
    // Busca dados complementares do aluno e nome do treinador vinculado
    $stmt = $pdo->prepare("
        SELECT a.*, p.nome AS nome_professor 
        FROM alunos a 
        LEFT JOIN usuarios p ON a.professor_id = p.id AND p.academia_id=a.academia_id
        WHERE a.usuario_id = :usuario_id AND a.academia_id = :academia_id
        LIMIT 1
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':academia_id' => (int) $_SESSION['academia_id']]);
    $dadosAluno = $stmt->fetch();
} catch (PDOException $e) {
    // Em caso de tabela pendente ou erro de conexão, mantém fallback gracioso
    $dadosAluno = null;
}


// Indicadores do dashboard: cada consulta e independente para preservar o painel
// caso alguma funcionalidade ainda nao esteja instalada.
$dashboard = [
    'xp' => 0, 'fichas' => 0, 'ofensiva' => 0,
    'xp_semana' => 0, 'agendamentos' => [], 'avaliacao' => null,
    'desafios_ativos' => null, 'mensagens_nao_lidas' => null
];
$hojeDashboard = new DateTimeImmutable('today');
try {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(xp),0) AS xp, COUNT(*) AS fichas FROM gamificacao_treinos_concluidos WHERE aluno_id = ?');
    $stmt->execute([$usuario_id]);
    $totais = $stmt->fetch(PDO::FETCH_ASSOC);
    $dashboard['xp'] = (int)($totais['xp'] ?? 0);
    $dashboard['fichas'] = (int)($totais['fichas'] ?? 0);
    $stmt = $pdo->prepare('SELECT DISTINCT data_treino FROM gamificacao_treinos_concluidos WHERE aluno_id = ? AND data_treino <= CURDATE() ORDER BY data_treino DESC LIMIT 400');
    $stmt->execute([$usuario_id]);
    $dias = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $diaEsperado = $hojeDashboard;
    if (!empty($dias) && $dias[0] !== $diaEsperado->format('Y-m-d')) {
        $diaEsperado = $diaEsperado->modify('-1 day');
    }
    foreach ($dias as $dia) {
        if ($dia !== $diaEsperado->format('Y-m-d')) break;
        $dashboard['ofensiva']++;
        $diaEsperado = $diaEsperado->modify('-1 day');
    }
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(xp),0) FROM gamificacao_treinos_concluidos WHERE aluno_id = ? AND data_treino BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND CURDATE()');
    $stmt->execute([$usuario_id]);
    $dashboard['xp_semana'] = (int)$stmt->fetchColumn();
} catch (PDOException $e) { /* Mantenha o painel acessivel. */ }
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(r.xp),0) FROM gamificacao_desafios_recompensas r WHERE r.aluno_id = ?");
    $stmt->execute([$usuario_id]);
    $dashboard['xp'] += (int)$stmt->fetchColumn();
} catch (PDOException $e) { /* Recompensas opcionais. */ }
try {
    $stmt = $pdo->prepare("SELECT id, titulo, tipo, data_agendamento, hora_inicio, hora_fim FROM agendamentos WHERE aluno_id = ? AND status = 'agendado' AND (data_agendamento > CURDATE() OR (data_agendamento = CURDATE() AND hora_fim >= CURTIME())) ORDER BY data_agendamento, hora_inicio LIMIT 2");
    $stmt->execute([$usuario_id]);
    $dashboard['agendamentos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* Agenda opcional. */ }
try {
    $stmt = $pdo->prepare('SELECT data_avaliacao, peso, percentual_gordura FROM avaliacoes_fisicas WHERE aluno_id = ? ORDER BY data_avaliacao DESC, id DESC LIMIT 1');
    $stmt->execute([$usuario_id]);
    $dashboard['avaliacao'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) { /* Avaliacoes opcionais. */ }
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM mensagens WHERE destinatario_id = ? AND lida = 0');
    $stmt->execute([$usuario_id]);
    $dashboard['mensagens_nao_lidas'] = (int)$stmt->fetchColumn();
} catch (PDOException $e) { /* Mensagens opcionais. */ }
$nivelDashboard = intdiv($dashboard['xp'], 500) + 1;
$xpNivelDashboard = $dashboard['xp'] % 500;
$progressoNivelDashboard = ($xpNivelDashboard / 500) * 100;
$ligaDashboard = $dashboard['xp_semana'] >= 1500 ? 'Diamante' : ($dashboard['xp_semana'] >= 900 ? 'Ouro' : ($dashboard['xp_semana'] >= 400 ? 'Prata' : 'Bronze'));
function dashEsc($valor) { return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8'); }
function dashData($valor) { return $valor ? date('d/m/Y', strtotime($valor)) : '—'; }
function dashHora($valor) { return substr((string)$valor, 0, 5); }

require_once __DIR__ . '/../layouts/header.php';
?>
<div class="gc-section-marker">VISÃO GERAL</div>


<!-- Renderização de alertas de acesso negado quando redirecionado -->
<?php renderizarAvisoPermissao(); ?>

<style>
.gc-dash-hero{background:linear-gradient(115deg,#18324c,#17263c 62%,#132033);border:1px solid #34475d;border-radius:18px;padding:28px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap}
.gc-dash-hero h1{color:white;font-size:28px;font-weight:800;margin:0 0 6px}
.gc-dash-hero p{color:#a6bed6;margin:0;font-size:14px}
.gc-dash-hero a{display:inline-flex;align-items:center;gap:8px;text-decoration:none;background:#0e91d3;color:white;border-radius:9px;padding:12px 16px;font-weight:700;font-size:13px}
.gc-dash-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}
.gc-dash-stat{background:#1e293b;border:1px solid #344256;border-radius:14px;padding:19px;min-width:0}
.gc-dash-stat .gc-label{font-size:12px;color:#9db6d0;display:flex;align-items:center;gap:8px;margin-bottom:12px}
.gc-dash-stat strong{display:block;font-size:27px;line-height:1.2;color:white;font-weight:800}
.gc-dash-stat small{display:block;color:#8ca6c3;margin-top:7px;font-size:12px}
.gc-dash-columns{display:grid;grid-template-columns:1.1fr 1fr;gap:18px;margin-bottom:24px}
.gc-dash-panel{background:#1e293b;border:1px solid #344256;border-radius:14px;padding:22px;min-width:0}
.gc-dash-panel h2{font-size:17px;color:#fff;margin:0 0 15px;font-weight:750}
.gc-dash-panel p{color:#9db6d0;font-size:13px;margin:0 0 12px}
.gc-dash-progress{background:#101a2b;height:11px;border-radius:20px;overflow:hidden;margin:12px 0 8px}
.gc-dash-progress>div{height:100%;background:linear-gradient(90deg,#0ea5e9,#38bdf8);border-radius:20px}
.gc-dash-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #334155;color:white;font-size:13px}
.gc-dash-row:last-child{border-bottom:0}
.gc-dash-link{color:#38bdf8;font-size:13px;text-decoration:none;font-weight:700}
.gc-dash-section{color:#fff;font-size:20px;font-weight:800;margin:28px 0 15px}
@media(max-width:900px){.gc-dash-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.gc-dash-columns{grid-template-columns:1fr}}
@media(max-width:500px){.gc-dash-hero{padding:20px}.gc-dash-stats{gap:9px}.gc-dash-stat{padding:13px}.gc-dash-stat strong{font-size:23px}}
</style>
<div class="gc-dash-hero">
  <div>
    <h1>Meu Dashboard <span aria-hidden="true">👋</span></h1>
    <p>Acompanhe seus treinos, conquistas e próximos compromissos em um só lugar.</p>
  </div>
  <a href="meus_treinos.php"><i class="fa-solid fa-dumbbell"></i> Ir para meu treino</a>
</div>
<div class="gc-dash-stats">
  <div class="gc-dash-stat"><span class="gc-label"><i class="fa-solid fa-bolt" style="color:#fbbf24"></i> XP acumulado</span><strong><?= number_format($dashboard['xp'],0,',','.') ?></strong><small><?= (int)$dashboard['fichas'] ?> ficha(s) concluída(s)</small></div>
  <div class="gc-dash-stat"><span class="gc-label"><i class="fa-solid fa-shield-halved" style="color:#38bdf8"></i> Nível atual</span><strong><?= $nivelDashboard ?></strong><small><?= $xpNivelDashboard ?> / 500 XP neste nível</small></div>
  <div class="gc-dash-stat"><span class="gc-label"><i class="fa-solid fa-fire" style="color:#fb923c"></i> Ofensiva de treinos</span><strong><?= (int)$dashboard['ofensiva'] ?> 🔥</strong><small>Dias consecutivos com ficha completa</small></div>
  <div class="gc-dash-stat"><span class="gc-label"><i class="fa-solid fa-trophy" style="color:#fbbf24"></i> Liga da semana</span><strong><?= dashEsc($ligaDashboard) ?></strong><small><?= (int)$dashboard['xp_semana'] ?> XP de treinos nesta semana</small></div>
</div>
<div class="gc-dash-columns">
  <section class="gc-dash-panel">
    <h2><i class="fa-solid fa-chart-simple" style="color:#38bdf8"></i> Seu progresso</h2>
    <p>Rumo ao nível <?= $nivelDashboard + 1 ?></p>
    <div class="gc-dash-progress" role="progressbar" aria-valuenow="<?= $xpNivelDashboard ?>" aria-valuemin="0" aria-valuemax="500"><div style="width:<?= $progressoNivelDashboard ?>%"></div></div>
    <p><?= $xpNivelDashboard ?> de 500 XP para avançar</p>
    <div class="gc-dash-row"><span>Fichas completas</span><strong><?= (int)$dashboard['fichas'] ?></strong></div>
    <div class="gc-dash-row"><span>XP de treinos na semana</span><strong><?= (int)$dashboard['xp_semana'] ?></strong></div>
    <div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:15px"><a class="gc-dash-link" href="gamificacao.php">Ver conquistas →</a><a class="gc-dash-link" href="ranking.php">Ranking →</a><a class="gc-dash-link" href="desafios.php">Meus desafios →</a></div>
  </section>
  <section class="gc-dash-panel">
    <h2><i class="fa-solid fa-calendar-check" style="color:#38bdf8"></i> Próximos agendamentos</h2>
    <?php if (!$dashboard['agendamentos']): ?>
      <p>Nenhum atendimento futuro agendado.</p>
    <?php else: foreach ($dashboard['agendamentos'] as $ag): ?>
      <div class="gc-dash-row"><div><strong><?= dashEsc($ag['titulo']) ?></strong><div style="color:#9db6d0;margin-top:5px"><?= dashData($ag['data_agendamento']) ?> • <?= dashHora($ag['hora_inicio']) ?>–<?= dashHora($ag['hora_fim']) ?></div></div><i class="fa-solid fa-calendar-day" style="color:#38bdf8"></i></div>
    <?php endforeach; endif; ?>
    <a class="gc-dash-link" href="agendamentos.php" style="display:inline-block;margin-top:15px">Ver minha agenda →</a>
  </section>
  <section class="gc-dash-panel">
    <h2><i class="fa-solid fa-heart-pulse" style="color:#34d399"></i> Última avaliação física</h2>
    <?php if (!$dashboard['avaliacao']): ?>
      <p>Você ainda não tem avaliações físicas registradas.</p>
    <?php else: $av = $dashboard['avaliacao']; ?>
      <p>Realizada em <?= dashData($av['data_avaliacao']) ?></p>
      <div class="gc-dash-row"><span>Peso</span><strong><?= $av['peso'] !== null ? number_format((float)$av['peso'],1,',','.') . ' kg' : 'Não informado' ?></strong></div>
      <div class="gc-dash-row"><span>Gordura corporal</span><strong><?= $av['percentual_gordura'] !== null ? number_format((float)$av['percentual_gordura'],1,',','.') . '%' : 'Não informado' ?></strong></div>
    <?php endif; ?>
    <a class="gc-dash-link" href="minha_evolucao.php" style="display:inline-block;margin-top:15px">Comparar avaliações →</a>
  </section>
  <section class="gc-dash-panel">
    <h2><i class="fa-solid fa-user-check" style="color:#fbbf24"></i> Meu acompanhamento</h2>
    <div class="gc-dash-row"><span>Matrícula</span><strong><?= dashEsc(ucfirst($dadosAluno['status_matricula'] ?? 'Não informada')) ?></strong></div>
    <div class="gc-dash-row"><span>Personal trainer</span><strong><?= dashEsc($dadosAluno['nome_professor'] ?? 'Aguardando vínculo') ?></strong></div>
    <?php if ($dashboard['mensagens_nao_lidas'] !== null): ?><div class="gc-dash-row"><span>Mensagens não lidas</span><strong><?= $dashboard['mensagens_nao_lidas'] ?></strong></div><?php endif; ?>
    <a class="gc-dash-link" href="mensagens.php" style="display:inline-block;margin-top:15px">Conversar com professor →</a>
  </section>
</div>
<h2 class="gc-dash-section">Acesso rápido</h2>

<div class="gc-quick-grid">
    <!-- Card Resumo da Matrícula -->
    <div class="panel-card gc-quick-card" style="margin-bottom: 0;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px; color: #fff; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-id-card" style="color: var(--cor-principal);"></i> Dados da Matrícula
        </h3>
        <div style="margin-bottom: 12px; font-size: 14px;">
            <span style="color: var(--text-muted);">Status:</span> 
            <?php 
                $statusMatricula = $dadosAluno['status_matricula'] ?? 'ativa';
                $isAtiva = ($statusMatricula === 'ativa');
            ?>
            <span style="color: <?php echo $isAtiva ? '#10b981' : '#f59e0b'; ?>; font-weight: 700; margin-left: 6px;">
                <i class="fa-solid <?php echo $isAtiva ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i> 
                <?php echo ucfirst($statusMatricula); ?>
            </span>
        </div>
        <div style="font-size: 14px;">
            <span style="color: var(--text-muted);">Personal Trainer:</span> 
            <strong style="color: #fff; margin-left: 6px;">
                <?php echo htmlspecialchars($dadosAluno['nome_professor'] ?? 'Aguardando vínculo'); ?>
            </strong>
        </div>
    </div>
    
    <!-- Card Ficha de Treino -->
    <div class="panel-card gc-quick-card" style="margin-bottom: 0;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 12px; color: #fff; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-dumbbell" style="color: var(--cor-accent);"></i> Ficha de Treino
        </h3>
        <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 14px;">Acesse a lista de exercícios e séries prescritas pelo seu treinador.</p>
        <a href="meus_treinos.php" class="btn btn-accent" style="width: 100%;">
            <i class="fa-solid fa-eye"></i> Visualizar Treino Atual
        </a>
    </div>

    <!-- Card Meu Progresso -->
    <div class="panel-card gc-quick-card" style="margin-bottom: 0;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 12px; color: #fff; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-chart-line" style="color: #38bdf8;"></i> Meu Progresso
        </h3>
        <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 14px;">Veja os exercícios concluídos hoje e seu histórico de treinos.</p>
        <a href="meu_progresso.php" class="btn btn-primary" style="width: 100%;">
            <i class="fa-solid fa-chart-column"></i> Acompanhar Meu Progresso
        </a>
    </div>
    <!-- Card Minha Evolução Física -->
    <div class="panel-card gc-quick-card" style="margin-bottom: 0;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 12px; color: #fff; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-heart-pulse" style="color: #34d399;"></i> Minha Evolução Física
        </h3>
        <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 14px;">Consulte suas avaliações físicas, IMC, medidas e evolução do peso.</p>
        <a href="minha_evolucao.php" class="btn btn-primary" style="width: 100%;">
            <i class="fa-solid fa-chart-line"></i> Ver Minha Evolução
        </a>
    </div>
    <!-- Card Ranking Semanal -->
    <div class="panel-card gc-quick-card" style="margin-bottom: 0;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 12px; color: #fff; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-trophy" style="color: #fbbf24;"></i> Ranking e Ligas
        </h3>
        <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 14px;">Compare seu XP semanal com outros alunos do mesmo professor. Participação opcional.</p>
        <a href="ranking.php" class="btn btn-primary" style="width: 100%;">
            <i class="fa-solid fa-ranking-star"></i> Ver Ranking Semanal
        </a>
    </div>
    <!-- Card Meus Desafios -->
    <div class="panel-card gc-quick-card" style="margin-bottom: 0;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 12px; color: #fff;"><i class="fa-solid fa-bullseye" style="color: #fbbf24;"></i> Meus Desafios</h3>
        <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 14px;">Acompanhe as metas do professor e desbloqueie XP extra.</p>
        <a href="desafios.php" class="btn btn-primary" style="width: 100%;"><i class="fa-solid fa-trophy"></i> Ver Desafios</a>
    </div>
    <!-- Card Minha Agenda -->
    <div class="panel-card gc-quick-card" style="margin-bottom: 0;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 12px; color: #fff;"><i class="fa-solid fa-calendar-days" style="color: #38bdf8;"></i> Minha Agenda</h3>
        <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 14px;">Veja suas aulas, avaliações e acompanhamentos agendados.</p>
        <a href="agendamentos.php" class="btn btn-primary" style="width: 100%;"><i class="fa-solid fa-calendar-check"></i> Ver Agendamentos</a>
    </div>
    <!-- Card Minhas Mensagens -->
    <div class="panel-card gc-quick-card" style="margin-bottom: 0;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 12px; color: #fff; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-comments" style="color: #38bdf8;"></i> Minhas Mensagens
        </h3>
        <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 14px;">Converse com seu professor e receba orientações.</p>
        <a href="mensagens.php" class="btn btn-primary" style="width: 100%;">
            <i class="fa-solid fa-envelope"></i> Abrir Mensagens
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
