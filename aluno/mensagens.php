<?php
// aluno/mensagens.php - Conversa privada com o professor vinculado.
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['aluno']);
require_once __DIR__ . '/../config/conexao.php';

$alunoId = (int) $_SESSION['usuario_id'];
$professor = null;
$mensagens = [];
$erro = '';
if (empty($_SESSION['csrf_mensagens_aluno'])) {
    $_SESSION['csrf_mensagens_aluno'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_mensagens_aluno'];

try {
    $stmt = $pdo->prepare("SELECT u.id, u.nome FROM alunos a
        INNER JOIN usuarios u ON u.id = a.professor_id
        WHERE a.usuario_id = :aluno AND u.nivel_acesso = 'professor'
        AND u.status = 'ativo' LIMIT 1");
    $stmt->execute([':aluno' => $alunoId]);
    $professor = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$professor) {
        $erro = 'Você ainda não possui um professor ativo vinculado.';
    } else {
        $professorId = (int) $professor['id'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            $texto = $_POST['mensagem'] ?? '';
            if (!is_string($token) || !hash_equals($csrf, $token)) {
                $erro = 'Sessão inválida. Atualize a página e tente novamente.';
            } elseif (!is_string($texto) || trim($texto) === '') {
                $erro = 'Digite uma mensagem antes de enviar.';
            } elseif (mb_strlen($texto, 'UTF-8') > 3000) {
                $erro = 'A mensagem deve ter no máximo 3.000 caracteres.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO mensagens (remetente_id, destinatario_id, mensagem) VALUES (:remetente, :destinatario, :mensagem)');
                $stmt->execute([':remetente' => $alunoId, ':destinatario' => $professorId, ':mensagem' => trim($texto)]);
                $_SESSION['flash_mensagem_aluno'] = true;
                header('Location: mensagens.php');
                exit;
            }
        }
        $stmt = $pdo->prepare('UPDATE mensagens SET lida = 1 WHERE remetente_id = :professor AND destinatario_id = :aluno AND lida = 0');
        $stmt->execute([':professor' => $professorId, ':aluno' => $alunoId]);
        $stmt = $pdo->prepare('SELECT id, remetente_id, mensagem, data_envio, lida FROM mensagens
            WHERE (remetente_id = :aluno_envia AND destinatario_id = :professor_recebe)
               OR (remetente_id = :professor_envia AND destinatario_id = :aluno_recebe)
            ORDER BY data_envio ASC, id ASC');
        $stmt->execute([
            ':aluno_envia' => $alunoId, ':professor_recebe' => $professorId,
            ':professor_envia' => $professorId, ':aluno_recebe' => $alunoId
        ]);
        $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Erro nas mensagens do aluno: ' . $e->getMessage());
    $erro = 'Não foi possível carregar as mensagens agora.';
}
$sucesso = !empty($_SESSION['flash_mensagem_aluno']);
unset($_SESSION['flash_mensagem_aluno']);
require_once __DIR__ . '/../layouts/header.php';
?>
<style>
.chat-voltar{color:var(--cor-principal);text-decoration:none;font-size:13px;font-weight:600}
.chat-topo{margin-bottom:24px}.chat-topo h1{font-size:26px;color:#fff;font-weight:800;margin:10px 0 6px}.chat-topo p{font-size:14px;color:var(--text-muted)}
.chat-alerta{padding:14px 18px;border-radius:10px;margin-bottom:18px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.35);color:#fca5a5}
.chat-sucesso{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#6ee7b7}
.chat-janela{background:#090d16;border:1px solid var(--border-color);border-radius:12px;min-height:320px;max-height:490px;overflow-y:auto;padding:22px;display:flex;flex-direction:column;gap:16px}
.chat-vazio{margin:auto;text-align:center;color:var(--text-muted);font-size:14px}
.chat-linha{display:flex;flex-direction:column;align-items:flex-start;gap:6px}.chat-linha.minha{align-items:flex-end}
.chat-balao{max-width:85%;padding:12px 16px;border-radius:13px;background:var(--bg-surface);border:1px solid var(--border-color);color:#e2e8f0;font-size:14px;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere}
.chat-linha.minha .chat-balao{background:rgba(2,132,199,.2);border-color:rgba(56,189,248,.3)}
.chat-meta{color:var(--text-muted);font-size:11px}.chat-form{margin-top:20px}.chat-form label{display:block;color:#fff;font-weight:600;font-size:14px;margin-bottom:10px}
.chat-form textarea{width:100%;min-height:110px;padding:14px;box-sizing:border-box;border-radius:10px;background:#090d16;border:1px solid var(--border-color);color:#fff;font:inherit;resize:vertical}
.chat-form textarea:focus{outline:2px solid var(--cor-principal);outline-offset:1px}.chat-acoes{display:flex;justify-content:flex-end;margin-top:12px}
@media(max-width:640px){.chat-janela{padding:12px}.chat-balao{max-width:95%}}
</style>
<div class="chat-topo">
    <a href="index.php" class="chat-voltar"><i class="fa-solid fa-arrow-left"></i> Voltar ao Painel</a>
    <h1><i class="fa-solid fa-comments" style="color:var(--cor-principal)"></i> Minhas Mensagens</h1>
    <p><?php if ($professor): ?>Conversa com <strong><?= htmlspecialchars($professor['nome'], ENT_QUOTES, 'UTF-8') ?></strong><?php else: ?>Comunicação com seu professor<?php endif; ?></p>
</div>
<?php if ($erro !== ''): ?><div class="chat-alerta" role="alert"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($professor): ?>
    <?php if ($sucesso): ?><div class="chat-alerta chat-sucesso" role="status">Mensagem enviada com sucesso!</div><?php endif; ?>
    <div class="panel-card">
        <h3 style="color:#fff;font-size:17px;margin-bottom:16px"><i class="fa-solid fa-user" style="color:var(--cor-principal)"></i> <?= htmlspecialchars($professor['nome'], ENT_QUOTES, 'UTF-8') ?></h3>
        <div class="chat-janela" id="chat-janela" aria-label="Histórico de mensagens">
            <?php if (!$mensagens): ?><div class="chat-vazio">Nenhuma mensagem ainda. Envie uma mensagem ao professor.</div><?php endif; ?>
            <?php foreach ($mensagens as $item): ?>
                <?php $minha = (int) $item['remetente_id'] === $alunoId; ?>
                <div class="chat-linha <?= $minha ? 'minha' : '' ?>">
                    <div class="chat-balao"><?= htmlspecialchars($item['mensagem'], ENT_QUOTES, 'UTF-8') ?></div>
                    <span class="chat-meta">
                        <?= $minha ? 'Você' : htmlspecialchars($professor['nome'], ENT_QUOTES, 'UTF-8') ?> ·
                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime($item['data_envio'])), ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($minha): ?> · <?= (int) $item['lida'] === 1 ? 'Lida' : 'Enviada' ?><?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="POST" action="mensagens.php" class="chat-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <label for="mensagem">Escrever mensagem</label>
            <textarea id="mensagem" name="mensagem" maxlength="3000" required placeholder="Digite sua mensagem para o professor..."></textarea>
            <div class="chat-acoes"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Enviar mensagem</button></div>
        </form>
    </div>
<?php endif; ?>
<script>document.addEventListener('DOMContentLoaded',function(){const chat=document.getElementById('chat-janela');if(chat)chat.scrollTop=chat.scrollHeight;});</script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
