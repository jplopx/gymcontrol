<?php
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['admin']);

require_once __DIR__ . '/../config/conexao.php';

$mensagem = '';
$erro = '';

// Processamento: Criar Novo Usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar_usuario') {
    $nome        = trim($_POST['nome'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $matricula   = trim($_POST['matricula'] ?? '');
    $senha       = trim($_POST['senha'] ?? '');
    $nivelAcesso = $_POST['nivel_acesso'] ?? 'aluno';

    if (!empty($nome) && !empty($email) && !empty($senha)) {
        try {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            $matVal    = !empty($matricula) ? $matricula : null;

            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nome, email, matricula, senha, nivel_acesso, tipo, status) 
                VALUES (:nome, :email, :matricula, :senha, :nivel_acesso, :tipo, 'ativo')
            ");
            $stmt->execute([
                ':nome'         => $nome,
                ':email'        => $email,
                ':matricula'    => $matVal,
                ':senha'        => $senhaHash,
                ':nivel_acesso' => $nivelAcesso,
                ':tipo'         => $nivelAcesso
            ]);

            $novoId = $pdo->lastInsertId();

            // Se for aluno, cria o registro correspondente na tabela alunos
            if ($nivelAcesso === 'aluno') {
                $stmtAluno = $pdo->prepare("INSERT INTO alunos (usuario_id, status_matricula) VALUES (:usuario_id, 'ativa')");
                $stmtAluno->execute([':usuario_id' => $novoId]);
            }

            $mensagem = "Usuário cadastrado com sucesso no nível " . ucfirst($nivelAcesso) . "!";
        } catch (PDOException $e) {
            $erro = "Erro ao cadastrar usuário: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha todos os campos obrigatórios.";
    }
}

// Processamento: Vincular Aluno a Professor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'vincular_professor') {
    $alunoUsuarioId = intval($_POST['aluno_id'] ?? 0);
    $professorId    = intval($_POST['professor_id'] ?? 0);

    if ($alunoUsuarioId > 0 && $professorId > 0) {
        try {
            $stmtVinc = $pdo->prepare("UPDATE alunos SET professor_id = :professor_id WHERE usuario_id = :usuario_id");
            $stmtVinc->execute([
                ':professor_id' => $professorId,
                ':usuario_id'   => $alunoUsuarioId
            ]);
            $mensagem = "Vínculo de professor atualizado com sucesso!";
        } catch (PDOException $e) {
            $erro = "Erro ao vincular professor: " . $e->getMessage();
        }
    } else {
        $erro = "Selecione o aluno e o professor.";
    }
}

// Consultas para o painel
$usuarios = [];
$professores = [];
$alunosSemProf = [];

