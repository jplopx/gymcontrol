<?php
// admin/personalizacao.php
require_once '../config/auth.php';
verificarAcesso(['admin']);

$configTema  = require '../config/tema.php';
$temaAtual   = $configTema['tema'];
$arquivoTema = $configTema['arquivo'];
$academiaId  = $configTema['academia_id'];

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novoNome         = trim($_POST['nome_academia'] ?? 'GymControl');
    $novaCorPrincipal = $_POST['cor_principal'] ?? '#0284c7';
    $novaCorDestaque  = $_POST['cor_destaque'] ?? '#10b981';
    $novoLayoutMenu   = $_POST['layout_menu'] ?? 'topo';
    $logoAtual        = $temaAtual['logo_url'];

    if (isset($_FILES['logo_arquivo']) && $_FILES['logo_arquivo']['error'] === UPLOAD_ERR_OK) {
        $pastaDestino = '../uploads/logos/';
        if (!is_dir($pastaDestino)) {
            mkdir($pastaDestino, 0755, true);
        }

        $extensao = strtolower(pathinfo($_FILES['logo_arquivo']['name'], PATHINFO_EXTENSION));
        if (in_array($extensao, ['png', 'jpg', 'jpeg', 'webp', 'svg'])) {
            $nomeArquivo = "logo_acad_{$academiaId}_" . time() . '.' . $extensao;
            if (move_uploaded_file($_FILES['logo_arquivo']['tmp_name'], $pastaDestino . $nomeArquivo)) {
                $logoAtual = 'uploads/logos/' . $nomeArquivo;
            }
        }
    }

    $novoTema = [
        'nome_academia' => $novoNome,
        'cor_principal' => $novaCorPrincipal,
        'cor_destaque'  => $novaCorDestaque,
        'layout_menu'   => $novoLayoutMenu,
        'logo_url'      => $logoAtual
    ];

    file_put_contents($arquivoTema, json_encode($novoTema, JSON_PRETTY_PRINT));
    $temaAtual = $novoTema;
    $mensagem = 'Identidade visual e layout salvos com sucesso!';
}

require_once '../layouts/header.php';
?>