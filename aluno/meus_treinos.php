<?php
// aluno/meus_treinos.php - Fichas de Treino do Aluno
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['aluno', 'admin']);
require_once __DIR__ . '/../config/conexao.php';

$usuario_id = $_SESSION['usuario_id'];

// Token de segurança para alterações no progresso.
if (empty($_SESSION['csrf_progresso'])) {
    $_SESSION['csrf_progresso'] = bin2hex(random_bytes(32));
}

// Salva a marcação de um exercício para o dia atual.
// A resposta é JSON para não recarregar a página inteira.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'marcar_exercicio') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if (!hash_equals($_SESSION['csrf_progresso'], (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'erro' => 'Sessão inválida. Atualize a página e tente novamente.']);
        exit;
    }

    $exercicioId = filter_var($_POST['exercicio_id'] ?? null, FILTER_VALIDATE_INT);
    $concluido = $_POST['concluido'] ?? null;
    if (!$exercicioId || $exercicioId < 1 || !in_array($concluido, ['0', '1'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Dados inválidos.']);
        exit;
    }

    try {
        // Garante que o exercício pertença a uma ficha do aluno logado.
        $verificar = $pdo->prepare("
            SELECT e.id, e.treino_id
            FROM exercicios e
            INNER JOIN treinos t ON t.id = e.treino_id
            WHERE e.id = :exercicio_id AND t.aluno_id = :aluno_id
            LIMIT 1
        ");
        $verificar->execute([
            ':exercicio_id' => $exercicioId,
            ':aluno_id' => $usuario_id
        ]);
         $exercicioAutorizado = $verificar->fetch(PDO::FETCH_ASSOC);
        if (!$exercicioAutorizado) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'Exercício não autorizado.']);
            exit;
        }

        // Salva a marcação e premia a ficha somente quando todos os exercícios
        // dela estiverem concluídos no dia. A chave UNIQUE impede XP duplicado.
        $pdo->beginTransaction();
        $salvar = $pdo->prepare("
            INSERT INTO progresso_exercicios
                (aluno_id, exercicio_id, data_treino, concluido)
            VALUES (:aluno_id, :exercicio_id, CURDATE(), :concluido)
            ON DUPLICATE KEY UPDATE
                concluido = VALUES(concluido), atualizado_em = CURRENT_TIMESTAMP
        ");
        $salvar->execute([
            ':aluno_id' => $usuario_id,
            ':exercicio_id' => $exercicioId,
            ':concluido' => (int) $concluido
        ]);

        $xpGanho = 0;
        if ($concluido === '1') {
            $contagem = $pdo->prepare("
                SELECT COUNT(*) AS total,
                       COALESCE(SUM(CASE WHEN p.concluido = 1 THEN 1 ELSE 0 END), 0) AS feitos
                FROM exercicios e
                LEFT JOIN progresso_exercicios p
                    ON p.exercicio_id = e.id
                   AND p.aluno_id = :aluno_progresso
                   AND p.data_treino = CURDATE()
                WHERE e.treino_id = :treino_id
            ");
            $contagem->execute([
                ':aluno_progresso' => $usuario_id,
                ':treino_id' => (int) $exercicioAutorizado['treino_id']
            ]);
            $totais = $contagem->fetch(PDO::FETCH_ASSOC);
            if ((int) $totais['total'] > 0 && (int) $totais['total'] === (int) $totais['feitos']) {
                $premiar = $pdo->prepare("
                    INSERT IGNORE INTO gamificacao_treinos_concluidos
                        (aluno_id, treino_id, data_treino, xp)
                    VALUES (:aluno_id, :treino_id, CURDATE(), 100)
                ");
                $premiar->execute([
                    ':aluno_id' => $usuario_id,
                    ':treino_id' => (int) $exercicioAutorizado['treino_id']
                ]);
                if ($premiar->rowCount() === 1) {
                    $xpGanho = 100;
                }
            }
        }
        $pdo->commit();
        echo json_encode(['ok' => true, 'concluido' => (int) $concluido, 'xp_ganho' => $xpGanho]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Erro ao salvar progresso: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'erro' => 'Não foi possível salvar o progresso.']);
    }
    exit;
}

// Os treinos são carregados do banco, nunca de exemplos fixos.
$dadosAluno = ['nome_professor' => 'Não atribuído'];
$treinos = [];
$erroCarregamento = '';

try {
    $stmt = $pdo->prepare("
        SELECT p.nome AS nome_professor
        FROM alunos a
        LEFT JOIN usuarios p ON p.id = a.professor_id
        WHERE a.usuario_id = :usuario_id
        LIMIT 1
    ");
    $stmt->execute([':usuario_id' => $usuario_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res && !empty($res['nome_professor'])) {
        $dadosAluno['nome_professor'] = $res['nome_professor'];
    }

    // Usa usuarios.id como aluno_id, conforme o esquema atual do GymControl.
    // O JOIN garante que cada ficha pertença ao aluno autenticado.
    $stmt = $pdo->prepare("
        SELECT t.id, t.titulo, t.descricao, t.data_criacao
        FROM treinos t
        WHERE t.aluno_id = :usuario_id
        ORDER BY t.data_criacao ASC, t.id ASC
    ");
    $stmt->execute([':usuario_id' => $usuario_id]);
    $fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtExercicios = $pdo->prepare("
        SELECT e.id, e.nome_exercicio, e.series, e.repeticoes, e.carga, e.observacoes,
               COALESCE(p.concluido, 0) AS concluido
        FROM exercicios e
        INNER JOIN treinos t ON t.id = e.treino_id
        LEFT JOIN progresso_exercicios p
            ON p.exercicio_id = e.id
           AND p.aluno_id = :aluno_progresso
           AND p.data_treino = CURDATE()
        WHERE e.treino_id = :treino_id AND t.aluno_id = :usuario_id
        ORDER BY e.id ASC
    ");

    foreach ($fichas as $ficha) {
        $stmtExercicios->execute([
            ':treino_id' => $ficha['id'],
            ':usuario_id' => $usuario_id,
            ':aluno_progresso' => $usuario_id
        ]);
        $itens = [];
        foreach ($stmtExercicios->fetchAll(PDO::FETCH_ASSOC) as $ex) {
            $itens[] = [
                'id' => (int) $ex['id'],
                'concluido' => (bool) $ex['concluido'],
                'nome' => $ex['nome_exercicio'],
                'series' => $ex['series'],
                'repeticoes' => $ex['repeticoes'],
                'carga' => $ex['carga'] ?: 'Não informada',
                'obs' => $ex['observacoes'] ?? ''
            ];
        }
        $treinos[(int) $ficha['id']] = [
            'titulo' => $ficha['titulo'],
            'orientacao' => $ficha['descricao'] ?? '',
            'exercicios' => $itens
        ];
    }
} catch (PDOException $e) {
    error_log('Erro ao carregar treinos do aluno: ' . $e->getMessage());
    $treinos = [];
    $erroCarregamento = 'Não foi possível carregar suas fichas agora. Tente novamente mais tarde.';
}

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

  <!-- Navegação pelas fichas cadastradas no banco -->
  <?php if (!empty($treinos)): ?>
    <div class="fichas-nav">
      <?php 
      $primeiroBotao = true;
      foreach ($treinos as $idFicha => $dadosFicha): 
      ?>
        <button 
          class="btn-ficha <?php echo $primeiroBotao ? 'active' : ''; ?>" 
          onclick="selecionarFicha('treino-<?php echo (int) $idFicha; ?>', this)">
          <?php echo htmlspecialchars($dadosFicha['titulo'], ENT_QUOTES, 'UTF-8'); ?>
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
  foreach ($treinos as $idFicha => $ficha): 
  ?>
    <div id="treino-<?php echo (int) $idFicha; ?>" class="conteudo-treino <?php echo $primeiroBloco ? 'active' : ''; ?>">
      
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
      <?php if (empty($ficha['exercicios'])): ?>
        <div class="panel-card" style="padding: 22px; color: var(--text-muted);">
          O professor ainda não cadastrou exercícios nesta ficha.
        </div>
      <?php endif; ?>
      <?php foreach ($ficha['exercicios'] as $ex): ?>
        <div class="exercicio-card <?php echo $ex['concluido'] ? 'concluido' : ''; ?>"
             data-exercicio-id="<?php echo (int) $ex['id']; ?>"
             onclick="marcarConcluido(this)"
             role="button" tabindex="0"
             aria-pressed="<?php echo $ex['concluido'] ? 'true' : 'false'; ?>"
             onkeydown="if(event.key === 'Enter' || event.key === ' '){ event.preventDefault(); marcarConcluido(this); }">
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
    <p style="color: var(--text-muted);"><?php echo htmlspecialchars($erroCarregamento ?: 'Nenhum treino cadastrado para a sua conta no momento.'); ?></p>
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

  const csrfProgresso = <?php echo json_encode($_SESSION['csrf_progresso']); ?>;

  async function marcarConcluido(card) {
    if (card.dataset.salvando === '1') return;
    const novoEstado = !card.classList.contains('concluido');
    card.dataset.salvando = '1';
    card.style.cursor = 'wait';

    const dados = new URLSearchParams({
      acao: 'marcar_exercicio',
      csrf_token: csrfProgresso,
      exercicio_id: card.dataset.exercicioId,
      concluido: novoEstado ? '1' : '0'
    });

    try {
      const resposta = await fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        credentials: 'same-origin',
        body: dados.toString()
      });
      const resultado = await resposta.json();
      if (!resposta.ok || !resultado.ok) {
        throw new Error(resultado.erro || 'Erro ao salvar progresso.');
      }
      card.classList.toggle('concluido', resultado.concluido === 1);
      card.setAttribute('aria-pressed', resultado.concluido === 1 ? 'true' : 'false');
    } catch (erro) {
      alert(erro.message || 'Não foi possível salvar. Tente novamente.');
    } finally {
      card.dataset.salvando = '0';
      card.style.cursor = '';
    }
  }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>