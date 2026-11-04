<?php
require_once __DIR__ . '/../config/auth.php';
verificarAcesso('aluno');

require_once __DIR__ . '/../config/conexao.php';

$usuario_id = $_SESSION['usuario_id'];

// Busca os dados do aluno e o nome do professor vinculado a ele
$stmt = $pdo->prepare("
    SELECT a.*, p.nome as nome_professor 
    FROM alunos a 
    LEFT JOIN usuarios p ON a.professor_id = p.id 
    WHERE a.usuario_id = :usuario_id
");
$stmt->execute([':usuario_id' => $usuario_id]);
$dadosAluno = $stmt->fetch();

require_once __DIR__ . '/../layouts/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
    <div>
        <h1 style="font-size: 28px; font-weight: 800; color: #fff; letter-spacing: -0.5px; margin-bottom: 4px;">Meu Painel</h1>
        <p style="color: var(--text-muted); font-size: 15px;">Acompanhe sua matrícula e seus treinos.</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 32px;">
    <!-- Card Resumo da Matrícula -->
    <div class="panel-card" style="margin-bottom: 0;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px; color: #fff; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-id-card" style="color: var(--cor-principal);"></i> Dados da Matrícula
        </h3>
        <div style="margin-bottom: 12px; font-size: 14px;">
            <span style="color: var(--text-muted);">Status:</span> 
            <span style="color: #10b981; font-weight: 700; margin-left: 6px;"><i class="fa-solid fa-circle-check"></i> Ativa</span>
        </div>
        <div style="font-size: 14px;">
            <span style="color: var(--text-muted);">Personal Trainer:</span> 
            <strong style="color: #fff; margin-left: 6px;">
                <?php echo htmlspecialchars($dadosAluno['nome_professor'] ?? 'Aguardando vínculo'); ?>
            </strong>
        </div>
    </div>
    
    <!-- Card Ficha de Treino (Onde o Nicolas vai focar) -->
    <div class="panel-card" style="margin-bottom: 0;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 12px; color: #fff; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-dumbbell" style="color: var(--cor-accent);"></i> Ficha de Treino
        </h3>
        <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 14px;">Acesse a lista de exercícios e séries prescritas pelo seu treinador.</p>
        <a href="meus_treinos.php" class="btn btn-accent" style="width: 100%;">
            <i class="fa-solid fa-eye"></i> Visualizar Treino Atual
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>