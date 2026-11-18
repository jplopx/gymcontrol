<?php
// Execução APENAS pelo terminal, nunca via navegador.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Apenas via terminal.'); }
require_once __DIR__ . '/../config/conexao.php';
function perguntar(string $prompt): string { echo $prompt; return trim((string)fgets(STDIN)); }
try {
    $t=$pdo->query("SHOW TABLES LIKE 'superadmins'")->fetchColumn();
    if (!$t) throw new RuntimeException('Execute primeiro: php database/migrar_superadmin.php');
    $nome=perguntar('Nome do administrador geral: ');
    $email=mb_strtolower(perguntar('E-mail do administrador geral: '), 'UTF-8');
    $senha=perguntar('Senha forte (mínimo 12 caracteres; entrada visível no terminal): ');
    if (mb_strlen($nome)<3 || mb_strlen($nome)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150 || strlen($senha)<12 || strlen($senha)>72) throw new RuntimeException('Nome, e-mail ou senha inválidos.');
    $q=$pdo->prepare('INSERT INTO superadmins(nome,email,senha) VALUES(?,?,?)');
    $q->execute([$nome,$email,password_hash($senha,PASSWORD_DEFAULT)]);
    echo "Superadministrador criado. Acesse /gymcontrol/superadmin/login.php\n";
} catch(Throwable $e) { fwrite(STDERR,'Falha: '.$e->getMessage()."\n"); exit(1); }
