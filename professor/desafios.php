<?php
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['professor', 'admin']);
require_once __DIR__ . '/../config/conexao.php';
$professorId = (int)$_SESSION['usuario_id'];
$alunoId = filter_input(INPUT_GET, 'aluno_id', FILTER_VALIDATE_INT);
if (!$alunoId) { http_response_code(400); exit('Aluno inválido.'); }
$stmt = $pdo->prepare("SELECT u.nome FROM alunos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.usuario_id=? AND a.professor_id=? AND u.status='ativo' LIMIT 1");
$stmt->execute([$alunoId, $professorId]);
$alunoNome = $stmt->fetchColumn();
if ($alunoNome === false) { http_response_code(403); exit('Aluno não vinculado a este professor.'); }
if (empty($_SESSION['csrf_desafios'])) $_SESSION['csrf_desafios'] = bin2hex(random_bytes(32));
$erro = ''; $sucesso = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  if (!hash_equals($_SESSION['csrf_desafios'], (string)($_POST['csrf']??''))) throw new RuntimeException('Sessão inválida.');
  $titulo=trim((string)($_POST['titulo']??'')); $descricao=trim((string)($_POST['descricao']??''));
  $tipo=(string)($_POST['tipo']??''); $meta=filter_var($_POST['meta']??null,FILTER_VALIDATE_INT);
  $xp=filter_var($_POST['xp']??null,FILTER_VALIDATE_INT);
  $inicio=(string)($_POST['inicio']??''); $fim=(string)($_POST['fim']??'');
  $di=DateTimeImmutable::createFromFormat('!Y-m-d',$inicio); $df=DateTimeImmutable::createFromFormat('!Y-m-d',$fim);
  if (mb_strlen($titulo)<3 || mb_strlen($titulo)>150 || !in_array($tipo,['fichas','dias'],true) || !$meta || $meta>100 || $xp===false || $xp<0 || $xp>500 || !$di || !$df || $di->format('Y-m-d')!==$inicio || $df->format('Y-m-d')!==$fim || $inicio>$fim) throw new RuntimeException('Confira os dados, as datas, a meta (1 a 100) e o XP (0 a 500).');
  $stmt=$pdo->prepare('INSERT INTO gamificacao_desafios (professor_id,aluno_id,titulo,descricao,tipo,meta,xp_recompensa,data_inicio,data_fim) VALUES (?,?,?,?,?,?,?, ?, ?)');
  $stmt->execute([$professorId,$alunoId,$titulo,$descricao,$tipo,$meta,$xp,$inicio,$fim]);
  header('Location: desafios.php?aluno_id='.$alunoId.'&criado=1'); exit;
 } catch (RuntimeException $e) { $erro=$e->getMessage(); }
 catch (PDOException $e) { error_log($e->getMessage()); $erro='Não foi possível salvar. Execute primeiro o SQL da etapa 3.'; }
}
if (isset($_GET['criado'])) $sucesso='Desafio criado com sucesso!';
$desafios=[];
try { $stmt=$pdo->prepare('SELECT d.*, (SELECT COUNT(*) FROM gamificacao_treinos_concluidos g WHERE g.aluno_id=d.aluno_id AND g.data_treino BETWEEN d.data_inicio AND d.data_fim) AS fichas, (SELECT COUNT(DISTINCT g.data_treino) FROM gamificacao_treinos_concluidos g WHERE g.aluno_id=d.aluno_id AND g.data_treino BETWEEN d.data_inicio AND d.data_fim) AS dias, r.concedido_em FROM gamificacao_desafios d LEFT JOIN gamificacao_desafios_recompensas r ON r.desafio_id=d.id WHERE d.aluno_id=? AND d.professor_id=? ORDER BY d.id DESC'); $stmt->execute([$alunoId,$professorId]); $desafios=$stmt->fetchAll(PDO::FETCH_ASSOC); } catch(PDOException $e){error_log($e->getMessage());$erro='Execute o SQL da etapa 3 antes de abrir os desafios.';}
function esc($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
require_once __DIR__.'/../layouts/header.php';
?>
<style>.ds-input{width:100%;padding:11px;border-radius:8px;border:1px solid var(--border-color);background:#101827;color:#fff}.ds-label{display:block;color:#cbd5e1;margin:12px 0 5px}.ds-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}.ds-muted{color:var(--text-muted)}.ds-item{padding:16px;border:1px solid var(--border-color);border-radius:10px;margin-top:12px}</style>
<a href="index.php" style="color:var(--cor-principal)">← Voltar aos alunos</a><h1 style="color:#fff;margin:14px 0">🎯 Desafios de <?=esc($alunoNome)?></h1>
<?php if($erro):?><div class="panel-card" style="color:#fca5a5"><?=esc($erro)?></div><?php endif;?>
<?php if($sucesso):?><div class="panel-card" style="color:#86efac"><?=esc($sucesso)?></div><?php endif;?>
<div class="panel-card"><h2 style="color:#fff">Criar desafio</h2><p class="ds-muted">O aluno ganha XP extra uma única vez ao atingir a meta dentro do período.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf_desafios'])?>">
<label class="ds-label">Título</label><input class="ds-input" name="titulo" maxlength="150" required placeholder="Ex.: Complete 3 fichas nesta semana">
<label class="ds-label">Orientações (opcional)</label><textarea class="ds-input" name="descricao" rows="2"></textarea>
<div class="ds-grid"><div><label class="ds-label">Tipo</label><select class="ds-input" name="tipo"><option value="fichas">Fichas completas</option><option value="dias">Dias com ficha completa</option></select></div><div><label class="ds-label">Meta</label><input class="ds-input" type="number" name="meta" min="1" max="100" value="3" required></div><div><label class="ds-label">XP de recompensa</label><input class="ds-input" type="number" name="xp" min="0" max="500" value="150" required></div></div>
<div class="ds-grid"><div><label class="ds-label">Início</label><input class="ds-input" type="date" name="inicio" value="<?=date('Y-m-d')?>" required></div><div><label class="ds-label">Fim</label><input class="ds-input" type="date" name="fim" value="<?=date('Y-m-d',strtotime('+6 days'))?>" required></div></div>
<button class="btn btn-primary" type="submit" style="margin-top:18px">Criar desafio</button></form></div>
<div class="panel-card"><h2 style="color:#fff">Desafios cadastrados</h2>
<?php if(!$desafios):?><p class="ds-muted">Nenhum desafio cadastrado.</p><?php endif;?>
<?php foreach($desafios as $d):$valor=(int)$d[$d['tipo']==='dias'?'dias':'fichas'];$meta=(int)$d['meta'];?><div class="ds-item"><strong style="color:#fff"><?=esc($d['titulo'])?></strong><p class="ds-muted"><?=esc($d['descricao'])?></p><p class="ds-muted"><?=date('d/m/Y',strtotime($d['data_inicio']))?> a <?=date('d/m/Y',strtotime($d['data_fim']))?> · <?=min($valor,$meta)?>/<?=$meta?> <?= $d['tipo']==='dias'?'dias':'fichas' ?> · +<?=(int)$d['xp_recompensa']?> XP</p><p style="color:<?= $d['concedido_em']?'#86efac':'#93c5fd' ?>"><?= $d['concedido_em']?'Recompensa entregue':'Aguardando conclusão' ?></p></div><?php endforeach;?></div>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