try {
    $usuarios = $pdo->query("SELECT id, nome, email, matricula, nivel_acesso, tipo, status, criado_em FROM usuarios ORDER BY id DESC")->fetchAll();
    $professores = $pdo->query("SELECT id, nome FROM usuarios WHERE (nivel_acesso = 'professor' OR tipo = 'professor') AND status = 'ativo'")->fetchAll();
    $alunosSemProf = $pdo->query("
        SELECT u.id, u.nome, a.professor_id, p.nome AS nome_professor
        FROM usuarios u
        JOIN alunos a ON u.id = a.usuario_id
        LEFT JOIN usuarios p ON a.professor_id = p.id
        WHERE (u.nivel_acesso = 'aluno' OR u.tipo = 'aluno') AND u.status = 'ativo'
    ")->fetchAll();
} catch (PDOException $e) {
    // Base sem conexão inicial
}

require_once '../layouts/header.php';
?>

<?php renderizarAvisoPermissao(); ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 28px; font-weight: 800; color: #fff; letter-spacing: -0.5px; margin-bottom: 4px;">Painel de Administração (Acesso Total)</h1>
        <p style="color: var(--text-muted); font-size: 15px;">Gerenciamento geral de contas, perfis RBAC e módulos operacionais.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="recepcao.php" class="btn btn-outline" style="font-size: 13px;">
            <i class="fa-solid fa-id-badge" style="color: #38bdf8;"></i> Módulo Balcão / Recepção
        </a>
        <a href="loja.php" class="btn btn-outline" style="font-size: 13px;">
            <i class="fa-solid fa-cash-register" style="color: #10b981;"></i> Módulo Loja / PDV
        </a>
    </div>
</div>

<?php if (!empty($mensagem)): ?>
    <div style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 16px; border-radius: var(--radius); margin-bottom: 24px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-circle-check" style="font-size: 18px;"></i> <?php echo htmlspecialchars($mensagem); ?>
    </div>
<?php endif; ?>

<?php if (!empty($erro)): ?>
    <div style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 16px; border-radius: var(--radius); margin-bottom: 24px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-circle-exclamation" style="font-size: 18px;"></i> <?php echo htmlspecialchars($erro); ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 24px; margin-bottom: 32px;">
    <!-- Formulário: Novo Usuário -->
    <div class="panel-card" style="margin-bottom: 0;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #fff;">
            <i class="fa-solid fa-user-plus" style="color: var(--cor-principal);"></i> Cadastrar Novo Usuário (RBAC)
        </h3>
        <form method="POST" action="index.php">
            <input type="hidden" name="acao" value="criar_usuario">
            
            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" name="nome" required placeholder="Ex: Rodrigo Nogueira">
            </div>

            <div class="form-group">
                <label>E-mail de Acesso (Login para Alunos)</label>
                <input type="email" name="email" required placeholder="exemplo@gymcontrol.com">
            </div>

            <div class="form-group">
                <label>Matrícula / ID Funcional (Para Funcionários)</label>
                <input type="text" name="matricula" placeholder="Ex: ADM002, REC002, LOJA002 (Opcional)">
            </div>

            <div class="form-group">
                <label>Senha Inicial</label>
                <input type="password" name="senha" required placeholder="Digite uma senha inicial">
            </div>

            <div class="form-group">
                <label>Nível de Acesso (RBAC)</label>
                <select name="nivel_acesso">
                    <option value="aluno">Aluno (Acesso exclusivo /aluno/)</option>
                    <option value="recepcao">Recepção (Acesso exclusivo admin/recepcao.php)</option>
                    <option value="loja">Loja / PDV (Acesso exclusivo admin/loja.php)</option>
                    <option value="professor">Professor / Personal (Acesso /professor/)</option>
                    <option value="admin">Administrador Geral (Acesso Total)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                <i class="fa-solid fa-check"></i> Concluir Cadastro
            </button>
        </form>
    </div>

    <!-- Formulário: Vínculo de Personal ao Aluno -->
    <div class="panel-card" style="margin-bottom: 0;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 10px; color: #fff;">
            <i class="fa-solid fa-link" style="color: var(--cor-accent);"></i> Associar Personal ao Aluno
        </h3>
        <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">
            Defina qual professor da academia será o responsável por prescrever os treinos do aluno.
        </p>

        <form method="POST" action="index.php">
            <input type="hidden" name="acao" value="vincular_professor">

            <div class="form-group">
                <label>Selecione o Aluno</label>
                <select name="aluno_id" required>
                    <option value="">Escolha um aluno cadastrado...</option>
                    <?php foreach ($alunosSemProf as $a): ?>
                        <option value="<?php echo $a['id']; ?>">
                            <?php echo htmlspecialchars($a['nome']); ?> 
                            (Atual: <?php echo htmlspecialchars($a['nome_professor'] ?? 'Nenhum'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Selecione o Personal</label>
                <select name="professor_id" required>
                    <option value="">Escolha o professor...</option>
                    <?php foreach ($professores as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-accent" style="width: 100%; margin-top: 10px;">
                <i class="fa-solid fa-floppy-disk"></i> Salvar Vínculo
            </button>
        </form>
    </div>
</div>

<!-- Tabela de Usuários Cadastrados -->
<div class="panel-card">
    <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #fff;">
        <i class="fa-solid fa-users" style="color: var(--cor-principal);"></i> Contas Registradas e Permissões RBAC
    </h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted);">
                    <th style="padding: 14px 16px;">ID</th>
                    <th style="padding: 14px 16px;">Nome</th>
                    <th style="padding: 14px 16px;">Matrícula</th>
                    <th style="padding: 14px 16px;">E-mail</th>
                    <th style="padding: 14px 16px;">Nível RBAC</th>
                    <th style="padding: 14px 16px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($usuarios)): ?>
                    <?php foreach ($usuarios as $u): ?>
                        <?php 
                            $nivel = $u['nivel_acesso'] ?? $u['tipo'];
                            $badgeColor = '#38bdf8';
                            if ($nivel === 'admin') $badgeColor = '#ef4444';
                            elseif ($nivel === 'professor') $badgeColor = '#10b981';
                            elseif ($nivel === 'recepcao') $badgeColor = '#f59e0b';
                            elseif ($nivel === 'loja') $badgeColor = '#a855f7';
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 14px 16px; color: var(--text-muted);"><?php echo $u['id']; ?></td>
                            <td style="padding: 14px 16px; font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($u['nome']); ?></td>
                            <td style="padding: 14px 16px; color: #94a3b8; font-family: monospace;">
                                <?php echo htmlspecialchars($u['matricula'] ?? '—'); ?>
                            </td>
                            <td style="padding: 14px 16px; color: var(--text-muted);"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td style="padding: 14px 16px;">
                                <span style="text-transform: uppercase; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: rgba(255,255,255,0.06); color: <?php echo $badgeColor; ?>; border: 1px solid <?php echo $badgeColor; ?>40;">
                                    <?php echo htmlspecialchars($nivel); ?>
                                </span>
                            </td>
                            <td style="padding: 14px 16px;">
                                <?php if ($u['status'] === 'ativo'): ?>
                                    <span style="color: #10b981; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                                        <i class="fa-solid fa-circle-check"></i> Ativo
                                    </span>
                                <?php else: ?>
                                    <span style="color: #ef4444; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                                        <i class="fa-solid fa-circle-xmark"></i> Inativo
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding: 24px; text-align: center; color: var(--text-muted);">
                            Nenhum usuário cadastrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
