<?php
require_once __DIR__ . '/../config/auth.php';
verificarAcesso('professor');

require_once __DIR__ . '/../config/conexao.php';

$professor_id = $_SESSION['usuario_id'];

// Busca apenas os alunos vinculados a este professor específico
$stmt = $pdo->prepare("
    SELECT u.id, u.nome, u.email, a.status_matricula 
    FROM usuarios u 
    JOIN alunos a ON u.id = a.usuario_id 
    WHERE a.professor_id = :professor_id AND u.status = 'ativo'
");
$stmt->execute([':professor_id' => $professor_id]);
$meusAlunos = $stmt->fetchAll();

require_once __DIR__ . '/../layouts/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
    <div>
        <h1 style="font-size: 28px; font-weight: 800; color: #fff; letter-spacing: -0.5px; margin-bottom: 4px;">Meus Alunos</h1>
        <p style="color: var(--text-muted); font-size: 15px;">Gerencie os treinos e acompanhe a evolução dos alunos sob sua responsabilidade.</p>
    </div>
</div>

<div class="panel-card">
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
                            <td style="padding: 14px 16px; display: flex; gap: 10px;">
                                <a href="treino.php?aluno_id=<?php echo $aluno['id']; ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                    <i class="fa-solid fa-dumbbell"></i> Ficha de Treino
                                </a>
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