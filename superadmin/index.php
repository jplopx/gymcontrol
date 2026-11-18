<?php
require_once __DIR__ . '/auth.php';
$super=exigirSuper($pdo); $superId=(int)$super['id'];

require_once __DIR__ . '/../config/credenciais_academia.php';

/** Toda academia recebe um código numérico exclusivo, com 8 algarismos. */
function superCriarAcademia(PDO $pdo, string $nome, string $plano, string $email, string $whatsapp, string $gestor): array {
    $inserir = $pdo->prepare("INSERT INTO academias (codigo,nome,status,plano,contato_email,contato_whatsapp,gestor_nome) VALUES (?,?,'ativa',?,?,?,?)");
    for ($tentativa = 0; $tentativa < 12; $tentativa++) {
        $codigo = gcGerarCredencialAcademia($pdo);
        try {
            $inserir->execute([$codigo,$nome,$plano,$email,$whatsapp,$gestor]);
            return ['id'=>(int)$pdo->lastInsertId(), 'codigo'=>$codigo];
        } catch (PDOException $e) {
            // Em concorrência, o índice UNIQUE do banco é a garantia final.
            if (($e->errorInfo[1] ?? 0) === 1062 && stripos($e->getMessage(), 'codigo') !== false) continue;
            throw $e;
        }
    }
    throw new RuntimeException('Não foi possível gerar uma credencial exclusiva para a academia.');
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals(superCsrf(),(string)($_POST['csrf']??''))) superRedir('erro','Formulário expirado. Atualize a página.');
    $acao=(string)($_POST['acao']??'');
    try {
        if (in_array($acao,['aprovar','recusar'],true)) {
            $id=filter_var($_POST['solicitacao_id']??null,FILTER_VALIDATE_INT);
            if (!$id) throw new DomainException('Solicitação inválida.');
            $pdo->beginTransaction();
            $q=$pdo->prepare('SELECT * FROM contatos_interesse WHERE id=? FOR UPDATE');
            $q->execute([$id]); $s=$q->fetch(PDO::FETCH_ASSOC);
            if (!$s || $s['status']!=='pendente') throw new DomainException('Esta solicitação já foi analisada ou não existe.');
            if ($acao==='recusar') {
                $motivo=trim((string)($_POST['observacao']??''));
                if (mb_strlen($motivo)<5 || mb_strlen($motivo)>500) throw new DomainException('Descreva o motivo da recusa (5 a 500 caracteres).');
                $q=$pdo->prepare("UPDATE contatos_interesse SET status='recusada',analisado_por=?,analisado_em=NOW(),observacao=? WHERE id=?");
                $q->execute([$superId,$motivo,$id]);
                superAudit($pdo,$superId,'recusar_solicitacao',null,$id,'Solicitação recusada');
                $pdo->commit(); superRedir('ok','Solicitação recusada. Nenhum cadastro foi criado.');
            }
            $senhaTemporaria=bin2hex(random_bytes(10));
            $nome=trim($s['nome_academia']); $responsavel=trim($s['responsavel']);
            $email=mb_strtolower(trim($s['email']),'UTF-8');
            if (mb_strlen($nome)<2 || mb_strlen($nome)>150 || mb_strlen($responsavel)<3 || mb_strlen($responsavel)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>100) {
                throw new DomainException('O gestor desta solicitação possui dados incompatíveis. Peça a correção antes de aprovar.');
            }
            $novaAcademia=superCriarAcademia($pdo,$nome,$s['plano_interesse'],$email,$s['telefone'],$responsavel);
            $academia=$novaAcademia['id'];
            $q=$pdo->prepare("INSERT INTO usuarios(academia_id,nome,email,matricula,senha,nivel_acesso,tipo,status,trocar_senha) VALUES(?,?,?,NULL,?,'admin','admin','ativo',1)");
            $q->execute([$academia,$responsavel,$email,password_hash($senhaTemporaria,PASSWORD_DEFAULT)]);
            $q=$pdo->prepare("UPDATE contatos_interesse SET status='aprovada',academia_id=?,analisado_por=?,analisado_em=NOW(),observacao=NULL WHERE id=?");
            $q->execute([$academia,$superId,$id]);
            superAudit($pdo,$superId,'aprovar_solicitacao',$academia,$id,'Academia e gestor criados');
            $pdo->commit();
            $_SESSION['credencial_gestor']=['academia'=>$nome,'codigo'=>$novaAcademia['codigo'],'email'=>$email,'senha'=>$senhaTemporaria];
            superRedir('ok','Academia aprovada e gestor criado. Copie a credencial temporária abaixo: ela será exibida uma única vez.');
        }
        if ($acao==='criar_academia') {
            $nome=trim((string)($_POST['nome']??'')); $gestor=trim((string)($_POST['gestor']??''));
            $email=mb_strtolower(trim((string)($_POST['email']??'')),'UTF-8');
            $whatsapp=preg_replace('/\D+/','',(string)($_POST['whatsapp']??''));
            $plano=(string)($_POST['plano']??'');
            $planos=['Plano Studio','Plano Safira','Plano Infinity','Não informado'];
            if (mb_strlen($nome)<2 || mb_strlen($nome)>150 || mb_strlen($gestor)<3 || mb_strlen($gestor)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>100 || !in_array($plano,$planos,true) || !in_array(strlen($whatsapp),[10,11],true)) throw new DomainException('Preencha os dados da academia, gestor, e-mail, WhatsApp com DDD e plano.');
            $senhaTemporaria=bin2hex(random_bytes(10));
            $pdo->beginTransaction();
            $novaAcademia=superCriarAcademia($pdo,$nome,$plano,$email,$whatsapp,$gestor);
            $academia=$novaAcademia['id'];
            $q=$pdo->prepare("INSERT INTO usuarios(academia_id,nome,email,matricula,senha,nivel_acesso,tipo,status,trocar_senha) VALUES(?,?,?,NULL,?,'admin','admin','ativo',1)");
            $q->execute([$academia,$gestor,$email,password_hash($senhaTemporaria,PASSWORD_DEFAULT)]);
            superAudit($pdo,$superId,'criar_academia',$academia,null,'Cadastro manual');
            $pdo->commit();
            $_SESSION['credencial_gestor']=['academia'=>$nome,'codigo'=>$novaAcademia['codigo'],'email'=>$email,'senha'=>$senhaTemporaria];
            superRedir('ok','Academia criada. Copie a senha temporária exibida abaixo.');
        }
        if ($acao==='status_academia') {
            $academia=filter_var($_POST['academia_id']??null,FILTER_VALIDATE_INT);
            $status=(string)($_POST['status']??'');
            if (!$academia || !in_array($status,['ativa','inativa'],true)) throw new DomainException('Academia ou situação inválida.');
            $pdo->beginTransaction();
            $q=$pdo->prepare('SELECT id,status FROM academias WHERE id=? FOR UPDATE'); $q->execute([$academia]);
            $atual=$q->fetch(PDO::FETCH_ASSOC);
            if (!$atual) throw new DomainException('Academia inexistente.');
            $q=$pdo->prepare('UPDATE academias SET status=? WHERE id=?'); $q->execute([$status,$academia]);
            superAudit($pdo,$superId,'status_academia',$academia,null,'Status: '.$atual['status'].' -> '.$status);
            $pdo->commit(); superRedir('ok','Situação da academia atualizada.');
        }
        if ($acao==='alterar_plano') {
            $academia=filter_var($_POST['academia_id']??null,FILTER_VALIDATE_INT);
            $plano=(string)($_POST['plano']??'');
            if (!$academia || !in_array($plano,['Plano Studio','Plano Safira','Plano Infinity','Não informado'],true)) throw new DomainException('Plano inválido.');
            $pdo->beginTransaction();
            $q=$pdo->prepare('UPDATE academias SET plano=? WHERE id=?'); $q->execute([$plano,$academia]);
            if (!$q->rowCount()) { $v=$pdo->prepare('SELECT id FROM academias WHERE id=?'); $v->execute([$academia]); if (!$v->fetchColumn()) throw new DomainException('Academia inexistente.'); }
            superAudit($pdo,$superId,'alterar_plano',$academia,null,$plano);
            $pdo->commit(); superRedir('ok','Plano atualizado. Isso não representa cobrança ou pagamento realizado.');
        }
        throw new DomainException('Operação desconhecida.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (!($e instanceof DomainException)) error_log('GymControl superadmin: '.$e->getMessage());
        superRedir('erro',$e instanceof DomainException?$e->getMessage():'Falha na operação. Revise o banco e os dados e tente novamente.');
    }
}
$stats=[
    'academias'=>(int)$pdo->query('SELECT COUNT(*) FROM academias')->fetchColumn(),
    'ativas'=>(int)$pdo->query("SELECT COUNT(*) FROM academias WHERE status='ativa'")->fetchColumn(),
    'pedidos'=>(int)$pdo->query("SELECT COUNT(*) FROM contatos_interesse WHERE status='pendente'")->fetchColumn(),
    'alunos'=>(int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE nivel_acesso='aluno'")->fetchColumn()
];
$pedidos=$pdo->query('SELECT c.*,s.nome AS nome_analista FROM contatos_interesse c LEFT JOIN superadmins s ON s.id=c.analisado_por ORDER BY CASE WHEN c.status=\'pendente\' THEN 0 ELSE 1 END,c.id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
$academias=$pdo->query("SELECT a.*, (SELECT COUNT(*) FROM usuarios u WHERE u.academia_id=a.id AND u.nivel_acesso='aluno') AS alunos, (SELECT COUNT(*) FROM usuarios u WHERE u.academia_id=a.id AND u.nivel_acesso='admin') AS gestores FROM academias a ORDER BY a.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$logs=$pdo->query('SELECT l.*,s.nome FROM superadmin_auditoria l JOIN superadmins s ON s.id=l.superadmin_id ORDER BY l.id DESC LIMIT 25')->fetchAll(PDO::FETCH_ASSOC);
$flash=$_SESSION['super_flash']??null; unset($_SESSION['super_flash']);
$credencial=$_SESSION['credencial_gestor']??null; unset($_SESSION['credencial_gestor']);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta http-equiv="Cache-Control" content="no-store"><meta name="viewport" content="width=device-width, initial-scale=1"><title>GymControl • Gestão Global</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><style>
:root{color-scheme:dark;--bg:#0b1220;--card:#131e31;--card2:#19273e;--line:#2c3f58;--text:#e5efff;--muted:#9fb0c5;--blue:#38bdf8;--accent:#0284c7}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,Arial,sans-serif}a{color:var(--blue);text-decoration:none}header{position:sticky;top:0;z-index:2;background:#0e192bcf;backdrop-filter:blur(15px);border-bottom:1px solid var(--line)}.wrap{width:min(1350px,calc(100% - 40px));margin:0 auto}.top{display:flex;align-items:center;justify-content:space-between;min-height:76px;gap:12px;flex-wrap:wrap}.logo{font-size:20px;font-weight:800;color:#fff}.logo span{color:var(--blue)}nav{display:flex;gap:20px;flex-wrap:wrap;font-size:13px;font-weight:700}nav a{color:var(--muted)}nav a:hover{color:white}main{padding:35px 0 70px}.eyebrow{text-transform:uppercase;letter-spacing:.15em;color:var(--blue);font-weight:800;font-size:12px}.intro{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}h1{font-size:clamp(25px,4vw,37px);margin:8px 0}p{color:var(--muted);line-height:1.55}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:26px 0 35px}.stat,.panel{border:1px solid var(--line);background:var(--card);border-radius:16px;box-shadow:0 6px 28px #0001}.stat{padding:24px}.stat strong{display:block;font-size:31px;margin:9px 0}.stat small{color:var(--muted);font-weight:700}.panel{padding:25px;margin-top:24px}.panel h2{margin:0 0 8px;font-size:21px}.sub{color:var(--muted);font-size:13px;margin-bottom:22px}.flash{padding:15px 18px;border-radius:10px;border:1px solid #0b917b;background:#064e3b42;margin:22px 0}.flash.erro{border-color:#be4b5e;background:#7f1d1d30}.credentials{border:1px solid #eab308;background:#553c131f;padding:18px;border-radius:12px;margin:15px 0}.credentials code{display:inline-block;padding:8px;background:#090f1d;border-radius:6px;overflow-wrap:anywhere}.stack{display:flex;gap:9px;flex-wrap:wrap;align-items:center}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.field{display:flex;flex-direction:column;gap:7px}label{font-size:12px;color:var(--muted);font-weight:700}input,select,textarea{width:100%;border:1px solid #344860;border-radius:9px;background:#0b1526;color:var(--text);padding:11px 12px;font:inherit;outline:none}input:focus,select:focus,textarea:focus{border-color:var(--blue)}input,select{min-height:42px}button,.btn{border:none;border-radius:9px;padding:10px 15px;background:var(--accent);color:white;font-weight:750;cursor:pointer;font-size:13px}button:hover,.btn:hover{filter:brightness(1.13)}.ghost{background:#273850}.danger{background:#952d40}.success{background:#047857}.muted{color:var(--muted)}.pill{display:inline-block;font-size:12px;padding:5px 9px;border-radius:30px;border:1px solid #51647f;background:#253750}.pill.pendente{color:#fde68a;border-color:#957527}.pill.aprovada,.pill.ativa{color:#6ee7b7;border-color:#197b5b}.pill.recusada,.pill.inativa{color:#fca5a5;border-color:#874758}.tableScroll{overflow:auto;border:1px solid var(--line);border-radius:11px}table{width:100%;border-collapse:collapse;min-width:900px}th,td{text-align:left;padding:15px 13px;border-bottom:1px solid var(--line);vertical-align:top;font-size:13px}th{color:var(--muted);background:#1b2b41;font-size:12px}tr:last-child td{border-bottom:0}td strong{display:block;margin-bottom:5px}td .meta{color:var(--muted);font-size:12px}td form{margin:0}.operations{display:flex;flex-wrap:wrap;gap:8px;align-items:end}.operations select{min-width:135px}details{background:#18263b;border-radius:10px;padding:12px;margin:6px 0}summary{cursor:pointer;font-weight:700;font-size:13px}.reject{margin-top:12px} .reject textarea{min-height:70px;margin-bottom:8px}.notice{background:#102c45;border:1px solid #295778;border-radius:12px;padding:14px;margin-top:14px;font-size:13px;color:#c8e8ff}@media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}}@media(max-width:550px){.wrap{width:calc(100% - 24px)}.stats{gap:10px}.stat{padding:14px}.stat strong{font-size:24px}.panel{padding:15px}nav{gap:9px}.top{padding:12px 0}}
</style></head><body>
<header><div class="wrap top"><div class="logo">◈ GYM<span>CONTROL</span> <small style="font-size:11px;color:var(--muted)">GLOBAL</small></div><nav><a href="#solicitacoes">Solicitações</a><a href="#academias">Academias</a><a href="#criar">Nova academia</a><a href="#auditoria">Auditoria</a><a href="../index.php">Site</a><a href="logout.php">Sair</a></nav></div></header>
<main class="wrap"><div class="intro"><div><span class="eyebrow">Painel da plataforma • sem vínculo com academias</span><h1>Visão geral do GymControl</h1><p>Olá, <?=superEsc($super['nome'])?>. Aprove solicitações, gerencie academias e acompanhe as operações.</p></div><span class="pill">Superadministrador</span></div>
<?php if($flash):?><div class="flash <?= $flash[0]==='erro'?'erro':'' ?>" role="status"><?=superEsc($flash[1])?></div><?php endif;?>
<?php if($credencial):?><div class="credentials"><strong>Credencial temporária do novo gestor — exibição única</strong><p>Academia: <?=superEsc($credencial['academia'])?> • Credencial numérica: <code><?=superEsc($credencial['codigo']??'—')?></code> • E-mail: <code><?=superEsc($credencial['email'])?></code></p><p>Senha inicial: <code><?=superEsc($credencial['senha'])?></code></p><p>Copie e entregue ao gestor por um canal seguro. Nenhum e-mail automático foi enviado. O gestor deverá trocar a senha no primeiro acesso.</p></div><?php endif;?>
<div class="stats"><div class="stat"><small>Academias cadastradas</small><strong><?=$stats['academias']?></strong><span class="muted">Todas as unidades</span></div><div class="stat"><small>Academias ativas</small><strong><?=$stats['ativas']?></strong><span class="muted">Com acesso habilitado</span></div><div class="stat"><small>Solicitações pendentes</small><strong><?=$stats['pedidos']?></strong><span class="muted">Aguardando análise</span></div><div class="stat"><small>Alunos no sistema</small><strong><?=$stats['alunos']?></strong><span class="muted">Em todas as academias</span></div></div>
<section class="panel" id="solicitacoes"><h2>Solicitações de adesão</h2><p class="sub">Os pedidos enviados pelo formulário do site aparecem automaticamente aqui. Aprovar cria academia e gestor. Recusar registra uma justificativa.</p><div class="tableScroll"><table><thead><tr><th>Solicitação</th><th>Contato</th><th>Plano / Porte</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
<?php foreach($pedidos as $s):?><tr><td><strong>#<?= (int)$s['id']?> • <?=superEsc($s['nome_academia'])?></strong><span class="meta"><?=superEsc($s['criado_em'])?></span></td><td><strong><?=superEsc($s['responsavel'])?></strong><div class="meta"><?=superEsc($s['email'])?></div><div class="meta"><?=superEsc($s['telefone'])?></div></td><td><strong><?=superEsc($s['plano_interesse'])?></strong><span class="meta"><?=superEsc($s['estimativa_alunos'])?></span></td><td><span class="pill <?=superEsc($s['status'])?>"><?=superEsc(ucfirst($s['status']))?></span><?php if($s['analisado_em']):?><div class="meta"><?=superEsc($s['analisado_em'])?></div><?php endif;?><?php if($s['observacao']):?><div class="meta"><?=superEsc($s['observacao'])?></div><?php endif;?></td><td><?php if($s['status']==='pendente'):?><form method="post" onsubmit="return confirm('Aprovar e criar academia e gestor?')"><input type="hidden" name="csrf" value="<?=superEsc(superCsrf())?>"><input type="hidden" name="acao" value="aprovar"><input type="hidden" name="solicitacao_id" value="<?=(int)$s['id']?>"><button class="success">Aprovar e criar</button></form><details><summary>Recusar solicitação</summary><form class="reject" method="post" onsubmit="return confirm('Recusar esta solicitação?')"><input type="hidden" name="csrf" value="<?=superEsc(superCsrf())?>"><input type="hidden" name="acao" value="recusar"><input type="hidden" name="solicitacao_id" value="<?=(int)$s['id']?>"><label>Motivo da recusa (obrigatório)</label><textarea name="observacao" maxlength="500" minlength="5" required></textarea><button class="danger">Confirmar recusa</button></form></details><?php else:?><span class="meta">Analisado por <?=superEsc($s['nome_analista']??'—')?><?= $s['academia_id']?' • Academia #'.(int)$s['academia_id']:'' ?></span><?php endif;?></td></tr><?php endforeach;?>
<?php if(!$pedidos):?><tr><td colspan="5">Nenhuma solicitação cadastrada.</td></tr><?php endif;?></tbody></table></div><p class="sub" style="margin-top:12px">Exibindo as 200 solicitações mais recentes, com pendentes primeiro.</p></section>
<section class="panel" id="academias"><h2>Academias e unidades</h2><p class="sub">Controle global de acesso e plano. Desativar uma academia bloqueia os logins vinculados sem apagar dados.</p><div class="tableScroll"><table><thead><tr><th>Academia</th><th>Gestor / contato</th><th>Alunos</th><th>Plano</th><th>Situação / ações</th></tr></thead><tbody>
<?php foreach($academias as $a):?><tr><td><strong>#<?=(int)$a['id']?> • <?=superEsc($a['nome'])?></strong><span class="meta">Credencial: <code><?=superEsc($a['codigo'])?></code></span><?php if(!empty($a['criado_em'])):?><span class="meta">Criada em <?=superEsc($a['criado_em'])?></span><?php endif;?></td><td><strong><?=superEsc($a['gestor_nome']??'Não informado')?></strong><div class="meta"><?=superEsc($a['contato_email']??'—')?></div><div class="meta"><?=superEsc($a['contato_whatsapp']??'—')?></div></td><td><?= (int)$a['alunos']?><div class="meta"><?= (int)$a['gestores']?> gestor(es)</div></td><td><form method="post" class="operations"><input type="hidden" name="csrf" value="<?=superEsc(superCsrf())?>"><input type="hidden" name="acao" value="alterar_plano"><input type="hidden" name="academia_id" value="<?=(int)$a['id']?>"><select name="plano" aria-label="Plano da academia"><?php foreach(['Plano Studio','Plano Safira','Plano Infinity','Não informado'] as $plano):?><option value="<?=superEsc($plano)?>" <?= $a['plano']===$plano?'selected':''?>><?=superEsc($plano)?></option><?php endforeach;?></select><button class="ghost">Salvar</button></form></td><td><span class="pill <?=superEsc($a['status'])?>"><?=superEsc(ucfirst($a['status']))?></span><form method="post" style="margin-top:10px" onsubmit="return confirm('Confirmar alteração de acesso desta academia?')"><input type="hidden" name="csrf" value="<?=superEsc(superCsrf())?>"><input type="hidden" name="acao" value="status_academia"><input type="hidden" name="academia_id" value="<?=(int)$a['id']?>"><input type="hidden" name="status" value="<?=$a['status']==='ativa'?'inativa':'ativa'?>"><button class="<?=$a['status']==='ativa'?'danger':'success'?>"><?=$a['status']==='ativa'?'Desativar':'Reativar'?></button></form></td></tr><?php endforeach;?>
<?php if(!$academias):?><tr><td colspan="5">Nenhuma academia cadastrada.</td></tr><?php endif;?></tbody></table></div></section>
<section class="panel" id="criar"><h2>Criar academia manualmente</h2><p class="sub">Para cadastros feitos diretamente pela equipe do GymControl. O fluxo de solicitação pública continua sendo o recomendado.</p><form method="post" onsubmit="return confirm('Criar esta academia e a conta de gestor?')"><input type="hidden" name="csrf" value="<?=superEsc(superCsrf())?>"><input type="hidden" name="acao" value="criar_academia"><div class="grid"><div class="field"><label for="nome">Nome da academia ou estúdio</label><input id="nome" name="nome" maxlength="150" required></div><div class="field"><label for="gestor">Nome do gestor</label><input id="gestor" name="gestor" maxlength="100" required></div><div class="field"><label for="email">E-mail do gestor / contato</label><input id="email" name="email" type="email" maxlength="100" required></div><div class="field"><label for="whatsapp">WhatsApp com DDD</label><input id="whatsapp" name="whatsapp" type="tel" placeholder="(21) 99999-9999" maxlength="20" required></div><div class="field"><label for="plano">Plano inicial</label><select id="plano" name="plano"><option>Plano Studio</option><option>Plano Safira</option><option>Plano Infinity</option><option>Não informado</option></select></div></div><button style="margin-top:20px">Criar academia e gestor</button><div class="notice">A senha inicial será gerada automaticamente e mostrada uma única vez para entrega segura ao gestor. A academia não cobra nem provisiona assinatura automaticamente.</div></form></section>
<section class="panel" id="auditoria"><h2>Histórico de ações</h2><p class="sub">Últimas 25 alterações feitas pelos superadministradores.</p><div class="tableScroll"><table><thead><tr><th>Data</th><th>Administrador</th><th>Ação</th><th>Referências</th></tr></thead><tbody><?php foreach($logs as $l):?><tr><td><?=superEsc($l['criado_em'])?></td><td><?=superEsc($l['nome'])?></td><td><?=superEsc(str_replace('_',' ',$l['acao']))?><div class="meta"><?=superEsc($l['detalhes'])?></div></td><td>Academia <?= $l['academia_id']?'#'.(int)$l['academia_id']:'—'?> • Pedido <?=$l['solicitacao_id']?'#'.(int)$l['solicitacao_id']:'—'?></td></tr><?php endforeach;?><?php if(!$logs):?><tr><td colspan="4">Nenhuma ação registrada.</td></tr><?php endif;?></tbody></table></div></section>
</main></body></html>
