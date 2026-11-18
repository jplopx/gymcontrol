<?php
// Horários do sistema e do MySQL alinhados ao fuso de operação.
date_default_timezone_set('America/Sao_Paulo');
$host     = 'localhost';
$dbname   = 'gymcontrol';
$usuario  = 'root';
$senha    = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $usuario, $senha, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    error_log("GymControl: falha na conexão com o banco: " . $e->getMessage());
    http_response_code(503);
    exit("Banco de dados indisponível. Verifique a configuração da conexão.");
}