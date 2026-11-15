<?php
require_once __DIR__.'/../config/auth.php';
verificarAcesso(['aluno','admin']);
require_once __DIR__.'/../config/conexao.php';
$alunoId=(int)$_SESSION['usuario_id']; $erro=''; $desafios=[];
try {
 // Recompensas são registradas uma única vez por desafio (chave primária desafio_id).
 // A meta é verificada no servidor com base nas fichas realmente completas.
 $sql="SELECT d.*, (SELECT COUNT(*) FROM gamificacao_treinos_concluidos g WHERE g.aluno_id=d.aluno_id AND g.data_treino BETWEEN d.data_inicio AND d.data_fim) AS fichas, (SELECT COUNT(DISTINCT g.data_treino) FROM gamificacao_treinos_concluidos g WHERE g.aluno_id=d.aluno_id AND g.data_treino BETWEEN d.data_inicio AND d.data_fim) AS dias, r.concedido_em FROM gamificacao_desafios d LEFT JOIN gamificacao_desafios_recompensas r ON r.desafio_id=d.id WHERE d.aluno_id=? ORDER BY d.id DESC";
 $stmt=$pdo->prepare($sql);$stmt->execute([$alunoId]);$desafios=$stmt->fetchAll(PDO::FETCH_ASSOC);
 foreach($desafios as &$d){
  $valor=(int)$d[$d['tipo']==='dias'?'dias':'fichas'];
  if($valor>=(int)$d['meta'] && !$d['concedido_em'] && date('Y-m-d') >= $d['data_inicio']){
   $insert=$pdo->prepare('INSERT IGNORE INTO gamificacao_desafios_recompensas (desafio_id,aluno_id,xp) VALUES (?,?,?)');
   $insert->execute([(int)$d['id'],$alunoId,(int)$d['xp_recompensa']]);
   $d['concedido_em']=date('Y-m-d H:i:s');
  }
 }
 unset($d);
} catch(PDOException $e){error_log('Erro desafios aluno: '.$e->getMessage());$erro='Não foi possível carregar os desafios. Confira se o SQL da etapa 3 foi executado.';}
function esc($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
require_once __DIR__.'/../layouts/header.php';
?>
<style>.ds-muted{color:var(--text-muted);font-size:14px}.ds-bar{background:#0f172a;height:10px;border-radius:99px;overflow:hidden;margin:12px 0}.ds-bar>div{height:100%;background:var(--cor-principal)}.ds-card{border:1px solid var(--border-color);border-radius:12px;padding:18px;margin:12px 0}</style>
<a href="index.php" style="color:var(--cor-principal)">← Voltar ao Painel</a><h1 style="color:#fff;margin:16px 0">🎯 Meus Desafios</h1><p class="ds-muted">Complete as metas do professor para conquistar XP extra. A recompensa é concedida uma única vez.</p>
<?php if($erro):?><div class="panel-card" style="color:#fca5a5"><?=esc($erro)?></div><?php endif;?>
<div class="panel-card">
<?php if(!$desafios):?><p class="ds-muted">Seu professor ainda não criou desafios para você.</p><?php endif;?>
<?php foreach($desafios as $d):$valor=(int)$d[$d['tipo']==='dias'?'dias':'fichas'];$meta=(int)$d['meta'];$pct=min(100,(int)round($valor/$meta*100));$feito=$valor>=$meta;$expirou=date('Y-m-d')>$d['data_fim'];?>
<div class="ds-card"><h2 style="color:#fff;font-size:19px"><?=esc($d['titulo'])?></h2><p class="ds-muted"><?=esc($d['descricao'])?></p><p class="ds-muted"><?=date('d/m/Y',strtotime($d['data_inicio']))?> a <?=date('d/m/Y',strtotime($d['data_fim']))?> · +<?=(int)$d['xp_recompensa']?> XP</p><div class="ds-bar"><div style="width:<?=$pct?>%"></div></div><p class="ds-muted"><?=min($valor,$meta)?> de <?=$meta?> <?= $d['tipo']==='dias'?'dias com ficha completa':'fichas completas' ?></p><strong style="color:<?= $feito?'#86efac':'#93c5fd' ?>"><?= $feito?'🏅 Concluído! XP creditado':($expirou?'Prazo encerrado':'Em andamento') ?></strong></div>
<?php endforeach;?></div><p class="ds-muted">A contagem usa somente fichas completas registradas no sistema. Desafios antigos podem considerar conclusões registradas antes de sua criação, desde que estejam no período definido pelo professor.</p>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
