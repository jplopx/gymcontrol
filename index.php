<?php
session_start();
$logado = isset($_SESSION['usuario_id']);
$painelUrl = $logado ? $_SESSION['usuario_tipo'] . '/index.php' : 'login.php';
$usuarioNome = $logado ? $_SESSION['usuario_nome'] : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl — Sistema Integrado para Academias</title>
  
  <!-- Fonts & FontAwesome -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    /* ==========================================================================
       1. VARIAVEIS CSS & RESET (Identidade Visual Padrão)
       ========================================================================== */
    :root {
      --cor-principal: #0284c7;
      --cor-principal-hover: #0369a1;
      --cor-secundaria: #0f172a;
      --cor-accent: #10b981;
      --cor-accent-hover: #059669;
      
      --bg-light: #f8fafc;
      --bg-surface: #ffffff;
      --border-color: #e2e8f0;
      
      --text-main: #0f172a;
      --text-muted: #64748b;
      --text-light: #f8fafc;
      
      --radius: 8px;
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
      --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
      --shadow-lg: 0 12px 24px -4px rgba(0,0,0,0.12);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      background-color: var(--bg-light);
      color: var(--text-main);
      line-height: 1.6;
      font-size: 15px;
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    /* ==========================================================================
       2. NAVBAR / HEADER FIXO
       ========================================================================== */
    .navbar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 72px;
      background: rgba(15, 23, 42, 0.95);
      backdrop-filter: blur(8px);
      z-index: 1000;
      display: flex;
      align-items: center;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .container {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 24px;
    }

    .nav-wrapper {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 800;
      font-size: 20px;
      color: #fff;
    }

    .logo-badge {
      width: 38px;
      height: 38px;
      background: linear-gradient(135deg, var(--cor-principal), #38bdf8);
      border-radius: var(--radius);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      color: #fff;
      box-shadow: 0 4px 10px rgba(2, 132, 199, 0.4);
    }

    .nav-links {
      display: flex;
      gap: 28px;
      align-items: center;
      list-style: none;
    }

    .nav-links a {
      color: #cbd5e1;
      font-weight: 500;
      font-size: 14px;
      transition: color 0.2s;
    }

    .nav-links a:hover {
      color: #fff;
    }

    .nav-cta {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    /* ==========================================================================
       3. BUTTONS & BADGES
       ========================================================================== */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: var(--radius);
      font-weight: 600;
      font-size: 14px;
      border: 1px solid transparent;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .btn-primary {
      background-color: var(--cor-principal);
      color: #fff;
    }
    .btn-primary:hover {
      background-color: var(--cor-principal-hover);
      box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
    }

    .btn-accent {
      background-color: var(--cor-accent);
      color: #fff;
    }
    .btn-accent:hover {
      background-color: var(--cor-accent-hover);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .btn-outline {
      background: transparent;
      border-color: rgba(255,255,255,0.2);
      color: #fff;
    }
    .btn-outline:hover {
      background: rgba(255,255,255,0.1);
      border-color: #fff;
    }

    .badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      background: rgba(2, 132, 199, 0.15);
      color: #38bdf8;
      border: 1px solid rgba(56, 189, 248, 0.3);
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 16px;
    }

    /* ==========================================================================
       4. HERO SECTION
       ========================================================================== */
    .hero {
      padding: 140px 0 80px 0;
      background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
      color: #fff;
      position: relative;
      overflow: hidden;
    }

    .hero-grid {
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 40px;
      align-items: center;
    }

    .hero-text h1 {
      font-size: 42px;
      font-weight: 800;
      line-height: 1.2;
      letter-spacing: -0.5px;
      margin-bottom: 18px;
    }

    .hero-text h1 span {
      background: linear-gradient(135deg, #38bdf8, #10b981);
      background-clip: text;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .hero-text p {
      font-size: 17px;
      color: #94a3b8;
      margin-bottom: 32px;
      max-width: 540px;
    }

    .hero-btns {
      display: flex;
      gap: 16px;
      align-items: center;
    }

    .hero-mockup {
      position: relative;
    }

    .mockup-card {
      background: #090d16;
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.5);
    }

    .mockup-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 16px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
      padding-bottom: 12px;
    }

    .dot { width: 10px; height: 10px; border-radius: 50%; }
    .dot-red { background: #ef4444; }
    .dot-yellow { background: #f59e0b; }
    .dot-green { background: #10b981; }

    .mockup-preview-screen {
      background: #1e293b;
      border-radius: var(--radius);
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .mockup-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #0f172a;
      padding: 10px 14px;
      border-radius: 6px;
    }

    /* ==========================================================================
       5. PILARES
       ========================================================================== */
    .section {
      padding: 80px 0;
    }

    .section-header {
      text-align: center;
      max-width: 650px;
      margin: 0 auto 50px auto;
    }

    .section-header h2 {
      font-size: 32px;
      font-weight: 800;
      color: var(--text-main);
      margin-bottom: 12px;
      letter-spacing: -0.5px;
    }

    .section-header p {
      color: var(--text-muted);
      font-size: 16px;
    }

    .pillars-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 28px;
    }

    .pillar-card {
      background: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: var(--radius);
      padding: 32px 24px;
      box-shadow: var(--shadow-sm);
      transition: all 0.25s ease;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .pillar-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-md);
      border-color: var(--cor-principal);
    }

    .pillar-icon {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      background: rgba(2, 132, 199, 0.1);
      color: var(--cor-principal);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
    }

    .pillar-card h3 {
      font-size: 19px;
      font-weight: 700;
    }

    .pillar-card p {
      color: var(--text-muted);
      font-size: 14px;
      line-height: 1.6;
    }

    /* ==========================================================================
       6. RECURSOS
       ========================================================================== */
    .features-section {
      background-color: var(--bg-surface);
      border-top: 1px solid var(--border-color);
      border-bottom: 1px solid var(--border-color);
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 32px;
    }

    .feature-item {
      display: flex;
      gap: 18px;
    }

    .feature-icon-box {
      width: 44px;
      height: 44px;
      border-radius: var(--radius);
      background: #f1f5f9;
      color: var(--cor-principal);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }

    .feature-info h4 {
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .feature-info p {
      color: var(--text-muted);
      font-size: 14px;
    }

    /* ==========================================================================
       7. PLANOS
       ========================================================================== */
    .pricing-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 28px;
      align-items: stretch;
    }

    .pricing-card {
      background: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: 12px;
      padding: 36px 28px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      box-shadow: var(--shadow-sm);
      transition: all 0.2s ease;
    }

    .pricing-card.popular {
      border: 2px solid var(--cor-principal);
      box-shadow: var(--shadow-lg);
      transform: scale(1.03);
    }

    .popular-badge {
      position: absolute;
      top: -14px;
      left: 50%;
      transform: translateX(-50%);
      background: var(--cor-principal);
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      padding: 4px 14px;
      border-radius: 12px;
      letter-spacing: 0.5px;
    }

    .plan-name {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .plan-desc {
      font-size: 13px;
      color: var(--text-muted);
      margin-bottom: 24px;
      min-height: 40px;
    }

    .plan-price {
      font-size: 36px;
      font-weight: 800;
      color: var(--text-main);
      margin-bottom: 24px;
    }

    .plan-price span {
      font-size: 14px;
      color: var(--text-muted);
      font-weight: 500;
    }

    .plan-features {
      list-style: none;
      margin-bottom: 32px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .plan-features li {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
      color: var(--text-main);
    }

    .plan-features li i {
      color: var(--cor-accent);
      font-size: 14px;
    }

    /* ==========================================================================
       8. CONTATO
       ========================================================================== */
    .contact-box {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      border-radius: 16px;
      padding: 48px;
      color: #fff;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      align-items: center;
    }

    .contact-info h3 {
      font-size: 28px;
      font-weight: 800;
      margin-bottom: 12px;
    }

    .contact-info p {
      color: #94a3b8;
      font-size: 15px;
      margin-bottom: 24px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 14px;
    }

    .form-group label {
      font-size: 12px;
      font-weight: 600;
      color: #cbd5e1;
      text-transform: uppercase;
    }

    .form-group input, .form-group select {
      padding: 10px 14px;
      border-radius: var(--radius);
      border: 1px solid #334155;
      background: #090d16;
      color: #fff;
      font-size: 14px;
      outline: none;
    }

    .form-group input:focus, .form-group select:focus {
      border-color: var(--cor-principal);
    }

    /* ==========================================================================
       9. FOOTER
       ========================================================================== */
    .footer {
      background-color: var(--cor-secundaria);
      color: #94a3b8;
      padding: 60px 0 30px 0;
      border-top: 1px solid rgba(255,255,255,0.05);
    }

    .footer-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 40px;
      margin-bottom: 40px;
    }

    .footer-brand h4 {
      color: #fff;
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 12px;
    }

    .footer-links h5 {
      color: #fff;
      font-size: 14px;
      font-weight: 700;
      margin-bottom: 16px;
    }

    .footer-links ul {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .footer-links a {
      color: #94a3b8;
      font-size: 13px;
      transition: color 0.2s;
    }

    .footer-links a:hover { color: #fff; }

    .footer-bottom {
      border-top: 1px solid #1e293b;
      padding-top: 24px;
      text-align: center;
      font-size: 13px;
    }

    @media (max-width: 992px) {
      .hero-grid, .pillars-grid, .pricing-grid, .features-grid, .contact-box, .footer-grid {
        grid-template-columns: 1fr;
      }
      .hero-text h1 { font-size: 32px; }
      .nav-links { display: none; }
    }
  </style>
</head>
<body>

  <!-- ==========================================================================
     NAVBAR INTEGRADA AO BACKEND
     ========================================================================== -->
  <header class="navbar">
    <div class="container">
      <div class="nav-wrapper">
        <a href="index.php" class="logo">
          <div class="logo-badge">GC</div>
          <span>GymControl</span>
        </a>

        <ul class="nav-links">
          <li><a href="#inicio">Início</a></li>
          <li><a href="#sobre">A Plataforma</a></li>
          <li><a href="#recursos">Recursos</a></li>
          <li><a href="#planos">Planos</a></li>
          <li><a href="#contato">Fale Conosco</a></li>
        </ul>

        <div class="nav-cta">
          <?php if ($logado): ?>
            <a href="<?php echo htmlspecialchars($painelUrl); ?>" class="btn btn-accent">
              <i class="fa-solid fa-gauge-high"></i> Meu Painel (<?php echo htmlspecialchars(explode(' ', $usuarioNome)[0]); ?>)
            </a>
            <a href="logout.php" class="btn btn-outline" style="padding: 10px 14px;" title="Sair">
              <i class="fa-solid fa-right-from-bracket"></i>
            </a>
          <?php else: ?>
            <a href="login.php" class="btn btn-primary">
              <i class="fa-solid fa-arrow-right-to-bracket"></i> Acessar Sistema
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>

  <!-- ==========================================================================
     HERO SECTION
     ========================================================================== -->
  <section id="inicio" class="hero">
    <div class="container">
      <div class="hero-grid">
        <div class="hero-text">
          <div class="badge-pill">
            <i class="fa-solid fa-shield-halved"></i> Gestão Completa & Controle de Acesso
          </div>
          <h1>O Software de Gestão Centralizada para <span>Academias e Alunos</span></h1>
          <p>
            O GymControl conecta administradores, personal trainers e alunos em um só ambiente. Fichas de treinos dinâmicas, controle de mensalidades e mensagens diretas sem complicação.
          </p>
          <div class="hero-btns">
            <?php if ($logado): ?>
              <a href="<?php echo htmlspecialchars($painelUrl); ?>" class="btn btn-primary">
                <i class="fa-solid fa-gauge-high"></i> Ir para o Painel
              </a>
            <?php else: ?>
              <a href="login.php" class="btn btn-primary">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Entrar no Sistema
              </a>
              <a href="#sobre" class="btn btn-outline">Conhecer a Plataforma</a>
            <?php endif; ?>
          </div>
        </div>

        <div class="hero-mockup">
          <div class="mockup-card">
            <div class="mockup-header">
              <div class="dot dot-red"></div>
              <div class="dot dot-yellow"></div>
              <div class="dot dot-green"></div>
              <span style="font-size: 11px; color: #64748b; margin-left: 8px;">gymcontrol/acesso-sistema</span>
            </div>
            <div class="mockup-preview-screen">
              <div class="mockup-row">
                <span style="font-size: 12px; color: #38bdf8; font-weight: 600;">
                  <i class="fa-solid fa-id-card"></i> Validação de Matrícula
                </span>
                <span class="badge-pill" style="margin: 0; background: #dcfce7; color: #15803d; border: none;">Ativo</span>
              </div>
              <div style="background: #0f172a; padding: 20px; border-radius: 6px; text-align: center;">
                <div style="width: 58px; height: 58px; border-radius: 50%; background: #0284c7; margin: 0 auto 10px auto; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff;">
                  <i class="fa-solid fa-user-check"></i>
                </div>
                <h4 style="color: #fff; font-size: 15px;">Matrícula Regular</h4>
                <p style="color: #94a3b8; font-size: 12px; margin-top: 4px;">Aluno: Lucas Silva • Acesso Permitido</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ==========================================================================
     PILARES REALISTAS DO PROJETO
     ========================================================================== -->
  <section id="sobre" class="section">
    <div class="container">
      <div class="section-header">
        <h2>O que é o GymControl?</h2>
        <p>Um sistema web desenvolvido para resolver o dia a dia da academia com controle de permissões e rotinas transparentes.</p>
      </div>

      <div class="pillars-grid">
        <div class="pillar-card">
          <div class="pillar-icon">
            <i class="fa-solid fa-users-gear"></i>
          </div>
          <h3>Perfis RBAC Sob Medida</h3>
          <p>Visões independentes e seguras para Administradores da unidade, Professores e Alunos, com dados estritamente protegidos por sessão.</p>
        </div>

        <div class="pillar-card">
          <div class="pillar-icon">
            <i class="fa-solid fa-dumbbell"></i>
          </div>
          <h3>Fichas de Treino Dinâmicas</h3>
          <p>O personal trainer prescreve exercícios, repetições e cargas diretamente na ficha do aluno, disponível para consulta no celular.</p>
        </div>

        <div class="pillar-card">
          <div class="pillar-icon">
            <i class="fa-solid fa-comments"></i>
          </div>
          <h3>Comunicação Integrada</h3>
          <p>Mural de mensagens diretas entre o aluno e seu treinador para alinhamento de metas, feedback de intensidade e dúvidas.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ==========================================================================
     RECURSOS E FUNCIONALIDADES
     ========================================================================== -->
  <section id="recursos" class="section features-section">
    <div class="container">
      <div class="section-header">
        <h2>Recursos Práticos da Aplicação</h2>
        <p>Desenvolvido com arquitetura sólida em PHP e MySQL para máxima estabilidade.</p>
      </div>

      <div class="features-grid">
        <div class="feature-item">
          <div class="feature-icon-box"><i class="fa-solid fa-shield-check"></i></div>
          <div class="feature-info">
            <h4>Controle de Status da Matrícula</h4>
            <p>O sistema valida instantaneamente se o aluno está com o status ativo antes de liberar treinos ou permissões.</p>
          </div>
        </div>

        <div class="feature-item">
          <div class="feature-icon-box"><i class="fa-solid fa-clipboard-list"></i></div>
          <div class="feature-info">
            <h4>Gestão de Séries e Exercícios</h4>
            <p>Organização clara de treinos divididos por grupos musculares com atualização em tempo real no banco relacional.</p>
          </div>
        </div>

        <div class="feature-item">
          <div class="feature-icon-box"><i class="fa-solid fa-user-tie"></i></div>
          <div class="feature-info">
            <h4>Painel Operacional do Personal</h4>
            <p>Área focada no professor, filtrando exclusivamente a lista dos alunos sob sua orientação.</p>
          </div>
        </div>

        <div class="feature-item">
          <div class="feature-icon-box"><i class="fa-solid fa-lock"></i></div>
          <div class="feature-info">
            <h4>Autenticação Segura</h4>
            <p>Senhas criptografadas com hash nativo e controle rigoroso de sessões ativas.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ==========================================================================
     PLANOS DE MENSALIDADE DA ACADEMIA
     ========================================================================== -->
  <section id="planos" class="section">
  <div class="container">
    <div class="section-header">
      <span class="badge-pill">PLANOS DE ASSINATURA SAAS</span>
      <h2>Escolha o plano ideal para a sua Academia</h2>
      <p>Contrate a plataforma GymControl para modernizar a sua gestão. Sem taxas sobre as mensalidades dos seus alunos.</p>
    </div>

    <div class="pricing-grid">
      <!-- PLANO STUDIO -->
      <div class="pricing-card">
        <div>
          <h3 class="plan-name">Plano Studio</h3>
          <p class="plan-desc">Ideal para estúdios de personal, boxes pequenos e academias de bairro.</p>
          <div class="plan-price">R$ 89,90 <span>/mês</span></div>
          <ul class="plan-features">
            <li>✓ Até 200 alunos ativos</li>
            <li>✓ 1 Licença de Usuário (Recepção)</li>
            <li>✓ Módulo de Prescrição de Treinos</li>
            <li>✓ Controle Financeiro Básico</li>
            <li>✓ Suporte via E-mail</li>
          </ul>
        </div>
        <button class="btn btn-outline btn-block" onclick="selecionarPlano('Plano Studio')">Contratar para Minha Academia</button>
      </div>

      <!-- PLANO SAFIRA (POPULAR) -->
      <div class="pricing-card popular">
        <span class="popular-badge">Mais Escolhido</span>
        <div>
          <h3 class="plan-name">Plano Safira</h3>
          <p class="plan-desc">A solução completa para academias em crescimento que buscam alta segurança.</p>
          <div class="plan-price">R$ 299,90 <span>/mês</span></div>
          <ul class="plan-features">
            <li>✓ Alunos Ativos Ilimitados</li>
            <li>✓ <strong>Reconhecimento Facial na Catraca</strong></li>
            <li>✓ Personalização da Marca (Multi-tenant)</li>
            <li>✓ Painel de Professores & Alunos</li>
            <li>✓ Gestão de Adimplência Automatizada</li>
            <li>✓ Suporte Prioritário no WhatsApp</li>
          </ul>
        </div>
        <button class="btn btn-primary btn-block" onclick="selecionarPlano('Plano Safira')">Contratar para Minha Academia</button>
      </div>

      <!-- PLANO INFINITY -->
      <div class="pricing-card">
        <div>
          <h3 class="plan-name">Plano Infinity</h3>
          <p class="plan-desc">Para grandes redes de academias, complexos esportivos e multi-unidades.</p>
          <div class="plan-price">R$ 429,90 <span>/mês</span></div>
          <ul class="plan-features">
            <li>✓ Recursos Ilimitados</li>
            <li>✓ Múltiplas Unidades/Academias</li>
            <li>✓ Reconhecimento Facial Ilimitado</li>
            <li>✓ Módulo Avançado de Relatórios & BI</li>
            <li>✓ API de Integração Financeira</li>
            <li>✓ Gerente de Conta Dedicado 24/7</li>
          </ul>
        </div>
        <button class="btn btn-outline btn-block" onclick="selecionarPlano('Plano Infinity')">Contratar para Minha Academia</button>
      </div>
    </div>
  </div>
</section>
  <!-- ==========================================================================
     CONTATO
     ========================================================================== -->
  <section id="contato" class="section">
  <div class="container">
    <div class="contact-box">
      
      <div class="contact-info">
        <span class="badge-pill">FALE COM NOSSO TIME COMERCIAL</span>
        <h3>Modernize a gestão da sua academia com o GymControl</h3>
        <p>Preencha o formulário para agendar uma demonstração personalizada da plataforma, tirar dúvidas sobre planos SaaS e entender como implementar a biometria facial na sua catraca.</p>
        
        <ul style="list-style: none; display: flex; flex-direction: column; gap: 12px; margin-top: 20px;">
          <li>✓ Atendimento consultivo para donos e gestores</li>
          <li>✓ Demonstração prática do painel multi-tenant</li>
          <li>✓ Auxílio na escolha do plano ideal para sua unidade</li>
        </ul>
      </div>

      <form id="formInteresse" class="contact-form-body" onsubmit="enviarInteresse(event)">
        <div class="form-group">
          <label for="nomeAcademia">Nome da Academia ou Estúdio *</label>
          <input type="text" id="nomeAcademia" required placeholder="Ex: FitCenter Copacabana">
        </div>

        <div class="form-group">
          <label for="nomeResponsavel">Seu Nome (Gestor / Proprietário) *</label>
          <input type="text" id="nomeResponsavel" required placeholder="Ex: Nicolas Tavares">
        </div>

        <div class="form-group">
          <label for="emailCorporativo">E-mail de Contato *</label>
          <input type="email" id="emailCorporativo" required placeholder="contato@suaacademia.com.br">
        </div>

        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label for="telefoneWhatsapp">WhatsApp com DDD *</label>
            <input type="tel" id="telefoneWhatsapp" required placeholder="(21) 99999-9999">
          </div>

          <div class="form-group">
            <label for="planoInteresse">Plano de Interesse</label>
            <select id="planoInteresse">
              <option value="Não sei / Quero ajuda">Quero ajuda para escolher</option>
              <option value="Plano Studio">Plano Studio (Até 200 alunos)</option>
              <option value="Plano Safira">Plano Safira (Reconhecimento Facial)</option>
              <option value="Plano Infinity">Plano Infinity (Multi-unidades)</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="qtdAlunos">Estimativa de Alunos Ativos</label>
          <select id="qtdAlunos">
            <option value="Até 100">Até 100 alunos</option>
            <option value="100 a 300">100 a 300 alunos</option>
            <option value="300 a 800">300 a 800 alunos</option>
            <option value="Mais de 800">Mais de 800 alunos</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="height: 48px; font-size: 15px; margin-top: 10px;">
          Solicitar Demonstração do GymControl
        </button>
      </form>

    </div>
  </div>
</section>

  <!-- ==========================================================================
     FOOTER INSTITUCIONAL
     ========================================================================== -->
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <h4>GymControl</h4>
          <p>O sistema definitivo para gestão inteligente, rotinas de treinos e acompanhamento esportivo de alunos e profissionais.</p>
        </div>

        <div class="footer-links">
          <h5>Navegação</h5>
          <ul>
            <li><a href="#sobre">A Plataforma</a></li>
            <li><a href="#recursos">Recursos</a></li>
            <li><a href="#planos">Planos & Mensalidades</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h5>Acesso Rápido</h5>
          <ul>
            <li><a href="login.php">Área do Aluno</a></li>
            <li><a href="login.php">Área do Professor</a></li>
            <li><a href="login.php">Área Administrativa</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h5>Acadêmico</h5>
          <ul>
            <li><a href="#">UNISUAM — 2026</a></li>
            <li><a href="#">Módulo de Back-end</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; 2026 GymControl. Todos os direitos reservados. Projeto Acadêmico.</p>
      </div>
    </div>
  </footer>

  <script>
    function enviarInteresse(e) {
  e.preventDefault();
  
  const campoAcademia = document.getElementById('nomeAcademia');
  const campoResponsavel = document.getElementById('nomeResponsavel');
  
  const academia = campoAcademia && campoAcademia.value ? campoAcademia.value : 'sua academia';
  const responsavel = campoResponsavel && campoResponsavel.value ? campoResponsavel.value : 'Gestor';
  
  alert(`Obrigado pelo contato, ${responsavel}! Nossa equipe comercial entrará em contato em breve para apresentar a plataforma GymControl para a ${academia}.`);
  
  const form = document.getElementById('formInteresse');
  if (form) {
    form.reset();
  }
}

    function selecionarPlano(plano) {
      const campoPlano = document.getElementById('planoInteresse');
      if (campoPlano) {
        campoPlano.value = plano;
        campoPlano.scrollIntoView({ behavior: 'smooth' });
        campoPlano.focus();
      }
    }
  </script>
</body>
</html>