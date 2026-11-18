<?php
/**
 * Identificador público de tenant. A senha do usuário continua obrigatória.
 * O índice UNIQUE em academias.codigo é necessário para impedir colisões reais.
 */
function gcGerarCredencialAcademia(PDO $pdo): string
{
    $consulta = $pdo->prepare('SELECT id FROM academias WHERE codigo = ? LIMIT 1');
    for ($tentativa = 0; $tentativa < 40; $tentativa++) {
        $codigo = (string) random_int(10000000, 99999999);
        $consulta->execute([$codigo]);
        if (!$consulta->fetchColumn()) return $codigo;
    }
    throw new RuntimeException('Não foi possível reservar uma credencial exclusiva.');
}
