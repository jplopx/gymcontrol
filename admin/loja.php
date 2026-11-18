<?php
/** GymControl: PDV da academia — produtos, venda manual e pedidos do totem. */
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['admin', 'recepcao', 'loja']);
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/pdv_funcoes.php';
$academiaId = (int) $_SESSION['academia_id'];
$vendedorId = (int) $_SESSION['usuario_id'];
$gerenciaCatalogo = in_array($_SESSION['usuario_nivel'] ?? '', ['admin', 'recepcao'], true);
if (empty($_SESSION['csrf_loja'])) $_SESSION['csrf_loja'] = bin2hex(random_bytes(32));
$abas = ['manual','produtos','totem'];
$abaSolicitada = (string)($_GET['aba'] ?? 'manual');
$aba = in_array($abaSolicitada, $abas, true) ? $abaSolicitada : 'manual';
if ($aba === 'produtos' && !$gerenciaCatalogo) $aba = 'manual';
function lojaIr(string $aba, string $tipo, string $mensagem): never {
    $_SESSION['loja_flash'] = ['tipo'=>$tipo,'mensagem'=>$mensagem];
    header('Location: loja.php?aba=' . $aba, true, 303); exit;
}
function lojaCsrf(): void {
    if (!hash_equals((string) $_SESSION['csrf_loja'], (string) ($_POST['csrf'] ?? ''))) {
        throw new DomainException('Formulário expirado. Atualize a página.');
    }
}
function lojaId($valor): int {
    $n = filter_var($valor, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($n === false || $n === null) throw new DomainException('Identificador inválido.');
    return (int) $n;
}
function lojaPagamento($valor): string {
    $opcoes = ['PIX','Cartao_Debito','Cartao_Credito','Dinheiro'];
    if (!in_array($valor, $opcoes, true)) throw new DomainException('Forma de pagamento inválida.');
    return $valor;
}
function lojaRegistrarItens(PDO $pdo, int $academiaId, int $vendedorId, ?int $alunoId, string $pagamento, array $quantidades, ?array $precosFixos = null): array {
    if (!$quantidades || count($quantidades) > 50) throw new DomainException('Carrinho inválido.');
    ksort($quantidades, SORT_NUMERIC);
    $buscar = $pdo->prepare('SELECT id,nome,preco,estoque,ativo FROM loja_produtos WHERE id=? AND academia_id=? FOR UPDATE');
    $baixar = $pdo->prepare('UPDATE loja_produtos SET estoque=estoque-? WHERE id=? AND academia_id=? AND estoque>=?');
    $linhas = [];
    $totalCentavos = 0;
    foreach ($quantidades as $produtoId => $qtd) {
        $buscar->execute([$produtoId, $academiaId]);
        $p = $buscar->fetch(PDO::FETCH_ASSOC);
        if (!$p || !(int)$p['ativo'] || (int)$p['estoque'] < $qtd) throw new DomainException('Um produto está indisponível ou sem estoque suficiente.');
        $unitario = $precosFixos === null ? (string)$p['preco'] : ($precosFixos[$produtoId] ?? null);
        if (!is_string($unitario) || !preg_match('/^\d+\.\d{2}$/D', $unitario)) throw new DomainException('Preço do pedido inválido.');
        $totalCentavos += pdvCentavos($unitario) * $qtd;
        $linhas[] = [$produtoId, $qtd, $unitario];
        $baixar->execute([$qtd,$produtoId,$academiaId,$qtd]);
        if ($baixar->rowCount() !== 1) throw new DomainException('Estoque alterado durante a venda. Atualize e tente novamente.');
    }
    $total = number_format($totalCentavos/100, 2, '.', '');
    $stmt = $pdo->prepare('INSERT INTO loja_vendas (academia_id,vendedor_id,aluno_id,forma_pagamento,total) VALUES (?,?,?,?,?)');
    $stmt->execute([$academiaId,$vendedorId,$alunoId,$pagamento,$total]);
    $vendaId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare('INSERT INTO loja_itens_venda (venda_id,produto_id,quantidade,preco_unitario) VALUES (?,?,?,?)');
    foreach ($linhas as $linha) $stmt->execute([$vendaId, ...$linha]);
    return [$vendaId, $totalCentavos];
}

$tipoConteudo = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$ehJson = str_starts_with($tipoConteudo, 'application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$ehJson) {
    $acao = (string)($_POST['acao'] ?? '');
    $abaRetorno = str_starts_with($acao, 'produto_') ? 'produtos' : 'totem';
    $imagemNova = null;
    try {
        lojaCsrf();
        if (str_starts_with($acao, 'produto_')) {
            if (!$gerenciaCatalogo) throw new DomainException('Somente recepção ou administração pode alterar o catálogo.');
            if (in_array($acao, ['produto_criar','produto_editar'], true)) {
                $nome = trim((string)($_POST['nome'] ?? ''));
                $marca = trim((string)($_POST['marca'] ?? ''));
                $gramasTexto = trim((string)($_POST['gramatura_g'] ?? ''));
                $gramatura = null;
                if (mb_strlen($marca, 'UTF-8') > 100) {
                    throw new DomainException('A marca deve ter no máximo 100 caracteres.');
                }
                if ($gramasTexto !== '') {
                    if (!ctype_digit($gramasTexto) || strlen($gramasTexto) > 8 ||
                        (int)$gramasTexto < 1 || (int)$gramasTexto > 10000000) {
                        throw new DomainException('Informe a gramatura em gramas, entre 1 e 10.000.000.');
                    }
                    $gramatura = (int)$gramasTexto;
                }
                $marca = $marca === '' ? null : $marca;
                $preco = pdvPreco($_POST['preco'] ?? null);
                $estoque = pdvQuantidade($_POST['estoque'] ?? null);
                if (mb_strlen($nome, 'UTF-8') < 2 || mb_strlen($nome, 'UTF-8') > 150 || !$preco || $estoque === null) {
                    throw new DomainException('Informe nome (2–150 caracteres), preço e estoque válidos.');
                }
                $id = null;
                $antiga = null;
                if ($acao === 'produto_editar') {
                    $id = lojaId($_POST['produto_id'] ?? null);
                    $stmt = $pdo->prepare('SELECT foto FROM loja_produtos WHERE id=? AND academia_id=?');
                    $stmt->execute([$id,$academiaId]);
                    $antiga = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$antiga) throw new DomainException('Produto não encontrado nesta academia.');
                }
                if (isset($_FILES['foto'])) $imagemNova = pdvSalvarImagem($_FILES['foto'], $academiaId);
                if ($acao === 'produto_criar') {
                    $stmt = $pdo->prepare('INSERT INTO loja_produtos (academia_id,nome,marca,gramatura_g,preco,estoque,ativo,foto) VALUES (?,?,?,?,?,?,1,?)');
                    $stmt->execute([$academiaId,$nome,$marca,$gramatura,$preco,$estoque,$imagemNova]);
                } else {
                    if ($imagemNova !== null) {
                        $stmt = $pdo->prepare('UPDATE loja_produtos SET nome=?,marca=?,gramatura_g=?,preco=?,estoque=?,foto=? WHERE id=? AND academia_id=?');
                        $stmt->execute([$nome,$marca,$gramatura,$preco,$estoque,$imagemNova,$id,$academiaId]);
                        pdvRemoverImagem($antiga['foto'], $academiaId);
                    } else {
                        $stmt = $pdo->prepare('UPDATE loja_produtos SET nome=?,marca=?,gramatura_g=?,preco=?,estoque=? WHERE id=? AND academia_id=?');
                        $stmt->execute([$nome,$marca,$gramatura,$preco,$estoque,$id,$academiaId]);
                    }
                }
                lojaIr('produtos','ok',$acao === 'produto_criar' ? 'Produto cadastrado com sucesso.' : 'Produto atualizado com sucesso.');
            }
            if (in_array($acao, ['produto_status','produto_excluir'], true)) {
                $id = lojaId($_POST['produto_id'] ?? null);
                $stmt = $pdo->prepare('SELECT ativo,foto FROM loja_produtos WHERE id=? AND academia_id=?');
                $stmt->execute([$id,$academiaId]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$p) throw new DomainException('Produto não encontrado.');
                if ($acao === 'produto_excluir') {
                    $stmt = $pdo->prepare('SELECT (SELECT COUNT(*) FROM loja_itens_venda WHERE produto_id=?) + (SELECT COUNT(*) FROM loja_pedidos_itens WHERE produto_id=?)');
                    $stmt->execute([$id,$id]);
                    if ((int)$stmt->fetchColumn() > 0) throw new DomainException('Produto com histórico de vendas/pedidos: desative-o em vez de excluir.');
                    $stmt = $pdo->prepare('DELETE FROM loja_produtos WHERE id=? AND academia_id=?');
                    $stmt->execute([$id,$academiaId]);
                    pdvRemoverImagem($p['foto'], $academiaId);
                    lojaIr('produtos','ok','Produto excluído.');
                }
                $stmt = $pdo->prepare('UPDATE loja_produtos SET ativo=? WHERE id=? AND academia_id=?');
                $novo = (int)$p['ativo'] === 1 ? 0 : 1;
                $stmt->execute([$novo,$id,$academiaId]);
                lojaIr('produtos','ok', $novo ? 'Produto reativado.' : 'Produto desativado.');
            }
        }
        if ($acao === 'totem_gerar') {
            if (!$gerenciaCatalogo) throw new DomainException('Somente administração ou recepção pode configurar o totem.');
            $token = pdvToken();
            $stmt = $pdo->prepare('INSERT INTO loja_totens (academia_id,token_hash,ativo) VALUES (?,?,1) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),ativo=1');
            $stmt->execute([$academiaId,hash('sha256',$token)]);
            $_SESSION['loja_url_totem'] = '../totem/index.php?k=' . $token;
            lojaIr('totem','ok','Link seguro do totem gerado. Copie agora; gerar outro invalida o anterior.');
        }
        if ($acao === 'totem_desativar') {
            if (!$gerenciaCatalogo) throw new DomainException('Sem permissão para alterar o totem.');
            $stmt = $pdo->prepare('UPDATE loja_totens SET ativo=0 WHERE academia_id=?');
            $stmt->execute([$academiaId]);
            unset($_SESSION['loja_url_totem']);
            lojaIr('totem','ok','Totem desativado. Links anteriores não aceitam novos pedidos.');
        }
        if ($acao === 'pedido_concluir' || $acao === 'pedido_cancelar') {
            $id = lojaId($_POST['pedido_id'] ?? null);
            if ($acao === 'pedido_concluir') {
                if (($_POST['confirmo_pagamento'] ?? '') !== 'sim') throw new DomainException('Confirme que o pagamento foi realmente recebido.');
                $pagamento = lojaPagamento($_POST['forma_pagamento'] ?? null);
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id,status,total FROM loja_pedidos_totem WHERE id=? AND academia_id=? FOR UPDATE');
            $stmt->execute([$id,$academiaId]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pedido || $pedido['status'] !== 'pendente') throw new DomainException('Pedido não encontrado ou já atendido.');
            if ($acao === 'pedido_cancelar') {
                $stmt = $pdo->prepare("UPDATE loja_pedidos_totem SET status='cancelado' WHERE id=? AND academia_id=? AND status='pendente'");
                $stmt->execute([$id,$academiaId]);
                $pdo->commit();
                lojaIr('totem','ok','Pedido cancelado, sem baixa de estoque.');
            }
            $stmt = $pdo->prepare('SELECT produto_id,quantidade,preco_unitario FROM loja_pedidos_itens WHERE pedido_id=? ORDER BY produto_id');
            $stmt->execute([$id]);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $qtds = []; $precos = [];
            foreach ($itens as $item) { $qtds[(int)$item['produto_id']] = (int)$item['quantidade']; $precos[(int)$item['produto_id']] = (string)$item['preco_unitario']; }
            [$vendaId,$centavos] = lojaRegistrarItens($pdo,$academiaId,$vendedorId,null,$pagamento,$qtds,$precos);
            if ($centavos !== pdvCentavos((string)$pedido['total'])) throw new DomainException('O total do pedido está inconsistente.');
            $stmt = $pdo->prepare("UPDATE loja_pedidos_totem SET status='concluido',venda_id=? WHERE id=? AND academia_id=? AND status='pendente'");
            $stmt->execute([$vendaId,$id,$academiaId]);
            if ($stmt->rowCount() !== 1) throw new DomainException('Pedido já processado.');
            $pdo->commit();
            lojaIr('totem','ok','Pagamento confirmado e venda #' . $vendaId . ' registrada. Estoque atualizado.');
        }
        throw new DomainException('Operação não reconhecida.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($imagemNova !== null) pdvRemoverImagem($imagemNova, $academiaId);
        if (!($e instanceof DomainException)) error_log('PDV loja POST: ' . $e->getMessage());
        $mensagem = $e instanceof DomainException ? $e->getMessage() : (((int)($e instanceof PDOException ? ($e->errorInfo[1] ?? 0) : 0)) === 1062 ? 'Já existe um produto com esse nome nesta academia.' : 'Falha ao salvar. Confira se a migração do PDV foi aplicada.');
        lojaIr($abaRetorno,'erro',$mensagem);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ehJson) {
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    try {
        $dados = json_decode(file_get_contents('php://input'), true);
        if (!is_array($dados) || !hash_equals((string)$_SESSION['csrf_loja'], (string)($dados['csrf'] ?? ''))) throw new DomainException('Sessão inválida. Recarregue a página.');
        if (!is_array($dados['itens'] ?? null) || count($dados['itens']) < 1 || count($dados['itens']) > 50) throw new DomainException('Carrinho inválido.');
        $alunoId = pdvQuantidade($dados['aluno_id'] ?? null, 2147483647);
        if ($alunoId === null) throw new DomainException('Escolha o comprador.');
        $forma = lojaPagamento($dados['forma_pagamento'] ?? null);
        $quantidades=[];
        foreach ($dados['itens'] as $item) {
            if (!is_array($item)) throw new DomainException('Item inválido.');
            $id=lojaId($item['id'] ?? null); $qtd=pdvQuantidade($item['qtd'] ?? null,100);
            if (!$qtd || isset($quantidades[$id])) throw new DomainException('Quantidade inválida ou produto duplicado.');
            $quantidades[$id]=$qtd;
        }
        $pdo->beginTransaction();
        if ($alunoId > 0) {
            $stmt = $pdo->prepare("SELECT u.id FROM usuarios u JOIN alunos a ON a.usuario_id=u.id AND a.academia_id=u.academia_id WHERE u.id=? AND u.academia_id=? AND u.nivel_acesso='aluno' LIMIT 1");
            $stmt->execute([$alunoId,$academiaId]);
            if (!$stmt->fetchColumn()) throw new DomainException('Aluno não pertence a esta academia.');
        }
        [$id,$total] = lojaRegistrarItens($pdo,$academiaId,$vendedorId,$alunoId ?: null,$forma,$quantidades);
        $pdo->commit();
        echo json_encode(['ok'=>true,'venda_id'=>$id,'total'=>number_format($total/100,2,',','.')],JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (!($e instanceof DomainException)) error_log('PDV venda manual: '.$e->getMessage());
        http_response_code($e instanceof DomainException ? 422 : 500);
        echo json_encode(['ok'=>false,'erro'=>$e instanceof DomainException ? $e->getMessage() : 'Não foi possível registrar a venda.'],JSON_UNESCAPED_UNICODE);
    }
    exit;
}
$stmt = $pdo->prepare('SELECT id,nome,marca,gramatura_g,preco,estoque,ativo,foto FROM loja_produtos WHERE academia_id=? ORDER BY nome,id');
$stmt->execute([$academiaId]);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$disponiveis = array_values(array_filter($produtos, static fn($p)=>(int)$p['ativo']===1 && (int)$p['estoque']>0));
$stmt = $pdo->prepare("SELECT u.id,u.nome FROM usuarios u JOIN alunos a ON a.usuario_id=u.id AND a.academia_id=u.academia_id WHERE u.academia_id=? AND u.nivel_acesso='aluno' ORDER BY u.nome");
$stmt->execute([$academiaId]);
$alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare('SELECT v.id,v.total,v.criado_em,u.nome AS vendedor FROM loja_vendas v JOIN usuarios u ON u.id=v.vendedor_id WHERE v.academia_id=? ORDER BY v.id DESC LIMIT 8');
$stmt->execute([$academiaId]);
$vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT p.id,p.total,p.criado_em, COUNT(i.id) AS itens FROM loja_pedidos_totem p JOIN loja_pedidos_itens i ON i.pedido_id=p.id WHERE p.academia_id=? AND p.status='pendente' GROUP BY p.id,p.total,p.criado_em ORDER BY p.id LIMIT 60");
$stmt->execute([$academiaId]);
$pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$itensDosPedidos = [];
if ($pendentes) {
    $idsPedidos = array_map(static fn($p)=>(int)$p['id'], $pendentes);
    $placeholders = implode(',', array_fill(0,count($idsPedidos),'?'));
    $sqlItens = 'SELECT i.pedido_id,i.quantidade,i.preco_unitario,prod.nome,prod.marca,prod.gramatura_g
        FROM loja_pedidos_itens i JOIN loja_pedidos_totem ped ON ped.id=i.pedido_id
        JOIN loja_produtos prod ON prod.id=i.produto_id AND prod.academia_id=ped.academia_id
        WHERE ped.academia_id=? AND i.pedido_id IN (' . $placeholders . ') ORDER BY i.pedido_id,i.id';
    $stmtItens = $pdo->prepare($sqlItens);
    $stmtItens->execute(array_merge([$academiaId],$idsPedidos));
    foreach ($stmtItens->fetchAll(PDO::FETCH_ASSOC) as $item) $itensDosPedidos[(int)$item['pedido_id']][] = $item;
}
$stmt = $pdo->prepare('SELECT ativo FROM loja_totens WHERE academia_id=?');
$stmt->execute([$academiaId]);
$totemAtivo = (int)($stmt->fetchColumn() ?: 0) === 1;
$flash = $_SESSION['loja_flash'] ?? null; unset($_SESSION['loja_flash']);
$linkTotem = $_SESSION['loja_url_totem'] ?? null; unset($_SESSION['loja_url_totem']);
$nomeOperador = (string)($_SESSION['usuario_nome'] ?? 'Colaborador');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Loja e PDV | GymControl</title>
<link rel="stylesheet" href="../assets/pdv.css">
</head>
<body>
<header class="topbar"><div class="shell topbar-inner">
  <a class="brand" href="../index.php"><span class="brand-symbol">GC</span><span>GymControl <small>/ Loja & PDV</small></span></a>
  <div class="topbar-actions"><span class="operator">Operador: <strong><?= pdvEsc($nomeOperador) ?></strong></span>
  <?php if (($_SESSION['usuario_nivel'] ?? '') === 'admin'): ?><a href="index.php" class="navlink">Painel administrativo</a><?php endif; ?>
  <?php if (in_array($_SESSION['usuario_nivel'] ?? '', ['admin','recepcao'], true)): ?><a href="recepcao.php" class="navlink">Recepção</a><?php endif; ?>
  <a href="../logout.php" class="navlink">Sair</a></div>
</div></header>
<main class="shell page">
<?php renderizarAvisoPermissao(); ?>
<div class="page-intro"><div><p class="eyebrow">Gestão comercial • Academia</p><h1>Loja & Ponto de Venda</h1><p>Seu catálogo, vendas do balcão e pedidos do autoatendimento em um só lugar.</p></div><span class="permission-tag">🔒 Dados exclusivos desta academia</span></div>
<?php if ($flash): ?><div class="notice <?= $flash['tipo']==='ok'?'success':'error' ?>" role="status"><?= pdvEsc($flash['mensagem']) ?></div><?php endif; ?>
<nav class="tabs" aria-label="Seções do PDV">
 <a href="?aba=manual" class="tab <?= $aba==='manual'?'active':'' ?>" <?= $aba==='manual'?'aria-current="page"':'' ?>>🛒 Venda manual</a>
 <?php if ($gerenciaCatalogo): ?><a href="?aba=produtos" class="tab <?= $aba==='produtos'?'active':'' ?>" <?= $aba==='produtos'?'aria-current="page"':'' ?>>📦 Cadastrar produtos</a><?php endif; ?>
 <a href="?aba=totem" class="tab <?= $aba==='totem'?'active':'' ?>" <?= $aba==='totem'?'aria-current="page"':'' ?>>📱 Totem & pedidos <?php if ($pendentes): ?><span class="count"><?= count($pendentes) ?></span><?php endif; ?></a>
</nav>
<?php if ($aba === 'manual'): ?>
<div class="split-layout">
<section class="surface">
 <div class="surface-heading"><div><span class="overline">Atendimento presencial</span><h2>Escolha os produtos</h2><p>Clique para adicionar ao carrinho do cliente.</p></div><span class="chip"><?= count($disponiveis) ?> disponíveis</span></div>
 <div class="catalog-grid">
 <?php if (!$disponiveis): ?><div class="empty-panel"><strong>Seu catálogo está vazio.</strong><p><?php if($gerenciaCatalogo): ?>Cadastre produtos na aba Produtos para começar.<?php else: ?>Solicite ao administrador ou à recepção que cadastre os produtos.<?php endif;?></p></div><?php endif; ?>
 <?php foreach ($disponiveis as $p): ?><button type="button" class="catalog-card js-product" data-id="<?= (int)$p['id'] ?>" data-name="<?= pdvEsc($p['nome']) ?>" data-details="<?= pdvEsc(pdvDetalhesProduto($p)) ?>" data-price="<?= pdvCentavos((string)$p['preco']) ?>" data-stock="<?= (int)$p['estoque'] ?>">
 <div class="product-image"><?php if (!empty($p['foto'])): ?><img loading="lazy" src="../<?= pdvEsc($p['foto']) ?>" alt="<?= pdvEsc($p['nome']) ?>"><?php else: ?><span aria-hidden="true">✦</span><?php endif; ?></div>
 <strong><?= pdvEsc($p['nome']) ?></strong><?php if (pdvDetalhesProduto($p) !== ''): ?><span class="product-meta"><?= pdvEsc(pdvDetalhesProduto($p)) ?></span><?php endif; ?><span class="price">R$ <?= number_format((float)$p['preco'],2,',','.') ?></span><small>Estoque: <?= (int)$p['estoque'] ?> · Adicionar +</small>
 </button><?php endforeach; ?>
 </div>
</section>
<aside class="surface cart-panel"><div class="surface-heading"><div><span class="overline">Resumo do atendimento</span><h2>Carrinho</h2></div><span class="chip">Venda #nova</span></div>
 <label class="field">Aluno comprador<select id="alunoComprador"><option value="0">Cliente avulso / visitante</option><?php foreach ($alunos as $a): ?><option value="<?= (int)$a['id'] ?>"><?= pdvEsc($a['nome']) ?></option><?php endforeach; ?></select></label>
 <div id="listaCarrinho" class="cart-list" aria-live="polite"><p class="muted">Nenhum produto adicionado.</p></div>
 <div class="summary-total"><span>Total da venda</span><strong id="valorTotal">R$ 0,00</strong></div>
 <label class="field">Forma de pagamento recebida<select id="pagamentoLoja"><option value="PIX">PIX</option><option value="Cartao_Debito">Cartão de débito</option><option value="Cartao_Credito">Cartão de crédito</option><option value="Dinheiro">Dinheiro</option></select></label>
 <p class="hint">O operador confirma o pagamento separadamente; o sistema não cobra automaticamente.</p>
 <button id="finalizarVenda" type="button" class="btn primary full">Confirmar pagamento e registrar venda</button>
 <div id="feedbackManual" class="sr-status" role="status"></div>
</aside></div>
<section class="surface recent"><div class="surface-heading"><h2>Últimas vendas</h2></div>
 <?php if (!$vendas): ?><p class="muted">Nenhuma venda registrada nesta academia.</p><?php endif; ?>
 <?php foreach($vendas as $v): ?><div class="sale-row"><strong>#<?= (int)$v['id'] ?></strong><span><?= pdvEsc($v['vendedor']) ?></span><span><?= pdvEsc(date('d/m/Y H:i',strtotime($v['criado_em']))) ?></span><b>R$ <?= number_format((float)$v['total'],2,',','.') ?></b></div><?php endforeach; ?>
</section>
<?php elseif ($aba === 'produtos' && $gerenciaCatalogo): ?>
<div class="split-layout product-layout">
<section class="surface"><div class="surface-heading"><div><span class="overline">Catálogo próprio</span><h2>Cadastrar produto</h2><p>Produtos e imagens ficam isolados nesta academia.</p></div></div>
 <form method="post" enctype="multipart/form-data" class="product-form" action="?aba=produtos">
  <input type="hidden" name="csrf" value="<?= pdvEsc($_SESSION['csrf_loja']) ?>"><input type="hidden" name="acao" value="produto_criar">
  <label class="field">Nome do produto<input name="nome" type="text" required minlength="2" maxlength="150" placeholder="Ex.: Whey Protein"></label>
  <div class="field-pair"><label class="field">Marca (opcional)<input name="marca" type="text" maxlength="100" placeholder="Ex.: Marca X"></label><label class="field">Gramatura em gramas (opcional)<input name="gramatura_g" type="number" min="1" max="10000000" step="1" placeholder="Ex.: 900 ou 1500"><small>De 1.000 g em diante será exibido em kg.</small></label></div>
  <div class="field-pair"><label class="field">Preço final (R$)<input name="preco" type="text" required inputmode="decimal" placeholder="89,90" pattern="[0-9]+([.,][0-9]{1,2})?"></label><label class="field">Quantidade em estoque<input name="estoque" type="number" min="0" max="1000000" value="0" required></label></div>
  <label class="field">Foto do produto <small>(opcional)</small><input name="foto" type="file" accept="image/png,image/webp,image/jpeg" class="js-photo" data-preview="fotoNova"></label>
  <div class="preview-box"><img id="fotoNova" class="photo-preview" alt="Prévia da foto" hidden><div class="preview-help"><strong>Prefira PNG com fundo transparente</strong><span>Também aceita JPG/WebP. Até 3 MB e 3000 × 3000 px.</span></div></div>
  <button class="btn primary full" type="submit">+ Cadastrar produto</button>
 </form></section>
<section class="surface"><div class="surface-heading"><div><span class="overline">Produtos da unidade</span><h2>Gerenciar catálogo</h2></div><span class="chip"><?= count($produtos) ?> itens</span></div>
 <div class="manage-list">
 <?php if (!$produtos): ?><p class="muted">Nenhum produto cadastrado.</p><?php endif; ?>
 <?php foreach ($produtos as $p): ?><details class="manage-item"><summary><span class="mini-image"><?php if ($p['foto']): ?><img src="../<?= pdvEsc($p['foto']) ?>" alt=""><?php else: ?>✦<?php endif; ?></span><span class="manage-name"><strong><?= pdvEsc($p['nome']) ?></strong><small><?php if (pdvDetalhesProduto($p) !== ''): ?><?= pdvEsc(pdvDetalhesProduto($p)) ?> · <?php endif; ?>R$ <?= number_format((float)$p['preco'],2,',','.') ?> · <?= (int)$p['estoque'] ?> un.</small></span><span class="state <?= (int)$p['ativo']?'on':'off' ?>"><?= (int)$p['ativo']?'Ativo':'Inativo' ?></span></summary>
 <form method="post" enctype="multipart/form-data" class="edit-form" action="?aba=produtos"><input type="hidden" name="csrf" value="<?= pdvEsc($_SESSION['csrf_loja']) ?>"><input type="hidden" name="acao" value="produto_editar"><input type="hidden" name="produto_id" value="<?= (int)$p['id'] ?>">
 <label class="field">Nome<input name="nome" required maxlength="150" minlength="2" value="<?= pdvEsc($p['nome']) ?>"></label><div class="field-pair"><label class="field">Marca (opcional)<input name="marca" maxlength="100" value="<?= pdvEsc($p['marca']) ?>"></label><label class="field">Gramatura (g)<input name="gramatura_g" type="number" min="1" max="10000000" step="1" value="<?= $p['gramatura_g'] === null ? '' : (int)$p['gramatura_g'] ?>" placeholder="Ex.: 1500"></label></div><div class="field-pair"><label class="field">Preço final<input name="preco" required value="<?= pdvEsc((string)$p['preco']) ?>"></label><label class="field">Estoque<input type="number" name="estoque" required min="0" max="1000000" value="<?= (int)$p['estoque'] ?>"></label></div><label class="field">Trocar foto (opcional)<input name="foto" type="file" accept="image/png,image/jpeg,image/webp"></label><button class="btn primary" type="submit">Salvar alterações</button></form>
 <div class="small-actions"><form method="post" action="?aba=produtos"><input type="hidden" name="csrf" value="<?= pdvEsc($_SESSION['csrf_loja']) ?>"><input type="hidden" name="acao" value="produto_status"><input type="hidden" name="produto_id" value="<?= (int)$p['id'] ?>"><button class="btn secondary" type="submit"><?= (int)$p['ativo']?'Desativar':'Reativar' ?></button></form><form method="post" action="?aba=produtos" onsubmit="return confirm('Excluir este produto sem histórico de vendas ou pedidos?')"><input type="hidden" name="csrf" value="<?= pdvEsc($_SESSION['csrf_loja']) ?>"><input type="hidden" name="acao" value="produto_excluir"><input type="hidden" name="produto_id" value="<?= (int)$p['id'] ?>"><button class="btn danger" type="submit">Excluir sem histórico</button></form></div>
 </details><?php endforeach; ?></div>
</section></div>
<?php elseif ($aba === 'totem'): ?>
<div class="totem-grid"><section class="surface"><div class="surface-heading"><div><span class="overline">Autoatendimento</span><h2>Configurar totem</h2><p>Uma experiência visual para o aluno montar o próprio pedido.</p></div></div>
 <div class="kiosk-intro"><div class="kiosk-icon">📱</div><div><h3>Totem GymControl</h3><p>Abra a tela de pedidos em um tablet, computador ou totem físico da academia. O cliente não precisa de login.</p></div></div>
 <span class="state <?= $totemAtivo?'on':'off' ?>"><?= $totemAtivo?'Totem ativado':'Totem desativado' ?></span>
 <?php if ($linkTotem): ?><div class="link-panel"><label for="linkTotem">Link gerado (copie agora)</label><input id="linkTotem" type="text" readonly value="<?= pdvEsc($linkTotem) ?>"><button class="btn secondary" id="copiarLinkTotem" type="button">Copiar link completo</button><a class="btn primary" target="_blank" rel="noopener noreferrer" href="<?= pdvEsc($linkTotem) ?>">Abrir totem ↗</a><p class="hint">Gerar outro link invalida o anterior. O totem usa uma sessão independente e não desconecta o funcionário. Em outro dispositivo, substitua localhost pelo endereço/IP do servidor acessível na rede.</p></div><?php endif; ?>
 <?php if ($gerenciaCatalogo): ?><div class="small-actions"><form method="post" action="?aba=totem"><input type="hidden" name="csrf" value="<?= pdvEsc($_SESSION['csrf_loja']) ?>"><input type="hidden" name="acao" value="totem_gerar"><button class="btn primary" type="submit"><?= $totemAtivo?'Gerar novo link':'Ativar e gerar link' ?></button></form><form method="post" action="?aba=totem"><input type="hidden" name="csrf" value="<?= pdvEsc($_SESSION['csrf_loja']) ?>"><input type="hidden" name="acao" value="totem_desativar"><button class="btn danger" type="submit" <?= !$totemAtivo?'disabled':'' ?>>Desativar</button></form></div><?php endif; ?>
 <div class="info-block"><strong>Como funciona o pagamento?</strong><p>O aluno escolhe os produtos no totem e recebe o número do pedido. O recepcionista confirma o pagamento no balcão antes de dar baixa no estoque e registrar a venda. Não existe integração com maquininha, banco ou Pix automático.</p></div>
 </section>
 <section class="surface"><div class="surface-heading"><div><span class="overline">Pedidos para atender</span><h2>Fila do totem</h2><p>Atualize a página para consultar novos pedidos.</p></div><span class="chip"><?= count($pendentes) ?> pendentes</span></div>
 <div class="orders-list"><?php if (!$pendentes): ?><div class="empty-panel"><strong>Nenhum pedido aguardando.</strong><p>Os novos pedidos do totem desta academia aparecerão aqui.</p></div><?php endif; ?>
 <?php foreach($pendentes as $pedido): ?><article class="order-card"><div class="order-head"><strong>Pedido #<?= (int)$pedido['id'] ?></strong><span class="state off">Aguardando pagamento</span></div><p><?= (int)$pedido['itens'] ?> tipo(s) de produto · <?= pdvEsc(date('d/m/Y H:i',strtotime($pedido['criado_em']))) ?></p><h3>R$ <?= number_format((float)$pedido['total'],2,',','.') ?></h3><ul class="order-items"><?php foreach (($itensDosPedidos[(int)$pedido['id']] ?? []) as $item): ?><li><strong><?= (int)$item['quantidade'] ?>× <?= pdvEsc($item['nome']) ?><?php if (pdvDetalhesProduto($item) !== ''): ?> <small>(<?= pdvEsc(pdvDetalhesProduto($item)) ?>)</small><?php endif; ?></strong><span>R$ <?= number_format((float)$item['preco_unitario'] * (int)$item['quantidade'],2,',','.') ?></span></li><?php endforeach; ?></ul>
 <form method="post" action="?aba=totem" class="order-form"><input type="hidden" name="csrf" value="<?= pdvEsc($_SESSION['csrf_loja']) ?>"><input type="hidden" name="acao" value="pedido_concluir"><input type="hidden" name="pedido_id" value="<?= (int)$pedido['id'] ?>"><label class="field">Pagamento recebido via<select name="forma_pagamento"><option value="PIX">PIX</option><option value="Cartao_Debito">Cartão de débito</option><option value="Cartao_Credito">Cartão de crédito</option><option value="Dinheiro">Dinheiro</option></select></label><label class="confirmation"><input type="checkbox" name="confirmo_pagamento" value="sim" required> Confirmo que recebi o pagamento deste pedido</label><button class="btn primary full" type="submit">Concluir pedido e baixar estoque</button></form>
 <form method="post" action="?aba=totem" onsubmit="return confirm('Cancelar o pedido sem registrar venda?')"><input type="hidden" name="csrf" value="<?= pdvEsc($_SESSION['csrf_loja']) ?>"><input type="hidden" name="acao" value="pedido_cancelar"><input type="hidden" name="pedido_id" value="<?= (int)$pedido['id'] ?>"><button class="btn secondary full" type="submit">Cancelar pedido</button></form></article><?php endforeach; ?></div>
 </section></div>
<?php endif; ?>
</main>
<script>window.GC_PDV = <?= json_encode(['csrf'=>$_SESSION['csrf_loja'],'aba'=>$aba],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<script defer src="../assets/pdv.js"></script>
</body></html>
