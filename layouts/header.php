<?php
require_once __DIR__ . '/../config/auth.php';
$configTema  = require __DIR__ . '/../config/tema.php';
$temaAtual   = $configTema['tema'];

$isSidebar = ($temaAtual['layout_menu'] === 'lateral');
// Estilos e navegação aplicados exclusivamente ao perfil correspondente.
// O CSS do aluno e o do professor não interferem nas outras áreas.
$gcAreaDiretorio = basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
$gcAlunoArea = ($gcAreaDiretorio === 'aluno');
$gcProfessorArea = ($gcAreaDiretorio === 'professor');
$gcAlunoPagina = basename($_SERVER['SCRIPT_NAME'] ?? '');
$gcProfessorPagina = $gcProfessorArea ? basename($_SERVER['SCRIPT_NAME'] ?? '') : '';

// Algumas páginas do professor exigem um aluno selecionado. Não criar links
// sem aluno_id: as rotas continuam exigindo e validando esse identificador.
$gcProfessorAlunoId = 0;
if ($gcProfessorArea) {
  $gcAlunoIdParametro = filter_input(INPUT_GET, 'aluno_id', FILTER_VALIDATE_INT);
  if (is_int($gcAlunoIdParametro) && $gcAlunoIdParametro > 0) {
    $gcProfessorAlunoId = $gcAlunoIdParametro;
  } elseif ($gcProfessorPagina === 'exercicios.php' && isset($treino['aluno_id'])) {
    $gcProfessorAlunoId = max(0, (int) $treino['aluno_id']);
  }
}
$gcProfessorNavegacao = [
  'treino.php' => ['fa-dumbbell', 'Fichas'],
  'avaliacoes.php' => ['fa-heart-pulse', 'Avaliações'],
  'desafios.php' => ['fa-bullseye', 'Desafios'],
  'agendamentos.php' => ['fa-calendar-days', 'Agenda'],
  'mensagens.php' => ['fa-comments', 'Mensagens'],
];
$gcAlunoNavegacao = [
  'index.php' => ['fa-house', 'Início'],
  'meus_treinos.php' => ['fa-dumbbell', 'Treinos'],
  'meu_progresso.php' => ['fa-chart-line', 'Progresso'],
  'minha_evolucao.php' => ['fa-heart-pulse', 'Evolução'],
  'gamificacao.php' => ['fa-medal', 'Conquistas'],
  'ranking.php' => ['fa-ranking-star', 'Ranking e ligas'],
  'desafios.php' => ['fa-bullseye', 'Desafios'],
  'agendamentos.php' => ['fa-calendar-days', 'Agenda'],
  'mensagens.php' => ['fa-comments', 'Mensagens'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Painel — <?php echo htmlspecialchars($temaAtual['nome_academia']); ?></title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <?php if ($gcAlunoArea): ?>
  <link rel="stylesheet" href="../assets/area-aluno.css?v=20261118-1">
  <script src="../assets/area-aluno.js?v=20261118-1" defer></script>
  <?php endif; ?>
  <?php if ($gcProfessorArea): ?>
  <link rel="stylesheet" href="../assets/area-professor.css?v=20261118-2">
  <script src="../assets/area-professor.js?v=20261118-2" defer></script>
  <?php endif; ?>
  <?php if ($gcAlunoArea || $gcProfessorArea): ?>
  <link rel="stylesheet" href="../assets/area-navegacao.css?v=20261118-1">
  <?php endif; ?>
  
  <style>
    :root {
      --cor-principal: <?php echo htmlspecialchars($temaAtual['cor_principal']); ?>;
      --cor-principal-hover: <?php echo htmlspecialchars($temaAtual['cor_principal']); ?>;
      --cor-secundaria: #0f172a;
      --cor-accent: <?php echo htmlspecialchars($temaAtual['cor_destaque']); ?>;
      --cor-accent-hover: <?php echo htmlspecialchars($temaAtual['cor_destaque']); ?>;
      
      --bg-light: #0f172a; 
      --bg-surface: #1e293b; 
      --border-color: #334155;
      
      --text-main: #f8fafc; 
      --text-muted: #94a3b8;
      
      --radius: 8px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
    body { background-color: var(--bg-light); color: var(--text-main); min-height: 100vh; display: flex; flex-direction: column; }
    a { text-decoration: none; color: inherit; }

    .navbar { height: 72px; background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(8px); display: flex; align-items: center; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 50; }
    .container { width: 100%; max-width: 1100px; margin: 0 auto; padding: 0 24px; }
    .nav-wrapper { display: flex; align-items: center; justify-content: space-between; }
    
    .logo { display: flex; align-items: center; gap: 12px; font-weight: 800; font-size: 20px; color: #fff; }
    .logo-badge { width: 38px; height: 38px; background: var(--cor-principal); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 18px; color: #fff; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4); }
    .logo-img { height: 38px; width: auto; max-width: 140px; object-fit: contain; border-radius: 6px; }
    
    .user-tag { display: flex; align-items: center; gap: 16px; color: var(--text-muted); font-size: 14px; }
    .badge-cargo { background: rgba(56, 189, 248, 0.15); color: var(--cor-principal); border: 1px solid var(--cor-principal); padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: var(--radius); font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: all 0.2s ease; }
    .btn-primary { background-color: var(--cor-principal); color: #fff; }
    .btn-primary:hover { filter: brightness(1.1); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }
    .btn-accent { background-color: var(--cor-accent); color: #fff; }
    .btn-accent:hover { filter: brightness(1.1); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }
    .btn-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 8px 14px; font-size: 13px; }
    .btn-danger:hover { background: #ef4444; color: #fff; }

    .main-wrapper { flex: 1; padding: 40px 0; }
    .panel-card { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 28px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); margin-bottom: 24px; }
    
    .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
    .form-group label { font-size: 13px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
    .form-group input, .form-group select { padding: 12px 14px; border-radius: var(--radius); border: 1px solid var(--border-color); background: #090d16; color: #fff; font-size: 14px; outline: none; transition: border-color 0.2s; }
    .form-group input:focus, .form-group select:focus { border-color: var(--cor-principal); }

    <?php if ($isSidebar): ?>
    /* Layout alternativo: Menu Lateral Fixo (Sidebar) */
    @media (min-width: 992px) {
      body { padding-left: 250px; }
      .navbar { position: fixed; top: 0; left: 0; bottom: 0; width: 250px; height: 100vh; border-bottom: none; border-right: 1px solid var(--border-color); align-items: flex-start; padding: 28px 20px; }
      .navbar .container { padding: 0; height: 100%; }
      .nav-wrapper { flex-direction: column; align-items: flex-start; height: 100%; }
      .user-tag { flex-direction: column; align-items: flex-start; margin-top: auto; width: 100%; gap: 12px; }
      .btn-danger { width: 100%; }
    }
    <?php endif; ?>
  </style>
</head>
<body<?php if ($gcAlunoArea || $gcProfessorArea): ?> class="<?= $gcAlunoArea ? 'gc-student-area' : 'gc-teacher-area' ?><?= $isSidebar ? ' gc-with-sidebar' : '' ?>"<?php endif; ?>>

  <?php if (($gcAlunoArea || $gcProfessorArea) && $isSidebar): ?>
  <!-- Envolve o cabeçalho e os atalhos para formar um único menu lateral no desktop.
       Em telas menores, display:contents mantém ambos na posição superior. -->
  <div class="gc-navigation-shell">
  <?php endif; ?>

  <header class="navbar">
    <div class="container">
      <div class="nav-wrapper">
        <a href="../index.php" class="logo">
          <?php if (!empty($temaAtual['logo_url'])): ?>
            <img src="../<?php echo htmlspecialchars($temaAtual['logo_url']); ?>" alt="Logo" class="logo-img">
          <?php else: ?>
            <div class="logo-badge">
              <?php echo htmlspecialchars(mb_strtoupper(mb_substr($temaAtual['nome_academia'], 0, 2)), ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>
          <span><?php echo htmlspecialchars($temaAtual['nome_academia']); ?></span>
        </a>

        <div class="user-tag">
          <span>Olá, <strong><?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário'); ?></strong></span>
          <span class="badge-cargo"><?php echo htmlspecialchars($_SESSION['usuario_nivel'] ?? $_SESSION['usuario_tipo'] ?? 'Painel'); ?></span>
          <a href="../logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket"></i> Sair
          </a>
        </div>
      </div>
    </div>
  </header>

  <?php if ($gcAlunoArea): ?>
  <nav class="gc-student-subnav" aria-label="Páginas da área do aluno">
    <div class="container">
      <div class="gc-student-nav-scroll">
        <?php foreach ($gcAlunoNavegacao as $gcArquivo => $gcNavItem): ?>
          <a class="gc-student-nav-link" href="<?= htmlspecialchars($gcArquivo, ENT_QUOTES, 'UTF-8') ?>"<?= $gcArquivo === $gcAlunoPagina ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid <?= htmlspecialchars($gcNavItem[0], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
            <?= htmlspecialchars($gcNavItem[1], ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </nav>
  <?php endif; ?>

  <?php if ($gcProfessorArea): ?>
  <nav class="gc-trainer-subnav" aria-label="Navegação da área do professor">
    <div class="container">
      <div class="gc-trainer-nav-scroll">
        <a class="gc-trainer-nav-link" href="index.php"<?= $gcProfessorPagina === 'index.php' ? ' aria-current="page"' : '' ?>>
          <i class="fa-solid fa-house" aria-hidden="true"></i> Visão geral
        </a>
        <a class="gc-trainer-nav-link" href="modelos.php"<?= $gcProfessorPagina === 'modelos.php' ? ' aria-current="page"' : '' ?>>
          <i class="fa-solid fa-layer-group" aria-hidden="true"></i> Modelos de ficha
        </a>
        <?php if ($gcProfessorAlunoId > 0): ?>
          <?php foreach ($gcProfessorNavegacao as $gcArquivo => $gcNavItem): ?>
            <a class="gc-trainer-nav-link" href="<?= htmlspecialchars($gcArquivo . '?aluno_id=' . $gcProfessorAlunoId, ENT_QUOTES, 'UTF-8') ?>"<?= $gcArquivo === $gcProfessorPagina ? ' aria-current="page"' : '' ?>>
              <i class="fa-solid <?= htmlspecialchars($gcNavItem[0], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
              <?= htmlspecialchars($gcNavItem[1], ENT_QUOTES, 'UTF-8') ?>
            </a>
          <?php endforeach; ?>
          <?php if ($gcProfessorPagina === 'exercicios.php'): ?>
            <span class="gc-trainer-nav-link" aria-current="page"><i class="fa-solid fa-list-check" aria-hidden="true"></i> Exercícios</span>
          <?php endif; ?>
        <?php elseif ($gcProfessorPagina === 'index.php'): ?>
          <a class="gc-trainer-nav-link" href="#lista-alunos"><i class="fa-solid fa-users" aria-hidden="true"></i> Meus alunos</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
  <?php endif; ?>

  <?php if (($gcAlunoArea || $gcProfessorArea) && $isSidebar): ?>
  </div>
  <?php endif; ?>

  <main class="main-wrapper">
    <div class="container">