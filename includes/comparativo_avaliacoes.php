<?php
// Componente de comparação; recebe $comparativoAvaliacoes (mais recente primeiro).
if (!isset($comparativoAvaliacoes) || !is_array($comparativoAvaliacoes)) return;
if (!function_exists('gcCmpEsc')) {
function gcCmpEsc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function gcCmpNum($v, int $d=1): string { return number_format((float)$v, $d, ',', '.'); }
}
$gcObjetivos = ['geral'=>'Acompanhar medidas','emagrecimento'=>'Redução de gordura','hipertrofia'=>'Ganho de massa muscular','manutencao'=>'Manutenção'];
$gcObjetivo = (string)($_GET['objetivo'] ?? 'geral');
if (!array_key_exists($gcObjetivo,$gcObjetivos)) $gcObjetivo='geral';
$gcAtual=$comparativoAvaliacoes[0]??null;
$gcAnterior=$comparativoAvaliacoes[1]??null;
$gcCampos=['peso'=>['Peso','kg',1],'percentual_gordura'=>['Gordura corporal','p.p.',1], 'cintura'=>['Cintura','cm',1],'abdomen'=>['Abdômen','cm',1], 'quadril'=>['Quadril','cm',1], 'peito'=>['Peito','cm',1], 'braco_direito'=>['Braço direito','cm',1], 'braco_esquerdo'=>['Braço esquerdo','cm',1], 'coxa_direita'=>['Coxa direita','cm',1], 'coxa_esquerda'=>['Coxa esquerda','cm',1]];
$gcLinhas=[];
if ($gcAtual && $gcAnterior) foreach($gcCampos as $gcKey=>$gcCfg) {
  $gcA=$gcAtual[$gcKey]??null; $gcB=$gcAnterior[$gcKey]??null;
  if ($gcA===null || $gcA==='' || $gcB===null || $gcB==='') continue;
  $gcDelta=round((float)$gcA-(float)$gcB,2);
  $gcLinhas[$gcKey]=['titulo'=>$gcCfg[0],'unidade'=>$gcCfg[1],'anterior'=>(float)$gcB,'atual'=>(float)$gcA,'delta'=>$gcDelta];
}
$gcResumo='As medidas mudaram entre as duas avaliações. Analise os resultados com o professor, considerando o objetivo e as condições das medições.';
if (!$gcLinhas) $gcResumo='Não há medidas preenchidas nas duas avaliações para comparar.';
elseif($gcObjetivo==='geral') $gcResumo='As diferenças mostram mudanças nas medidas, mas não determinam, isoladamente, se houve melhora. Selecione um objetivo para contextualizar a leitura.';
elseif($gcObjetivo==='emagrecimento') {
  if(isset($gcLinhas['percentual_gordura'])) {
    $v=$gcLinhas['percentual_gordura']['delta'];
    $gcResumo=$v < -0.3 ? 'A gordura corporal registrada diminuiu, um sinal compatível com o objetivo de redução de gordura. Confirme a tendência em avaliações futuras.' : ($v > 0.3 ? 'A gordura corporal registrada aumentou. Vale revisar as medições e o plano com o professor.' : 'A gordura corporal registrada ficou praticamente estável. Observe também cintura, rotina e evolução ao longo do tempo.');
  } else $gcResumo='Sem duas medições de gordura corporal, não é possível avaliar diretamente esse objetivo. As demais medidas ajudam no acompanhamento.';
} elseif($gcObjetivo==='hipertrofia') {
  $gcResumo='Aumento de peso ou circunferências, isoladamente, não comprova ganho de massa muscular. Compare também força, desempenho e gordura corporal com o professor.';
} else $gcResumo='Na manutenção, pequenas variações podem ser esperadas. Compare tendências e medidas com o professor, sem concluir apenas pelo peso ou IMC.';
$gcBase = (string)($_SERVER['PHP_SELF'] ?? '');
$gcParams = $_GET; unset($gcParams['objetivo']);
?>
<style>
.gc-cmp{margin:22px 0;padding:22px;background:#1e293b;border:1px solid #334155;border-radius:12px;color:#e2e8f0}
.gc-cmp h2{font-size:19px;color:#fff;margin:0 0 9px}.gc-cmp p{font-size:13px;color:#a9bdd5;line-height:1.6}
.gc-cmp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:10px;margin:15px 0}
.gc-cmp-tile{background:#0f172a;border:1px solid #334155;border-radius:9px;padding:12px}.gc-cmp-tile small{display:block;color:#93a8c1;margin-bottom:6px}.gc-cmp-tile strong{font-size:20px;color:#fff}
.gc-cmp-table{width:100%;border-collapse:collapse;font-size:13px}.gc-cmp-table th,.gc-cmp-table td{padding:11px;border-bottom:1px solid #334155;text-align:left;white-space:nowrap}.gc-cmp-table th{color:#9fb3ca}
.gc-cmp select{background:#0f172a;color:#fff;border:1px solid #475569;padding:9px;border-radius:7px;max-width:100%}
.gc-cmp-summary{border-left:3px solid #38bdf8;padding:10px 13px;background:#0f172a;margin-top:16px;line-height:1.6;font-size:14px}
</style>
<section class="gc-cmp" aria-label="Comparativo de avaliações físicas">
<h2>📊 Comparativo entre avaliações</h2>
<?php if (!$gcAtual || !$gcAnterior): ?>
<p>Registre pelo menos duas avaliações físicas para visualizar as diferenças automaticamente. A primeira avaliação continua disponível no histórico.</p>
<?php else: ?>
<p>Comparando <?= gcCmpEsc(date('d/m/Y',strtotime($gcAnterior['data_avaliacao']))) ?> com <?= gcCmpEsc(date('d/m/Y',strtotime($gcAtual['data_avaliacao']))) ?> (duas avaliações mais recentes).</p>
<form method="get" style="margin:14px 0">
<?php foreach($gcParams as $gcK=>$gcV): if(!is_scalar($gcV))continue; ?><input type="hidden" name="<?= gcCmpEsc($gcK) ?>" value="<?= gcCmpEsc($gcV) ?>"><?php endforeach; ?>
<label for="gc-objetivo" style="font-size:13px;display:block;margin-bottom:6px">Objetivo para interpretar as medidas</label>
<select name="objetivo" id="gc-objetivo" onchange="this.form.submit()">
<?php foreach($gcObjetivos as $gcK=>$gcLabel): ?><option value="<?= gcCmpEsc($gcK) ?>" <?= $gcObjetivo===$gcK?'selected':'' ?>><?= gcCmpEsc($gcLabel) ?></option><?php endforeach; ?>
</select><noscript><button type="submit">Aplicar</button></noscript>
</form>
<?php if($gcLinhas): ?>
<div class="gc-cmp-grid">
<?php foreach(['peso','percentual_gordura','cintura'] as $gcKey): if(!isset($gcLinhas[$gcKey]))continue; $gcR=$gcLinhas[$gcKey]; ?>
<div class="gc-cmp-tile"><small><?= gcCmpEsc($gcR['titulo']) ?></small><strong><?= $gcR['delta']>0?'+':($gcR['delta']<0?'−':'') ?><?= gcCmpNum(abs($gcR['delta'])) ?> <?= gcCmpEsc($gcR['unidade']) ?></strong></div>
<?php endforeach; ?>
</div>
<div style="overflow-x:auto"><table class="gc-cmp-table"><thead><tr><th>Indicador</th><th>Anterior</th><th>Atual</th><th>Variação</th></tr></thead><tbody>
<?php foreach($gcLinhas as $gcR): ?><tr><td><?= gcCmpEsc($gcR['titulo']) ?></td><td><?= gcCmpNum($gcR['anterior']) ?> <?= gcCmpEsc($gcR['unidade']==='p.p.'?'%':$gcR['unidade']) ?></td><td><?= gcCmpNum($gcR['atual']) ?> <?= gcCmpEsc($gcR['unidade']==='p.p.'?'%':$gcR['unidade']) ?></td><td><?= $gcR['delta']>0?'+':($gcR['delta']<0?'−':'') ?><?= gcCmpNum(abs($gcR['delta'])) ?> <?= gcCmpEsc($gcR['unidade']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<div class="gc-cmp-summary"><strong>Resumo:</strong> <?= gcCmpEsc($gcResumo) ?></div>
<p style="margin-top:12px">O objetivo selecionado é apenas um filtro de visualização, não fica salvo no cadastro. Variações podem refletir hidratação, técnica e horário da medição. Esta análise não substitui a avaliação profissional.</p>
<?php endif; ?>
</section>
