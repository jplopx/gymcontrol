<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/conexao.php';
if (empty($_SESSION['usuario_id']) || empty($_SESSION['academia_id'])) { header('Location: login.php'); exit; }
$id=(int)$_SESSION['usuario_id']; $academia=(int)$_SESSION['academia_id'];
$q=$pdo->prepare("SELECT u.senha,u.status,u.nivel_acesso,u.trocar_senha,a.status AS academia_status FROM usuarios u JOIN academias a ON a.id=u.academia_id WHERE u.id=? AND u.academia_id=? LIMIT 1");
$q->execute([$id,$academia]); $usuario=$q->fetch(PDO::FETCH_ASSOC);
if (!$usuario || $usuario['status']!=='ativo' || $usuario['academia_status']!=='ativa') { unset($_SESSION['usuario_id'],$_SESSION['usuario_nivel'],$_SESSION['academia_id']); header('Location: login.php'); exit; }
$erro='';
if (empty($_SESSION['csrf_senha'])) $_SESSION['csrf_senha']=bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $antiga=(string)($_POST['senha_atual']??''); $nova=(string)($_POST['senha_nova']??''); $confirma=(string)($_POST['confirmar']??'');
    if (!hash_equals($_SESSION['csrf_senha'],(string)($_POST['csrf']??''))) $erro='Formulário expirado.';
    elseif (!password_verify($antiga,$usuario['senha'])) $erro='Senha atual incorreta.';
    elseif (strlen($nova)<10 || strlen($nova)>72 || $nova!==$confirma) $erro='A nova senha precisa ter 10 a 72 caracteres e coincidir com a confirmação.';
    elseif ($nova===$antiga) $erro='Escolha uma senha diferente da atual.';
    else {
        $q=$pdo->prepare('UPDATE usuarios SET senha=?,trocar_senha=0 WHERE id=? AND academia_id=? AND status=\'ativo\'');
        $q->execute([password_hash($nova,PASSWORD_DEFAULT),$id,$academia]);
        session_regenerate_id(true);
        header('Location: '.getGymControlBaseUrl().getRotaPrincipalPorNivel($usuario['nivel_acesso'])); exit;
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Alterar senha • GymControl</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0b1220;color:#e5efff;font-family:Inter,Arial;padding:20px}main{max-width:440px;width:100%;padding:35px;border:1px solid #304159;border-radius:18px;background:#142034}p{color:#a6b8cc;line-height:1.6}label{display:block;margin:15px 0 6px;font-weight:600;font-size:13px}input{width:100%;padding:13px;border-radius:9px;border:1px solid #364961;background:#091526;color:white}button{margin-top:22px;padding:13px;width:100%;border:0;border-radius:9px;background:#0284c7;color:white;font-weight:700;cursor:pointer}.erro{color:#fda4af}a{color:#7dd3fc}</style></head><body><main><h1>Proteja seu acesso</h1><p><?= (int)$usuario['trocar_senha']===1?'Você está usando uma credencial temporária. Troque a senha para continuar.':'Altere sua senha de acesso.'?></p><?php if($erro):?><p class="erro" role="alert"><?=htmlspecialchars($erro,ENT_QUOTES,'UTF-8')?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_senha'],ENT_QUOTES,'UTF-8')?>"><label for="atual">Senha atual</label><input id="atual" name="senha_atual" type="password" required autocomplete="current-password"><label for="nova">Nova senha (mínimo 10 caracteres)</label><input id="nova" name="senha_nova" type="password" required minlength="10" autocomplete="new-password"><label for="confirmar">Confirme a senha</label><input id="confirmar" name="confirmar" type="password" required minlength="10" autocomplete="new-password"><button>Salvar nova senha</button></form><p><a href="logout.php">Sair</a></p></main></body></html>
