<?php
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['aluno', 'admin']);
require_once __DIR__ . '/../config/conexao.php';
$alunoId = (int) $_SESSION['usuario_id'];
$xp = 0; $totalTreinos = 0; $diasAtivos = []; $erro = '';
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(xp),0) AS xp, COUNT(*) AS total FROM gamificacao_treinos_concluidos WHERE aluno_id = :id");
    $stmt->execute([':id' => $alunoId]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    $xp = (int) $res['xp']; $totalTreinos = (int) $res['total'];
    $stmtBonus = $pdo->prepare('SELECT COALESCE(SUM(xp),0) FROM gamificacao_desafios_recompensas WHERE aluno_id = :id');
    $stmtBonus->execute([':id'=>$alunoId]);
    $xp += (int)$stmtBonus->fetchColumn();
    $stmt = $pdo->prepare("SELECT DISTINCT data_treino FROM gamificacao_treinos_concluidos WHERE aluno_id = :id ORDER BY data_treino DESC");
    $stmt->execute([':id' => $alunoId]);
    $diasAtivos = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('Erro gamificacao: ' . $e->getMessage());
    $erro = 'Não foi possível carregar a gamificação. Confira se o SQL foi executado.';
}
$nivel = intdiv($xp, 500) + 1;
$xpNivel = $xp % 500;
$progressoNivel = (int) round($xpNivel / 500 * 100);
// Sequência de dias corridos com fichas completas (indicador informativo).
$sequencia = 0;
if ($diasAtivos) {
    $inicio = new DateTimeImmutable('today');
    $ultimo = new DateTimeImmutable($diasAtivos[0]);
    if ($ultimo->format('Y-m-d') === $inicio->format('Y-m-d') || $ultimo->format('Y-m-d') === $inicio->modify('-1 day')->format('Y-m-d')) {
        $esperado = $ultimo;
        foreach ($diasAtivos as $dia) {
            if ($dia !== $esperado->format('Y-m-d')) break;
            $sequencia++;
            $esperado = $esperado->modify('-1 day');
        }
    }
}
$medalhas = [
    ['nome' => 'Primeiro treino', 'meta' => 1],
    ['nome' => '5 treinos completos', 'meta' => 5],
    ['nome' => '10 treinos completos', 'meta' => 10],
    ['nome' => '25 treinos completos', 'meta' => 25],
];
require_once __DIR__ . '/../layouts/header.php';
?>
<style>
.gami-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:20px 0}
.gami-num{font-size:32px;font-weight:800;color:#fff;margin:12px 0 4px}
.gami-muted{color:var(--text-muted);font-size:13px}
.gami-bar{height:12px;border-radius:99px;background:#0f172a;overflow:hidden;margin:15px 0}
.gami-bar>div{height:100%;background:var(--cor-principal)}
.gami-medalhas{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-top:16px}
.gami-medalha{padding:18px;border:1px solid var(--border-color);border-radius:12px;background:#0f172a;color:#fff}
.gami-medalha.bloqueada{opacity:.5}
</style>
<a href="index.php" style="color:var(--cor-principal);text-decoration:none;font-size:13px;font-weight:600;"><i class="fa-solid fa-arrow-left"></i> Voltar ao Painel</a>
<h1 style="font-size:27px;color:#fff;margin:14px 0 6px;"><i class="fa-solid fa-trophy" style="color:#fbbf24;"></i> Minha Gamificação</h1>
<p class="gami-muted">Conclua uma ficha para ganhar 100 XP. Desafios concluídos também concedem XP extra. <a href="desafios.php" style="color:var(--cor-principal)">Ver desafios</a>.</p>
<?php if ($erro): ?><div class="panel-card" style="color:#fca5a5;margin-top:20px;"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php else: ?>
<div class="gami-grid">
  <div class="panel-card" style="margin:0"><div class="gami-muted">XP acumulado</div><div class="gami-num"><?= $xp ?></div></div>
  <div class="panel-card" style="margin:0"><div class="gami-muted">Nível</div><div class="gami-num"><?= $nivel ?></div></div>
  <div class="panel-card" style="margin:0"><div class="gami-muted">Fichas concluídas</div><div class="gami-num"><?= $totalTreinos ?></div></div>
  <div class="panel-card" style="margin:0"><div class="gami-muted">Dias seguidos com ficha completa</div><div class="gami-num"><?= $sequencia ?> 🔥</div></div>
</div>
<div class="panel-card">
  <h2 style="color:#fff;font-size:19px;">Progresso para o nível <?= $nivel + 1 ?></h2>
  <div class="gami-bar"><div style="width:<?= $progressoNivel ?>%"></div></div>
  <p class="gami-muted"><?= $xpNivel ?> / 500 XP neste nível</p>
</div>
<div class="panel-card">
  <h2 style="color:#fff;font-size:19px;">Conquistas</h2>
  <div class="gami-medalhas">
  <?php foreach ($medalhas as $medalha): $liberada = $totalTreinos >= $medalha['meta']; ?>
    <div class="gami-medalha <?= $liberada ? '' : 'bloqueada' ?>">
      <div style="font-size:24px;"><?= $liberada ? '🏅' : '🔒' ?></div>
      <strong><?= htmlspecialchars($medalha['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
      <div class="gami-muted" style="margin-top:6px;"><?= $liberada ? 'Conquistada!' : 'Ainda não conquistada' ?></div>
    </div>
  <?php endforeach; ?>
  </div>
</div>
<p class="gami-muted">A sequência considera dias corridos com fichas completas. Dias de descanso podem interromper esse indicador nesta primeira versão; isso não significa perda de progresso ou XP. A pontuação começa após a instalação desta etapa.</p>
<?php endif; ?>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
