<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/conexao.php';

// Se já estiver logado, redireciona diretamente para a página principal do seu perfil
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($_SESSION['usuario_id']) && !empty($_SESSION['usuario_nivel'])) {
    // Mesmo em login.php, conferir se a sessão continua válida.
    $checkLogin = $pdo->prepare("SELECT u.nivel_acesso, u.academia_id, u.trocar_senha, u.status, ac.status AS academia_status,
        al.status_matricula AS status_matricula_aluno
        FROM usuarios u JOIN academias ac ON ac.id = u.academia_id
        LEFT JOIN alunos al ON al.usuario_id=u.id AND al.academia_id=u.academia_id
        WHERE u.id = ? LIMIT 1");
    $checkLogin->execute([(int) $_SESSION['usuario_id']]);
    $contaAtiva = $checkLogin->fetch(PDO::FETCH_ASSOC);
    if (!$contaAtiva || $contaAtiva['status'] !== 'ativo' || $contaAtiva['academia_status'] !== 'ativa'
        || ($contaAtiva['nivel_acesso'] === 'aluno' && $contaAtiva['status_matricula_aluno'] !== 'ativa')
        || (int) $contaAtiva['academia_id'] !== (int) ($_SESSION['academia_id'] ?? 0)) {
        $_SESSION = [];
        session_regenerate_id(true);
    } else {
    $destino = getGymControlBaseUrl() . ((int)$contaAtiva['trocar_senha']===1 ? 'trocar_senha.php' : getRotaPrincipalPorNivel($contaAtiva['nivel_acesso']));
    header("Location: " . $destino);
    exit;
    }
}

// Uma credencial numérica identifica a academia; nunca aceitar academia_id do navegador.
$credencialInformada = trim(is_string($_POST['credencial_academia'] ?? null) ? $_POST['credencial_academia'] : '');
$academiaEscolhida = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/^[0-9]{1,8}$/D', $credencialInformada)) {
    $buscarAcademia = $pdo->prepare("SELECT id FROM academias WHERE codigo = ? AND status = 'ativa' LIMIT 1");
    $buscarAcademia->execute([$credencialInformada]);
    $academiaEscolhida = (int) ($buscarAcademia->fetchColumn() ?: 0);
}
$erro = '';
$sucesso = '';
$abaAtiva = $_POST['tipo_login'] ?? ($_GET['aba'] ?? 'aluno');
if (!in_array($abaAtiva, ['aluno', 'funcionario'], true)) $abaAtiva = 'aluno';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nunca reaproveitar a autenticação de uma academia em uma tentativa de outra.
    // A escolha só é válida após a conferência da credencial E do vínculo no banco.
    foreach (['usuario_id', 'usuario_nome', 'usuario_email', 'usuario_matricula',
              'usuario_nivel', 'usuario_tipo', 'academia_id'] as $chaveSessao) {
        unset($_SESSION[$chaveSessao]);
    }
    $tipoLogin = trim($_POST['tipo_login'] ?? 'aluno');
    $senha     = (string) ($_POST['senha'] ?? '');

    if (!in_array($tipoLogin, ['aluno', 'funcionario'], true)) {
        $erro = 'Tipo de acesso inválido.';
    } elseif ($academiaEscolhida < 1) {
        $erro = 'Credencial da academia inválida ou academia inativa.';
    } elseif ($tipoLogin === 'aluno') {
        // 1. AUTENTICAÇÃO DE ALUNOS: E-MAIL E SENHA
        $email = trim($_POST['email'] ?? '');

        if (!empty($email) && !empty($senha)) {
            $stmt = $pdo->prepare("
                SELECT u.id, u.nome, u.email, u.matricula, u.senha, u.nivel_acesso, u.trocar_senha, u.status, u.academia_id, ac.status AS academia_status,
                    al.status_matricula AS status_matricula_aluno
                FROM usuarios u JOIN academias ac ON ac.id = u.academia_id
                LEFT JOIN alunos al ON al.usuario_id=u.id AND al.academia_id=u.academia_id
                WHERE u.email = :email AND u.academia_id = :academia
                LIMIT 1
            ");
            $stmt->execute([':email' => $email, ':academia' => $academiaEscolhida]);
            $usuario = $stmt->fetch();

            if ($usuario && (int) $usuario['academia_id'] === $academiaEscolhida && password_verify($senha, $usuario['senha'])) {
                if (($usuario['status'] !== 'ativo' || $usuario['academia_status'] !== 'ativa'
                    || $usuario['status_matricula_aluno'] !== 'ativa')) {
                    $erro = 'Esta conta de aluno está inativa. Procure a recepção da academia.';
                } elseif ($usuario['nivel_acesso'] !== 'aluno') {
                    $erro = 'Este e-mail pertence a um colaborador. Por favor, acesse pela aba "Equipe & Gestão".';
                } else {
                    // Login de Aluno autorizado
                    session_regenerate_id(true);
                    $_SESSION['usuario_id']        = $usuario['id'];
                    $_SESSION['usuario_nome']      = $usuario['nome'];
                    $_SESSION['usuario_email']     = $usuario['email'];
                    $_SESSION['usuario_matricula'] = $usuario['matricula'] ?? null;
                    $_SESSION['usuario_nivel']     = $usuario['nivel_acesso'];
                    $_SESSION['usuario_tipo']      = $usuario['nivel_acesso'];
                    $_SESSION['academia_id']      = (int) $usuario['academia_id'];

                    header("Location: " . getGymControlBaseUrl() . ((int)$usuario['trocar_senha']===1 ? 'trocar_senha.php' : getRotaPrincipalPorNivel($usuario['nivel_acesso'])));
                    exit;
                }
            } else {
                $erro = 'E-mail ou senha incorretos.';
            }
        } else {
            $erro = 'Preencha o e-mail e a senha do aluno.';
        }
    } else {
        // 2. AUTENTICAÇÃO DE FUNCIONÁRIOS E ADMINISTRADORES: MATRÍCULA / ID E SENHA
        $identificador = trim($_POST['identificador'] ?? '');

        if (!empty($identificador) && !empty($senha)) {
            $isNum = ctype_digit($identificador);
            $numId = $isNum ? intval($identificador) : 0;

            $stmt = $pdo->prepare("
                SELECT u.id, u.nome, u.email, u.matricula, u.senha, u.nivel_acesso, u.trocar_senha, u.status, u.academia_id, ac.status AS academia_status
                FROM usuarios u JOIN academias ac ON ac.id = u.academia_id
                WHERE u.academia_id = :academia
                  AND (u.matricula = :matricula OR (u.id = :numId AND :isNum = 1) OR u.email = :email)
                LIMIT 1
            ");
            $stmt->execute([
                ':matricula' => $identificador,
                ':numId'         => $numId,
                ':isNum'         => $isNum ? 1 : 0,
                ':email' => $identificador,
                ':academia' => $academiaEscolhida
            ]);
            $usuario = $stmt->fetch();

            if ($usuario && (int) $usuario['academia_id'] === $academiaEscolhida && password_verify($senha, $usuario['senha'])) {
                if (($usuario['status'] !== 'ativo' || $usuario['academia_status'] !== 'ativa')) {
                    $erro = 'Esta conta de colaborador está inativa. Contate a administração.';
                } elseif ($usuario['nivel_acesso'] === 'aluno') {
                    $erro = 'Acesso restrito a funcionários. Alunos devem realizar o login na aba "Sou Aluno".';
                } else {
                    // Login de Funcionário/Admin autorizado
                    session_regenerate_id(true);
                    $_SESSION['usuario_id']        = $usuario['id'];
                    $_SESSION['usuario_nome']      = $usuario['nome'];
                    $_SESSION['usuario_email']     = $usuario['email'];
                    $_SESSION['usuario_matricula'] = $usuario['matricula'] ?? null;
                    $_SESSION['usuario_nivel']     = $usuario['nivel_acesso'];
                    $_SESSION['usuario_tipo']      = $usuario['nivel_acesso'];
                    $_SESSION['academia_id']      = (int) $usuario['academia_id'];

                    header("Location: " . getGymControlBaseUrl() . ((int)$usuario['trocar_senha']===1 ? 'trocar_senha.php' : getRotaPrincipalPorNivel($usuario['nivel_acesso'])));
                    exit;
                }
            } else {
                $erro = 'Matrícula/ID ou senha incorretos.';
            }
        } else {
            $erro = 'Informe a matrícula ou ID e a sua senha.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — GymControl</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { 
            background: radial-gradient(circle at 50% 20%, #1e293b 0%, #0f172a 100%); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            color: #fff; 
            padding: 24px;
        }
        .login-card { 
            background: #1e293b; 
            padding: 36px 32px; 
            border-radius: 14px; 
            width: 100%; 
            max-width: 440px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.45); 
            border: 1px solid #334155; 
        }
        .logo-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        .logo-badge { 
            width: 42px; 
            height: 42px; 
            background: linear-gradient(135deg, #0284c7, #38bdf8); 
            border-radius: 10px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 20px; 
            font-weight: 800; 
            color: #fff; 
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.4); 
        }
        .logo-text { font-size: 24px; font-weight: 800; color: #fff; letter-spacing: -0.5px; }

        /* ABAS RBAC */
        .tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #090d16;
            padding: 4px;
            border-radius: 8px;
            gap: 4px;
            margin-bottom: 24px;
            border: 1px solid #334155;
        }
        .tab-btn {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding: 10px 8px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .tab-btn.active {
            background: #0284c7;
            color: #fff;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.35);
        }

        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #cbd5e1; }
        .input-wrapper { position: relative; }
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 14px;
        }
        input { 
            width: 100%; 
            padding: 12px 14px 12px 40px; 
            border-radius: 8px; 
            border: 1px solid #334155; 
            background: #090d16; 
            color: #fff; 
            font-size: 14px; 
            outline: none; 
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus { 
            border-color: #38bdf8; 
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15); 
        }
        button.btn-submit { 
            width: 100%; 
            padding: 13px; 
            background: #0284c7; 
            border: none; 
            border-radius: 8px; 
            color: #fff; 
            font-weight: 700; 
            font-size: 14px; 
            cursor: pointer; 
            transition: 0.2s; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        button.btn-submit:hover { background: #0369a1; }

        .alert-error { 
            background: rgba(239, 68, 68, 0.15); 
            border: 1px solid rgba(239, 68, 68, 0.35); 
            color: #f87171; 
            padding: 12px; 
            border-radius: 8px; 
            font-size: 13px; 
            margin-bottom: 18px; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
        }

        .hint { 
            margin-top: 24px; 
            font-size: 12px; 
            color: #94a3b8; 
            line-height: 1.6; 
            border-top: 1px solid #334155; 
            padding-top: 18px; 
        }
        .hint-badge {
            display: inline-block;
            background: #090d16;
            border: 1px solid #334155;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            color: #38bdf8;
            margin: 2px 0;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
            color: #64748b;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: #cbd5e1; }
    </style>
    <link rel="stylesheet" href="assets/login-premium.css?v=20261118-1">
</head>
<body class="gc-login-page">
<div class="gc-auth-shell">
  <aside class="gc-auth-showcase" aria-label="Conheça a plataforma GymControl">
    <a class="gc-auth-brand" href="index.php" aria-label="GymControl — página inicial">
      <span class="gc-auth-brand-mark" aria-hidden="true">GC</span>
      <span>GymControl</span>
    </a>
    <div class="gc-auth-eyebrow">Uma experiência conectada</div>
    <h1>Seu treino. Sua evolução. <span>Tudo em um lugar.</span></h1>
    <p>Uma plataforma para acompanhar seus treinos, manter contato com seu professor e evoluir com organização — do seu jeito.</p>
    <div class="gc-auth-preview" aria-label="Representação ilustrativa de progresso">
      <div class="gc-preview-heading">
        <span class="gc-preview-title"><span class="gc-preview-dot"></span> Seu espaço de evolução</span>
        <span class="gc-preview-badge">Visão integrada</span>
      </div>
      <div class="gc-preview-graph" aria-hidden="true">
        <span style="--gc-height:29%;--gc-step:.08s"></span>
        <span style="--gc-height:43%;--gc-step:.14s"></span>
        <span style="--gc-height:35%;--gc-step:.2s"></span>
        <span style="--gc-height:64%;--gc-step:.26s"></span>
        <span style="--gc-height:57%;--gc-step:.32s"></span>
        <span style="--gc-height:82%;--gc-step:.38s"></span>
        <span style="--gc-height:95%;--gc-step:.44s"></span>
      </div>
      <div class="gc-preview-captions"><span><i class="fa-solid fa-dumbbell"></i> Treinos</span><span><i class="fa-solid fa-chart-line"></i> Evolução</span><span><i class="fa-solid fa-calendar-check"></i> Agenda</span></div>
    </div>
    <div class="gc-auth-footnote"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Identidade visual integrada à página inicial.</div>
  </aside>
  <div class="gc-auth-form-area">
    <div class="login-card">
      <div class="gc-auth-form-eyebrow">Seu acesso GymControl</div>
      <h2 class="gc-auth-form-title">Boas-vindas de volta</h2>
      <p class="gc-auth-form-subtitle">Informe a credencial da sua academia e acesse sua área com segurança.</p>
    <div class="logo-header">
        <div class="logo-badge">GC</div>
        <div class="logo-text">GymControl</div>
    </div>

    <!-- Renderiza alertas de acesso negado se vier redirecionado de middleware -->
    <?php renderizarAvisoPermissao(); ?>

    <?php if (!empty($erro)): ?>
        <div class="alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?php echo htmlspecialchars($erro); ?></span>
        </div>
    <?php endif; ?>

    <!-- Abas RBAC para segmentação de acesso -->
    <div class="tabs">
        <button type="button" class="tab-btn <?php echo $abaAtiva === 'aluno' ? 'active' : ''; ?>" onclick="trocarAba('aluno')">
            <i class="fa-solid fa-graduation-cap"></i> Sou Aluno
        </button>
        <button type="button" class="tab-btn <?php echo $abaAtiva === 'funcionario' ? 'active' : ''; ?>" onclick="trocarAba('funcionario')">
            <i class="fa-solid fa-id-badge"></i> Equipe & Gestão
        </button>
    </div>

    <!-- FORMULÁRIO 1: ALUNO (E-MAIL + SENHA) -->
    <form id="formAluno" method="POST" action="login.php" style="<?php echo $abaAtiva === 'aluno' ? 'display:block;' : 'display:none;'; ?>">
        <input type="hidden" name="tipo_login" value="aluno">
        <div class="form-group"><label for="credencialAlunoLogin">Credencial da academia</label>
          <div class="input-wrapper"><i class="fa-solid fa-building input-icon"></i>
            <input id="credencialAlunoLogin" name="credencial_academia" type="text" inputmode="numeric"
                pattern="[0-9]{1,8}" minlength="1" maxlength="8" required
                placeholder="Até 8 números" autocomplete="off"
                value="<?= htmlspecialchars($credencialInformada, ENT_QUOTES, 'UTF-8') ?>"></div>
          <label title="Guarda somente a credencial desta academia neste navegador para preenchê-la no próximo login. Você poderá alterá-la ou desmarcar esta opção a qualquer momento." style="display:inline-flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer;text-transform:none">
            <input type="checkbox" id="salvarCredencialAluno" class="salvar-credencial" title="Guarda somente a credencial da academia neste navegador, não sua senha. Pode ser alterada ou apagada ao desmarcar." style="width:auto;height:auto">
            Salvar credencial da academia
          </label>
        </div>

        <div class="form-group">
            <label>E-mail do Aluno</label>
            <div class="input-wrapper">
                <i class="fa-regular fa-envelope input-icon"></i>
                <input type="email" name="email" required placeholder="seuemail@gymcontrol.com" autocomplete="username">
            </div>
        </div>

        <div class="form-group">
            <label>Senha</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-lock input-icon"></i>
                <input type="password" name="senha" required placeholder="Digite sua senha" autocomplete="current-password">
            </div>
        </div>

        <button type="submit" class="btn-submit">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Acessar Meu Treino
        </button>
    </form>

    <!-- FORMULÁRIO 2: FUNCIONÁRIO / ADMIN (MATRÍCULA OU ID + SENHA) -->
    <form id="formFuncionario" method="POST" action="login.php" style="<?php echo $abaAtiva === 'funcionario' ? 'display:block;' : 'display:none;'; ?>">
        <input type="hidden" name="tipo_login" value="funcionario">
        <div class="form-group"><label for="credencialFuncionarioLogin">Credencial da academia</label>
          <div class="input-wrapper"><i class="fa-solid fa-building input-icon"></i>
            <input id="credencialFuncionarioLogin" name="credencial_academia" type="text" inputmode="numeric"
                pattern="[0-9]{1,8}" minlength="1" maxlength="8" required
                placeholder="Até 8 números" autocomplete="off"
                value="<?= htmlspecialchars($credencialInformada, ENT_QUOTES, 'UTF-8') ?>"></div>
          <label title="Guarda somente a credencial desta academia neste navegador para preenchê-la no próximo login. Você poderá alterá-la ou desmarcar esta opção a qualquer momento." style="display:inline-flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer;text-transform:none">
            <input type="checkbox" id="salvarCredencialFuncionario" class="salvar-credencial" title="Guarda somente a credencial da academia neste navegador, não sua senha. Pode ser alterada ou apagada ao desmarcar." style="width:auto;height:auto">
            Salvar credencial da academia
          </label>
        </div>

        <div class="form-group">
            <label>Matrícula / ID Funcional</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-id-card-clip input-icon"></i>
                <input type="text" name="identificador" required placeholder="Ex: ADM001, REC001, LOJA001 ou ID" autocomplete="username">
            </div>
        </div>

        <div class="form-group">
            <label>Senha</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-lock input-icon"></i>
                <input type="password" name="senha" required placeholder="Digite sua senha corporativa" autocomplete="current-password">
            </div>
        </div>

        <button type="submit" class="btn-submit">
            <i class="fa-solid fa-shield-halved"></i> Entrar no Painel Operacional
        </button>
    </form>



    <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar para a página inicial</a>
    </div>
    <p class="gc-auth-security-note"><i class="fa-solid fa-lock" aria-hidden="true"></i> Sua senha não é armazenada pela opção de salvar credencial.</p>
  </div>
</div>

<script>
function trocarAba(tipo) {
    const btnAluno = document.querySelectorAll('.tab-btn')[0];
    const btnFunc = document.querySelectorAll('.tab-btn')[1];
    const formAluno = document.getElementById('formAluno');
    const formFunc = document.getElementById('formFuncionario');

    if (tipo === 'aluno') {
        btnAluno.classList.add('active');
        btnFunc.classList.remove('active');
        formAluno.style.display = 'block';
        formFunc.style.display = 'none';
    } else {
        btnFunc.classList.add('active');
        btnAluno.classList.remove('active');
        formFunc.style.display = 'block';
        formAluno.style.display = 'none';
    }
}

// A credencial é só um identificador da academia, nunca uma senha de acesso.
(function () {
    const chave = 'gymcontrol_credencial_academia';
    const campos = [...document.querySelectorAll('input[name="credencial_academia"]')];
    const caixas = [...document.querySelectorAll('.salvar-credencial')];
    let guardada = '';
    try { guardada = localStorage.getItem(chave) || ''; } catch (_) {}
    if (/^[0-9]{1,8}$/.test(guardada)) {
        campos.forEach(c => { if (!c.value) c.value = guardada; });
        caixas.forEach(c => c.checked = true);
    }
    campos.forEach(campo => campo.addEventListener('input', () => {
        campo.value = campo.value.replace(/[^0-9]/g, '').slice(0, 8);
        campos.forEach(outro => { if (outro !== campo) outro.value = campo.value; });
    }));
    caixas.forEach(caixa => caixa.addEventListener('change', () => {
        caixas.forEach(outra => { if (outra !== caixa) outra.checked = caixa.checked; });
        if (!caixa.checked) { try { localStorage.removeItem(chave); } catch (_) {} }
    }));
    [document.getElementById('formAluno'), document.getElementById('formFuncionario')].forEach(form => {
        form.addEventListener('submit', () => {
            const codigo = form.querySelector('input[name="credencial_academia"]').value;
            const salvar = form.querySelector('.salvar-credencial').checked;
            try {
                if (salvar && /^[0-9]{1,8}$/.test(codigo)) localStorage.setItem(chave, codigo);
                else localStorage.removeItem(chave);
            } catch (_) {} // Modo privado ou armazenamento bloqueado não deve impedir o login.
        });
    });
})();
</script>

</body>
</html>
