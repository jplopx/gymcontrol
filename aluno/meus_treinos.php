<?php
// aluno/meus_treinos.php - Fichas de Treino do Aluno
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['aluno', 'admin']);
require_once __DIR__ . '/../config/conexao.php';

$usuario_id = $_SESSION['usuario_id'];

// Dados do professor vinculado
$dadosAluno = [
    'nome_professor' => 'Prof. Carlos Silva'
];

try {
    $stmt = $pdo->prepare("
        SELECT a.*, p.nome as nome_professor 
        FROM alunos a 
        LEFT JOIN usuarios p ON a.professor_id = p.id 
        WHERE a.usuario_id = :usuario_id
        LIMIT 1
    ");
    $stmt->execute([':usuario_id' => $usuario_id]);
    $res = $stmt->fetch();
    if ($res && !empty($res['nome_professor'])) {
        $dadosAluno['nome_professor'] = $res['nome_professor'];
    }
} catch (PDOException $e) {
    // Mantém fallback simulado em caso de base sem conexão
}

// ESTRUTURA DINÂMICA DE TREINOS (Cadastre quantas fichas o aluno tiver: A, B, C, D, E...)
$treinos = [
    'A' => [
        'titulo' => 'Treino A — Peito & Tríceps',
        'orientacao' => 'Manter intervalo de 60s entre as séries. Focar na execução controlada no supino.',
        'exercicios' => [
            ['nome' => 'Supino Reto com Barra', 'series' => 4, 'repeticoes' => '10 a 12', 'carga' => '30kg (cada lado)', 'obs' => 'Cadência 2-0-2.'],
            ['nome' => 'Supino Inclinado com Halteres', 'series' => 3, 'repeticoes' => '12', 'carga' => '22kg', 'obs' => 'Manter escápulas aduzidas.'],
            ['nome' => 'Tríceps Corda na Polia', 'series' => 4, 'repeticoes' => '12 a 15', 'carga' => '35kg', 'obs' => 'Abrir a corda no final do movimento.']
        ]
    ],
    'B' => [
        'titulo' => 'Treino B — Costas & Bíceps',
        'orientacao' => 'Concentrar a força na contração das escapulas. Evitar usar impulso do tronco.',
        'exercicios' => [
            ['nome' => 'Puxada Frontal no Pulley', 'series' => 4, 'repeticoes' => '12', 'carga' => '45kg', 'obs' => 'Sem usar o balanço do corpo.'],
            ['nome' => 'Remada Curvada com Barra', 'series' => 4, 'repeticoes' => '10', 'carga' => '20kg (cada lado)', 'obs' => 'Manter a coluna bem selada.'],
            ['nome' => 'Rosca Direta com Barra W', 'series' => 3, 'repeticoes' => '10', 'carga' => '12kg (cada lado)', 'obs' => 'Cotovelos junto ao corpo.']
        ]
    ], 
    'C' => [
        'titulo' => 'Treino C — Quadríceps & Panturrilha',
        'orientacao' => 'Aquecer bem os joelhos na extensora antes de iniciar o agachamento pesado.',
        'exercicios' => [
            ['nome' => 'Agachamento Livre com Barra', 'series' => 4, 'repeticoes' => '8 a 10', 'carga' => '35kg (cada lado)', 'obs' => 'Descer até 90 graus com tronco reto.'],
            ['nome' => 'Cadeira Extensora', 'series' => 4, 'repeticoes' => '12 a 15', 'carga' => '50kg', 'obs' => 'Pico de contração de 1s no topo.'],
            ['nome' => 'Gêmeos Sentado (Panturrilha)', 'series' => 4, 'repeticoes' => '15 a 20', 'carga' => '40kg', 'obs' => 'Alongamento máximo na descida.']
        ]
    ],
    'D' => [
        'titulo' => 'Treino D — Ombros & Core',
        'orientacao' => 'Cadência lenta nas elevações laterais para preservar a articulação.',
        'exercicios' => [
            ['nome' => 'Desenvolvimento com Halteres', 'series' => 4, 'repeticoes' => '10 a 12', 'carga' => '16kg', 'obs' => 'Manter o core firme.'],
            ['nome' => 'Elevação Lateral na Polia', 'series' => 4, 'repeticoes' => '15', 'carga' => '10kg', 'obs' => 'Controlar a volta.']
        ] 
    ]
];

require_once __DIR__ . '/../layouts/header.php';
?>

<style>
  .aluno-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 24px; 
    flex-wrap: wrap; 
    gap: 16px; 
  }

  .aluno-title h1 { 
    font-size: 24px; 
    font-weight: 800; 
    color: #fff; 
    margin-top: 6px; 
  }

  .aluno-title p { 
    color: var(--text-muted); 
    font-size: 14px; 
  }
  
  .btn-voltar { 
    color: var(--cor-principal); 
    font-weight: 600; 
    font-size: 13px; 
    display: inline-flex; 
    align-items: center; 
    gap: 6px; 
    text-decoration: none; 
    transition: opacity 0.2s;
  }

  .btn-voltar:hover {
    opacity: 0.8;
  }
  
  /* Seletor de Fichas Dinâmico com Rolagem no Mobile */
  .fichas-nav { 
    display: flex; 
    gap: 8px; 
    background: #090d16; 
    padding: 6px; 
    border-radius: 10px; 
    border: 1px solid var(--border-color); 
    max-width: 100%;
    overflow-x: auto;
    white-space: nowrap;
    scrollbar-width: none;
  }

  .fichas-nav::-webkit-scrollbar {
    display: none;
  }

  .btn-ficha { 
    padding: 8px 18px; 
    border-radius: 6px; 
    font-size: 13px; 
    font-weight: 700; 
    color: var(--text-muted); 
    background: transparent; 
    border: none; 
    cursor: pointer; 
    transition: all 0.2s; 
    flex-shrink: 0;
  }

  .btn-ficha.active { 
    background: var(--cor-principal); 
    color: #fff; 
    box-shadow: 0 2px 8px rgba(2, 132, 199, 0.4); 
  }

  /* Controle de Visibilidade dos Treinos */
  .conteudo-treino { 
    display: none; 
  }

  .conteudo-treino.active { 
    display: flex; 
    flex-direction: column; 
    gap: 14px; 
  }

  /* Cards de Orientações e Exercícios */
  .orientacao-card { 
    background: rgba(2, 132, 199, 0.1); 
    border: 1px solid rgba(56, 189, 248, 0.3); 
    border-radius: 10px; 
    padding: 16px 20px; 
    margin-bottom: 24px; 
    display: flex; 
    align-items: flex-start; 
    gap: 14px; 
  }

  .orientacao-card i { 
    color: #38bdf8; 
    font-size: 20px; 
    margin-top: 2px; 
  }

  .orientacao-card strong { 
    color: #38bdf8; 
    display: block; 
    font-size: 13px; 
    text-transform: uppercase; 
    margin-bottom: 4px; 
  }

  .orientacao-card p { 
    color: #e2e8f0; 
    font-size: 14px; 
  }

  .exercicio-card { 
    background: var(--bg-surface); 
    border: 1px solid var(--border-color); 
    border-radius: 12px; 
    padding: 18px 20px; 
    display: flex; 
    align-items: center; 
    justify-content: space-between; 
    transition: transform 0.15s, border-color 0.15s; 
  }

  .exercicio-card:hover { 
    border-color: var(--cor-principal); 
    transform: translateY(-2px); 
  }

  .exercicio-info h3 { 
    font-size: 16px; 
    font-weight: 700; 
    color: #fff; 
    margin-bottom: 6px; 
  }

  .exercicio-tags { 
    display: flex; 
    gap: 12px; 
    flex-wrap: wrap; 
  }

  .tag-item { 
    font-size: 13px; 
    color: var(--text-muted); 
    display: flex; 
    align-items: center; 
    gap: 6px; 
  }

  .tag-item strong { 
    color: #fff; 
  }

  .exercicio-obs { 
    margin-top: 8px; 
    font-size: 12px; 
    color: #94a3b8; 
    font-style: italic; 
  }

  .check-box { 
    width: 28px; 
    height: 28px; 
    border-radius: 50%; 
    border: 2px solid var(--border-color); 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    cursor: pointer; 
    color: transparent; 
    transition: all 0.2s; 
    flex-shrink: 0; 
  }

  .exercicio-card.concluido { 
    opacity: 0.6; 
    background: #090d16; 
  }

  .exercicio-card.concluido .check-box { 
    background: var(--cor-accent); 
    border-color: var(--cor-accent); 
    color: #fff; 
  }

  .exercicio-card.concluido h3 { 
    text-decoration: line-through; 
  }

  @media (max-width: 640px) {
    .aluno-header { 
      flex-direction: column; 
      align-items: flex-start; 
    }
    .fichas-nav { 
      width: 100%; 
    }
    .exercicio-card { 
      align-items: flex-start; 
    }
  }
