<?php
// Ranking semanal opt-in, limitado aos alunos do mesmo professor.
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['aluno', 'admin']);
require_once __DIR__ . '/../config/conexao.php';

$usuarioId = (int) $_SESSION['usuario_id'];
$erro = '';
$aviso = '';
$participa = false;
$ranking = [];
$professorId = null;
$inicio = date('Y-m-d', strtotime('monday this week'));
$fim = date('Y-m-d', strtotime('sunday this week'));

if (empty($_SESSION['csrf_ranking'])) {
    $_SESSION['csrf_ranking'] = bin2hex(random_bytes(32));
}
try {
    $stmt = $pdo->prepare('SELECT professor_id FROM alunos WHERE usuario_id = :id LIMIT 1');
    $stmt->execute([':id' => $usuarioId]);
    $professorId = $stmt->fetchColumn();
    if (!$professorId) {
        $aviso = 'Seu cadastro ainda não possui professor vinculado. O ranking ficará disponível quando houver vínculo.';
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!hash_equals($_SESSION['csrf_ranking'], (string) ($_POST['csrf_token'] ?? ''))) {
                throw new RuntimeException('Sessão inválida. Atualize a página.');
            }
            $novo = (string) ($_POST['participa'] ?? '');
            if (!in_array($novo, ['0', '1'], true)) {
                throw new RuntimeException('Opção inválida.');
            }
            $stmt = $pdo->prepare('INSERT INTO gamificacao_ranking_participacao (aluno_id, participa) VALUES (:id, :participa) ON DUPLICATE KEY UPDATE participa = VALUES(participa)');
            $stmt->execute([':id' => $usuarioId, ':participa' => (int) $novo]);
            header('Location: ranking.php');
            exit;
        }
        $stmt = $pdo->prepare('SELECT participa FROM gamificacao_ranking_participacao WHERE aluno_id = :id');
        $stmt->execute([':id' => $usuarioId]);
        $participa = (bool) $stmt->fetchColumn();

        if ($participa) {
            // Somente alunos do mesmo professor que consentiram participar.
            // Usa XP efetivamente concedido por ficha, não cliques nos exercícios.
            $stmt = $pdo->prepare('SELECT u.id, u.nome,
                (SELECT COALESCE(SUM(g.xp),0) FROM gamificacao_treinos_concluidos g WHERE g.aluno_id=u.id AND g.data_treino BETWEEN :inicio AND :fim)
                + (SELECT COALESCE(SUM(r.xp),0) FROM gamificacao_desafios_recompensas r WHERE r.aluno_id=u.id AND DATE(r.concedido_em) BETWEEN :inicio_bonus AND :fim_bonus) AS xp_semana
                FROM alunos a
                INNER JOIN usuarios u ON u.id = a.usuario_id
                INNER JOIN gamificacao_ranking_participacao rp ON rp.aluno_id=u.id AND rp.participa=1
                WHERE a.professor_id=:professor_id
                ORDER BY xp_semana DESC, u.nome ASC, u.id ASC');
            $stmt->execute([':inicio' => $inicio, ':fim' => $fim, ':inicio_bonus' => $inicio, ':fim_bonus' => $fim, ':professor_id' => (int) $professorId]);
            $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (RuntimeException $e) {
    $erro = $e->getMessage();
} catch (PDOException $e) {
    error_log('Erro no ranking: ' . $e->getMessage());
    $erro = 'Não foi possível carregar o ranking. Verifique se o SQL da Etapa 2 foi executado.';
}

function ligaPorXp(int $xp): array {
    if ($xp >= 1500) return ['Diamante', '#67e8f9'];
    if ($xp >= 900) return ['Ouro', '#fbbf24'];
    if ($xp >= 400) return ['Prata', '#cbd5e1'];
    return ['Bronze', '#f59e0b'];
}
require_once __DIR__ . '/../layouts/header.php';
?>
<style>
.rank-top { margin-bottom:24px; }
.rank-top h1 { color:#fff; font-size:26px; margin:12px 0 8px; }
.rank-muted { color:var(--text-muted); font-size:14px; }
.rank-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 8px; border-bottom:1px solid var(--border-color); }
.rank-row:last-child { border-bottom:0; }
.rank-person { display:flex; align-items:center; gap:14px; min-width:0; }
.rank-position { width:34px; color:#fbbf24; font-weight:800; text-align:center; }
.rank-name { color:#fff; font-weight:700; overflow-wrap:anywhere; }
.rank-xp { color:#fff; font-weight:800; white-space:nowrap; }
.rank-liga { font-size:12px; margin-top:4px; }
.rank-btn { background:var(--cor-principal); color:#fff; border:0; border-radius:8px; padding:12px 18px; cursor:pointer; font-weight:700; }
.rank-btn.secondary { background:#334155; }
.rank-highlight { background:rgba(56,189,248,.08); border-radius:8px; }
</style>
<div class="rank-top">
  <a href="index.php" style="color:var(--cor-principal); text-decoration:none; font-weight:600; font-size:13px;">← Voltar ao Painel</a>
  <h1>🏆 Ranking Semanal</h1>
  <p class="rank-muted">Semana de <?= date('d/m/Y', strtotime($inicio)) ?> a <?= date('d/m/Y', strtotime($fim)) ?>. Competição opcional entre alunos do mesmo professor.</p>
</div>
<?php if ($erro): ?><div class="panel-card" style="color:#fca5a5;"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($aviso): ?><div class="panel-card"><p class="rank-muted"><?= htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8') ?></p></div><?php endif; ?>
<?php if (!$erro && $professorId): ?>
<div class="panel-card" style="margin-bottom:20px;">
  <h2 style="color:#fff; font-size:18px; margin-bottom:8px;">Sua participação</h2>
  <p class="rank-muted" style="margin-bottom:16px;">Seu nome e XP semanal só aparecem para outros participantes vinculados ao mesmo professor quando você aceita participar.</p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_ranking'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="participa" value="<?= $participa ? '0' : '1' ?>">
    <button type="submit" class="rank-btn <?= $participa ? 'secondary' : '' ?>"><?= $participa ? 'Sair do ranking' : 'Participar do ranking' ?></button>
  </form>
</div>
<div class="panel-card">
  <h2 style="color:#fff; font-size:19px; margin-bottom:8px;">Classificação desta semana</h2>
  <?php if (!$participa): ?>
    <p class="rank-muted">Ative sua participação para visualizar o ranking.</p>
  <?php elseif (!$ranking): ?>
    <p class="rank-muted">Nenhum participante encontrado nesta semana.</p>
  <?php else: ?>
    <?php foreach ($ranking as $indice => $aluno):
      $xp = (int) $aluno['xp_semana'];
      [$liga, $cor] = ligaPorXp($xp);
      $meu = (int) $aluno['id'] === $usuarioId;
    ?>
    <div class="rank-row <?= $meu ? 'rank-highlight' : '' ?>">
      <div class="rank-person">
        <span class="rank-position"><?= $indice + 1 ?>º</span>
        <div>
          <div class="rank-name"><?= htmlspecialchars($aluno['nome'], ENT_QUOTES, 'UTF-8') ?><?= $meu ? ' (você)' : '' ?></div>
          <div class="rank-liga" style="color:<?= $cor ?>;">◆ Liga <?= $liga ?></div>
        </div>
      </div>
      <div class="rank-xp"><?= $xp ?> XP</div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<div class="panel-card" style="margin-top:20px;">
  <h2 style="color:#fff; font-size:18px; margin-bottom:10px;">Como funcionam as ligas?</h2>
  <p class="rank-muted">Bronze: 0–399 XP · Prata: 400–899 XP · Ouro: 900–1499 XP · Diamante: 1500 XP ou mais, conquistados na semana. Nesta versão, as ligas são faixas de XP semanal; promoção e rebaixamento entre semanas serão adicionados em uma etapa posterior.</p>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
