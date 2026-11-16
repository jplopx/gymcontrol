<?php
// config/tema.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$academiaId = $_SESSION['academia_id'] ?? 1;
$pastaTemas = __DIR__ . '/temas/';
if (!is_dir($pastaTemas)) {
    mkdir($pastaTemas, 0755, true);
}

$arquivoTema = $pastaTemas . "academia_{$academiaId}.json";

$temaAtual = [
    'nome_academia' => 'GymControl',
    'cor_principal' => '#0284c7',
    'cor_destaque'  => '#10b981',
    'layout_menu'   => 'topo',
    'logo_url'      => ''
];

if (file_exists($arquivoTema)) {
    $dadosSalvos = json_decode(file_get_contents($arquivoTema), true);
    if (is_array($dadosSalvos)) {
        $temaAtual = array_merge($temaAtual, $dadosSalvos);
    }
}

// Retorna explicitamente para o analisador estático reconhecer todas as variáveis
return [
    'tema'        => $temaAtual,
    'arquivo'     => $arquivoTema,
    'academia_id' => $academiaId
];