</style>

<div class="aluno-header">
  <div class="aluno-title">
    <a href="index.php" class="btn-voltar">
      <i class="fa-solid fa-arrow-left"></i> Voltar ao Painel
    </a>
    <h1><i class="fa-solid fa-dumbbell" style="color: var(--cor-principal);"></i> Minha Ficha de Treino</h1>
    <p>Professor responsável: <strong><?php echo htmlspecialchars($dadosAluno['nome_professor']); ?></strong></p>
  </div>

  <!-- NAVEGAÇÃO DOS BOTÕES DUMMY GERADA AUTOMATICAMENTE PELAS CHAVES DO ARRAY -->
  <?php if (!empty($treinos)): ?>
    <div class="fichas-nav">
      <?php 
      $primeiroBotao = true;
      foreach ($treinos as $letra => $dadosFicha): 
      ?>
        <button 
          class="btn-ficha <?php echo $primeiroBotao ? 'active' : ''; ?>" 
          onclick="selecionarFicha('treino-<?php echo strtolower($letra); ?>', this)">
          Treino <?php echo $letra; ?>
        </button>
      <?php 
        $primeiroBotao = false;
      endforeach; 
      ?>
    </div>
  <?php endif; ?>
</div>

<!-- BLOCOS DE CONTEÚDO DOS TREINOS GERADOS AUTOMATICAMENTE -->
<?php if (!empty($treinos)): ?>
  <?php 
  $primeiroBloco = true;
  foreach ($treinos as $letra => $ficha): 
  ?>
    <div id="treino-<?php echo strtolower($letra); ?>" class="conteudo-treino <?php echo $primeiroBloco ? 'active' : ''; ?>">
      
      <!-- Orientações do Treinador -->
      <?php if (!empty($ficha['orientacao'])): ?>
        <div class="orientacao-card">
          <i class="fa-solid fa-user-gear"></i>
          <div>
            <strong><?php echo htmlspecialchars($ficha['titulo']); ?></strong>
            <p><?php echo htmlspecialchars($ficha['orientacao']); ?></p>
          </div>
        </div>
      <?php endif; ?>

      <!-- Lista dos Exercícios -->
      <?php foreach ($ficha['exercicios'] as $ex): ?>
        <div class="exercicio-card" onclick="marcarConcluido(this)">
          <div class="exercicio-info">
            <h3><?php echo htmlspecialchars($ex['nome']); ?></h3>
            
            <div class="exercicio-tags">
              <span class="tag-item"><i class="fa-solid fa-layer-group"></i> Séries: <strong><?php echo htmlspecialchars($ex['series']); ?></strong></span>
              <span class="tag-item"><i class="fa-solid fa-rotate-right"></i> Reps: <strong><?php echo htmlspecialchars($ex['repeticoes']); ?></strong></span>
              <span class="tag-item"><i class="fa-solid fa-weight-hanging"></i> Carga: <strong><?php echo htmlspecialchars($ex['carga']); ?></strong></span>
            </div>

            <?php if (!empty($ex['obs'])): ?>
              <div class="exercicio-obs">
                <i class="fa-regular fa-comment-dots"></i> <?php echo htmlspecialchars($ex['obs']); ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="check-box" title="Marcar como feito">
            <i class="fa-solid fa-check"></i>
          </div>
        </div>
      <?php endforeach; ?>

    </div>
  <?php 
    $primeiroBloco = false;
  endforeach; 
  ?>
<?php else: ?>
  <div class="panel-card" style="text-align: center; padding: 40px;">
    <p style="color: var(--text-muted);">Nenhum treino cadastrado para a sua conta no momento.</p>
  </div>
<?php endif; ?>

<!-- SCRIPTS DE INTERAÇÃO -->
<script>
  function selecionarFicha(idTreino, elementoBotao) {
    document.querySelectorAll('.conteudo-treino').forEach(treino => {
      treino.classList.remove('active');
    });

    document.querySelectorAll('.btn-ficha').forEach(btn => {
      btn.classList.remove('active');
    });

    document.getElementById(idTreino).classList.add('active');
    elementoBotao.classList.add('active');
  }

  function marcarConcluido(card) {
    card.classList.toggle('concluido');
  }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>