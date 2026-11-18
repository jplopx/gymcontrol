<?php
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['admin']);
require_once __DIR__ . '/../config/conexao.php';

$academiaId = (int) $_SESSION['academia_id'];
$usuarioLogado = (int) $_SESSION['usuario_id'];
$credencialStmt = $pdo->prepare('SELECT codigo FROM academias WHERE id=? LIMIT 1');
$credencialStmt->execute([$academiaId]);
$credencialAcademia = (string)($credencialStmt->fetchColumn() ?: '');

if (empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
$csrfAdmin = $_SESSION['csrf_admin'];
$mensagem = $_SESSION['admin_mensagem'] ?? '';
$erro = $_SESSION['admin_erro'] ?? '';
unset($_SESSION['admin_mensagem'], $_SESSION['admin_erro']);

function adminEsc($valor): string { return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'); }
function adminRedirecionar(string $tipo, string $texto): void {
    $_SESSION[$tipo === 'erro' ? 'admin_erro' : 'admin_mensagem'] = $texto;
    header('Location: index.php'); exit;
}

// Somente POST, com validação CSRF e isolamento da academia, altera dados.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfAdmin, (string) ($_POST['csrf_token'] ?? ''))) {
        adminRedirecionar('erro', 'Formulário expirado. Atualize a página e tente novamente.');
    }
    $acao = (string) ($_POST['acao'] ?? '');
    if ($acao === 'criar_usuario') {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
        $matricula = trim((string) ($_POST['matricula'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');
        $nivel = (string) ($_POST['nivel_acesso'] ?? '');
        if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100
            || strlen($senha) < 8 || strlen($senha) > 72 || strlen($matricula) > 30
            || !in_array($nivel, ['aluno','recepcao','loja','professor','admin'], true)) {
            adminRedirecionar('erro', 'Confira nome, e-mail, nível, matrícula (até 30 caracteres) e senha (8 a 72 caracteres).');
        }
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO usuarios (academia_id,nome,email,matricula,senha,nivel_acesso,tipo,status)
                VALUES (?,?,?,?,?,?,?, 'ativo')");
            $stmt->execute([$academiaId,$nome,$email,$matricula !== '' ? $matricula : null,password_hash($senha,PASSWORD_DEFAULT),$nivel,$nivel]);
            $id = (int) $pdo->lastInsertId();
            if ($nivel === 'aluno') {
                $stmt = $pdo->prepare("INSERT INTO alunos (academia_id,usuario_id,status_matricula) VALUES (?,?,'ativa')");
                $stmt->execute([$academiaId,$id]);
            }
            $pdo->commit();
            adminRedirecionar('ok', 'Conta de ' . $nome . ' cadastrada com sucesso.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('GymControl admin criar: ' . $e->getMessage());
            adminRedirecionar('erro', ($e->errorInfo[1] ?? 0) == 1062 ? 'E-mail ou matrícula já cadastrado nesta academia.' : 'Falha no cadastro. Confira se o banco está atualizado.');
        }
    }
    if ($acao === 'vincular_professor' || $acao === 'desassociar_aluno') {
        $alunoId = filter_var($_POST['aluno_id'] ?? null, FILTER_VALIDATE_INT);
        $professorId = filter_var($_POST['professor_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$alunoId || ($acao === 'vincular_professor' && !$professorId)) {
            adminRedirecionar('erro', 'Selecione um aluno e, para associar, um professor.');
        }
        try {
            $pdo->beginTransaction();
            // Bloqueia apenas o vínculo local; sem excluir matrícula, treinos ou histórico.
            $stmt = $pdo->prepare("SELECT a.professor_id FROM alunos a JOIN usuarios u ON u.id=a.usuario_id AND u.academia_id=a.academia_id
                WHERE a.usuario_id=? AND a.academia_id=? AND u.nivel_acesso='aluno' FOR UPDATE");
            $stmt->execute([$alunoId,$academiaId]);
            $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$aluno) throw new DomainException('Aluno não encontrado nesta academia.');
            if ($acao === 'vincular_professor') {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id=? AND academia_id=? AND nivel_acesso='professor' AND status='ativo'");
                $stmt->execute([$professorId,$academiaId]);
                if (!$stmt->fetchColumn()) throw new DomainException('Professor indisponível nesta academia.');
                $stmt = $pdo->prepare('UPDATE alunos SET professor_id=? WHERE usuario_id=? AND academia_id=?');
                $stmt->execute([$professorId,$alunoId,$academiaId]);
                $texto = 'Professor associado ao aluno com sucesso.';
            } else {
                if ($aluno['professor_id'] === null) throw new DomainException('Este aluno já está sem professor associado.');
                $stmt = $pdo->prepare('UPDATE alunos SET professor_id=NULL WHERE usuario_id=? AND academia_id=?');
                $stmt->execute([$alunoId,$academiaId]);
                $texto = 'Aluno desassociado do professor. A matrícula e os históricos foram preservados.';
            }
            $pdo->commit(); adminRedirecionar('ok', $texto);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (!($e instanceof DomainException)) error_log('GymControl admin vínculo: '.$e->getMessage());
            adminRedirecionar('erro', $e instanceof DomainException ? $e->getMessage() : 'Não foi possível alterar o vínculo.');
        }
    }
    if ($acao === 'alterar_matricula') {
        $alunoId = filter_var($_POST['aluno_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status_matricula'] ?? '');
        if (!$alunoId || !in_array($status,['ativa','pendente','bloqueada'],true)) adminRedirecionar('erro','Informe aluno e situação de matrícula válidos.');
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT a.usuario_id FROM alunos a JOIN usuarios u ON u.id=a.usuario_id AND u.academia_id=a.academia_id
                WHERE a.usuario_id=? AND a.academia_id=? AND u.nivel_acesso='aluno' FOR UPDATE");
            $stmt->execute([$alunoId,$academiaId]);
            if (!$stmt->fetchColumn()) throw new DomainException('Aluno não encontrado nesta academia.');
            $stmt = $pdo->prepare("UPDATE alunos SET status_matricula=? WHERE usuario_id=? AND academia_id=?");
            $stmt->execute([$status,$alunoId,$academiaId]);
            $stmt = $pdo->prepare("UPDATE usuarios SET status=? WHERE id=? AND academia_id=? AND nivel_acesso='aluno'");
            $stmt->execute([$status === 'ativa' ? 'ativo' : 'inativo',$alunoId,$academiaId]);
            $pdo->commit(); adminRedirecionar('ok','Situação da matrícula atualizada.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (!($e instanceof DomainException)) error_log('GymControl matrícula: '.$e->getMessage());
            adminRedirecionar('erro',$e instanceof DomainException ? $e->getMessage() : 'Não foi possível atualizar a matrícula.');
        }
    }
    if ($acao === 'excluir_login_funcionario') {
        $id = filter_var($_POST['funcionario_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id === $usuarioLogado) adminRedirecionar('erro','Não é possível excluir o próprio login.');
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id,nome,status FROM usuarios WHERE id=? AND academia_id=? AND nivel_acesso IN ('recepcao','loja','professor','admin') FOR UPDATE");
            $stmt->execute([$id,$academiaId]);
            $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$funcionario) throw new DomainException('Funcionário não encontrado nesta academia.');
            if ($funcionario['status'] !== 'ativo') throw new DomainException('Este funcionário já está sem acesso ativo.');
            // Revoga login sem DELETE: outras tabelas referenciam o usuário com ON DELETE RESTRICT.
            // Credencial anterior não volta a funcionar mesmo com reativação acidental do status.
            $senhaInutilizada = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET status='inativo', senha=? WHERE id=? AND academia_id=?");
            $stmt->execute([$senhaInutilizada,$id,$academiaId]);
            // Um professor removido não pode permanecer como responsável principal por alunos.
            $stmt = $pdo->prepare('UPDATE alunos SET professor_id=NULL WHERE academia_id=? AND professor_id=?');
            $stmt->execute([$academiaId,$id]);
            $pdo->commit(); adminRedirecionar('ok','Login de ' . $funcionario['nome'] . ' removido. Histórico cadastral e operacional preservado.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (!($e instanceof DomainException)) error_log('GymControl remover login: '.$e->getMessage());
            adminRedirecionar('erro',$e instanceof DomainException ? $e->getMessage() : 'Não foi possível remover o login.');
        }
    }
    adminRedirecionar('erro','Ação inválida.');
}

$usuarios=[]; $professores=[]; $alunos=[]; $matriculas=[];
try {
    $stmt=$pdo->prepare('SELECT id,nome,email,matricula,nivel_acesso,status,criado_em FROM usuarios WHERE academia_id=? ORDER BY id DESC');
    $stmt->execute([$academiaId]); $usuarios=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt=$pdo->prepare("SELECT id,nome FROM usuarios WHERE academia_id=? AND nivel_acesso='professor' AND status='ativo' ORDER BY nome");
    $stmt->execute([$academiaId]); $professores=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt=$pdo->prepare("SELECT u.id,u.nome,a.professor_id,p.nome AS nome_professor,a.status_matricula
        FROM usuarios u JOIN alunos a ON a.usuario_id=u.id AND a.academia_id=u.academia_id
        LEFT JOIN usuarios p ON p.id=a.professor_id AND p.academia_id=a.academia_id
        WHERE u.academia_id=? AND u.nivel_acesso='aluno' ORDER BY u.nome");
    $stmt->execute([$academiaId]); $alunos=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $matriculas=$alunos;
} catch (PDOException $e) {
    error_log('GymControl admin consultas: '.$e->getMessage());
    $erro='Não foi possível consultar os usuários. Confira a estrutura do banco de dados.';
}
$funcionarios=array_values(array_filter($usuarios,static fn($u)=>in_array($u['nivel_acesso'],['admin','professor','recepcao','loja'],true)));
$alunosAssociados=array_values(array_filter($alunos,static fn($u)=>$u['professor_id']!==null));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Administração | GymControl</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--ink:#122139;--muted:#65758d;--brand:#2563eb;--brand-dark:#1d4ed8;--line:#e0e7ef;--surface:#fff;--bg:#f3f6fb;--green:#067647;--radius:18px}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font:14px/1.5 Inter,system-ui,-apple-system,Segoe UI,sans-serif;color:var(--ink)}button,input,select{font:inherit}button{cursor:pointer}a{text-decoration:none;color:inherit}
.navbar{background:#101d33;color:white;min-height:74px}.nav-wrap{max-width:1260px;margin:auto;padding:15px 26px;display:flex;align-items:center;justify-content:space-between;gap:14px}.brand{display:flex;align-items:center;gap:11px;font-size:19px;font-weight:800}.brand-mark{background:linear-gradient(135deg,#2563eb,#38bdf8);padding:10px;border-radius:12px;font-size:14px}.nav-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.nav-user{color:#cbd5e1;font-size:13px}.nav-user strong{color:white}.nav-link{border:1px solid #52627a;border-radius:10px;padding:8px 13px;font-weight:600;font-size:13px}
.page{max-width:1260px;margin:auto;padding:30px 26px 70px}.eyebrow{font-weight:800;letter-spacing:.1em;font-size:11px;color:#2563eb;text-transform:uppercase;margin:0 0 8px}.page-title{font-size:clamp(25px,3vw,34px);letter-spacing:-.045em;line-height:1.15;margin:0}.page-sub{margin:10px 0 0;color:var(--muted);max-width:800px}.top{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:25px}.step-note{background:#eaf2ff;color:#1d4ed8;border:1px solid #c8d9ff;padding:10px 15px;border-radius:12px;font-size:12px;font-weight:700;white-space:nowrap}
.layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,1fr);gap:22px;align-items:start}.panel{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);box-shadow:0 8px 28px rgba(23,42,68,.04);overflow:hidden}.panel-heading{padding:23px 26px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:13px}.panel-heading h2{font-size:18px;margin:0;line-height:1.3;letter-spacing:-.02em}.panel-heading p{color:var(--muted);margin:3px 0 0;font-size:12px}.heading-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#ebf2ff;color:#2563eb;font-size:18px;flex-shrink:0}.form-body{padding:24px 26px 28px}.section-heading{font-size:12px;letter-spacing:.08em;font-weight:800;text-transform:uppercase;color:#405370;margin:2px 0 15px;display:flex;gap:8px;align-items:center}.section-heading:not(:first-child){padding-top:23px;border-top:1px solid var(--line);margin-top:25px}.fields{display:grid;grid-template-columns:1fr 1fr;gap:16px}.field{min-width:0}.field.wide{grid-column:1/-1}.field label{display:block;font-size:13px;font-weight:700;margin-bottom:7px}.field input,.field select{width:100%;height:46px;border:1px solid #cbd5e1;background:#fff;border-radius:10px;padding:0 13px;outline:0;color:#16263e;transition:.15s}.field input:focus,.field select:focus,.search-input:focus{border-color:#2563eb;box-shadow:0 0 0 3px #2563eb1c}.field input::placeholder{color:#94a3b8}.field small{color:var(--muted);font-size:11px;display:block;margin-top:5px}.input-password{position:relative}.input-password input{padding-right:46px}.toggle-password{position:absolute;right:7px;top:6px;border:0;background:#eff4fa;border-radius:8px;width:34px;height:34px;color:#4c6381}.policy{margin:25px 0 18px;border:1px solid #d7e7fb;background:#f2f8ff;padding:13px 15px;border-radius:12px;color:#335176;font-size:12px;display:flex;gap:10px}.policy i{color:#2563eb;margin-top:2px}.submit{width:100%;height:49px;border:0;background:var(--brand);color:white;font-size:14px;font-weight:800;border-radius:11px;display:flex;justify-content:center;align-items:center;gap:10px}.submit:hover{background:var(--brand-dark)}.submit:disabled{opacity:.6;cursor:wait}.notice{border-radius:12px;border:1px solid;padding:13px 15px;margin-bottom:20px;font-size:13px;font-weight:600}.notice.error{background:#fff1f2;color:#b42318;border-color:#fecdd3}.notice.success{background:#ecfdf3;color:#067647;border-color:#abefc6}
.sidebar{display:flex;flex-direction:column;gap:20px}.sidebar-body{padding:21px 23px}.search-row{display:flex;gap:9px}.search-input{height:45px;min-width:0;flex:1;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;outline:0}.small-btn{background:#2563eb;color:#fff;border:0;border-radius:10px;padding:0 18px;font-weight:700}.result-box{margin-top:15px;display:none}.result-item{border:1px solid var(--line);border-radius:10px;padding:12px;margin-top:9px}.result-item strong{display:block}.result-item small{display:block;color:var(--muted);margin-top:3px}.chip{display:inline-flex;border-radius:20px;background:#fff7ed;color:#9a3412;padding:3px 9px;font-size:11px;font-weight:800}.chip.active{background:#ecfdf3;color:#047857}.recent-list{list-style:none;padding:0;margin:0}.recent-item{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid var(--line)}.recent-item:last-child{border:0}.avatar{background:#e9f1ff;color:#2563eb;display:grid;place-items:center;width:39px;height:39px;border-radius:12px;font-weight:800;flex-shrink:0}.recent-copy{flex:1;min-width:0}.recent-copy strong{display:block;overflow-wrap:anywhere}.recent-copy small{display:block;color:var(--muted);font-size:11px}.recent-status{font-size:11px;font-weight:800;color:#9a3412;background:#fff7ed;border-radius:20px;padding:4px 8px}.camera-toggle{background:#f5f8fc;border:1px solid var(--line);width:100%;text-align:left;border-radius:12px;padding:13px 15px;display:flex;align-items:center;justify-content:space-between;font-weight:700}.camera-section{display:none;margin-top:14px}.camera-section.open{display:block}.camera-frame{background:#101d33;aspect-ratio:16/10;border-radius:12px;overflow:hidden;display:grid;place-items:center;position:relative}.camera-frame video{width:100%;height:100%;object-fit:cover}.camera-frame .camera-hint{position:absolute;pointer-events:none;color:#fff;background:#111e33bd;padding:7px 11px;border-radius:8px;font-size:12px}.camera-buttons{display:flex;gap:10px;margin-top:10px}.camera-buttons button{flex:1;border:1px solid #bfd2ed;background:#edf4ff;color:#1d4ed8;border-radius:9px;padding:9px;font-weight:700}.camera-buttons button:last-child{background:#2563eb;color:white;border-color:#2563eb}.muted{font-size:12px;color:var(--muted);margin:11px 0 0}.empty{color:var(--muted);font-size:13px;margin:0}.footer-note{text-align:center;color:#8390a2;font-size:12px;margin:30px 0 0}
@media(max-width:960px){.layout{grid-template-columns:1fr}.step-note{display:none}}@media(max-width:600px){.page{padding:23px 14px 50px}.nav-wrap{padding:13px 15px}.brand{font-size:16px}.nav-user{display:none}.panel-heading,.form-body,.sidebar-body{padding:18px}.fields{grid-template-columns:1fr}.field.wide{grid-column:1}.top{margin-bottom:20px}.nav-link{padding:7px 9px}.search-row{flex-wrap:wrap}.small-btn{height:42px}.recent-item{align-items:flex-start}.recent-status{max-width:95px;text-align:center}}

/* Extensões administrativas; identidade visual da recepção preservada */
.admin-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,1fr);gap:22px;align-items:start}.admin-stack{display:flex;flex-direction:column;gap:22px}.admin-full{margin-top:22px}.admin-panel-body{padding:23px 26px}.wide-actions{display:flex;flex-wrap:wrap;gap:10px}.action-button{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:12px 15px;border:0;border-radius:10px;color:#fff;background:#2563eb;font-weight:800;cursor:pointer}.action-button:hover{background:#1d4ed8}.action-button.danger{background:#b42318}.action-button.danger:hover{background:#912018}.action-button.soft{color:#1d4ed8;background:#eaf2ff}.action-button.soft:hover{background:#d7e8ff}.action-button:disabled{opacity:.5;cursor:not-allowed}.muted-line{font-size:12px;color:var(--muted);margin:10px 0 0}.data-table{border-collapse:collapse;width:100%;font-size:13px;min-width:730px}.data-table th{text-align:left;padding:13px 14px;color:#526681;background:#f5f8fc;border-bottom:1px solid var(--line);font-size:11px;text-transform:uppercase;letter-spacing:.045em}.data-table td{padding:14px;border-bottom:1px solid var(--line);vertical-align:middle}.table-scroll{overflow-x:auto}.table-user{font-weight:700;color:var(--ink)}.table-sub{color:var(--muted);font-size:11px;display:block;margin-top:3px;overflow-wrap:anywhere}.pill{display:inline-block;border-radius:99px;padding:5px 9px;font-size:11px;font-weight:800;background:#e9f1ff;color:#1d4ed8}.pill.green{background:#ecfdf3;color:#067647}.pill.red{background:#fff1f2;color:#b42318}.pill.amber{background:#fff7ed;color:#9a3412}.inline-form{margin:0}.mini-danger{border:1px solid #fecdd3;color:#b42318;background:#fff1f2;padding:8px 10px;border-radius:9px;font-size:12px;font-weight:800;cursor:pointer}.mini-danger:hover{background:#ffe4e6}.empty-state{color:var(--muted);text-align:center;padding:22px}.input-password{position:relative}.field select:disabled{opacity:.5}.helper-box{margin-top:15px;border:1px solid #d7e7fb;background:#f2f8ff;padding:13px;border-radius:12px;color:#335176;font-size:12px}.top-links{display:flex;gap:8px;flex-wrap:wrap}.field .inline-checkbox{display:flex;gap:8px;align-items:center}.inline-check{display:flex;gap:8px;align-items:center;font-size:13px;font-weight:600}.inline-check input{width:auto;height:auto}.form-actions{padding-top:18px}.danger-note{border:1px solid #fecdd3;background:#fff1f2;color:#9c241d;padding:12px;border-radius:10px;font-size:12px;margin-bottom:14px}
@media(max-width:960px){.admin-layout{grid-template-columns:1fr}}@media(max-width:600px){.admin-panel-body{padding:18px}.top-links{width:100%}.action-button{width:100%}}
</style></head>
<body>
<header class="navbar"><div class="nav-wrap">
  <a class="brand" href="../index.php"><span class="brand-mark">GC</span><span>GymControl <span style="color:#82b9ff;font-size:12px;font-weight:600">/ Administração</span></span></a>
  <div class="nav-actions"><span class="nav-user">Olá, <strong><?= adminEsc($_SESSION['usuario_nome'] ?? 'Administrador') ?></strong></span>
    <a class="nav-link" href="recepcao.php">Recepção</a><a class="nav-link" href="loja.php">Loja</a>
    <a class="nav-link" href="personalização.php">Personalização</a>
    <a class="nav-link" href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
  </div>
</div></header>
<main class="page">
<?php renderizarAvisoPermissao(); ?>
<div class="top"><div><p class="eyebrow">Gestão de contas e matrículas</p><h1 class="page-title">Painel de Administração</h1><p class="page-sub">Credencial de login desta academia: <strong><?= adminEsc($credencialAcademia) ?></strong></p><p class="page-sub">Cadastre pessoas, gerencie o vínculo dos professores, aprove matrículas e controle os acessos da sua academia.</p></div><span class="step-note"><i class="fa-solid fa-shield-halved"></i> Acesso administrativo</span></div>
<?php if ($erro): ?><div class="notice error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?= adminEsc($erro) ?></div><?php endif; ?>
<?php if ($mensagem): ?><div class="notice success" role="status"><i class="fa-solid fa-circle-check"></i> <?= adminEsc($mensagem) ?></div><?php endif; ?>
<div class="admin-layout">
<section class="panel" aria-labelledby="novoUsuario"><div class="panel-heading"><div class="heading-icon"><i class="fa-solid fa-user-plus"></i></div><div><h2 id="novoUsuario">Cadastrar usuário</h2><p>Crie uma conta para aluno ou colaborador da academia.</p></div></div>
<form method="post" action="index.php" class="form-body" id="formUsuario">
<input type="hidden" name="csrf_token" value="<?= adminEsc($csrfAdmin) ?>"><input type="hidden" name="acao" value="criar_usuario">
<div class="section-heading"><i class="fa-solid fa-address-card"></i> Dados pessoais</div><div class="fields">
<div class="field wide"><label for="nome">Nome completo *</label><input id="nome" name="nome" required minlength="3" maxlength="100" autocomplete="name" placeholder="Ex.: Mariana Oliveira Silva"></div>
<div class="field wide"><label for="email">E-mail de acesso *</label><input id="email" name="email" type="email" required maxlength="100" autocomplete="email" placeholder="usuario@exemplo.com"></div>
</div>
<div class="section-heading"><i class="fa-solid fa-id-card"></i> Vínculo com a academia</div><div class="fields">
<div class="field"><label for="matricula">Matrícula / ID funcional</label><input id="matricula" name="matricula" maxlength="30" placeholder="Ex.: REC002 (opcional)"></div>
<div class="field"><label for="nivel">Nível de acesso *</label><select id="nivel" name="nivel_acesso" required><option value="aluno">Aluno</option><option value="recepcao">Atendente / recepção</option><option value="loja">Loja / PDV</option><option value="professor">Professor</option><option value="admin">Administrador</option></select></div>
</div>
<div class="section-heading"><i class="fa-solid fa-lock"></i> Credenciais</div><div class="fields"><div class="field wide"><label for="senha">Senha inicial *</label><div class="input-password"><input id="senha" name="senha" type="password" minlength="8" maxlength="72" autocomplete="new-password" required placeholder="Mínimo de 8 caracteres"><button type="button" class="toggle-password" id="mostrarSenha" aria-label="Mostrar senha"><i class="fa-regular fa-eye"></i></button></div><small>A senha será armazenada com hash de segurança.</small></div></div>
<div class="policy"><i class="fa-solid fa-circle-info"></i><span>Alunos criados aqui recebem matrícula ativa. Para cadastrar alunos com aprovação pendente, use a <a href="recepcao.php" style="color:#1d4ed8;text-decoration:underline;font-weight:700">Recepção</a>.</span></div>
<button class="submit" type="submit" id="salvarUsuario"><i class="fa-solid fa-user-check"></i> Criar conta</button></form></section>
<div class="admin-stack">
<section class="panel"><div class="panel-heading"><div class="heading-icon"><i class="fa-solid fa-link"></i></div><div><h2>Associar professor</h2><p>Defina o profissional responsável pelo aluno.</p></div></div>
<form class="form-body" method="post"><input type="hidden" name="csrf_token" value="<?= adminEsc($csrfAdmin) ?>"><input type="hidden" name="acao" value="vincular_professor">
<div class="fields"><div class="field wide"><label for="alunoVinc">Aluno</label><select name="aluno_id" id="alunoVinc" required><option value="">Selecione um aluno</option><?php foreach ($alunos as $a): ?><option value="<?= (int) $a['id'] ?>"><?= adminEsc($a['nome'] . ($a['nome_professor'] ? ' · Atual: '.$a['nome_professor'] : ' · Sem professor')) ?></option><?php endforeach; ?></select></div>
<div class="field wide"><label for="professorVinc">Professor</label><select name="professor_id" id="professorVinc" required><option value="">Selecione um professor</option><?php foreach ($professores as $p): ?><option value="<?= (int)$p['id'] ?>"><?= adminEsc($p['nome']) ?></option><?php endforeach; ?></select></div></div>
<div class="form-actions"><button type="submit" class="submit" <?= !$alunos || !$professores ? 'disabled' : '' ?>><i class="fa-solid fa-link"></i> Salvar associação</button></div></form></section>
<section class="panel"><div class="panel-heading"><div class="heading-icon"><i class="fa-solid fa-link-slash"></i></div><div><h2>Desassociar aluno</h2><p>Retire o professor sem excluir a matrícula ou os treinos.</p></div></div>
<form class="form-body" method="post" id="formDesassociar"><input type="hidden" name="csrf_token" value="<?= adminEsc($csrfAdmin) ?>"><input type="hidden" name="acao" value="desassociar_aluno"><div class="field"><label for="alunoDesvinc">Aluno com professor associado</label><select name="aluno_id" id="alunoDesvinc" required><option value="">Selecione um aluno</option><?php foreach ($alunosAssociados as $a): ?><option value="<?= (int)$a['id'] ?>"><?= adminEsc($a['nome'].' · '.$a['nome_professor']) ?></option><?php endforeach; ?></select></div>
<p class="muted-line">O aluno continuará matriculado. O histórico de treinos e avaliações não será apagado.</p><div class="form-actions"><button type="submit" class="action-button soft" style="width:100%" <?= !$alunosAssociados ? 'disabled' : '' ?>><i class="fa-solid fa-unlink"></i> Desassociar professor</button></div></form></section>
<section class="panel"><div class="panel-heading"><div class="heading-icon"><i class="fa-solid fa-clipboard-check"></i></div><div><h2>Situação da matrícula</h2><p>Aprove ou bloqueie o acesso do aluno.</p></div></div>
<form class="form-body" method="post"><input type="hidden" name="csrf_token" value="<?= adminEsc($csrfAdmin) ?>"><input type="hidden" name="acao" value="alterar_matricula">
<div class="fields"><div class="field wide"><label for="alunoMatricula">Aluno</label><select id="alunoMatricula" name="aluno_id" required><option value="">Selecione um aluno</option><?php foreach ($matriculas as $a): ?><option value="<?= (int)$a['id'] ?>"><?= adminEsc($a['nome'].' · '.ucfirst($a['status_matricula'])) ?></option><?php endforeach; ?></select></div><div class="field wide"><label for="statusMatricula">Nova situação</label><select id="statusMatricula" name="status_matricula" required><option value="ativa">Ativa (liberar login)</option><option value="pendente">Pendente (sem login)</option><option value="bloqueada">Bloqueada (sem login)</option></select></div></div><div class="form-actions"><button class="submit" type="submit" <?= !$matriculas ? 'disabled' : '' ?>>Salvar matrícula</button></div></form></section>
</div></div>
<section class="panel admin-full"><div class="panel-heading"><div class="heading-icon"><i class="fa-solid fa-user-lock"></i></div><div><h2>Login de funcionários</h2><p>Remova credenciais sem destruir o histórico de atendimento, aulas e transações.</p></div></div>
<div class="admin-panel-body"><div class="danger-note"><i class="fa-solid fa-triangle-exclamation"></i> <strong>Excluir login</strong> bloqueia a conta imediatamente, invalida a senha atual e desvincula alunos do professor removido. O registro do funcionário permanece no banco para preservar referências e histórico. Não é uma exclusão física do cadastro.</div>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Funcionário</th><th>Função</th><th>Situação</th><th>Ação</th></tr></thead><tbody>
<?php foreach ($funcionarios as $f): ?><tr><td><span class="table-user"><?= adminEsc($f['nome']) ?></span><span class="table-sub"><?= adminEsc($f['email']) ?></span></td><td><span class="pill"><?= adminEsc(ucfirst($f['nivel_acesso'])) ?></span></td><td><span class="pill <?= $f['status']==='ativo'?'green':'red' ?>"><?= $f['status']==='ativo'?'Ativo':'Inativo' ?></span></td><td><?php if ((int)$f['id']===$usuarioLogado): ?><span class="table-sub">Sua conta</span><?php elseif ($f['status']!=='ativo'): ?><span class="table-sub">Sem login ativo</span><?php else: ?><form method="post" class="inline-form excluir-login" data-nome="<?= adminEsc($f['nome']) ?>"><input type="hidden" name="csrf_token" value="<?= adminEsc($csrfAdmin) ?>"><input type="hidden" name="acao" value="excluir_login_funcionario"><input type="hidden" name="funcionario_id" value="<?= (int)$f['id'] ?>"><button type="submit" class="mini-danger"><i class="fa-solid fa-user-xmark"></i> Excluir login</button></form><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$funcionarios): ?><tr><td colspan="4" class="empty-state">Nenhum funcionário encontrado.</td></tr><?php endif; ?></tbody></table></div></div></section>
<section class="panel admin-full"><div class="panel-heading"><div class="heading-icon"><i class="fa-solid fa-users"></i></div><div><h2>Contas cadastradas</h2><p>Visão geral dos usuários desta academia.</p></div></div>
<div class="table-scroll"><table class="data-table"><thead><tr><th>ID</th><th>Nome</th><th>Matrícula</th><th>Nível</th><th>Status</th></tr></thead><tbody>
<?php foreach ($usuarios as $u): ?><tr><td><?= (int)$u['id'] ?></td><td><span class="table-user"><?= adminEsc($u['nome']) ?></span><span class="table-sub"><?= adminEsc($u['email']) ?></span></td><td><?= adminEsc($u['matricula'] ?: '—') ?></td><td><span class="pill"><?= adminEsc(ucfirst($u['nivel_acesso'])) ?></span></td><td><span class="pill <?= $u['status']==='ativo'?'green':'red' ?>"><?= $u['status']==='ativo'?'Ativo':'Inativo' ?></span></td></tr><?php endforeach; ?>
<?php if (!$usuarios): ?><tr><td colspan="5" class="empty-state">Nenhum usuário cadastrado.</td></tr><?php endif; ?></tbody></table></div></section>
<p class="footer-note">GymControl · Administração · Acesso restrito à academia autenticada</p>
</main>
<script>
'use strict';
document.getElementById('mostrarSenha').addEventListener('click',()=>{const input=document.getElementById('senha');const mostra=input.type==='password';input.type=mostra?'text':'password';document.getElementById('mostrarSenha').setAttribute('aria-label',mostra?'Ocultar senha':'Mostrar senha');});
document.getElementById('formUsuario').addEventListener('submit',()=>{const b=document.getElementById('salvarUsuario');b.disabled=true;b.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Salvando conta...';});
document.getElementById('formDesassociar').addEventListener('submit',e=>{if(!confirm('Desassociar este aluno do professor? A matrícula será preservada.'))e.preventDefault();});
document.querySelectorAll('.excluir-login').forEach(form=>form.addEventListener('submit',e=>{if(!confirm('Remover o login de '+form.dataset.nome+'? O acesso será bloqueado, a senha invalidada e o histórico preservado.'))e.preventDefault();}));
</script></body></html>
