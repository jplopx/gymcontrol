<?php
/** Endpoint público do totem. Somente enfileira pedido: NUNCA confirma pagamento ou baixa estoque. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Sessão pública do totem: nunca compartilha cookie/dados com login administrativo.
    // Caminho restrito à pasta /totem/; funciona mesmo quando o projeto está em subpasta.
    session_name('GYMCONTROL_TOTEM');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    $caminhoCookieTotem = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/totem/index.php')), '/') . '/';
    session_set_cookie_params([
        'lifetime' => 0, 'path' => $caminhoCookieTotem, 'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}
function erroTotem(string $mensagem, int $codigo=422): never {
    http_response_code($codigo);
    echo json_encode(['ok'=>false,'erro'=>$mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') erroTotem('Método não permitido.',405);
if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')),'application/json')) erroTotem('Envie dados JSON.',415);
$dados = json_decode(file_get_contents('php://input'), true);
if (!is_array($dados) || empty($_SESSION['totem_csrf']) || !hash_equals((string)$_SESSION['totem_csrf'],(string)($dados['csrf'] ?? ''))) erroTotem('Sessão expirada. Reabra o totem.',403);
$academiaId = (int)($_SESSION['totem_academia_id'] ?? 0);
$hash = (string)($_SESSION['totem_token_hash'] ?? '');
if (!$academiaId || !preg_match('/^[a-f0-9]{64}$/D',$hash)) erroTotem('Totem não autorizado.',403);
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/pdv_funcoes.php';
try {
    $stmt = $pdo->prepare("SELECT t.academia_id FROM loja_totens t JOIN academias a ON a.id=t.academia_id WHERE t.academia_id=? AND t.token_hash=? AND t.ativo=1 AND a.status='ativa'");
    $stmt->execute([$academiaId,$hash]);
    if (!$stmt->fetchColumn()) erroTotem('Este totem foi desativado. Solicite ajuda à recepção.',403);
    $agora=time();
    $limite = array_values(array_filter((array)($_SESSION['totem_ultimos_pedidos'] ?? []),static fn($t)=>is_int($t) && $t > $agora - 600));
    if (count($limite) >= 4) erroTotem('Limite temporário de pedidos atingido. Procure a recepção.',429);
    $itens = $dados['itens'] ?? null;
    if (!is_array($itens) || count($itens)<1 || count($itens)>30) erroTotem('Selecione de 1 a 30 produtos.');
    $qtds=[];
    foreach($itens as $item) {
        if (!is_array($item)) erroTotem('Produto inválido.');
        $id=filter_var($item['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $q=filter_var($item['qtd'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>20]]);
        if (!$id || !$q || isset($qtds[$id])) erroTotem('Quantidade inválida ou produto duplicado.');
        $qtds[(int)$id] = (int)$q;
    }
    ksort($qtds,SORT_NUMERIC);
    // Evita abuso automatizado de um único token: no máximo 40 pedidos em 5 minutos por academia.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loja_pedidos_totem WHERE academia_id=? AND criado_em >= (NOW() - INTERVAL 5 MINUTE)");
    $stmt->execute([$academiaId]);
    if ((int)$stmt->fetchColumn() >= 40) erroTotem('Totem temporariamente ocupado. Solicite ajuda à recepção.',429);
    $pdo->beginTransaction();
    $consulta = $pdo->prepare('SELECT id,preco,estoque FROM loja_produtos WHERE id=? AND academia_id=? AND ativo=1 FOR UPDATE');
    $total=0; $linhas=[];
    foreach($qtds as $id => $q) {
        $consulta->execute([$id,$academiaId]);
        $produto=$consulta->fetch(PDO::FETCH_ASSOC);
        if (!$produto || (int)$produto['estoque'] < $q) throw new DomainException('Um item ficou indisponível. Volte e atualize o pedido.');
        $preco=(string)$produto['preco'];
        $total += pdvCentavos($preco) * $q;
        $linhas[]=[$id,$q,$preco];
    }
    $stmt=$pdo->prepare("INSERT INTO loja_pedidos_totem (academia_id,status,total) VALUES (?,'pendente',?)");
    $stmt->execute([$academiaId,number_format($total/100,2,'.','')]);
    $pedidoId=(int)$pdo->lastInsertId();
    $stmt=$pdo->prepare('INSERT INTO loja_pedidos_itens (pedido_id,produto_id,quantidade,preco_unitario) VALUES (?,?,?,?)');
    foreach($linhas as $linha) $stmt->execute([$pedidoId,...$linha]);
    $pdo->commit();
    $limite[]=$agora; $_SESSION['totem_ultimos_pedidos']=$limite;
    echo json_encode(['ok'=>true,'pedido_id'=>$pedidoId],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (!($e instanceof DomainException)) error_log('GymControl totem: '.$e->getMessage());
    erroTotem($e instanceof DomainException ? $e->getMessage() : 'Não foi possível enviar seu pedido. Procure a recepção.', $e instanceof DomainException ? 422 : 500);
}
