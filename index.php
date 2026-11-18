<?php
require_once __DIR__ . '/config/auth.php';
$logado = !empty($_SESSION['usuario_id']) && !empty($_SESSION['usuario_nivel']);
$painelUrl = $logado ? getRotaPrincipalPorNivel($_SESSION['usuario_nivel']) : 'login.php';
$usuarioNome = $logado ? ($_SESSION['usuario_nome'] ?? '') : '';

// O formulário público grava a solicitação; não promete e-mail nem retorno automático.
if (empty($_SESSION['csrf_contato'])) $_SESSION['csrf_contato'] = bin2hex(random_bytes(32));
$erroInteresse = '';
$dadosInteresse = [
    'nome_academia' => '', 'responsavel' => '', 'email' => '',
    'telefone' => '', 'plano' => 'Não sei / Quero ajuda', 'qtd_alunos' => 'Até 100'
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'interesse') {
    foreach ($dadosInteresse as $campo => $padrao) {
        $dadosInteresse[$campo] = trim((string)($_POST[$campo] ?? $padrao));
    }
}
function gcCampo($valor) { return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8'); }

$sucessoInteresse = !empty($_SESSION['contato_registrado']);
unset($_SESSION['contato_registrado']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'interesse') {
    $nomeAcademia = trim((string) ($_POST['nome_academia'] ?? ''));
    $responsavel = trim((string) ($_POST['responsavel'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $telefone = trim((string) ($_POST['telefone'] ?? ''));
    $plano = trim((string) ($_POST['plano'] ?? ''));
    $quantidade = trim((string) ($_POST['qtd_alunos'] ?? ''));
    if (!hash_equals($_SESSION['csrf_contato'], (string) ($_POST['csrf_token'] ?? ''))) {
        $erroInteresse = 'Formulário inválido. Atualize a página.';
    } elseif (mb_strlen($nomeAcademia) < 2 || mb_strlen($nomeAcademia) > 150 ||
        mb_strlen($responsavel) < 2 || mb_strlen($responsavel) > 120 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150 ||
        !in_array(strlen(preg_replace('/\D+/','',$telefone)), [10,11], true) ||
        !in_array($plano,['Não sei / Quero ajuda','Plano Studio','Plano Safira','Plano Infinity'],true) ||
        !in_array($quantidade,['Até 100','100 a 300','300 a 800','Mais de 800'],true)) {
        $erroInteresse = 'Confira os dados de contato e o plano selecionado.';
    } elseif (($_SESSION['ultimo_contato_em'] ?? 0) > time() - 300) {
        $erroInteresse = 'Você enviou uma solicitação recentemente. Aguarde alguns minutos antes de enviar outra.';
    } else {
        require_once __DIR__ . '/config/conexao.php';
        try {
            $stmt = $pdo->prepare('INSERT INTO contatos_interesse (nome_academia,responsavel,email,telefone,plano_interesse,estimativa_alunos) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$nomeAcademia,$responsavel,$email,$telefone,$plano,$quantidade]);
            $_SESSION['ultimo_contato_em'] = time();
            $_SESSION['contato_registrado'] = true;
            header('Location: index.php?interesse=registrado#contato');
            exit;
        } catch (PDOException $e) {
            error_log('Erro ao salvar interesse: ' . $e->getMessage());
            $erroInteresse = 'Não foi possível registrar a solicitação agora.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0f172a">
  <meta name="description" content="GymControl: gestão de academias, treinos e equipes em uma plataforma integrada. Conheça os planos e solicite sua adesão.">
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
        .btn-block {
      width: 100%;
    }

       .pricing-card .btn-outline {
      background: transparent;
      border: 1.5px solid var(--cor-principal);
      color: var(--cor-principal);
    }

       .pricing-card .btn-outline:hover {
      background: var(--cor-principal);
      border-color: var(--cor-principal);
      color: #fff;
      box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
    }
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

/* ===== GYMCONTROL / LANDING DESIGN SYSTEM 2026 ===== */
:root { --radius: 16px; --shadow-lg: 0 25px 75px rgba(15,23,42,.13); }
html { scroll-padding-top: 90px; }
body { overflow-x: hidden; }
::selection { background: #0284c7; color: white; }
:focus-visible { outline: 3px solid #38bdf8; outline-offset: 4px; }
.container { max-width: 1240px; }
.skip-link {position:fixed;z-index:2000;top:-100px;left:18px;background:#fff;padding:12px 18px;border-radius:12px;color:#0f172a;}
.skip-link:focus {top:12px;}
.scroll-meter {position:fixed;z-index:1200;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#0284c7,#10b981);pointer-events:none;}
.navbar {height:80px;background:rgba(9,17,34,.84);border-bottom:1px solid rgba(255,255,255,.09);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);transition:height .3s,background .3s,box-shadow .3s;}
.navbar.is-scrolled {height:68px;background:rgba(9,17,34,.96);box-shadow:0 12px 36px rgba(2,6,23,.18);}
.logo {letter-spacing:-.7px;font-size:22px;}
.logo-badge {border-radius:12px;transform:rotate(-8deg);box-shadow:0 6px 22px rgba(2,132,199,.33);}
.logo-badge::first-letter {color:#fff;}
.nav-links {gap:22px;}
.nav-links a {position:relative;font-size:13px;color:#cbd5e1;}
.nav-links a:after {content:'';position:absolute;left:0;bottom:-8px;width:0;height:2px;background:#38bdf8;transition:width .3s;}
.nav-links a:hover:after,.nav-links a.is-current:after {width:100%;}
.nav-actions {display:flex;align-items:center;gap:10px;}
.menu-toggle {display:none;background:none;border:1px solid rgba(255,255,255,.22);border-radius:12px;padding:10px;cursor:pointer;}
.menu-toggle span {display:block;width:21px;height:2px;margin:4px 0;background:white;border-radius:5px;transition:transform .25s,opacity .25s;}
.btn {position:relative;isolation:isolate;overflow:hidden;border-radius:12px;min-height:44px;transition:transform .28s cubic-bezier(.2,.8,.2,1),box-shadow .28s,background .28s,border .28s;}
.btn:before {content:'';position:absolute;inset:0;left:-130%;background:linear-gradient(110deg,transparent 20%,rgba(255,255,255,.18) 50%,transparent 80%);transform:skewX(-18deg);transition:left .65s;z-index:-1;}
.btn:hover {transform:translateY(-3px);}
.btn:hover:before {left:150%;}
.btn-primary {background:linear-gradient(125deg,#0284c7,#0369a1);box-shadow:0 10px 28px rgba(2,132,199,.25);}
.btn-primary:hover {box-shadow:0 15px 32px rgba(2,132,199,.35);}
.hero {min-height:720px;display:flex;align-items:center;padding:155px 0 110px;background:radial-gradient(ellipse at 75% 22%,rgba(2,132,199,.19),transparent 38%),linear-gradient(145deg,#080f1d 0%,#0f172a 53%,#17263c 100%);isolation:isolate;}
.hero-grid {grid-template-columns:1.03fr .97fr;gap:60px;position:relative;z-index:2;}
.hero-glow {position:absolute;border-radius:50%;filter:blur(45px);pointer-events:none;z-index:-1;opacity:.5;animation:glowShift 13s ease-in-out infinite alternate;}
.hero-glow-one {width:410px;height:410px;background:radial-gradient(circle,rgba(2,132,199,.36),transparent 70%);top:3%;right:8%;}
.hero-glow-two {width:370px;height:370px;background:radial-gradient(circle,rgba(16,185,129,.2),transparent 70%);bottom:2%;left:1%;animation-delay:-6s;}
.hero-gridlines {position:absolute;inset:0;background-image:linear-gradient(rgba(148,163,184,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(148,163,184,.035) 1px,transparent 1px);background-size:62px 62px;mask-image:linear-gradient(to right,transparent,#000);pointer-events:none;z-index:-1;}
.hero-text {animation:heroEnter .85s .1s both;}
.hero-text h1 {font-size:clamp(40px,4.35vw,66px);line-height:1.09;letter-spacing:-2.5px;margin:10px 0 23px;max-width:680px;}
.hero-text h1 span {background:linear-gradient(95deg,#38bdf8,#7dd3fc 45%,#34d399);background-size:200% 200%;-webkit-background-clip:text;background-clip:text;animation:gradientDrift 8s ease infinite;}
.hero-text p {font-size:17px;line-height:1.85;max-width:545px;margin-bottom:29px;color:#a9bad0;}
.hero-btns {flex-wrap:wrap;gap:12px;}
.hero-btns .btn {padding:14px 22px;}
.hero .badge-pill {background:rgba(56,189,248,.09);border-color:rgba(56,189,248,.23);padding:8px 14px;color:#d7edfb;letter-spacing:.2px;}
.live-dot {display:inline-block;width:7px;height:7px;background:#34d399;border-radius:50%;box-shadow:0 0 0 5px rgba(52,211,153,.11);animation:livePulse 2.1s infinite;}
.hero-trust {display:flex;align-items:center;flex-wrap:wrap;gap:10px 21px;margin-top:35px;color:#9caec2;font-size:12px;}
.hero-trust i {color:#34d399;margin-right:6px;}
.hero-mockup {position:relative;animation:heroEnter .9s .3s both;transform-style:preserve-3d;perspective:900px;}
.mockup-card {padding:9px;background:#111a2c;border-radius:20px;border:1px solid rgba(148,163,184,.24);box-shadow:0 30px 90px rgba(0,0,0,.46),0 0 70px rgba(2,132,199,.12);animation:floatSlow 7s ease-in-out infinite;will-change:transform;}
.mockup-header {padding:12px 16px 15px;margin-bottom:0;border-bottom:1px solid rgba(255,255,255,.06);}
.dot {width:9px;height:9px;}
.mockup-url {font-size:10px;color:#94a3b8;margin-left:auto;}
.mockup-url i {margin-right:5px;}
.mockup-dashboard {display:grid;grid-template-columns:48px 1fr;background:#0c1525;border-radius:0 0 12px 12px;min-height:328px;overflow:hidden;}
.dash-sidebar {background:#111c2f;border-right:1px solid rgba(255,255,255,.055);display:flex;align-items:center;flex-direction:column;gap:21px;padding:16px 5px;}
.dash-sidebar i {color:#5b6c84;font-size:13px;}
.dash-sidebar i.active {color:#38bdf8;}
.dash-symbol {background:linear-gradient(135deg,#0284c7,#10b981);width:28px;height:28px;display:grid;place-items:center;border-radius:9px;color:white;font-size:9px;font-weight:800;}
.dash-main {min-width:0;padding:20px;}
.dash-topline {display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.dash-topline div:first-child {display:flex;flex-direction:column;gap:2px;}
.dash-topline small {font-size:9px;color:#38bdf8;letter-spacing:1.4px;}
.dash-topline strong {font-size:16px;color:#f8fafc;letter-spacing:-.5px;}
.dash-avatar {border:1px solid #34435b;color:#94a3b8;width:31px;height:31px;border-radius:50%;display:grid;place-items:center;}
.dash-metrics {display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.dash-metric {background:#18243a;border:1px solid #293750;border-radius:11px;padding:14px;display:flex;flex-direction:column;gap:6px;}
.dash-metric span {font-size:11px;color:#9cafc5;}
.dash-metric strong {font-size:20px;color:#38bdf8;line-height:1;}
.dash-metric:nth-child(2) strong {color:#34d399;}
.dash-metric small {font-size:9px;color:#72859e;}
.dash-chart {border-radius:11px;border:1px solid #27374e;background:#111d31;margin-top:11px;padding:15px 15px 10px;}
.dash-chart-title {display:flex;justify-content:space-between;gap:6px;align-items:center;}
.dash-chart-title strong {font-size:11px;color:#d9e7f8;}
.dash-chart-title span {font-size:9px;color:#6f8ba9;}
.dash-bars {height:86px;display:flex;align-items:flex-end;gap:9px;margin-top:14px;border-bottom:1px solid #344155;}
.dash-bars span {flex:1;height:var(--h);min-width:0;border-radius:5px 5px 0 0;background:linear-gradient(180deg,#38bdf8,#075985);animation:barRise 1.25s both;transform-origin:bottom;}
.dash-bars span:nth-child(3n) {background:linear-gradient(180deg,#34d399,#047857);}
.dash-bars span:nth-child(2n) {animation-delay:.16s;}
.dash-bottom {display:flex;align-items:center;justify-content:space-between;margin-top:12px;color:#a5b8cb;font-size:10px;}
.dash-bottom i {color:#34d399;}
.floating-tag {position:absolute;z-index:3;background:rgba(18,32,52,.95);backdrop-filter:blur(15px);border:1px solid rgba(125,211,252,.25);box-shadow:0 12px 30px rgba(0,0,0,.25);border-radius:12px;padding:12px 15px;color:#e2e8f0;font-size:11px;font-weight:600;white-space:nowrap;animation:floatTag 5s ease-in-out infinite;}
.floating-tag i {color:#34d399;margin-right:7px;}
.floating-tag-top {top:-26px;right:-20px;}
.floating-tag-bottom {bottom:-22px;left:-30px;animation-delay:-2.5s;}
.section {padding:112px 0;position:relative;}
.section-header {max-width:750px;margin-bottom:56px;}
.section-header h2 {font-size:clamp(30px,3vw,44px);letter-spacing:-1.6px;line-height:1.15;margin-bottom:16px;}
.section-header p {line-height:1.8;}
#sobre {background:radial-gradient(circle at 5% 10%,rgba(2,132,199,.055),transparent 35%),#f8fafc;}
.pillars-grid,.pricing-grid {gap:20px;}
.pillar-card {border-radius:20px;padding:34px 28px;gap:18px;box-shadow:0 15px 40px rgba(15,23,42,.035);transition:transform .35s cubic-bezier(.2,.8,.2,1),box-shadow .35s,border .35s;}
.pillar-card:hover {transform:translateY(-9px);box-shadow:0 24px 55px rgba(15,23,42,.1);}
.pillar-icon {width:58px;height:58px;border-radius:17px;background:linear-gradient(135deg,#e0f2fe,#dbeafe);font-size:23px;}
.pillar-card h3 {letter-spacing:-.55px;}
.pillar-card p {line-height:1.8;}
.features-section {background:#fff;}
.features-grid {gap:20px;}
.feature-item {border:1px solid #e2e8f0;border-radius:18px;padding:25px;background:#fff;transition:transform .3s,box-shadow .3s,border .3s;}
.feature-item:hover {transform:translateY(-5px);box-shadow:0 16px 36px rgba(15,23,42,.065);border-color:#bae6fd;}
.feature-icon-box {height:50px;width:50px;border-radius:14px;background:#e0f2fe;}
.feature-info p {line-height:1.75;}
#planos {background:linear-gradient(#f8fafc,#f1f5f9);}
.pricing-card {border-radius:22px;padding:35px 27px;box-shadow:0 12px 34px rgba(15,23,42,.04);transition:transform .32s,box-shadow .32s,border .32s;}
.pricing-card:hover {transform:translateY(-10px);box-shadow:0 25px 55px rgba(15,23,42,.12);}
.pricing-card.popular {transform:translateY(-8px);box-shadow:0 20px 55px rgba(2,132,199,.13);}
.pricing-card.popular:hover {transform:translateY(-15px);}
.popular-badge {padding:7px 17px;letter-spacing:.8px;border-radius:99px;}
.plan-price {letter-spacing:-1px;}
.plan-features li {font-size:13px;line-height:1.5;align-items:flex-start;}
.pricing-card .btn {margin-top:5px;}
#contato {background:#f8fafc;}
.contact-box {border-radius:28px;padding:clamp(26px,5vw,65px);gap:50px;position:relative;overflow:hidden;box-shadow:0 30px 70px rgba(15,23,42,.15);background:radial-gradient(circle at 0% 100%,rgba(16,185,129,.11),transparent 34%),linear-gradient(135deg,#0b1526,#172943);}
.contact-box:before {content:'';width:330px;height:330px;position:absolute;right:-140px;top:-150px;border-radius:50%;background:rgba(2,132,199,.12);filter:blur(12px);pointer-events:none;}
.contact-highlight {display:inline-flex;gap:8px;align-items:center;color:#7dd3fc;font-size:11px;font-weight:800;letter-spacing:1.1px;margin-bottom:26px;}
.contact-info h3 {font-size:clamp(29px,2.7vw,42px);line-height:1.2;letter-spacing:-1.2px;}
.contact-info p {line-height:1.8;}
.contact-info li {color:#c8d6e6;font-size:13px;}
.contact-form-body {position:relative;background:rgba(255,255,255,.065);padding:28px;border:1px solid rgba(255,255,255,.12);border-radius:22px;box-shadow:0 20px 55px rgba(0,0,0,.1);}
.form-group {margin-bottom:17px;}
.form-group label {text-transform:none;font-size:12px;letter-spacing:.1px;color:#d8e5f4;}
.form-group input,.form-group select {min-height:49px;width:100%;background:rgba(7,15,28,.62);border:1px solid rgba(148,163,184,.25);border-radius:11px;color:#fff;transition:border .2s,box-shadow .2s,background .2s;}
.form-group input::placeholder {color:#7386a0;}
.form-group input:focus,.form-group select:focus {border-color:#38bdf8;box-shadow:0 0 0 3px rgba(56,189,248,.13);background:#0b1526;}
.contact-form-body .btn {min-height:52px;}
.contact-form-body>p {color:#a5b4c8!important;line-height:1.7;}
.form-alert {display:flex;gap:10px;align-items:flex-start;padding:13px 15px;border-radius:12px;font-size:13px;margin-bottom:18px;line-height:1.6;}
.form-alert.success {background:rgba(16,185,129,.14);border:1px solid rgba(16,185,129,.35);color:#b4f7d3;}
.form-alert.error {background:rgba(248,113,113,.13);border:1px solid rgba(248,113,113,.35);color:#fecaca;}
.footer {padding-top:75px;background:#0b1322;}
.footer-brand h4 {font-size:22px;letter-spacing:-.8px;}
.footer-links a:hover {color:#38bdf8;}
.footer-bottom {padding-top:26px;}
[data-reveal] {opacity:0;transform:translateY(36px);transition:opacity .72s ease,transform .72s cubic-bezier(.2,.8,.2,1);transition-delay:var(--reveal-delay,0ms);}
[data-reveal].is-visible {opacity:1;transform:translateY(0);}
@keyframes heroEnter {from {opacity:0;transform:translateY(26px)} to {opacity:1;transform:translateY(0)}}
@keyframes floatSlow {0%,100% {transform:translateY(0) rotate(-1deg)} 50% {transform:translateY(-12px) rotate(.5deg)}}
@keyframes floatTag {0%,100% {transform:translateY(0)} 50% {transform:translateY(-9px)}}
@keyframes glowShift {to {transform:translate(40px,-38px) scale(1.15)}}
@keyframes livePulse {50% {box-shadow:0 0 0 9px rgba(52,211,153,0)}}
@keyframes gradientDrift {0%,100% {background-position:0% 50%} 50% {background-position:100% 50%}}
@keyframes barRise {from {transform:scaleY(.1);opacity:.3} to {transform:scaleY(1);opacity:1}}
@media(max-width:1100px){.nav-links {gap:13px;}.nav-links a {font-size:12px;}.hero-grid {gap:30px;}.hero-text h1 {font-size:clamp(36px,4vw,52px);}}
@media(max-width:992px){.navbar {height:74px;}.nav-wrapper {position:relative;}.menu-toggle {display:block;}.nav-links {display:none;position:absolute;top:calc(100% + 17px);left:0;right:0;flex-direction:column;align-items:stretch;gap:0;background:#0f172a;border:1px solid #334155;border-radius:15px;padding:10px;box-shadow:0 20px 50px rgba(0,0,0,.25);}.nav-links.is-open {display:flex;}.nav-links a {display:block;padding:12px;font-size:14px;}.nav-links a:after {display:none;}.hero-grid {grid-template-columns:1fr;gap:70px;}.hero {padding:140px 0 95px;}.hero-text {text-align:center;}.hero-text p {margin-left:auto;margin-right:auto;}.hero-btns,.hero-trust {justify-content:center;}.hero-mockup {max-width:580px;width:90%;margin:0 auto;}.pillars-grid,.pricing-grid {grid-template-columns:repeat(2,minmax(0,1fr));}.pricing-card.popular {transform:none;}.pricing-card.popular:hover {transform:translateY(-7px);}.features-grid {grid-template-columns:repeat(2,minmax(0,1fr));}.contact-box {grid-template-columns:1fr;gap:28px;}.footer-grid {grid-template-columns:repeat(2,1fr);}}
@media(max-width:650px){.container {padding:0 19px;}.navbar {height:70px;}.navbar.is-scrolled {height:66px;}.logo {font-size:19px;gap:7px;}.logo-badge {width:33px;height:33px;font-size:14px;}.nav-cta .btn {font-size:0;gap:0;padding:10px 12px;}.nav-cta .btn i {font-size:15px;}.nav-cta {gap:5px;}.hero {min-height:auto;padding:126px 0 92px;}.hero-text h1 {font-size:clamp(35px,8.6vw,50px);letter-spacing:-1.7px;}.hero-text p {font-size:15px;line-height:1.75;}.hero-trust {font-size:10px;gap:10px;}.hero-btns .btn {font-size:13px;}.hero-mockup {width:100%;}.floating-tag-top {right:-4px;top:-26px;}.floating-tag-bottom {left:0;bottom:-20px;}.floating-tag {font-size:9px;padding:9px;}.dash-main {padding:12px;}.dash-topline strong {font-size:12px;}.dash-metric {padding:9px;}.dash-chart {padding:11px;}.dash-metric small {font-size:8px;}.section {padding:75px 0;}.section-header {margin-bottom:35px;}.pillars-grid,.pricing-grid,.features-grid,.footer-grid {grid-template-columns:1fr;}.feature-item {padding:21px;}.pricing-card {padding:30px 25px;}.contact-box {padding:24px 16px;border-radius:19px;}.contact-form-body {padding:20px 15px;}.form-row {grid-template-columns:1fr!important;gap:0!important;}.contact-info h3 {font-size:29px;}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after {animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important;}[data-reveal] {opacity:1!important;transform:none!important;}.mockup-card {transform:none!important;}}

  </style>
</head>
<body>
  <div class="scroll-meter" aria-hidden="true"></div>
  <a class="skip-link" href="#inicio">Ir para o conteúdo principal</a>

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

        <ul class="nav-links" id="mainNav">
          <li><a href="#inicio">Início</a></li>
          <li><a href="#sobre">A Plataforma</a></li>
          <li><a href="#recursos">Recursos</a></li>
          <li><a href="#planos">Planos</a></li>
          <li><a href="#contato">Fale Conosco</a></li>
        </ul>

        <div class="nav-actions"><div class="nav-cta">
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
        <button type="button" class="menu-toggle" aria-label="Abrir menu de navegação" aria-controls="mainNav" aria-expanded="false"><span></span><span></span><span></span></button>
        </div>
      </div>
    </div>
  </header>

  <!-- ==========================================================================
     HERO SECTION
     ========================================================================== -->
  <section id="inicio" class="hero">
    <div class="hero-glow hero-glow-one" aria-hidden="true"></div><div class="hero-glow hero-glow-two" aria-hidden="true"></div>
    <div class="hero-gridlines" aria-hidden="true"></div>
    <div class="container">
      <div class="hero-grid">
        <div class="hero-text">
          <div class="badge-pill">
            <span class="live-dot"></span> A plataforma para uma operação mais inteligente
          </div>
          <h1>Sua academia no controle. <span>Seu crescimento em movimento.</span></h1>
          <p>
            Uma experiência de gestão feita para conectar sua equipe, organizar os treinos e simplificar o dia a dia da sua academia — tudo em um só lugar.
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
              <a href="#contato" class="btn btn-outline">Solicitar adesão <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
            <?php endif; ?>
          </div>
          <div class="hero-trust"><span><i class="fa-solid fa-circle-check"></i> Gestão por perfil</span><span><i class="fa-solid fa-circle-check"></i> Treinos organizados</span><span><i class="fa-solid fa-circle-check"></i> Acesso online</span></div>
        </div>

        <div class="hero-mockup" data-tilt>
          <div class="floating-tag floating-tag-top"><i class="fa-solid fa-circle-check"></i> Operação organizada</div>
          <div class="mockup-card">
            <div class="mockup-header">
              <div class="dot dot-red"></div><div class="dot dot-yellow"></div><div class="dot dot-green"></div>
              <span class="mockup-url"><i class="fa-solid fa-lock"></i> GymControl / Visão geral</span>
            </div>
            <div class="mockup-dashboard">
              <aside class="dash-sidebar" aria-hidden="true"><div class="dash-symbol">GC</div><i class="fa-solid fa-chart-pie active"></i><i class="fa-solid fa-users"></i><i class="fa-solid fa-dumbbell"></i><i class="fa-solid fa-calendar-days"></i><i class="fa-solid fa-gear"></i></aside>
              <div class="dash-main">
                <div class="dash-topline"><div><small>PAINEL DE GESTÃO</small><strong>Visão da sua academia</strong></div><div class="dash-avatar"><i class="fa-solid fa-user"></i></div></div>
                <div class="dash-metrics"><div class="dash-metric"><span>Alunos</span><strong><i class="fa-solid fa-users"></i></strong><small>Cadastro centralizado</small></div><div class="dash-metric"><span>Treinos</span><strong><i class="fa-solid fa-dumbbell"></i></strong><small>Rotinas organizadas</small></div></div>
                <div class="dash-chart"><div class="dash-chart-title"><strong>Organização do dia a dia</strong><span>Visão ilustrativa</span></div><div class="dash-bars" aria-hidden="true"><span style="--h:38%"></span><span style="--h:66%"></span><span style="--h:49%"></span><span style="--h:86%"></span><span style="--h:64%"></span><span style="--h:100%"></span><span style="--h:78%"></span><span style="--h:91%"></span><span style="--h:68%"></span></div></div>
                <div class="dash-bottom"><span><i class="fa-solid fa-check-circle"></i> Gestão em um só lugar</span><i class="fa-solid fa-arrow-trend-up"></i></div>
              </div>
            </div>
          </div>
          <div class="floating-tag floating-tag-bottom"><i class="fa-solid fa-bolt"></i> Uma plataforma. Vários perfis.</div>
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
        <p>Ferramentas pensadas para deixar a operação mais simples, do cadastro ao acompanhamento dos alunos.</p>
      </div>

      <div class="features-grid">
        <div class="feature-item">
          <div class="feature-icon-box"><i class="fa-solid fa-shield-halved"></i></div>
          <div class="feature-info">
            <h4>Gestão de Matrículas</h4>
            <p>Acompanhe o cadastro e o status dos alunos em uma área centralizada.</p>
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
      <span class="badge-pill">PLANOS GYMCONTROL</span>
      <h2>Escolha o plano ideal para a sua Academia</h2>
      <p>Conheça as opções e solicite uma análise de adesão. Os recursos e condições são apresentados pela equipe antes da contratação.</p>
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
        <button class="btn btn-outline btn-block" onclick="selecionarPlano('Plano Studio')">Solicitar adesão</button>
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
        <button class="btn btn-primary btn-block" onclick="selecionarPlano('Plano Safira')">Solicitar adesão</button>
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
        <button class="btn btn-outline btn-block" onclick="selecionarPlano('Plano Infinity')">Solicitar adesão</button>
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
        <div class="contact-highlight"><i class="fa-solid fa-paper-plane"></i> SOLICITAÇÃO DE ADESÃO</div>
        <span class="badge-pill">FALE COM NOSSO TIME COMERCIAL</span>
        <h3>Modernize a gestão da sua academia com o GymControl</h3>
        <p>Conte um pouco sobre sua academia e escolha o plano que faz sentido para seu momento. A administração geral receberá sua solicitação para análise.</p>
        
        <ul style="list-style: none; display: flex; flex-direction: column; gap: 12px; margin-top: 20px;">
          <li>✓ Atendimento consultivo para donos e gestores</li>
          <li>✓ Gestão com ambientes separados por academia</li>
          <li>✓ Solicitação analisada antes da ativação</li>
        </ul>
      </div>

      <form id="formInteresse" method="POST" class="contact-form-body">
        <?php if ($sucessoInteresse): ?><div class="form-alert success" role="status"><i class="fa-solid fa-circle-check"></i> Solicitação recebida! Ela está disponível para análise pela administração geral.</div><?php endif; ?>
        <?php if ($erroInteresse): ?><div class="form-alert error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?=gcCampo($erroInteresse)?></div><?php endif; ?>
        <input type="hidden" name="acao" value="interesse">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_contato'],ENT_QUOTES,'UTF-8') ?>">
        <div class="form-group">
          <label for="nomeAcademia">Nome da Academia ou Estúdio *</label>
          <input type="text" id="nomeAcademia" name="nome_academia" maxlength="150" required value="<?=gcCampo($dadosInteresse['nome_academia'])?>" placeholder="Ex: FitCenter Copacabana">
        </div>

        <div class="form-group">
          <label for="nomeResponsavel">Seu Nome (Gestor / Proprietário) *</label>
          <input type="text" id="nomeResponsavel" name="responsavel" maxlength="120" required value="<?=gcCampo($dadosInteresse['responsavel'])?>" placeholder="Ex: Nicolas Tavares">
        </div>

        <div class="form-group">
          <label for="emailCorporativo">E-mail de Contato *</label>
          <input type="email" id="emailCorporativo" name="email" maxlength="150" required value="<?=gcCampo($dadosInteresse['email'])?>" placeholder="contato@suaacademia.com.br">
        </div>

        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label for="telefoneWhatsapp">WhatsApp com DDD *</label>
            <input type="tel" id="telefoneWhatsapp" name="telefone" maxlength="30" required value="<?=gcCampo($dadosInteresse['telefone'])?>" placeholder="(21) 99999-9999">
          </div>

          <div class="form-group">
            <label for="planoInteresse">Plano de Interesse</label>
            <select id="planoInteresse" name="plano">
              <option value="Não sei / Quero ajuda" <?= $dadosInteresse['plano'] === 'Não sei / Quero ajuda' ? 'selected' : '' ?>>Quero ajuda para escolher</option>
              <option value="Plano Studio" <?= $dadosInteresse['plano'] === 'Plano Studio' ? 'selected' : '' ?>>Plano Studio (Até 200 alunos)</option>
              <option value="Plano Safira" <?= $dadosInteresse['plano'] === 'Plano Safira' ? 'selected' : '' ?>>Plano Safira (Reconhecimento Facial)</option>
              <option value="Plano Infinity" <?= $dadosInteresse['plano'] === 'Plano Infinity' ? 'selected' : '' ?>>Plano Infinity (Multi-unidades)</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="qtdAlunos">Estimativa de Alunos Ativos</label>
          <select id="qtdAlunos" name="qtd_alunos">
            <option value="Até 100" <?= $dadosInteresse['qtd_alunos'] === 'Até 100' ? 'selected' : '' ?>>Até 100 alunos</option>
            <option value="100 a 300" <?= $dadosInteresse['qtd_alunos'] === '100 a 300' ? 'selected' : '' ?>>100 a 300 alunos</option>
            <option value="300 a 800" <?= $dadosInteresse['qtd_alunos'] === '300 a 800' ? 'selected' : '' ?>>300 a 800 alunos</option>
            <option value="Mais de 800" <?= $dadosInteresse['qtd_alunos'] === 'Mais de 800' ? 'selected' : '' ?>>Mais de 800 alunos</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="height: 48px; font-size: 15px; margin-top: 10px;">
          Registrar solicitação de adesão
        </button>
        <p style="margin-top:10px;font-size:12px;color:var(--text-muted);">Sua solicitação será analisada pela administração geral. O envio não cria a academia nem ativa uma assinatura automaticamente.</p>
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
            <li><span>Projeto acadêmico • 2026</span></li>
            <li><span>Desenvolvimento web</span></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; 2026 GymControl. Todos os direitos reservados. Projeto Acadêmico.</p>
      </div>
    </div>
  </footer>

  <script>
    function selecionarPlano(plano) {
      const campoPlano = document.getElementById('planoInteresse');
      const contato = document.getElementById('contato');
      if (!campoPlano || !contato) return;
      campoPlano.value = plano;
      contato.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
      window.setTimeout(() => campoPlano.focus({ preventScroll: true }), window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 500);
    }
    (() => {
      const navbar = document.querySelector('.navbar');
      const meter = document.querySelector('.scroll-meter');
      const toggle = document.querySelector('.menu-toggle');
      const menu = document.getElementById('mainNav');
      const syncScroll = () => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        meter.style.width = (max > 0 ? Math.min(100, window.scrollY / max * 100) : 0) + '%';
        navbar.classList.toggle('is-scrolled', window.scrollY > 24);
      };
      window.addEventListener('scroll', syncScroll, { passive: true });
      syncScroll();
      function closeMenu() {
        menu.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Abrir menu de navegação');
      }
      toggle.addEventListener('click', () => {
        const open = menu.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Fechar menu de navegação' : 'Abrir menu de navegação');
      });
      menu.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMenu));
      document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });
      document.addEventListener('click', e => { if (!menu.contains(e.target) && !toggle.contains(e.target)) closeMenu(); });
      if ('IntersectionObserver' in window) {
        const sections = document.querySelectorAll('#sobre,#recursos,#planos,#contato');
        const headings = document.querySelectorAll('.section-header,.pillar-card,.feature-item,.pricing-card,.contact-info,.contact-form-body,.footer-grid > div');
        headings.forEach((element, index) => { element.dataset.reveal = ''; element.style.setProperty('--reveal-delay', ((index % 3) * 90) + 'ms'); });
        const obs = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); obs.unobserve(entry.target); } }), { threshold: .08, rootMargin: '0px 0px -24px 0px' });
        headings.forEach(el => obs.observe(el));
        const links = document.querySelectorAll('.nav-links a[href^="#"]');
        const activeObs = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) links.forEach(a => a.classList.toggle('is-current', a.getAttribute('href') === '#' + entry.target.id)); }), { rootMargin: '-20% 0px -65% 0px' });
        document.querySelectorAll('section[id]').forEach(el => activeObs.observe(el));
      }
      const phone = document.getElementById('telefoneWhatsapp');
      phone?.addEventListener('input', () => {
        const v = phone.value.replace(/\D/g, '').slice(0, 11);
        phone.value = v.length > 6 ? `(${v.slice(0,2)}) ${v.slice(2, v.length===11?7:6)}-${v.slice(v.length===11?7:6)}` : v.length > 2 ? `(${v.slice(0,2)}) ${v.slice(2)}` : v;
      });
      const form = document.getElementById('formInteresse');
      form?.addEventListener('submit', () => {
        if (!form.checkValidity()) return;
        const submit = form.querySelector('button[type="submit"]');
        submit.disabled = true;
        submit.textContent = 'Enviando solicitação...';
      });
      const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (!reduced && window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
        const mockup = document.querySelector('.hero-mockup');
        mockup?.addEventListener('pointermove', e => {
          const box = mockup.getBoundingClientRect();
          const x = (e.clientX - box.left) / box.width - .5;
          const y = (e.clientY - box.top) / box.height - .5;
          mockup.style.transform = `perspective(1000px) rotateY(${x*4}deg) rotateX(${-y*4}deg)`;
        });
        mockup?.addEventListener('pointerleave', () => { mockup.style.transform = ''; });
      }
    })();
  </script>
</body>
</html>