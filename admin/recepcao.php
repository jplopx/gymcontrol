<?php
// admin/recepcao.php - Balcão & Recepção GymControl
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['recepcao', 'admin']);
require_once __DIR__ . '/../config/conexao.php';

$academiaId = (int) $_SESSION['academia_id'];
$usuarioAtendente = (int) $_SESSION['usuario_id'];
if (empty($_SESSION['csrf_recepcao'])) $_SESSION['csrf_recepcao'] = bin2hex(random_bytes(32));
$erroRecepcao = '';
$sucessoRecepcao = $_SESSION['sucesso_recepcao'] ?? '';
unset($_SESSION['sucesso_recepcao']);

// Consulta somente matrículas da academia autenticada; não representa validação financeira ou catraca.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['acao_ajax'] ?? '') === 'buscar_aluno') {
    header('Content-Type: application/json; charset=utf-8');
    $termo = trim((string) ($_GET['q'] ?? ''));
    if ($termo === '' || mb_strlen($termo) > 100) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Informe nome, CPF ou matrícula com até 100 caracteres.']);
        exit;
    }
    $cpfBusca = preg_replace('/\D/', '', $termo);
    if (strlen($cpfBusca) === 11) {
        $cpfBusca = substr($cpfBusca, 0, 3) . '.' . substr($cpfBusca, 3, 3) . '.' . substr($cpfBusca, 6, 3) . '-' . substr($cpfBusca, 9, 2);
    }
    $stmt = $pdo->prepare("SELECT u.id,u.nome,u.status,a.status_matricula
        FROM alunos a JOIN usuarios u ON u.id = a.usuario_id AND u.academia_id = a.academia_id
        WHERE a.academia_id = :academia AND (u.nome LIKE :nome OR a.cpf = :cpf OR u.matricula = :matricula)
        ORDER BY u.nome LIMIT 8");
    $stmt->execute([':academia' => $academiaId, ':nome' => '%' . str_replace(['%', '_'], ['\\%', '\\_'], $termo) . '%',
                    ':cpf' => $cpfBusca, ':matricula' => $termo]);
    echo json_encode(['ok' => true, 'alunos' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastrar_aluno') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $cpf = preg_replace('/\D/', '', (string) ($_POST['cpf'] ?? ''));
    $telefone = preg_replace('/\D/', '', (string) ($_POST['telefone'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
    $senha = (string) ($_POST['senha'] ?? '');
    $plano = trim((string) ($_POST['plano'] ?? ''));
    $pagamento = trim((string) ($_POST['forma_pagamento'] ?? ''));

    if (!hash_equals($_SESSION['csrf_recepcao'], (string) ($_POST['csrf_token'] ?? ''))) {
        $erroRecepcao = 'Sua sessão do formulário expirou. Recarregue a página antes de tentar novamente.';
    } elseif (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
        $erroRecepcao = 'Informe o nome completo do aluno (entre 3 e 100 caracteres).';
    } elseif (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        $erroRecepcao = 'Informe um CPF com 11 dígitos válidos.';
    } elseif (strlen($telefone) !== 10 && strlen($telefone) !== 11) {
        $erroRecepcao = 'Informe um telefone com DDD (10 ou 11 dígitos).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $erroRecepcao = 'Informe um endereço de e-mail válido de até 100 caracteres.';
    } elseif (strlen($senha) < 8 || strlen($senha) > 72) {
        $erroRecepcao = 'A senha inicial deve ter entre 8 e 72 caracteres.';
    } elseif (!in_array($plano, ['Mensal', 'Semestral', 'Anual VIP'], true)) {
        $erroRecepcao = 'Selecione um plano de matrícula válido.';
    } elseif (!in_array($pagamento, ['Cartao', 'PIX', 'Dinheiro'], true)) {
        $erroRecepcao = 'Selecione uma forma de pagamento válida.';
    } else {
        $cpfFormatado = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        $telefoneFormatado = strlen($telefone) === 11
            ? '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7)
            : '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
        try {
            // Verificação amigável. Os índices UNIQUE do banco continuam protegendo de concorrência.
            $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE academia_id = ? AND email = ? LIMIT 1');
            $stmt->execute([$academiaId, $email]);
            if ($stmt->fetchColumn()) {
                $erroRecepcao = 'Já existe uma conta com este e-mail nesta academia.';
            } else {
                $stmt = $pdo->prepare('SELECT 1 FROM alunos WHERE academia_id = ? AND cpf = ? LIMIT 1');
                $stmt->execute([$academiaId, $cpfFormatado]);
                if ($stmt->fetchColumn()) $erroRecepcao = 'Este CPF já possui cadastro nesta academia.';
            }
            if (!$erroRecepcao) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO usuarios (academia_id,nome,email,senha,nivel_acesso,tipo,status)
                    VALUES (?,?,?,?,'aluno','aluno','inativo')");
                $stmt->execute([$academiaId, $nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
                $novoAlunoId = (int) $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO alunos (academia_id,usuario_id,cpf,telefone,status_matricula)
                    VALUES (?,?,?,?,'pendente')");
                $stmt->execute([$academiaId, $novoAlunoId, $cpfFormatado, $telefoneFormatado]);
                $stmt = $pdo->prepare('INSERT INTO recepcao_matriculas (academia_id,aluno_id,plano,forma_pagamento_prevista,registrado_por) VALUES (?,?,?,?,?)');
                $stmt->execute([$academiaId, $novoAlunoId, $plano, $pagamento, $usuarioAtendente]);
                $pdo->commit();
                $_SESSION['sucesso_recepcao'] = 'Aluno ' . $nome . ' cadastrado com sucesso! Matrícula aguardando aprovação da administração.';
                header('Location: recepcao.php');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Cadastro recepção GymControl: ' . $e->getMessage());
            $codigo = (string) ($e->errorInfo[1] ?? '');
            if ($codigo === '1062') {
                $erroRecepcao = 'Já existe um cadastro com este e-mail ou CPF nesta academia.';
            } elseif ($codigo === '1146' || $codigo === '1054') {
                $erroRecepcao = 'O banco de dados está desatualizado para a recepção. Verifique o arquivo database/ATUALIZACAO_RECEPCAO.sql e o esquema instalado.';
            } else {
                $erroRecepcao = 'Não foi possível concluir o cadastro. Confira a conexão e tente novamente.';
            }
        }
    }
}
// Preserva os valores após uma validação malsucedida, sem reapresentar a senha.
$valoresFormulario = [
    'nome' => $nome ?? '', 'cpf' => $_POST['cpf'] ?? '', 'telefone' => $_POST['telefone'] ?? '',
    'email' => $email ?? '', 'plano' => $plano ?? 'Mensal', 'forma_pagamento' => $pagamento ?? 'PIX'
];
function campoRecepcao($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
$cadastrosRecentes = [];
try {
    $stmt = $pdo->prepare('SELECT u.nome,rm.plano,rm.criado_em,a.status_matricula
        FROM recepcao_matriculas rm JOIN usuarios u ON u.id=rm.aluno_id
        JOIN alunos a ON a.usuario_id=u.id
        WHERE rm.academia_id=? AND u.academia_id=? ORDER BY rm.id DESC LIMIT 10');
    $stmt->execute([$academiaId,$academiaId]);
    $cadastrosRecentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Histórico recepção: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recepção e Matrículas | GymControl</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--ink:#122139;--muted:#65758d;--brand:#2563eb;--brand-dark:#1d4ed8;--line:#e0e7ef;--surface:#fff;--bg:#f3f6fb;--green:#067647;--radius:18px}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font:14px/1.5 Inter,system-ui,-apple-system,Segoe UI,sans-serif;color:var(--ink)}button,input,select{font:inherit}button{cursor:pointer}a{text-decoration:none;color:inherit}
.navbar{background:#101d33;color:white;min-height:74px}.nav-wrap{max-width:1260px;margin:auto;padding:15px 26px;display:flex;align-items:center;justify-content:space-between;gap:14px}.brand{display:flex;align-items:center;gap:11px;font-size:19px;font-weight:800}.brand-mark{background:linear-gradient(135deg,#2563eb,#38bdf8);padding:10px;border-radius:12px;font-size:14px}.nav-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.nav-user{color:#cbd5e1;font-size:13px}.nav-user strong{color:white}.nav-link{border:1px solid #52627a;border-radius:10px;padding:8px 13px;font-weight:600;font-size:13px}
.page{max-width:1260px;margin:auto;padding:30px 26px 70px}.eyebrow{font-weight:800;letter-spacing:.1em;font-size:11px;color:#2563eb;text-transform:uppercase;margin:0 0 8px}.page-title{font-size:clamp(25px,3vw,34px);letter-spacing:-.045em;line-height:1.15;margin:0}.page-sub{margin:10px 0 0;color:var(--muted);max-width:800px}.top{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:25px}.step-note{background:#eaf2ff;color:#1d4ed8;border:1px solid #c8d9ff;padding:10px 15px;border-radius:12px;font-size:12px;font-weight:700;white-space:nowrap}
.layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,1fr);gap:22px;align-items:start}.panel{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);box-shadow:0 8px 28px rgba(23,42,68,.04);overflow:hidden}.panel-heading{padding:23px 26px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:13px}.panel-heading h2{font-size:18px;margin:0;line-height:1.3;letter-spacing:-.02em}.panel-heading p{color:var(--muted);margin:3px 0 0;font-size:12px}.heading-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#ebf2ff;color:#2563eb;font-size:18px;flex-shrink:0}.form-body{padding:24px 26px 28px}.section-heading{font-size:12px;letter-spacing:.08em;font-weight:800;text-transform:uppercase;color:#405370;margin:2px 0 15px;display:flex;gap:8px;align-items:center}.section-heading:not(:first-child){padding-top:23px;border-top:1px solid var(--line);margin-top:25px}.fields{display:grid;grid-template-columns:1fr 1fr;gap:16px}.field{min-width:0}.field.wide{grid-column:1/-1}.field label{display:block;font-size:13px;font-weight:700;margin-bottom:7px}.field input,.field select{width:100%;height:46px;border:1px solid #cbd5e1;background:#fff;border-radius:10px;padding:0 13px;outline:0;color:#16263e;transition:.15s}.field input:focus,.field select:focus,.search-input:focus{border-color:#2563eb;box-shadow:0 0 0 3px #2563eb1c}.field input::placeholder{color:#94a3b8}.field small{color:var(--muted);font-size:11px;display:block;margin-top:5px}.input-password{position:relative}.input-password input{padding-right:46px}.toggle-password{position:absolute;right:7px;top:6px;border:0;background:#eff4fa;border-radius:8px;width:34px;height:34px;color:#4c6381}.policy{margin:25px 0 18px;border:1px solid #d7e7fb;background:#f2f8ff;padding:13px 15px;border-radius:12px;color:#335176;font-size:12px;display:flex;gap:10px}.policy i{color:#2563eb;margin-top:2px}.submit{width:100%;height:49px;border:0;background:var(--brand);color:white;font-size:14px;font-weight:800;border-radius:11px;display:flex;justify-content:center;align-items:center;gap:10px}.submit:hover{background:var(--brand-dark)}.submit:disabled{opacity:.6;cursor:wait}.notice{border-radius:12px;border:1px solid;padding:13px 15px;margin-bottom:20px;font-size:13px;font-weight:600}.notice.error{background:#fff1f2;color:#b42318;border-color:#fecdd3}.notice.success{background:#ecfdf3;color:#067647;border-color:#abefc6}
.sidebar{display:flex;flex-direction:column;gap:20px}.sidebar-body{padding:21px 23px}.search-row{display:flex;gap:9px}.search-input{height:45px;min-width:0;flex:1;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;outline:0}.small-btn{background:#2563eb;color:#fff;border:0;border-radius:10px;padding:0 18px;font-weight:700}.result-box{margin-top:15px;display:none}.result-item{border:1px solid var(--line);border-radius:10px;padding:12px;margin-top:9px}.result-item strong{display:block}.result-item small{display:block;color:var(--muted);margin-top:3px}.chip{display:inline-flex;border-radius:20px;background:#fff7ed;color:#9a3412;padding:3px 9px;font-size:11px;font-weight:800}.chip.active{background:#ecfdf3;color:#047857}.recent-list{list-style:none;padding:0;margin:0}.recent-item{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid var(--line)}.recent-item:last-child{border:0}.avatar{background:#e9f1ff;color:#2563eb;display:grid;place-items:center;width:39px;height:39px;border-radius:12px;font-weight:800;flex-shrink:0}.recent-copy{flex:1;min-width:0}.recent-copy strong{display:block;overflow-wrap:anywhere}.recent-copy small{display:block;color:var(--muted);font-size:11px}.recent-status{font-size:11px;font-weight:800;color:#9a3412;background:#fff7ed;border-radius:20px;padding:4px 8px}.camera-toggle{background:#f5f8fc;border:1px solid var(--line);width:100%;text-align:left;border-radius:12px;padding:13px 15px;display:flex;align-items:center;justify-content:space-between;font-weight:700}.camera-section{display:none;margin-top:14px}.camera-section.open{display:block}.camera-frame{background:#101d33;aspect-ratio:16/10;border-radius:12px;overflow:hidden;display:grid;place-items:center;position:relative}.camera-frame video{width:100%;height:100%;object-fit:cover}.camera-frame .camera-hint{position:absolute;pointer-events:none;color:#fff;background:#111e33bd;padding:7px 11px;border-radius:8px;font-size:12px}.camera-buttons{display:flex;gap:10px;margin-top:10px}.camera-buttons button{flex:1;border:1px solid #bfd2ed;background:#edf4ff;color:#1d4ed8;border-radius:9px;padding:9px;font-weight:700}.camera-buttons button:last-child{background:#2563eb;color:white;border-color:#2563eb}.muted{font-size:12px;color:var(--muted);margin:11px 0 0}.empty{color:var(--muted);font-size:13px;margin:0}.footer-note{text-align:center;color:#8390a2;font-size:12px;margin:30px 0 0}
@media(max-width:960px){.layout{grid-template-columns:1fr}.step-note{display:none}}@media(max-width:600px){.page{padding:23px 14px 50px}.nav-wrap{padding:13px 15px}.brand{font-size:16px}.nav-user{display:none}.panel-heading,.form-body,.sidebar-body{padding:18px}.fields{grid-template-columns:1fr}.field.wide{grid-column:1}.top{margin-bottom:20px}.nav-link{padding:7px 9px}.search-row{flex-wrap:wrap}.small-btn{height:42px}.recent-item{align-items:flex-start}.recent-status{max-width:95px;text-align:center}}
</style>
</head>
<body>
<header class="navbar"><div class="nav-wrap">
  <a class="brand" href="../index.php"><span class="brand-mark">GC</span><span>GymControl <span style="color:#82b9ff;font-size:12px;font-weight:600">/ Recepção</span></span></a>
  <div class="nav-actions"><span class="nav-user">Olá, <strong><?= campoRecepcao($_SESSION['usuario_nome'] ?? 'Atendente') ?></strong></span>
    <?php if (($_SESSION['usuario_nivel'] ?? '') === 'admin'): ?><a class="nav-link" href="index.php">Painel geral</a><?php endif; ?><a class="nav-link" href="loja.php">Loja &amp; PDV</a>
    <a class="nav-link" href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
  </div>
</div></header>
<main class="page">
<?php renderizarAvisoPermissao(); ?>
<div class="top"><div><p class="eyebrow">Gestão de matrículas</p><h1 class="page-title">Painel do Atendente</h1><p class="page-sub">Cadastre novos alunos, consulte matrículas e acompanhe registros recentes da sua academia.</p></div><div class="step-note"><i class="fa-solid fa-shield-halved"></i> Cadastro com aprovação</div></div>
<?php if ($erroRecepcao): ?><div class="notice error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?= campoRecepcao($erroRecepcao) ?></div><?php endif; ?>
<?php if ($sucessoRecepcao): ?><div class="notice success" role="status"><i class="fa-solid fa-circle-check"></i> <?= campoRecepcao($sucessoRecepcao) ?></div><?php endif; ?>
<div class="layout">
<section class="panel" aria-labelledby="tituloCadastro">
<div class="panel-heading"><div class="heading-icon"><i class="fa-solid fa-user-plus"></i></div><div><h2 id="tituloCadastro">Nova matrícula</h2><p>Preencha os dados para registrar um aluno na academia.</p></div></div>
<form method="POST" id="formMatricula" class="form-body" autocomplete="on">
<input type="hidden" name="acao" value="cadastrar_aluno"><input type="hidden" name="csrf_token" value="<?= campoRecepcao($_SESSION['csrf_recepcao']) ?>">
<div class="section-heading"><i class="fa-solid fa-address-card"></i> Dados pessoais</div>
<div class="fields">
<div class="field wide"><label for="nomeAluno">Nome completo *</label><input id="nomeAluno" name="nome" autocomplete="name" maxlength="100" required minlength="3" value="<?= campoRecepcao($valoresFormulario['nome']) ?>" placeholder="Ex.: Mariana Oliveira Silva"></div>
<div class="field"><label for="cpfAluno">CPF *</label><input id="cpfAluno" name="cpf" inputmode="numeric" maxlength="14" required value="<?= campoRecepcao($valoresFormulario['cpf']) ?>" placeholder="000.000.000-00"><small>Utilizado para identificar a matrícula.</small></div>
<div class="field"><label for="telefoneAluno">WhatsApp / telefone *</label><input id="telefoneAluno" name="telefone" type="tel" autocomplete="tel" maxlength="15" required value="<?= campoRecepcao($valoresFormulario['telefone']) ?>" placeholder="(21) 99999-9999"></div>
</div>
<div class="section-heading"><i class="fa-solid fa-credit-card"></i> Plano e pagamento previsto</div>
<div class="fields">
<div class="field"><label for="planoMatricula">Plano *</label><select id="planoMatricula" name="plano" required>
<?php foreach (['Mensal' => 'Mensal', 'Semestral' => 'Semestral', 'Anual VIP' => 'Anual VIP'] as $id => $rotulo): ?><option value="<?= campoRecepcao($id) ?>" <?= $valoresFormulario['plano'] === $id ? 'selected' : '' ?>><?= campoRecepcao($rotulo) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="formaPagamento">Forma de pagamento prevista *</label><select id="formaPagamento" name="forma_pagamento" required>
<?php foreach (['PIX' => 'PIX', 'Cartao' => 'Cartão', 'Dinheiro' => 'Dinheiro'] as $id => $rotulo): ?><option value="<?= campoRecepcao($id) ?>" <?= $valoresFormulario['forma_pagamento'] === $id ? 'selected' : '' ?>><?= campoRecepcao($rotulo) ?></option><?php endforeach; ?></select></div>
</div>
<div class="section-heading"><i class="fa-solid fa-lock"></i> Credenciais de acesso</div>
<div class="fields">
<div class="field wide"><label for="emailAluno">E-mail *</label><input type="email" id="emailAluno" name="email" autocomplete="email" maxlength="100" required placeholder="aluno@exemplo.com" value="<?= campoRecepcao($valoresFormulario['email']) ?>"></div>
<div class="field wide"><label for="senhaAluno">Senha inicial *</label><div class="input-password"><input type="password" id="senhaAluno" name="senha" autocomplete="new-password" minlength="8" maxlength="72" required placeholder="Mínimo de 8 caracteres"><button class="toggle-password" type="button" id="toggleSenha" aria-label="Mostrar senha"><i class="fa-regular fa-eye"></i></button></div><small>O acesso ficará bloqueado até a aprovação da matrícula.</small></div>
</div>
<div class="policy"><i class="fa-solid fa-circle-info"></i><span>O cadastro será salvo como <strong>pendente</strong>. A administração precisa aprovar a matrícula. Selecionar uma forma de pagamento não realiza nenhuma cobrança.</span></div>
<button class="submit" type="submit" id="btnCadastrar"><i class="fa-solid fa-user-check"></i> Cadastrar aluno</button>
</form></section>
<aside class="sidebar">
<section class="panel"><div class="panel-heading"><div class="heading-icon"><i class="fa-solid fa-magnifying-glass"></i></div><div><h2>Consultar aluno</h2><p>Pesquise por nome, CPF ou matrícula.</p></div></div>
<div class="sidebar-body"><div class="search-row"><input id="inputBusca" class="search-input" placeholder="Nome, CPF ou matrícula" maxlength="100" aria-label="Buscar aluno"><button type="button" class="small-btn" id="btnBuscar">Buscar</button></div><div id="resultadoBusca" class="result-box" aria-live="polite"></div><p class="muted">Consulta informativa; não verifica pagamentos nem libera catraca.</p></div></section>
<section class="panel"><div class="panel-heading"><div class="heading-icon"><i class="fa-solid fa-clock-rotate-left"></i></div><div><h2>Cadastros recentes</h2><p>Últimas matrículas registradas nesta academia.</p></div></div>
<div class="sidebar-body"><ul class="recent-list">
<?php if (!$cadastrosRecentes): ?><li class="empty">Ainda não há matrículas registradas pela recepção.</li><?php endif; ?>
<?php foreach ($cadastrosRecentes as $cadastro): ?><li class="recent-item"><span class="avatar"><?= campoRecepcao(mb_strtoupper(mb_substr($cadastro['nome'], 0, 1))) ?></span><span class="recent-copy"><strong><?= campoRecepcao($cadastro['nome']) ?></strong><small><?= campoRecepcao($cadastro['plano']) ?> · <?= campoRecepcao(date('d/m/Y H:i', strtotime($cadastro['criado_em']))) ?></small></span><span class="recent-status"><?= campoRecepcao(ucfirst($cadastro['status_matricula'])) ?></span></li><?php endforeach; ?>
</ul></div></section>
<section class="panel"><div class="panel-heading"><div class="heading-icon"><i class="fa-solid fa-camera"></i></div><div><h2>Câmera do balcão</h2><p>Prévia local opcional, sem biometria.</p></div></div><div class="sidebar-body"><button type="button" class="camera-toggle" id="abrirCamera"><span><i class="fa-solid fa-video"></i> Mostrar câmera</span><i class="fa-solid fa-chevron-down"></i></button><div class="camera-section" id="areaCamera"><div class="camera-frame"><video id="webcamRecepcao" autoplay playsinline muted></video><span class="camera-hint" id="cameraHint">Câmera desligada</span></div><div class="camera-buttons"><button type="button" id="ligarCamera">Ligar câmera</button><button type="button" id="tirarFoto">Capturar prévia</button></div><p class="muted" id="cameraStatus">Foto não é enviada nem salva no sistema.</p><canvas id="canvasSnap" hidden></canvas></div></div></section>
</aside>
</div><p class="footer-note">GymControl · Recepção · Acesso restrito à academia autenticada</p>
</main>
<script>
'use strict';
const cpfInput=document.getElementById('cpfAluno');
const telInput=document.getElementById('telefoneAluno');
cpfInput.addEventListener('input',()=>{const d=cpfInput.value.replace(/\D/g,'').slice(0,11);cpfInput.value=d.replace(/^(\d{3})(\d)/,'$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1-$2')});
telInput.addEventListener('input',()=>{const d=telInput.value.replace(/\D/g,'').slice(0,11);if(d.length>6){const size=d.length>10?5:4;telInput.value='('+d.slice(0,2)+') '+d.slice(2,2+size)+'-'+d.slice(2+size)}else if(d.length>2)telInput.value='('+d.slice(0,2)+') '+d.slice(2);else telInput.value=d});
document.getElementById('toggleSenha').addEventListener('click',()=>{const input=document.getElementById('senhaAluno');const show=input.type==='password';input.type=show?'text':'password';document.getElementById('toggleSenha').setAttribute('aria-label',show?'Ocultar senha':'Mostrar senha')});
document.getElementById('formMatricula').addEventListener('submit',()=>{const b=document.getElementById('btnCadastrar');b.disabled=true;b.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Salvando matrícula...'});
const resultados=document.getElementById('resultadoBusca');
function esc(texto){return String(texto??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
async function buscarAlunoBalcao(){const termo=document.getElementById('inputBusca').value.trim();resultados.style.display='block';if(!termo){resultados.textContent='Digite nome, CPF ou matrícula para pesquisar.';return;}resultados.textContent='Buscando...';try{const resposta=await fetch('recepcao.php?acao_ajax=buscar_aluno&q='+encodeURIComponent(termo),{credentials:'same-origin'});const dados=await resposta.json();if(!resposta.ok||!dados.ok)throw new Error(dados.erro||'Falha na consulta.');if(!dados.alunos.length){resultados.textContent='Nenhum aluno encontrado nesta academia.';return;}resultados.innerHTML=dados.alunos.map(a=>'<div class="result-item"><strong>'+esc(a.nome)+'</strong><small>Matrícula: '+esc(a.status_matricula)+' · Conta: '+esc(a.status)+'</small></div>').join('');}catch(e){resultados.textContent=e.message||'Não foi possível consultar.';}}
document.getElementById('btnBuscar').addEventListener('click',buscarAlunoBalcao);document.getElementById('inputBusca').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();buscarAlunoBalcao();}});
let streamRecepcao=null;const areaCamera=document.getElementById('areaCamera');document.getElementById('abrirCamera').addEventListener('click',()=>{areaCamera.classList.toggle('open');if(!areaCamera.classList.contains('open')&&streamRecepcao){streamRecepcao.getTracks().forEach(t=>t.stop());streamRecepcao=null;document.getElementById('webcamRecepcao').srcObject=null;document.getElementById('cameraHint').hidden=false;}});
document.getElementById('ligarCamera').addEventListener('click',async()=>{try{if(streamRecepcao)streamRecepcao.getTracks().forEach(t=>t.stop());streamRecepcao=await navigator.mediaDevices.getUserMedia({video:true});document.getElementById('webcamRecepcao').srcObject=streamRecepcao;document.getElementById('cameraHint').hidden=true;document.getElementById('cameraStatus').textContent='Prévia de câmera ativa. Imagens não são transmitidas.';}catch(e){document.getElementById('cameraStatus').textContent='Não foi possível abrir a câmera: '+e.message;}});
document.getElementById('tirarFoto').addEventListener('click',()=>{const v=document.getElementById('webcamRecepcao');if(!streamRecepcao||!v.videoWidth){document.getElementById('cameraStatus').textContent='Ligue a câmera antes de capturar.';return;}const canvas=document.getElementById('canvasSnap');canvas.width=v.videoWidth;canvas.height=v.videoHeight;canvas.getContext('2d').drawImage(v,0,0);document.getElementById('cameraStatus').textContent='Prévia capturada localmente. A imagem não foi salva nem enviada.';});
window.addEventListener('beforeunload',()=>{if(streamRecepcao)streamRecepcao.getTracks().forEach(t=>t.stop())});
</script>
</body></html>
