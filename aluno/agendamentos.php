<?php
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['aluno', 'admin']);
require_once __DIR__ . '/../config/conexao.php';
$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
$stmt = $pdo->prepare("SELECT ag.*, u.nome AS professor_nome FROM agendamentos ag JOIN usuarios u ON u.id=ag.professor_id WHERE ag.aluno_id=? ORDER BY ag.data_agendamento DESC, ag.hora_inicio DESC, ag.id DESC");
$stmt->execute([$usuarioId]);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
function escAgendaAluno($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$proximos = [];
$historico = [];
$agora = date('Y-m-d H:i:s');
foreach ($registros as $r) {
    if ($r['status'] === 'agendado' && $r['data_agendamento'].' '.$r['hora_fim'] >= $agora) $proximos[] = $r;
    else $historico[] = $r;
}
usort($proximos, fn($a,$b) => strcmp($a['data_agendamento'].' '.$a['hora_inicio'],$b['data_agendamento'].' '.$b['hora_inicio']));
require_once __DIR__ . '/../layouts/header.php';
?>
<div class="gc-section-marker">MEUS COMPROMISSOS</div>

<p><a href="index.php" style="color:#38bdf8">← Voltar ao meu painel</a></p>
<h1 style="color:#fff;margin:14px 0 24px">📅 Minha Agenda</h1>
<p style="color:#94a3b8;margin-bottom:20px">Consulte os atendimentos marcados pelo seu professor. Para solicitar alterações, envie uma mensagem ao professor.</p>
<?php foreach (['Próximos agendamentos'=>$proximos,'Histórico e cancelamentos'=>$historico] as $titulo=>$itens): ?>
<div class="panel-card"><h2 style="color:#fff;margin-bottom:16px"><?= escAgendaAluno($titulo) ?></h2>
<?php if (!$itens): ?><p style="color:#94a3b8">Nenhum registro nesta seção.</p><?php endif; ?>
<?php foreach ($itens as $a): ?>
<div class="gc-agenda-event">
<strong><?= escAgendaAluno($a['titulo']) ?></strong> <span style="color:#93c5fd">· <?= escAgendaAluno(ucfirst($a['tipo'])) ?></span>
<p style="margin:8px 0;color:#cbd5e1"><?= escAgendaAluno(date('d/m/Y',strtotime($a['data_agendamento']))) ?> · <?= escAgendaAluno(substr($a['hora_inicio'],0,5)) ?>–<?= escAgendaAluno(substr($a['hora_fim'],0,5)) ?></p>
<p style="color:#cbd5e1">Professor: <?= escAgendaAluno($a['professor_nome']) ?> · Status: <strong><?= escAgendaAluno(ucfirst($a['status'])) ?></strong></p>
<?php if ($a['observacoes']): ?><p style="white-space:pre-wrap;margin-top:10px"><?= escAgendaAluno($a['observacoes']) ?></p><?php endif; ?>
</div>
<?php endforeach; ?></div>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
