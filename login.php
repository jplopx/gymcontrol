<?php
session_start();

// Se já estiver logado, chuta direto para a pasta do perfil dele
if (isset($_SESSION['usuario_id'])) {
    header("Location: " . $_SESSION['usuario_tipo'] . "/index.php");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/conexao.php';

    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if (!empty($email) && !empty($senha)) {
        $stmt = $pdo->prepare("SELECT id, nome, email, senha, tipo, status FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            if ($usuario['status'] !== 'ativo') {
                $erro = 'Esta conta está inativa. Procure a administração.';
            } else {
                $_SESSION['usuario_id']   = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_tipo'] = $usuario['tipo'];

                // Redireciona de acordo com o cargo
                header("Location: " . $usuario['tipo'] . "/index.php");
                exit;
            }
        } else {
            $erro = 'E-mail ou senha incorretos.';
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — GymControl</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #0f172a; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #fff; }
        .login-card { background: #1e293b; padding: 40px; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); border: 1px solid #334155; }
        .logo { font-size: 24px; font-weight: 700; text-align: center; margin-bottom: 24px; color: #38bdf8; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; margin-bottom: 6px; color: #94a3b8; }
        input { width: 100%; padding: 12px; border-radius: 6px; border: 1px solid #334155; background: #090d16; color: #fff; outline: none; }
        input:focus { border-color: #0284c7; }
        button { width: 100%; padding: 12px; background: #0284c7; border: none; border-radius: 6px; color: #fff; font-weight: 600; cursor: pointer; transition: 0.2s; }
        button:hover { background: #0369a1; }
        .alert { background: #ef4444; color: #fff; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; text-align: center; }
        .hint { margin-top: 20px; font-size: 12px; color: #64748b; line-height: 1.5; border-top: 1px solid #334155; padding-top: 15px; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="logo">GymControl</div>

    <?php if (!empty($erro)): ?>
        <div class="alert"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label>E-mail</label>
            <input type="email" name="email" required placeholder="Digite seu e-mail">
        </div>
        <div class="form-group">
            <label>Senha</label>
            <input type="password" name="senha" required placeholder="Digite sua senha">
        </div>
        <button type="submit">Entrar no Sistema</button>
    </form>

    <div class="hint">
        <strong>Contas de teste (senha: 123456):</strong><br>
        Admin: admin@gymcontrol.com<br>
        Professor: professor@gymcontrol.com<br>
        Aluno: aluno@gymcontrol.com
    </div>
</div>

</body>
</html>