<?php
// Personalização real por academia; preserva o arquivo de tema de cada unidade.
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['admin']);

$configTema  = require __DIR__ . '/../config/tema.php';
$temaAtual   = $configTema['tema'];
$arquivoTema = $configTema['arquivo'];
$academiaId  = (int) $configTema['academia_id'];
$mensagem = '';
$erro = '';
if (empty($_SESSION['csrf_tema'])) $_SESSION['csrf_tema'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novoNome = trim((string) ($_POST['nome_academia'] ?? ''));
    $novaCorPrincipal = (string) ($_POST['cor_principal'] ?? '');
    $novaCorDestaque = (string) ($_POST['cor_destaque'] ?? '');
    $novoLayoutMenu = (string) ($_POST['layout_menu'] ?? 'topo');
    $logoAtual = $temaAtual['logo_url'];
    $novoArquivoLogo = null;

    if (!hash_equals($_SESSION['csrf_tema'], (string) ($_POST['csrf_token'] ?? ''))) {
        $erro = 'Formulário inválido. Atualize a página e tente novamente.';
    } elseif ($novoNome === '' || mb_strlen($novoNome) > 150) {
        $erro = 'Informe um nome de academia de até 150 caracteres.';
    } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $novaCorPrincipal) ||
              !preg_match('/^#[0-9a-fA-F]{6}$/', $novaCorDestaque)) {
        $erro = 'As cores devem estar no formato hexadecimal (#RRGGBB).';
    } elseif (!in_array($novoLayoutMenu, ['topo','lateral'], true)) {
        $erro = 'Layout inválido.';
    }

    if (!$erro && isset($_FILES['logo_arquivo']) && $_FILES['logo_arquivo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $logo = $_FILES['logo_arquivo'];
        if ($logo['error'] !== UPLOAD_ERR_OK || $logo['size'] <= 0 || $logo['size'] > 2 * 1024 * 1024) {
            $erro = 'Imagem inválida. O limite é 2 MB.';
        } else {
            $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($logo['tmp_name']);
            $extensoes = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
            if (!isset($extensoes[$tipo]) || getimagesize($logo['tmp_name']) === false) {
                $erro = 'Envie uma imagem PNG, JPG ou WebP válida. SVG não é permitido.';
            } else {
                $pastaDestino = __DIR__ . '/../uploads/logos/';
                if (!is_dir($pastaDestino) && !mkdir($pastaDestino, 0755, true) && !is_dir($pastaDestino)) {
                    $erro = 'Não foi possível criar a pasta de logos.';
                } else {
                    $nomeArquivo = 'logo_acad_' . $academiaId . '_' . bin2hex(random_bytes(8)) . '.' . $extensoes[$tipo];
                    $novoArquivoLogo = $pastaDestino . $nomeArquivo;
                    if (!move_uploaded_file($logo['tmp_name'], $novoArquivoLogo)) {
                        $erro = 'Falha ao salvar a imagem.';
                        $novoArquivoLogo = null;
                    } else {
                        $logoAtual = 'uploads/logos/' . $nomeArquivo;
                    }
                }
            }
        }
    }

    if (!$erro) {
        $novoTema = [
            'nome_academia' => $novoNome,
            'cor_principal' => $novaCorPrincipal,
            'cor_destaque' => $novaCorDestaque,
            'layout_menu' => $novoLayoutMenu,
            'logo_url' => $logoAtual
        ];
        $json = json_encode($novoTema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json !== false && file_put_contents($arquivoTema, $json, LOCK_EX) !== false) {
            $temaAtual = $novoTema;
            $mensagem = 'Identidade visual e layout salvos com sucesso!';
        } else {
            $erro = 'Não foi possível gravar a personalização. Verifique as permissões da pasta config/temas.';
            if ($novoArquivoLogo && is_file($novoArquivoLogo)) unlink($novoArquivoLogo);
        }
    }
}

require_once __DIR__ . '/../layouts/header.php';
?>
<div class="panel-card" style="max-width:720px;margin-inline:auto">
    <h1 style="font-size:26px;margin-bottom:10px">Personalização da academia</h1>
    <p style="color:var(--text-muted);margin-bottom:24px">Aparência exclusiva da sua academia, sem afetar as demais.</p>
    <?php if ($mensagem): ?><p style="color:#86efac;margin-bottom:16px"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($erro): ?><p style="color:#fca5a5;margin-bottom:16px"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_tema'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group"><label for="nome_academia">Nome da academia</label><input id="nome_academia" name="nome_academia" maxlength="150" required value="<?= htmlspecialchars($temaAtual['nome_academia'], ENT_QUOTES, 'UTF-8') ?>"></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
            <div class="form-group"><label for="cor_principal">Cor principal</label><input type="color" id="cor_principal" name="cor_principal" value="<?= htmlspecialchars($temaAtual['cor_principal'], ENT_QUOTES, 'UTF-8') ?>" required></div>
            <div class="form-group"><label for="cor_destaque">Cor de destaque</label><input type="color" id="cor_destaque" name="cor_destaque" value="<?= htmlspecialchars($temaAtual['cor_destaque'], ENT_QUOTES, 'UTF-8') ?>" required></div>
        </div>
        <div class="form-group"><label for="layout_menu">Posição do menu</label>
            <select name="layout_menu" id="layout_menu">
                <option value="topo" <?= $temaAtual['layout_menu'] === 'topo' ? 'selected' : '' ?>>Superior</option>
                <option value="lateral" <?= $temaAtual['layout_menu'] === 'lateral' ? 'selected' : '' ?>>Lateral</option>
            </select>
        </div>
        <div class="form-group"><label for="logo_arquivo">Logo (PNG, JPG ou WebP, até 2 MB)</label><input type="file" name="logo_arquivo" id="logo_arquivo" accept="image/png,image/jpeg,image/webp"></div>
        <?php if ($temaAtual['logo_url']): ?><p style="color:var(--text-muted);margin-bottom:14px">Logo atual: <img src="../<?= htmlspecialchars($temaAtual['logo_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Logo da academia" style="max-width:140px;max-height:60px;vertical-align:middle"></p><?php endif; ?>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar personalização</button>
        <a href="index.php" class="btn btn-accent" style="margin-left:8px">Voltar</a>
    </form>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
