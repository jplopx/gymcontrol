<?php
require_once __DIR__ . '/auth.php';
if (!empty($_SESSION['superadmin_id'])) { header('Location: index.php'); exit; }
$erro='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals(superCsrf(),(string)($_POST['csrf']??''))) $erro='Sessão expirada. Atualize a página.';
    else {
        $email=mb_strtolower(trim((string)($_POST['email']??'')),'UTF-8');
        $senha=(string)($_POST['senha']??'');
        $q=$pdo->prepare('SELECT id,senha,ativo FROM superadmins WHERE email=? LIMIT 1');
        $q->execute([$email]); $u=$q->fetch(PDO::FETCH_ASSOC);
        if ($u && (int)$u['ativo']===1 && password_verify($senha,$u['senha'])) {
            session_regenerate_id(true); $_SESSION['superadmin_id']=(int)$u['id'];
            header('Location: index.php'); exit;
        }
        $erro='Credenciais incorretas ou conta desativada.';
        usleep(180000);
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Gestão Global • GymControl</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 25% 15%,#123454,#0b1221 60%);color:#e9f2ff;font-family:Inter,Arial;min-height:100vh;display:grid;place-items:center;padding:20px}main{width:min(440px,100%);background:#121d30;border:1px solid #2c3d56;padding:38px;border-radius:22px;box-shadow:0 25px 65px #0006}.brand{font-weight:800;color:#7dd3fc}h1{font-size:26px;margin:15px 0 8px}p{color:#9eb0c5;font-size:14px;line-height:1.6}label{display:block;font-size:13px;font-weight:700;margin:19px 0 7px}input{width:100%;background:#0b1526;border:1px solid #354661;color:#fff;border-radius:10px;padding:14px;font-size:15px;outline:none}input:focus{border-color:#38bdf8}button{margin-top:24px;width:100%;padding:14px;border:0;border-radius:10px;background:#0284c7;color:white;font-weight:800;cursor:pointer}.erro{padding:12px;border:1px solid #f87171;color:#fda4af;border-radius:9px}a{color:#7dd3fc;text-decoration:none}</style></head><body><main>
<div class="brand">◈ GYMCONTROL / GLOBAL</div><h1>Administração geral</h1><p>Acesso exclusivo aos responsáveis pela plataforma. Não pertence a nenhuma academia.</p>
<?php if($erro):?><div class="erro" role="alert"><?=superEsc($erro)?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=superEsc(superCsrf())?>"><label for="email">E-mail</label><input id="email" type="email" name="email" autocomplete="username" required><label for="senha">Senha</label><input id="senha" type="password" name="senha" autocomplete="current-password" required><button>Entrar no painel global</button></form>
<p style="margin-top:25px"><a href="../index.php">← Voltar ao site</a></p></main></body></html>
