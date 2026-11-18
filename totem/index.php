<?php
/** GymControl — autoatendimento público aberto por token criado na academia. */
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
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
// Sessão própria do totem: nenhuma operação aqui apaga a sessão de funcionários.
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/pdv_funcoes.php';
$token = (string)($_GET['k'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/D',$token)) { http_response_code(404); exit('Totem indisponível. Solicite o link na recepção.'); }
$hash = hash('sha256',$token);
$stmt = $pdo->prepare("SELECT t.academia_id,a.nome FROM loja_totens t JOIN academias a ON a.id=t.academia_id WHERE t.token_hash=? AND t.ativo=1 AND a.status='ativa' LIMIT 1");
$stmt->execute([$hash]);
$academia = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$academia) { http_response_code(404); exit('Este totem não está ativo. Solicite um novo link na recepção.'); }
if (($_SESSION['totem_token_hash'] ?? '') !== $hash) {
    session_regenerate_id(true);
    $_SESSION['totem_token_hash'] = $hash;
    $_SESSION['totem_academia_id'] = (int)$academia['academia_id'];
    $_SESSION['totem_csrf'] = bin2hex(random_bytes(32));
    $_SESSION['totem_ultimos_pedidos'] = [];
}
$id = (int)$academia['academia_id'];
$stmt = $pdo->prepare('SELECT id,nome,marca,gramatura_g,preco,estoque,foto FROM loja_produtos WHERE academia_id=? AND ativo=1 AND estoque>0 ORDER BY nome');
$stmt->execute([$id]);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#0f172a"><meta name="gc-csrf" content="<?= pdvEsc($_SESSION['totem_csrf']) ?>"><title>Totem de pedidos | GymControl</title><link rel="stylesheet" href="../assets/totem.css"></head>
<body>
<div class="ambient halo-one" aria-hidden="true"></div><div class="ambient halo-two" aria-hidden="true"></div>
<header class="kiosk-header"><div class="kiosk-shell header-content"><div class="kiosk-brand"><span class="logo-gc">GC</span><span><strong>GymControl</strong><small>Self Service • <?= pdvEsc((string)$academia['nome']) ?></small></span></div><span class="kiosk-secure">● Autoatendimento da academia</span></div></header>
<main class="kiosk-shell" id="kioskApp">
<section id="welcome" class="welcome-screen" aria-labelledby="welcomeTitle"><div class="welcome-copy"><div class="eyebrow-pill"><span class="live-dot"></span> O seu momento começa aqui</div><h1 id="welcomeTitle">Seu treino.<br><em>Suas escolhas.</em></h1><p>Suplementos, bebidas e acessórios: escolha seus favoritos em poucos toques. Retire e pague no balcão.</p><button class="big-cta" type="button" id="startOrder">Toque para começar <span>→</span></button><div class="welcome-benefits"><span>✓ Escolha no seu ritmo</span><span>✓ Revise antes de enviar</span><span>✓ Retire no balcão</span></div></div><div class="welcome-visual" aria-hidden="true"><div class="orbit orbit-a"></div><div class="orbit orbit-b"></div><div class="floating-tile tile-one">✦ <span>Escolha seus produtos</span></div><div class="floating-tile tile-two">◈ <span>Finalize em poucos toques</span></div><div class="hero-monogram">GC</div><div class="visual-glow"></div></div></section>
<section id="shopping" class="shopping-screen" hidden aria-labelledby="shopTitle"><div class="shopping-title"><div><p class="section-overline">01 / Sua seleção</p><h1 id="shopTitle">O que vai levar hoje?</h1><p>Escolha os produtos disponíveis na academia.</p></div><button id="backWelcome" type="button" class="small-secondary">← Início</button></div>
<div class="shop-controls"><label class="search-field"><span aria-hidden="true">⌕</span><input id="searchProducts" type="search" autocomplete="off" placeholder="Buscar produtos" aria-label="Buscar produtos"></label><span class="products-count"><?= count($produtos) ?> disponíveis</span></div>
<div class="shop-layout"><div id="kioskProducts" class="kiosk-products">
<?php if (!$produtos): ?><div class="empty-kiosk"><strong>Estamos preparando o catálogo.</strong><p>Volte em breve ou peça ajuda na recepção.</p></div><?php endif; ?>
<?php foreach($produtos as $p): ?><article class="kiosk-product" data-id="<?= (int)$p['id'] ?>" data-name="<?= pdvEsc($p['nome']) ?>" data-details="<?= pdvEsc(pdvDetalhesProduto($p)) ?>" data-price="<?= pdvCentavos((string)$p['preco']) ?>" data-stock="<?= (int)$p['estoque'] ?>"><div class="kiosk-product-image"><?php if ($p['foto']): ?><img loading="lazy" src="../<?= pdvEsc($p['foto']) ?>" alt="<?= pdvEsc($p['nome']) ?>"><?php else: ?><span class="fallback-product" aria-hidden="true">✦</span><?php endif; ?></div><div class="kiosk-product-details"><h2><?= pdvEsc($p['nome']) ?></h2><?php if (pdvDetalhesProduto($p) !== ''): ?><p class="product-meta"><?= pdvEsc(pdvDetalhesProduto($p)) ?></p><?php endif; ?><div class="product-bottom"><span class="product-price">R$ <?= number_format((float)$p['preco'],2,',','.') ?></span><button type="button" class="add-product" aria-label="Adicionar <?= pdvEsc($p['nome']) ?>">+ <span>Adicionar</span></button></div></div></article><?php endforeach; ?>
</div><aside class="kiosk-cart" aria-label="Carrinho"><div class="cart-top"><h2>Seu pedido</h2><span id="cartQuantity" class="cart-count">0 itens</span></div><div id="kioskCartItems" class="kiosk-cart-lines"><p class="cart-empty">Seu carrinho está vazio.<br>Adicione produtos para continuar.</p></div><div class="cart-footer"><div class="cart-total"><span>Total</span><strong id="cartTotal">R$ 0,00</strong></div><button type="button" id="reviewOrder" class="big-cta full" disabled>Revisar pedido →</button><small>Pagamento e retirada no balcão</small></div></aside></div>
</section>
<section id="review" class="result-screen" hidden aria-labelledby="reviewTitle"><div class="step-icon">🛍</div><p class="section-overline">02 / Confira seu pedido</p><h1 id="reviewTitle">Tudo certinho?</h1><p>Confira os produtos antes de enviar para a recepção.</p><div class="review-sheet"><div id="reviewItems"></div><div class="review-total"><span>Total estimado</span><strong id="reviewTotal">R$ 0,00</strong></div></div><div class="review-actions"><button type="button" id="editOrder" class="small-secondary large">← Editar carrinho</button><button type="button" id="sendOrder" class="big-cta">Enviar pedido →</button></div><p id="orderError" class="order-error" role="alert"></p><p class="disclaimer">Seu pedido será enviado, mas <strong>não será cobrado automaticamente</strong>. Confirme o pagamento na recepção. O estoque é validado na conclusão.</p></section>
<section id="complete" class="result-screen" hidden aria-labelledby="doneTitle"><div class="success-ring"><span>✓</span></div><p class="section-overline">03 / Pedido recebido</p><h1 id="doneTitle">Prontinho! 🎉</h1><p>Seu pedido foi enviado para a recepção. Anote ou mostre este número:</p><div class="order-number" id="orderNumber">#0000</div><div class="success-summary">Dirija-se ao balcão para <strong>pagar e retirar</strong> seus produtos. O pedido só vira uma venda quando a equipe confirmar o pagamento.</div><button id="restartOrder" type="button" class="big-cta">Novo pedido →</button></section>
</main>
<div class="mobile-bottom" id="mobileCart" hidden><span><small>Seu pedido</small><strong id="mobileTotal">R$ 0,00</strong></span><button id="mobileReview" type="button">Ver carrinho →</button></div>
<script defer src="../assets/totem.js"></script>
</body></html>
