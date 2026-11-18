<?php
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['professor', 'admin']);
require_once __DIR__ . '/../config/conexao.php';

$professorId = (int) ($_SESSION['usuario_id'] ?? 0);
$alunoId = filter_input(INPUT_GET, 'aluno_id', FILTER_VALIDATE_INT) ?: 0;
$erro = '';
$sucesso = '';
$aluno = null;
$agendamentos = [];
if (empty($_SESSION['csrf_agenda'])) $_SESSION['csrf_agenda'] = bin2hex(random_bytes(32));
function escAgenda($texto) { return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8'); }
function redirecionarAgenda($alunoId, $resultado) {
    header('Location: agendamentos.php?aluno_id=' . $alunoId . '&resultado=' . urlencode($resultado));
    exit;
}

if ($alunoId > 0) {
    $stmt = $pdo->prepare("SELECT u.id, u.nome FROM alunos a JOIN usuarios u ON u.id = a.usuario_id WHERE a.usuario_id = ? AND a.professor_id = ? AND a.academia_id = ? AND u.academia_id = a.academia_id LIMIT 1");
    $stmt->execute([$alunoId, $professorId, (int) $_SESSION['academia_id']]);
    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$aluno) {
    http_response_code(403);
    require_once __DIR__ . '/../layouts/header.php';
    echo '<div class="panel-card"><h2>Aluno não encontrado ou não vinculado a você.</h2><a href="index.php" class="btn btn-primary">Voltar</a></div>';
    require_once __DIR__ . '/../layouts/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Evita requisições de outros sites sem autorização do usuário.
    if (!hash_equals($_SESSION['csrf_agenda'], (string)($_POST['csrf'] ?? ''))) {
        $erro = 'Sessão de formulário inválida. Atualize a página.';
    } else {
        $acao = (string)($_POST['acao'] ?? '');
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        try {
            if ($acao === 'salvar') {
                $titulo = trim((string)($_POST['titulo'] ?? ''));
                $tipo = (string)($_POST['tipo'] ?? 'aula');
                $data = (string)($_POST['data_agendamento'] ?? '');
                $inicio = (string)($_POST['hora_inicio'] ?? '');
                $fim = (string)($_POST['hora_fim'] ?? '');
                $obs = trim((string)($_POST['observacoes'] ?? ''));
                $dataObj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
                if ($titulo === '' || mb_strlen($titulo) > 150 || !in_array($tipo, ['aula','avaliacao','acompanhamento','outro'], true)
                    || !$dataObj || $dataObj->format('Y-m-d') !== $data
                    || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $inicio)
                    || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $fim)
                    || $inicio >= $fim || mb_strlen($obs) > 5000
                    || $data < date('Y-m-d') || ($data === date('Y-m-d') && $fim <= date('H:i'))) {
                    throw new RuntimeException('Confira o título, tipo, data e horários. O fim deve ser posterior ao início.');
                }
                if ($id > 0) {
                    $check = $pdo->prepare("SELECT id FROM agendamentos WHERE id=? AND aluno_id=? AND professor_id=? AND status='agendado'");
                    $check->execute([$id,$alunoId,$professorId]);
                    if (!$check->fetchColumn()) throw new RuntimeException('Somente agendamentos ativos podem ser remarcados.');
                }
                // Serializa os agendamentos deste professor, impedindo reservas concorrentes no mesmo horário.
                $pdo->beginTransaction();
                $travaAgenda = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? FOR UPDATE');
                $travaAgenda->execute([$professorId]);
                // Intervalos [início, fim) se sobrepõem quando início < fim_existente e fim > início_existente.
                $conflito = $pdo->prepare("SELECT id FROM agendamentos WHERE professor_id=? AND data_agendamento=? AND status='agendado' AND hora_inicio < ? AND hora_fim > ? AND id <> ? LIMIT 1");
                $conflito->execute([$professorId,$data,$fim,$inicio,$id]);
                if ($conflito->fetchColumn()) throw new RuntimeException('O professor já possui um agendamento neste horário. Escolha outro intervalo.');
                if ($id > 0) {
                    $sql = $pdo->prepare("UPDATE agendamentos SET titulo=?, tipo=?, data_agendamento=?, hora_inicio=?, hora_fim=?, observacoes=? WHERE id=? AND aluno_id=? AND professor_id=? AND status='agendado'");
                    $sql->execute([$titulo,$tipo,$data,$inicio,$fim,$obs,$id,$alunoId,$professorId]);
                    $pdo->commit();
                    redirecionarAgenda($alunoId,'remarcado');
                }
                $sql = $pdo->prepare("INSERT INTO agendamentos (aluno_id,professor_id,titulo,tipo,data_agendamento,hora_inicio,hora_fim,observacoes) VALUES (?,?,?,?,?,?,?,?)");
                $sql->execute([$alunoId,$professorId,$titulo,$tipo,$data,$inicio,$fim,$obs]);
                $pdo->commit();
                redirecionarAgenda($alunoId,'criado');
            } elseif (in_array($acao,['cancelar','concluir'],true) && $id > 0) {
                $status = $acao === 'cancelar' ? 'cancelado' : 'concluido';
                $sql = $pdo->prepare("UPDATE agendamentos SET status=? WHERE id=? AND aluno_id=? AND professor_id=? AND status='agendado'");
                $sql->execute([$status,$id,$alunoId,$professorId]);
                if ($sql->rowCount() < 1) throw new RuntimeException('Agendamento não encontrado ou já finalizado.');
                redirecionarAgenda($alunoId,$status);
            } else {
                throw new RuntimeException('Ação inválida.');
            }
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Erro na agenda: ' . $e->getMessage());
            $erro = 'Não foi possível salvar. Verifique a conexão e tente novamente.';
        }
    }
}
$editar = null;
$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
if ($editarId) {
    $stmt = $pdo->prepare("SELECT * FROM agendamentos WHERE id=? AND aluno_id=? AND professor_id=? AND status='agendado'");
    $stmt->execute([$editarId,$alunoId,$professorId]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$stmt = $pdo->prepare("SELECT * FROM agendamentos WHERE aluno_id=? AND professor_id=? ORDER BY data_agendamento DESC, hora_inicio DESC, id DESC");
$stmt->execute([$alunoId,$professorId]);
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$mensagens = ['criado'=>'Agendamento criado com sucesso!','remarcado'=>'Agendamento atualizado!','cancelado'=>'Agendamento cancelado.','concluido'=>'Agendamento marcado como concluído.'];
$sucesso = $mensagens[$_GET['resultado'] ?? ''] ?? '';
require_once __DIR__ . '/../layouts/header.php';
?>
<style>
.agenda-form {display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}
.agenda-form label {display:flex;flex-direction:column;gap:7px;color:#cbd5e1;font-size:14px}
.agenda-form input,.agenda-form select,.agenda-form textarea {width:100%;padding:11px;background:#0f172a;color:#fff;border:1px solid #40506a;border-radius:8px;color-scheme:dark}
.agenda-item {border:1px solid #37465e;border-radius:12px;padding:16px;margin:12px 0;color:#e2e8f0}
.agenda-actions {display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.agenda-actions form {display:inline}
</style>
<section class="gc-section-banner">
<span class="gc-form-heading">Agenda do professor</span>
<p><a href="index.php" style="color:#38bdf8">← Voltar aos alunos</a></p>
<h1 style="color:#fff;margin:14px 0 12px">📅 Agendamentos de <?= escAgenda($aluno['nome']) ?></h1>
<p>Organize atendimentos e horários do aluno, com a mesma identidade do painel.</p>
</section>
<?php if ($erro): ?><div class="panel-card" style="color:#fca5a5"><?= escAgenda($erro) ?></div><?php endif; ?>
<?php if ($sucesso): ?><div class="panel-card" style="color:#6ee7b7"><?= escAgenda($sucesso) ?></div><?php endif; ?>
<div class="panel-card">
<h2 style="color:#fff;margin-bottom:18px"><?= $editar ? 'Remarcar / editar atendimento' : 'Novo agendamento' ?></h2>
<form method="post" class="agenda-form">
<input type="hidden" name="csrf" value="<?= escAgenda($_SESSION['csrf_agenda']) ?>">
<input type="hidden" name="acao" value="salvar">
<input type="hidden" name="id" value="<?= (int)($editar['id'] ?? 0) ?>">
<label style="grid-column:1/-1">Título<input name="titulo" maxlength="150" required placeholder="Ex.: Aula de musculação" value="<?= escAgenda($editar['titulo'] ?? '') ?>"></label>
<label>Tipo<select name="tipo"><?php foreach (['aula'=>'Aula','avaliacao'=>'Avaliação física','acompanhamento'=>'Acompanhamento','outro'=>'Outro'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($editar['tipo'] ?? 'aula') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
<label>Data<input type="date" name="data_agendamento" required value="<?= escAgenda($editar['data_agendamento'] ?? date('Y-m-d')) ?>"></label>
<label>Início<input type="time" name="hora_inicio" required value="<?= escAgenda(substr($editar['hora_inicio'] ?? '09:00',0,5)) ?>"></label>
<label>Fim<input type="time" name="hora_fim" required value="<?= escAgenda(substr($editar['hora_fim'] ?? '10:00',0,5)) ?>"></label>
<label style="grid-column:1/-1">Observações<textarea name="observacoes" rows="3" maxlength="5000"><?= escAgenda($editar['observacoes'] ?? '') ?></textarea></label>
<div style="grid-column:1/-1;display:flex;gap:12px;align-items:center"><button class="btn btn-primary" type="submit"><?= $editar ? 'Salvar alterações' : 'Criar agendamento' ?></button><?php if ($editar): ?><a class="btn btn-accent" href="agendamentos.php?aluno_id=<?= $alunoId ?>">Cancelar edição</a><?php endif; ?></div>
</form>
</div>
<div class="panel-card"><h2 style="color:#fff;margin-bottom:14px">Histórico e próximos horários</h2>
<?php if (!$agendamentos): ?><p style="color:#94a3b8">Nenhum agendamento cadastrado para este aluno.</p><?php endif; ?>
<?php foreach ($agendamentos as $a): ?>
<div class="agenda-item"><strong><?= escAgenda($a['titulo']) ?></strong> <span style="color:#93c5fd">(<?= escAgenda(ucfirst($a['tipo'])) ?>)</span>
<p style="margin:8px 0;color:#cbd5e1"><?= escAgenda(date('d/m/Y',strtotime($a['data_agendamento']))) ?> · <?= escAgenda(substr($a['hora_inicio'],0,5)) ?>–<?= escAgenda(substr($a['hora_fim'],0,5)) ?> · <strong><?= escAgenda(ucfirst($a['status'])) ?></strong></p>
<?php if ($a['observacoes']): ?><p style="white-space:pre-wrap"><?= escAgenda($a['observacoes']) ?></p><?php endif; ?>
<?php if ($a['status'] === 'agendado'): ?><div class="agenda-actions">
<a class="btn btn-primary" href="agendamentos.php?aluno_id=<?= $alunoId ?>&editar=<?= (int)$a['id'] ?>">Remarcar / editar</a>
<form method="post" onsubmit="return confirm('Cancelar este agendamento?')"><input type="hidden" name="csrf" value="<?= escAgenda($_SESSION['csrf_agenda']) ?>"><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button type="submit" class="btn btn-accent">Cancelar</button></form>
<form method="post" onsubmit="return confirm('Marcar como concluído?')"><input type="hidden" name="csrf" value="<?= escAgenda($_SESSION['csrf_agenda']) ?>"><input type="hidden" name="acao" value="concluir"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button type="submit" class="btn btn-primary">Concluir</button></form>
</div><?php endif; ?></div>
<?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
