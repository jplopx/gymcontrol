<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Função para proteger as páginas
function verificarAcesso($tipoPermitido) {
    // Se não estiver logado, chuta de volta pra tela de login
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: ../login.php");
        exit;
    }

    // Se o tipo do usuário for diferente do permitido na página
    if ($_SESSION['usuario_tipo'] !== $tipoPermitido) {
        // Redireciona pro painel correspondente ao tipo dele
        header("Location: ../" . $_SESSION['usuario_tipo'] . "/index.php");
        exit;
    }
}