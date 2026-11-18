<?php
/** Testes de código puro: sem conexão MySQL e sem alterar dados. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente terminal.'); }
require_once __DIR__ . '/../config/pdv_funcoes.php';

$testes = [
    'Preço em reais' => [pdvPreco('12,50'), '12.50'],
    'Preço não positivo rejeitado' => [pdvPreco('0'), null],
    'Preço inválido rejeitado' => [pdvPreco('2x'), null],
    'Quantidade negativa rejeitada' => [pdvQuantidade('-2'), null],
    'Quantidade positiva' => [pdvQuantidade('15'), 15],
    '499 gramas' => [pdvFormatarGramatura(499), '499 g'],
    '999 gramas' => [pdvFormatarGramatura(999), '999 g'],
    'Mil gramas' => [pdvFormatarGramatura(1000), '1 kg'],
    'Mil e quinhentas gramas' => [pdvFormatarGramatura(1500), '1,5 kg'],
    '2250 gramas' => [pdvFormatarGramatura(2250), '2,25 kg'],
    'Detalhes do produto' => [pdvDetalhesProduto(['marca'=>'Marca Teste','gramatura_g'=>1000]), 'Marca Teste · 1 kg'],
    'Conversão em centavos' => [pdvCentavos('42.99'), 4299],
];
$erros=0;
foreach ($testes as $descricao=>[$obtido,$esperado]) {
    $passou=($obtido===$esperado);
    echo ($passou?'[OK] ':'[FALHA] ') . $descricao . "\n";
    if (!$passou) $erros++;
}
$esperados = ['loja_produtos','loja_vendas','loja_itens_venda','loja_totens','loja_pedidos_totem','loja_pedidos_itens','avaliacoes_fisicas','agendamentos','contatos_interesse'];
foreach (['../database/estrutura.sql','../database/gymcontrol.sql','../Gymcontrol.sql'] as $fonte) {
    $conteudo=file_get_contents(__DIR__ . '/' . $fonte);
    foreach ($esperados as $nome) {
        $passou = preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+' . preg_quote($nome,'/') . '\b/i',$conteudo)===1;
        echo ($passou?'[OK] ':'[FALHA] ') . $fonte . ': ' . $nome . "\n";
        if (!$passou) $erros++;
    }
    $passou = preg_match('/\bfoto\s+VARCHAR\(255\)\s+NULL\b/i',$conteudo)===1;
    echo ($passou?'[OK] ':'[FALHA] ') . $fonte . ': foto de produto definida' . "\n";
    if (!$passou) $erros++;
}
echo $erros === 0 ? "\nTodos os testes locais passaram.\n" : "\n{$erros} teste(s) falharam.\n";
exit($erros ? 1 : 0);
