<?php
/** Operações compartilhadas da loja. Nunca confiar no academia_id recebido do cliente. */
function pdvEsc(?string $valor): string { return htmlspecialchars($valor ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function pdvPreco($valor): ?string {
    if (!is_string($valor) || !preg_match('/^\d{1,8}([,.]\d{1,2})?$/D', trim($valor))) return null;
    $preco = (float) str_replace(',', '.', trim($valor));
    return $preco > 0 && $preco <= 99999999.99 ? number_format($preco, 2, '.', '') : null;
}
function pdvQuantidade($valor, int $max = 1000000): ?int {
    $n = filter_var($valor, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>$max]]);
    return $n === false ? null : (int) $n;
}
/** Retorna o caminho relativo à raiz do projeto, ou null quando não houve upload. */
function pdvSalvarImagem(array $arquivo, int $academiaId): ?string {
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($arquivo['error'] ?? -1) !== UPLOAD_ERR_OK || !isset($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
        throw new DomainException('O envio da foto falhou. Tente novamente.');
    }
    if (($arquivo['size'] ?? 0) > 3 * 1024 * 1024 || ($arquivo['size'] ?? 0) <= 0) {
        throw new DomainException('A foto precisa ter no máximo 3 MB.');
    }
    $info = @getimagesize($arquivo['tmp_name']);
    $mime = $info['mime'] ?? '';
    $extensoes = ['image/png'=>'png', 'image/jpeg'=>'jpg', 'image/webp'=>'webp'];
    if (!isset($extensoes[$mime]) || ($info[0] ?? 0) > 3000 || ($info[1] ?? 0) > 3000 || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
        throw new DomainException('Envie uma imagem PNG, JPG ou WebP válida, de até 3000 × 3000 pixels.');
    }
    $dir = dirname(__DIR__) . '/uploads/produtos/' . $academiaId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta de fotos.');
    }
    $arquivoNome = bin2hex(random_bytes(20)) . '.' . $extensoes[$mime];
    if (!move_uploaded_file($arquivo['tmp_name'], $dir . '/' . $arquivoNome)) {
        throw new RuntimeException('Não foi possível salvar a foto no servidor.');
    }
    return 'uploads/produtos/' . $academiaId . '/' . $arquivoNome;
}
function pdvRemoverImagem(?string $foto, int $academiaId): void {
    if (!$foto || !preg_match('~^uploads/produtos/' . $academiaId . '/[a-f0-9]{40}\.(?:png|jpg|webp)$~D', $foto)) return;
    $arquivo = dirname(__DIR__) . '/' . $foto;
    if (is_file($arquivo)) @unlink($arquivo);
}
function pdvToken(): string { return bin2hex(random_bytes(32)); }
/** Preço final registrado no pedido/na venda, SEM confiar no valor enviado pelo navegador. */
function pdvCentavos(string $preco): int {
    $partes = explode('.', $preco);
    return ((int)$partes[0] * 100) + (int)str_pad($partes[1] ?? '0', 2, '0');
}

/** Exibe pesos em g até 999 g e em kg desde 1000 g (ex.: 1500 => 1,5 kg). */
function pdvFormatarGramatura($gramas): string {
    if ($gramas === null || $gramas === '' || (int)$gramas <= 0) return '';
    $n = (int)$gramas;
    if ($n < 1000) return $n . ' g';
    // No máximo três casas decimais, sem zeros finais.
    $kg = rtrim(rtrim(number_format($n / 1000, 3, ',', ''), '0'), ',');
    return $kg . ' kg';
}
/** Marca e peso aparecem de modo idêntico no PDV, carrinhos e totem. */
function pdvDetalhesProduto(array $produto): string {
    $marca = trim((string)($produto['marca'] ?? ''));
    $peso = pdvFormatarGramatura($produto['gramatura_g'] ?? null);
    return implode(' · ', array_filter([$marca, $peso], static fn($valor) => $valor !== ''));
}
