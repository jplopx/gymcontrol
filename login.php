<?php
require_once __DIR__ . '/config/auth.php';

// Se já estiver logado, redireciona diretamente para a página principal do seu perfil
if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['usuario_nivel'])) {
    $destino = getGymControlBaseUrl() . getRotaPrincipalPorNivel($_SESSION['usuario_nivel']);
    header("Location: " . $destino);
    exit;
}

$erro = '';
$sucesso = '';
$abaAtiva = $_POST['tipo_login'] ?? ($_GET['aba'] ?? 'aluno');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/conexao.php';

    $tipoLogin = trim($_POST['tipo_login'] ?? 'aluno');
    $senha     = trim($_POST['senha'] ?? '');

    if ($tipoLogin === 'aluno') {
        // 1. AUTENTICAÇÃO DE ALUNOS: E-MAIL E SENHA
        $email = trim($_POST['email'] ?? '');

        if (!empty($email) && !empty($senha)) {
            $stmt = $pdo->prepare("
                SELECT id, nome, email, matricula, senha, nivel_acesso, status 
                FROM usuarios 
                WHERE email = :email 
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                if ($usuario['status'] !== 'ativo') {
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

                    header("Location: " . getGymControlBaseUrl() . getRotaPrincipalPorNivel($usuario['nivel_acesso']));
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
                SELECT id, nome, email, matricula, senha, nivel_acesso, status 
                FROM usuarios 
                WHERE matricula = :matricula 
                   OR (id = :numId AND :isNum = 1)
                   OR email = :email
                LIMIT 1
            ");
            $stmt->execute([
                ':matricula' => $identificador,
                ':numId'         => $numId,
                ':isNum'         => $isNum ? 1 : 0,
                ':email' => $identificador
            ]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                if ($usuario['status'] !== 'ativo') {
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

                    header("Location: " . getGymControlBaseUrl() . getRotaPrincipalPorNivel($usuario['nivel_acesso']));
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
</head>
<body>

<div class="login-card">
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

    <div class="hint">
        <strong style="color: #cbd5e1; display: block; margin-bottom: 6px;">Contas para Teste RBAC (Senha padrão: 123456)</strong>
        • <strong>Aluno:</strong> <span class="hint-badge">aluno@gymcontrol.com</span> (Acesso /aluno/)<br>
        • <strong>Recepção:</strong> Matrícula <span class="hint-badge">REC001</span> (Acesso /admin/recepcao.php)<br>
        • <strong>Loja/PDV:</strong> Matrícula <span class="hint-badge">LOJA001</span> (Acesso /admin/loja.php)<br>
        • <strong>Professor:</strong> Matrícula <span class="hint-badge">PROF001</span> (Acesso /professor/)<br>
        • <strong>Admin Geral:</strong> Matrícula <span class="hint-badge">ADM001</span> (Acesso Total)
    </div>

    <a href="index.php" class="back-link">← Voltar para a Página Inicial</a>
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
</script>

</body>
</html>
