<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';
function superEsc($valor): string { return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8'); }
function exigirSuper(PDO $pdo): array {
    if (empty($_SESSION['superadmin_id'])) { header('Location: login.php'); exit; }
    $q=$pdo->prepare('SELECT id,nome,email FROM superadmins WHERE id=? AND ativo=1 LIMIT 1');
    $q->execute([(int)$_SESSION['superadmin_id']]); $conta=$q->fetch(PDO::FETCH_ASSOC);
    if (!$conta) { unset($_SESSION['superadmin_id']); header('Location: login.php'); exit; }
    return $conta;
}
function superCsrf(): string {
    if (empty($_SESSION['supercsrf'])) $_SESSION['supercsrf']=bin2hex(random_bytes(32));
    return $_SESSION['supercsrf'];
}
function superRedir(string $tipo, string $msg): void {
    $_SESSION['super_flash']=[$tipo,$msg]; header('Location: index.php'); exit;
}
function superAudit(PDO $pdo, int $id, string $acao, ?int $academia, ?int $solicitacao, string $detalhes=''): void {
    $q=$pdo->prepare('INSERT INTO superadmin_auditoria(superadmin_id,acao,academia_id,solicitacao_id,detalhes) VALUES(?,?,?,?,?)');
    $q->execute([$id,$acao,$academia,$solicitacao,mb_substr($detalhes,0,255)]);
}
