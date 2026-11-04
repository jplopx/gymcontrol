<?php
require_once __DIR__ . '/config/conexao.php';

$senhaNova = password_hash('123456', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE email IN ('admin@gymcontrol.com', 'professor@gymcontrol.com', 'aluno@gymcontrol.com')");
$stmt->execute([':senha' => $senhaNova]);

echo "Senhas atualizadas com sucesso para '123456'!";