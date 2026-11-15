
<?php
// professor/mensagens.php
// Chat entre professor e aluno vinculado.

require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['professor']);

require_once __DIR__ . '/../config/conexao.php';

$professorId = (int) $_SESSION['usuario_id'];
$alunoId = filter_input(INPUT_GET, 'aluno_id', FILTER_VALIDATE_INT);
$alunoId = $alunoId ?: 0;

$aluno = null;
$mensagens = [];
$erro = '';
$sucesso = false;

// Token de segurança para envio de mensagens.
if (empty($_SESSION['csrf_mensagens_professor'])) {
    $_SESSION['csrf_mensagens_professor'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_mensagens_professor'];

try {

    // Verifica se o aluno pertence ao professor logado.
    $stmt = $pdo->prepare("
        SELECT u.id, u.nome, u.email
        FROM alunos a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        WHERE a.professor_id = :professor_id
          AND a.usuario_id = :aluno_id
          AND u.nivel_acesso = 'aluno'
          AND u.status = 'ativo'
        LIMIT 1
    ");

    $stmt->execute([
        ':professor_id' => $professorId,
        ':aluno_id' => $alunoId
    ]);

    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aluno) {
        http_response_code(404);
        $erro = 'Aluno não encontrado ou não vinculado a você.';
    }

    // Envio de mensagem.
    if ($aluno && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $token = $_POST['csrf_token'] ?? '';
        $texto = trim($_POST['mensagem'] ?? '');

        if (!is_string($token) || !hash_equals($csrf, $token)) {
            $erro = 'Sessão inválida. Atualize a página.';
        } elseif (!is_string($texto) || $texto === '') {
            $erro = 'Digite uma mensagem.';
        } elseif (mb_strlen($texto, 'UTF-8') > 3000) {
            $erro = 'A mensagem deve ter no máximo 3000 caracteres.';
        } else {

            $stmt = $pdo->prepare("
                INSERT INTO mensagens
                    (remetente_id, destinatario_id, mensagem)
                VALUES
                    (:remetente, :destinatario, :mensagem)
            ");

            $stmt->execute([
                ':remetente' => $professorId,
                ':destinatario' => $alunoId,
                ':mensagem' => $texto
            ]);

            $_SESSION['mensagem_enviada'] = true;

            header('Location: mensagens.php?aluno_id=' . $alunoId);
            exit;
        }
    }

    if ($aluno) {

        // Marca mensagens recebidas como lidas.
        $stmt = $pdo->prepare("
            UPDATE mensagens
            SET lida = 1
            WHERE remetente_id = :aluno
              AND destinatario_id = :professor
              AND lida = 0
        ");

        $stmt->execute([
            ':aluno' => $alunoId,
            ':professor' => $professorId
        ]);

        // Busca o histórico completo da conversa.
        $stmt = $pdo->prepare("
            SELECT
                id,
                remetente_id,
                destinatario_id,
                mensagem,
                data_envio,
                lida
            FROM mensagens
            WHERE
                (
                    remetente_id = :professor_envio
                    AND destinatario_id = :aluno_recebe
                )
                OR
                (
                    remetente_id = :aluno_envio
                    AND destinatario_id = :professor_recebe
                )
            ORDER BY data_envio ASC, id ASC
        ");

        $stmt->execute([
            ':professor_envio' => $professorId,
            ':aluno_recebe' => $alunoId,
            ':aluno_envio' => $alunoId,
            ':professor_recebe' => $professorId
        ]);

        $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {

    error_log('Erro no chat do professor: ' . $e->getMessage());

    $erro = 'Não foi possível carregar as mensagens.';
}

$sucesso = !empty($_SESSION['mensagem_enviada']);
unset($_SESSION['mensagem_enviada']);

require_once __DIR__ . '/../layouts/header.php';
?>

<style>
.chat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 15px;
}

.chat-header h1 {
    color: #fff;
    font-size: 26px;
    font-weight: 800;
    margin: 8px 0;
}

.chat-header p {
    color: var(--text-muted);
    font-size: 14px;
}

.chat-voltar {
    color: var(--cor-principal);
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.chat-alerta {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 18px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.35);
    color: #fca5a5;
}

.chat-sucesso {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
    color: #6ee7b7;
}

.chat-container {
    background: #090d16;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    min-height: 320px;
    max-height: 490px;
    overflow-y: auto;
    padding: 22px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.chat-vazio {
    margin: auto;
    color: var(--text-muted);
    text-align: center;
    font-size: 14px;
}

.chat-mensagem {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
}

.chat-mensagem.minha {
    align-items: flex-end;
}

.chat-balao {
    max-width: 85%;
    padding: 12px 16px;
    border-radius: 13px;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    color: #e2e8f0;
    font-size: 14px;
    line-height: 1.5;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.chat-mensagem.minha .chat-balao {
    background: rgba(2, 132, 199, 0.2);
    border-color: rgba(56, 189, 248, 0.3);
}

.chat-meta {
    color: var(--text-muted);
    font-size: 11px;
}

.chat-form {
    margin-top: 20px;
}

.chat-form label {
    display: block;
    color: #fff;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 10px;
}

.chat-form textarea {
    width: 100%;
    min-height: 110px;
    padding: 14px;
    box-sizing: border-box;
    border-radius: 10px;
    background: #090d16;
    border: 1px solid var(--border-color);
    color: #fff;
    font: inherit;
    resize: vertical;
}

.chat-form textarea:focus {
    outline: 2px solid var(--cor-principal);
    outline-offset: 1px;
}

.chat-acoes {
    display: flex;
    justify-content: flex-end;
    margin-top: 12px;
}

@media (max-width: 640px) {
    .chat-container {
        padding: 12px;
    }

    .chat-balao {
        max-width: 95%;
    }
}
</style>

<div class="chat-header">
    <div>
        <a href="index.php" class="chat-voltar">
            <i class="fa-solid fa-arrow-left"></i>
            Voltar aos alunos
        </a>

        <h1>
            <i class="fa-solid fa-comments"
               style="color: var(--cor-principal);"></i>
            Mensagens
        </h1>

        <?php if ($aluno): ?>
            <p>
                Conversa com
                <strong>
                    <?= htmlspecialchars($aluno['nome'], ENT_QUOTES, 'UTF-8') ?>
                </strong>
            </p>
        <?php else: ?>
            <p>Comunicação entre professor e aluno.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($erro !== ''): ?>
    <div class="chat-alerta" role="alert">
        <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($aluno): ?>

    <?php if ($sucesso): ?>
        <div class="chat-alerta chat-sucesso" role="status">
            <i class="fa-solid fa-circle-check"></i>
            Mensagem enviada com sucesso!
        </div>
    <?php endif; ?>

    <div class="panel-card">

        <h3 style="color:#fff; font-size:17px; margin-bottom:16px;">
            <i class="fa-solid fa-user"
               style="color:var(--cor-principal);"></i>

            <?= htmlspecialchars($aluno['nome'], ENT_QUOTES, 'UTF-8') ?>
        </h3>

        <div class="chat-container"
             id="chat-container"
             aria-label="Histórico de mensagens">

            <?php if (empty($mensagens)): ?>
                <div class="chat-vazio">
                    <i class="fa-regular fa-comments"
                       style="font-size:28px; margin-bottom:12px;"></i>

                    <p>
                        Nenhuma mensagem ainda.
                        Envie a primeira orientação ao aluno.
                    </p>
                </div>
            <?php endif; ?>

            <?php foreach ($mensagens as $item): ?>

                <?php
                    $minha = (int) $item['remetente_id'] === $professorId;
                ?>

                <div class="chat-mensagem <?= $minha ? 'minha' : '' ?>">

                    <div class="chat-balao"><?= htmlspecialchars(
                        $item['mensagem'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?></div>

                    <span class="chat-meta">

                        <?= $minha
                            ? 'Você'
                            : htmlspecialchars(
                                $aluno['nome'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        ·

                        <?= htmlspecialchars(
                            date(
                                'd/m/Y H:i',
                                strtotime($item['data_envio'])
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                        <?php if ($minha): ?>
                            ·
                            <?= (int) $item['lida'] === 1
                                ? 'Lida'
                                : 'Enviada' ?>
                        <?php endif; ?>

                    </span>

                </div>

            <?php endforeach; ?>

        </div>

        <form method="POST"
              action="mensagens.php?aluno_id=<?= (int) $alunoId ?>"
              class="chat-form">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= htmlspecialchars(
                       $csrf,
                       ENT_QUOTES,
                       'UTF-8'
                   ) ?>">

            <label for="mensagem">
                Escrever mensagem
            </label>

            <textarea
                id="mensagem"
                name="mensagem"
                maxlength="3000"
                required
                placeholder="Digite sua mensagem para o aluno..."
            ></textarea>

            <div class="chat-acoes">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-paper-plane"></i>
                    Enviar mensagem
                </button>
            </div>

        </form>

    </div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chat = document.getElementById('chat-container');

    if (chat) {
        chat.scrollTop = chat.scrollHeight;
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
