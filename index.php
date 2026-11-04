<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl — O Sistema Integrado com Reconhecimento Facial para Academias</title>
  
  <!-- Fonts & FontAwesome -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    /* ==========================================================================
       1. VARIAVEIS CSS & RESET
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
       5. SEÇÃO: O QUE É O GYMCONTROL (3 PILARES)
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
       6. SEÇÃO: RECURSOS & FUNCIONALIDADES
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
       7. SEÇÃO DE PLANOS & VALORES
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
       8. SEÇÃO FORM DE INTERESSE
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

    /* RESPONSIVIDADE */
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
     NAVBAR FIXA
     ========================================================================== -->
  <header class="navbar">
    <div class="container">
      <div class="nav-wrapper">
        <a href="#" class="logo">
          <div class="logo-badge">GC</div>
          <span>GymControl</span>
        </a>

        <ul class="nav-links">
          <li><a href="#inicio">Início</a></li>
          <li><a href="#sobre">O que é?</a></li>
          <li><a href="#recursos">Recursos</a></li>
          <li><a href="#planos">Valores & Planos</a></li>
          <li><a href="#contato">Contato</a></li>
        </ul>

        <div class="nav-cta">
          <button class="btn btn-outline" onclick="abrirModalDemo()">Demonstração</button>
          <a href="#contato" class="btn btn-accent">Assinar Agora</a>
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
            <i class="fa-solid fa-sparkles"></i> Nova Plataforma SaaS Multi-tenant
          </div>
          <h1>O Software de Gestão com <span>Reconhecimento Facial</span> para sua Academia</h1>
          <p>
            O GymControl entrega o que os sistemas tradicionais prometem e vai além: segurança com biometria facial na catraca, personalização total com a sua marca e controle financeiro completo.
          </p>
          <div class="hero-btns">
            <a href="#planos" class="btn btn-primary btn-lg">
              <i class="fa-solid fa-rocket"></i> Ver Planos & Assinar
            </a>
            <a href="#sobre" class="btn btn-outline">Conhecer a Plataforma</a>
          </div>
        </div>

        <div class="hero-mockup">
          <div class="mockup-card">
            <div class="mockup-header">
              <div class="dot dot-red"></div>
              <div class="dot dot-yellow"></div>
              <div class="dot dot-green"></div>
              <span style="font-size: 11px; color: #64748b; margin-left: 8px;">gymcontrol.io/totem-catraca</span>
            </div>
            <div class="mockup-preview-screen">
              <div class="mockup-row">
                <span style="font-size: 12px; color: #38bdf8; font-weight: 600;">
                  <i class="fa-solid fa-camera"></i> Reconhecimento Facial Ativo
                </span>
                <span class="badge-pill" style="margin: 0; background: #dcfce7; color: #15803d; border: none;">Online</span>
              </div>
              <div style="background: #0f172a; padding: 20px; border-radius: 6px; text-align: center;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: #0284c7; margin: 0 auto 10px auto; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #fff;">
                  <i class="fa-solid fa-user-check"></i>
                </div>
                <h4 style="color: #fff; font-size: 15px;">Acesso Liberado!</h4>
                <p style="color: #94a3b8; font-size: 12px; margin-top: 4px;">Aluno: Rodrigo Nogueira • Plano VIP</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ==========================================================================
     SEÇÃO: O QUE É O GYMCONTROL? (3 PILARES)
     ========================================================================== -->
  <section id="sobre" class="section">
    <div class="container">
      <div class="section-header">
        <h2>O que é o GymControl?</h2>
        <p>Um ecossistema moderno para gestão de academias, pensado para substituir sistemas antigos por uma solução mais rápida, inteligente e personalizável.</p>
      </div>

      <div class="pillars-grid">
        <div class="pillar-card">
          <div class="pillar-icon">
            <i class="fa-solid fa-palette"></i>
          </div>
          <h3>Arquitetura Multi-Tenant</h3>
          <p>Sua academia é única. O GymControl permite que você personalize a plataforma inteira com o seu nome, seu logotipo e as suas cores institucionais.</p>
        </div>

        <div class="pillar-card">
          <div class="pillar-icon">
            <i class="fa-solid fa-face-smile"></i>
          </div>
          <h3>Reconhecimento Facial Integrado</h3>
          <p>Esqueça cartões ou biometria digital que falha. O sistema valida automaticamente o acesso na catraca verificando foto, matrícula e pagamento em tempo real.</p>
        </div>

        <div class="pillar-card">
          <div class="pillar-icon">
            <i class="fa-solid fa-users-gear"></i>
          </div>
          <h3>Gestão por Níveis de Acesso (RBAC)</h3>
          <p>Interfaces operacionais sob medida para Administradores, Recepcionistas no balcão, Professores prescrevendo treinos e Alunos.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ==========================================================================
     SEÇÃO: SOLUÇÕES & RECURSOS
     ========================================================================== -->
  <section id="recursos" class="section features-section">
    <div class="container">
      <div class="section-header">
        <h2>Tudo o que sua academia precisa para crescer</h2>
        <p>Desenvolvido para eliminar filas no balcão e garantir zero inadimplência.</p>
      </div>

      <div class="features-grid">
        <div class="feature-item">
          <div class="feature-icon-box"><i class="fa-solid fa-credit-card"></i></div>
          <div class="feature-info">
            <h4>Controle Financeiro & Inadimplência</h4>
            <p>Monitore mensalidades em dia, receba alertas de pendências automaticamente e bloqueie acessos indevidos na catraca.</p>
          </div>
        </div>

        <div class="feature-item">
          <div class="feature-icon-box"><i class="fa-solid fa-dumbbell"></i></div>
          <div class="feature-info">
            <h4>Fichas de Treino Dinâmicas</h4>
            <p>Professores criam e atualizam treinos com séries, cargas e repetições de forma ágil para consulta instantânea.</p>
          </div>
        </div>

        <div class="feature-item">
          <div class="feature-icon-box"><i class="fa-solid fa-address-card"></i></div>
          <div class="feature-info">
            <h4>Balcão de Recepção Ágil</h4>
            <p>Cadastre alunos em segundos capturando a foto direta da webcam e ativando a biometria sem complicações.</p>
          </div>
        </div>

        <div class="feature-item">
          <div class="feature-icon-box"><i class="fa-solid fa-chart-pie"></i></div>
          <div class="feature-info">
            <h4>Relatórios & Métricas Operacionais</h4>
            <p>Acompanhe o faturamento, taxa de ocupação por horário e quantidade de acessos diários em tempo real.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ==========================================================================
     SEÇÃO: VALORES & PLANOS DE ASSINATURA
     ========================================================================== -->
  <section id="planos" class="section">
    <div class="container">
      <div class="section-header">
        <h2>Planos e Valores Transparentes</h2>
        <p>Escolha o plano ideal para o tamanho da sua academia. Sem taxa de fidelidade nem letras miúdas.</p>
      </div>

      <div class="pricing-grid">
        <!-- PLANO STARTER -->
        <div class="pricing-card">
          <div>
            <div class="plan-name">Starter</div>
            <div class="plan-desc">Ideal para estúdios, boxes de crossfit ou pequenas academias.</div>
            <div class="plan-price">R$ 89,90 <span>/mês</span></div>

            <ul class="plan-features">
              <li><i class="fa-solid fa-check"></i> Até 150 Alunos Ativos</li>
              <li><i class="fa-solid fa-check"></i> Gestão Financeira Completa</li>
              <li><i class="fa-solid fa-check"></i> Prescrição de Treinos</li>
              <li><i class="fa-solid fa-check"></i> 2 Contas de Operador</li>
            </ul>
          </div>
          <a href="#contato" class="btn btn-primary" onclick="selecionarPlano('Starter')">Assinar Starter</a>
        </div>

        <!-- PLANO PRO -->
        <div class="pricing-card popular">
          <div class="popular-badge">Mais Popular</div>
          <div>
            <div class="plan-name">Pro Facial</div>
            <div class="plan-desc">Para academias de médio porte que buscam automação e controle com biometria.</div>
            <div class="plan-price">R$ 299,90 <span>/mês</span></div>

            <ul class="plan-features">
              <li><i class="fa-solid fa-check"></i> Alunos Ilimitados</li>
              <li><i class="fa-solid fa-check"></i> <strong>Reconhecimento Facial Integrado</strong></li>
              <li><i class="fa-solid fa-check"></i> <strong>Identidade Visual Customizada (Multi-tenant)</strong></li>
              <li><i class="fa-solid fa-check"></i> Suporte Prioritário WhatsApp</li>
            </ul>
          </div>
          <a href="#contato" class="btn btn-accent" onclick="selecionarPlano('Pro Facial')">Assinar Pro Facial</a>
        </div>

        <!-- PLANO ENTERPRISE -->
        <div class="pricing-card">
          <div>
            <div class="plan-name">Enterprise</div>
            <div class="plan-desc">Para redes de academias com múltiplas unidades e alta demanda.</div>
            <div class="plan-price">R$ 499,90 <span>/mês</span></div>

            <ul class="plan-features">
              <li><i class="fa-solid fa-check"></i> Multi-Unidades & Redes</li>
              <li><i class="fa-solid fa-check"></i> Reconhecimento Facial Ilimitado</li>
              <li><i class="fa-solid fa-check"></i> API aberta para integrações</li>
              <li><i class="fa-solid fa-check"></i> Gerente de Conta Dedicado</li>
            </ul>
          </div>
          <a href="#contato" class="btn btn-primary" onclick="selecionarPlano('Enterprise')">Falar com Consultor</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ==========================================================================
     SEÇÃO FORMULÁRIO DE INTERESSE (LEAD CAPTURE)
     ========================================================================== -->
  <section id="contato" class="section" style="padding-top: 0;">
    <div class="container">
      <div class="contact-box">
        <div class="contact-info">
          <h3>Pronto para transformar a gestão da sua academia?</h3>
          <p>Preencha os dados ao lado para receber um contato da nossa equipe ou agendar uma demonstração guiada do GymControl.</p>
          
          <div style="display: flex; flex-direction: column; gap: 14px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-circle-check" style="color: var(--cor-accent);"></i>
              <span>Teste grátis por 14 dias sem compromisso</span>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-circle-check" style="color: var(--cor-accent);"></i>
              <span>Migração simples dos seus dados atuais</span>
            </div>
          </div>
        </div>

        <form id="formInteresse" onsubmit="enviarInteresse(event)">
          <div class="form-group">
            <label>Nome da Academia *</label>
            <input type="text" id="nomeAcademia" required placeholder="Ex: FitCenter Copacabana">
          </div>

          <div class="form-group">
            <label>Seu Nome Completo *</label>
            <input type="text" id="nomeContato" required placeholder="Digite seu nome">
          </div>

          <div class="form-group">
            <label>E-mail Corporativo / Principal *</label>
            <input type="email" id="emailContato" required placeholder="seuemail@academia.com.br">
          </div>

          <div class="form-group">
            <label>WhatsApp / Telefone *</label>
            <input type="tel" id="telefoneContato" required placeholder="(21) 99999-9999">
          </div>

          <div class="form-group">
            <label>Plano de Interesse</label>
            <select id="planoInteresse">
              <option value="Pro Facial">Plano Pro Facial (Recomendado)</option>
              <option value="Starter">Plano Starter</option>
              <option value="Enterprise">Plano Enterprise</option>
            </select>
          </div>

          <button type="submit" class="btn btn-accent" style="width: 100%; margin-top: 10px;">
            <i class="fa-solid fa-paper-plane"></i> Solicitar Contato / Assinar
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
          <p>O sistema definitivo para gestão inteligente, segurança por biometria e controle total para academias de todos os portes.</p>
        </div>

        <div class="footer-links">
          <h5>Plataforma</h5>
          <ul>
            <li><a href="#sobre">O que é</a></li>
            <li><a href="#recursos">Recursos</a></li>
            <li><a href="#planos">Planos & Preços</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h5>Recursos</h5>
          <ul>
            <li><a href="#inicio">Reconhecimento Facial</a></li>
            <li><a href="#sobre">Arquitetura Multi-tenant</a></li>
            <li><a href="#recursos">Controle Financeiro</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h5>Suporte</h5>
          <ul>
            <li><a href="#contato">Falar com Consultor</a></li>
            <li><a href="#contato">Central de Ajuda</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; 2026 GymControl. Todos os direitos reservados. Centro Universitário Augusto Motta (UNISUAM).</p>
      </div>
    </div>
  </footer>

  <script>
    function selecionarPlano(nomePlano) {
      document.getElementById('planoInteresse').value = nomePlano;
    }

    function enviarInteresse(e) {
      e.preventDefault();
      const academia = document.getElementById('nomeAcademia').value;
      alert(`Obrigado pelo interesse, ${academia}! Nossa equipe entrará em contato em breve para liberar seu acesso ao GymControl.`);
      document.getElementById('formInteresse').reset();
    }

    function abrirModalDemo() {
      alert("Para testar o GymControl, role a página até a seção de Planos e selecione 'Assinar' ou preencha o formulário de contato!");
    }
  </script>
</body>
</html>