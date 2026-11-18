<?php
// aluno/meu_progresso.php - Acompanhamento de exercícios concluídos
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['aluno', 'admin']);
require_once __DIR__ . '/../config/conexao.php';

$usuario_id = (int) $_SESSION['usuario_id'];
$hoje = date('Y-m-d');
$inicio7 = date('Y-m-d', strtotime('-6 days'));
$inicio30 = date('Y-m-d', strtotime('-29 days'));
$totalPrescrito = 0;
$concluidosHoje = 0;
$dias7 = 0;
$dias30 = 0;
$historico = [];
$erro = '';

try {
    // Exercícios atualmente atribuídos ao aluno (não contam fichas de terceiros).
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM exercicios e INNER JOIN treinos t ON t.id = e.treino_id WHERE t.aluno_id = :aluno_id');
    $stmt->execute([':aluno_id' => $usuario_id]);
    $totalPrescrito = (int) $stmt->fetchColumn();

    // O progresso histórico fica vinculado ao aluno autenticado e aos seus treinos.
    $stmt = $pdo->prepare('SELECT p.data_treino, COUNT(*) AS quantidade
        FROM progresso_exercicios p
        INNER JOIN exercicios e ON e.id = p.exercicio_id
        INNER JOIN treinos t ON t.id = e.treino_id
        WHERE p.aluno_id = :aluno_id AND t.aluno_id = :dono_treino
          AND p.concluido = 1 AND p.data_treino BETWEEN :inicio AND :fim
        GROUP BY p.data_treino ORDER BY p.data_treino DESC');
    $stmt->execute([
        ':aluno_id' => $usuario_id,
        ':dono_treino' => $usuario_id,
        ':inicio' => $inicio30,
        ':fim' => $hoje
    ]);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($historico as $dia) {
        if ($dia['data_treino'] === $hoje) {
            $concluidosHoje = (int) $dia['quantidade'];
        }
        $dias30++;
        if ($dia['data_treino'] >= $inicio7) {
            $dias7++;
        }
    }
} catch (PDOException $e) {
    error_log('Erro no progresso do aluno: ' . $e->getMessage());
    $erro = 'Não foi possível carregar o progresso agora. Verifique a conexão e tente novamente.';
}

$percentual = $totalPrescrito > 0 ? min(100, (int) round(($concluidosHoje / $totalPrescrito) * 100)) : 0;
$datasConcluidas = [];
foreach ($historico as $dia) {
    $datasConcluidas[$dia['data_treino']] = (int) $dia['quantidade'];
}

require_once __DIR__ . '/../layouts/header.php';
?>
<div class="gc-section-marker">MEU DESEMPENHO</div>

<style>
  .progresso-topo { margin-bottom: 26px; }
  .progresso-topo a { color: var(--cor-principal); font-weight: 600; font-size: 13px; text-decoration: none; }
  .progresso-topo h1 { color: #fff; margin-top: 12px; font-size: 26px; }
  .progresso-topo p { color: var(--text-muted); margin-top: 6px; }
  .progresso-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 22px; }
  .progresso-numero { color: #fff; font-size: 32px; font-weight: 800; margin-top: 12px; }
  .progresso-legenda { color: var(--text-muted); font-size: 13px; }
  .progresso-barra { height: 12px; background: #0f172a; border-radius: 99px; overflow: hidden; margin-top: 18px; }
  .progresso-preenchimento { height: 100%; background: var(--cor-principal); border-radius: 99px; }
  .progresso-calendario { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px; margin-top: 18px; }
  .progresso-dia { border: 1px solid var(--border-color); background: #0f172a; border-radius: 9px; min-height: 62px; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 4px; color: var(--text-muted); font-size: 12px; }
  .progresso-dia.feito { border-color: #10b981; background: rgba(16, 185, 129, .12); color: #a7f3d0; }
  .progresso-dia.hoje { outline: 1px solid var(--cor-principal); }
  .progresso-dia strong { font-size: 15px; color: #fff; }
  .progresso-historico { list-style: none; padding: 0; margin: 14px 0 0; }
  .progresso-historico li { display: flex; justify-content: space-between; gap: 12px; padding: 13px 0; border-bottom: 1px solid var(--border-color); color: #e2e8f0; }
  .progresso-historico li:last-child { border-bottom: 0; }
  @media(max-width: 520px) { .progresso-calendario { gap: 4px; } .progresso-dia { min-height: 52px; font-size: 10px; } }
</style>

<div class="progresso-topo">
  <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Voltar ao Painel</a>
  <h1><i class="fa-solid fa-chart-line" style="color: var(--cor-principal);"></i> Meu Progresso</h1>
  <p>Acompanhe seus exercícios concluídos e os dias em que treinou.</p>
</div>

<?php if ($erro): ?>
  <div class="panel-card" style="color: #f87171;"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
<?php else: ?>
  <div class="panel-card" style="margin-bottom:22px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
    <div><h2 style="color:#fff;font-size:18px;">🏆 Seu desempenho</h2><p style="color:var(--text-muted);font-size:13px;margin-top:6px;">Acompanhe XP, nível e dias de treino.</p></div>
    <a class="btn btn-primary" href="gamificacao.php"><i class="fa-solid fa-trophy"></i> Ver gamificação</a>
  </div>
  <div class="progresso-grid">
    <div class="panel-card" style="margin-bottom: 0;">
      <div class="progresso-legenda">Exercícios marcados hoje (todas as fichas)</div>
      <div class="progresso-numero"><?= $concluidosHoje ?> <span style="font-size: 16px; color: var(--text-muted);">/ <?= $totalPrescrito ?></span></div>
    </div>
    <div class="panel-card" style="margin-bottom: 0;">
      <div class="progresso-legenda">Conclusão de hoje</div>
      <div class="progresso-numero"><?= $percentual ?>%</div>
      <div class="progresso-barra" role="progressbar" aria-valuenow="<?= $percentual ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Conclusão de hoje">
        <div class="progresso-preenchimento" style="width: <?= $percentual ?>%;"></div>
      </div>
    </div>
    <div class="panel-card" style="margin-bottom: 0;">
      <div class="progresso-legenda">Dias com exercícios concluídos (7 dias)</div>
      <div class="progresso-numero"><?= $dias7 ?></div>
    </div>
    <div class="panel-card" style="margin-bottom: 0;">
      <div class="progresso-legenda">Dias com exercícios concluídos (30 dias)</div>
      <div class="progresso-numero"><?= $dias30 ?></div>
    </div>
  </div>

  <div class="panel-card" style="margin-bottom: 22px;">
    <h2 style="color: #fff; font-size: 19px;">Últimos 30 dias</h2>
    <p style="color: var(--text-muted); font-size: 13px; margin-top: 6px;">Dias em verde possuem ao menos um exercício concluído.</p>
    <div class="progresso-calendario">
      <?php for ($i = 29; $i >= 0; $i--):
        $data = date('Y-m-d', strtotime("-$i days"));
        $quantidade = $datasConcluidas[$data] ?? 0;
      ?>
        <div class="progresso-dia <?= $quantidade > 0 ? 'feito' : '' ?> <?= $data === $hoje ? 'hoje' : '' ?>" title="<?= date('d/m/Y', strtotime($data)) ?>: <?= $quantidade ?> exercício(s)">
          <strong><?= date('d', strtotime($data)) ?></strong>
          <span><?= date('m', strtotime($data)) ?></span>
          <?php if ($quantidade > 0): ?><i class="fa-solid fa-check" aria-label="Exercícios concluídos"></i><?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
  </div>

  <div class="panel-card">
    <h2 style="color: #fff; font-size: 19px;">Histórico de conclusões</h2>
    <?php if (!$historico): ?>
      <p style="color: var(--text-muted); margin-top: 14px;">Nenhum exercício concluído nos últimos 30 dias.</p>
    <?php else: ?>
      <ul class="progresso-historico">
        <?php foreach ($historico as $dia): ?>
          <li>
            <span><?= date('d/m/Y', strtotime($dia['data_treino'])) ?></span>
            <strong><?= (int) $dia['quantidade'] ?> exercício(s)</strong>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <p style="color: var(--text-muted); font-size: 12px; margin-top: 12px;">O percentual de hoje considera todos os exercícios atualmente atribuídos às suas fichas. O histórico mostra conclusões registradas, não necessariamente sessões completas de treino.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
