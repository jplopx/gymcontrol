<?php
// config/tema.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Não usar academia 1 como fallback: isso exporia branding/configuração de outro tenant.
$academiaId = (int) ($_SESSION['academia_id'] ?? 0);
if ($academiaId < 1) {
    http_response_code(403);
    exit('Academia não autenticada. Faça login novamente.');
}
$pastaTemas = __DIR__ . '/temas/';
if (!is_dir($pastaTemas)) {
    mkdir($pastaTemas, 0755, true);
}

$arquivoTema = $pastaTemas . "academia_{$academiaId}.json";

$nomeAcademiaBanco = 'GymControl';
// Para unidades recém-criadas, usar seu próprio nome até que o gestor personalize o tema.
if (isset($pdo) && $pdo instanceof PDO) {
    $qNome = $pdo->prepare('SELECT nome FROM academias WHERE id=? LIMIT 1');
    $qNome->execute([$academiaId]);
    $nomeAcademiaBanco = (string)($qNome->fetchColumn() ?: 'GymControl');
}


$temaAtual = [
    'nome_academia' => $nomeAcademiaBanco,
    'cor_principal' => '#0284c7',
    'cor_destaque'  => '#10b981',
    'layout_menu'   => 'topo',
    'logo_url'      => ''
];

if (file_exists($arquivoTema)) {
    $dadosSalvos = json_decode(file_get_contents($arquivoTema), true);
    if (is_array($dadosSalvos)) {
        if (isset($dadosSalvos['nome_academia']) && is_string($dadosSalvos['nome_academia']) && mb_strlen($dadosSalvos['nome_academia']) <= 150)
            $temaAtual['nome_academia'] = $dadosSalvos['nome_academia'];
        foreach (['cor_principal', 'cor_destaque'] as $cor) {
            if (isset($dadosSalvos[$cor]) && is_string($dadosSalvos[$cor]) && preg_match('/^#[0-9a-fA-F]{6}$/', $dadosSalvos[$cor]))
                $temaAtual[$cor] = $dadosSalvos[$cor];
        }
        if (in_array($dadosSalvos['layout_menu'] ?? '', ['topo','lateral'], true))
            $temaAtual['layout_menu'] = $dadosSalvos['layout_menu'];
        if (isset($dadosSalvos['logo_url']) && is_string($dadosSalvos['logo_url']) &&
            preg_match('~^uploads/logos/logo_acad_[0-9]+_[a-f0-9]+\.(png|jpg|webp)$~', $dadosSalvos['logo_url']))
            $temaAtual['logo_url'] = $dadosSalvos['logo_url'];
    }
}

// Retorna explicitamente para o analisador estático reconhecer todas as variáveis
return [
    'tema'        => $temaAtual,
    'arquivo'     => $arquivoTema,
    'academia_id' => $academiaId
];