<?php
// ============================================================================
// GYMCONTROL - MIDDLEWARE DE AUTENTICAÇÃO E CONTROLE DE ACESSO RBAC
// ============================================================================

// 1. INICIALIZAÇÃO DE SESSÃO PHP SEGURA
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        $seguro = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
               || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $seguro,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        // Fallback seguro caso o diretório padrão de sessões do SO não tenha permissão de escrita
        $savePath = session_save_path();
        if (empty($savePath) || !is_writable($savePath)) {
            session_save_path(sys_get_temp_dir());
        }
    }
    session_start();
}

/**
 * Retorna o caminho relativo/absoluto para a raiz pública do GymControl.
 * Garante que redirecionamentos funcionem tanto na raiz do servidor quanto em subpastas.
 */
function getGymControlBaseUrl(): string {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir  = dirname($scriptName);
    $parent     = dirname($scriptDir);
    $current    = basename($scriptDir);

    if (in_array($current, ['admin', 'aluno', 'professor', 'config', 'layouts'], true)) {
        $base = $parent;
    } else {
        $base = $scriptDir;
    }

    $base = rtrim($base, '/\\');
    return ($base === '' ? '' : $base) . '/';
}

/**
 * Retorna o arquivo principal de entrada para cada nível de acesso RBAC.
 */
function getRotaPrincipalPorNivel(string $nivel): string {
    switch ($nivel) {
        case 'recepcao':
            return 'admin/recepcao.php';
        case 'loja':
            return 'admin/loja.php';
        case 'professor':
            return 'professor/index.php';
        case 'admin':
            return 'admin/index.php';
        case 'aluno':
        default:
            return 'aluno/index.php';
    }
}

/**
 * Middleware RBAC: Valida a autenticação e autorização para a página atual.
 *
 * @param array $niveisPermitidos Lista de níveis permitidos (ex: ['recepcao', 'admin'])
 * @return void
 */
function verificarAcesso(array $niveisPermitidos): void {
    // A conexão deve permanecer no escopo global usado pelas páginas.
    global $pdo;
    $baseUrl = getGymControlBaseUrl();

    // 1. Validação de Autenticação: usuário precisa estar com sessão ativa
    if (empty($_SESSION['usuario_id']) || empty($_SESSION['usuario_nivel'])) {
        $_SESSION['flash_aviso'] = [
            'tipo'     => 'warning',
            'mensagem' => 'Acesso restrito. Faça login para continuar.'
        ];
        header("Location: " . $baseUrl . "login.php");
        exit;
    }

    // Revalidar conta, perfil e academia no banco a cada acesso protegido.
    // Evita que contas desativadas/movidas continuem autenticadas pela sessão antiga.
    require_once __DIR__ . '/conexao.php';
    $stmtSessao = $pdo->prepare("SELECT u.nome, u.email, u.matricula, u.nivel_acesso,
            u.academia_id, u.status, u.trocar_senha, ac.status AS status_academia,
            al.status_matricula AS status_matricula_aluno
        FROM usuarios u JOIN academias ac ON ac.id = u.academia_id
        LEFT JOIN alunos al ON al.usuario_id=u.id AND al.academia_id=u.academia_id
        WHERE u.id = :id LIMIT 1");
    $stmtSessao->execute([':id' => (int) $_SESSION['usuario_id']]);
    $conta = $stmtSessao->fetch(PDO::FETCH_ASSOC);
    if (!$conta || $conta['status'] !== 'ativo' || $conta['status_academia'] !== 'ativa'
        || ($conta['nivel_acesso'] === 'aluno' && $conta['status_matricula_aluno'] !== 'ativa')) {
        $_SESSION = ['flash_aviso' => [
            'tipo' => 'warning', 'mensagem' => 'Sua sessão foi encerrada. Faça login novamente.'
        ]];
        session_regenerate_id(true);
        header('Location: ' . $baseUrl . 'login.php');
        exit;
    }
    // A academia da sessão é imutável: mudança no banco exige novo login.
    // Nunca substituir silenciosamente o tenant da sessão pelo tenant do usuário.
    if ((int) ($_SESSION['academia_id'] ?? 0) < 1 ||
        (int) $_SESSION['academia_id'] !== (int) $conta['academia_id']) {
        $_SESSION = ['flash_aviso' => [
            'tipo' => 'warning', 'mensagem' => 'Vínculo com academia alterado. Faça login novamente.'
        ]];
        session_regenerate_id(true);
        header('Location: ' . $baseUrl . 'login.php');
        exit;
    }
    $_SESSION['usuario_nome'] = $conta['nome'];
    $_SESSION['usuario_email'] = $conta['email'];
    $_SESSION['usuario_matricula'] = $conta['matricula'];
    $_SESSION['usuario_nivel'] = $conta['nivel_acesso'];
    $_SESSION['usuario_tipo'] = $conta['nivel_acesso'];
    if ((int)$conta['trocar_senha'] === 1) {
        header('Location: ' . $baseUrl . 'trocar_senha.php');
        exit;
    }


    $nivelUsuario = $_SESSION['usuario_nivel'];

    // 2. Validação de Autorização:
    // O nível 'admin' possui acesso às rotas da SUA academia, nunca às demais.
    $temPermissao = ($nivelUsuario === 'admin') || in_array($nivelUsuario, $niveisPermitidos, true);

    // 3. Se o acesso for negado, bloqueia e redireciona para a página principal da função do usuário
    if (!$temPermissao) {
        $destino = $baseUrl . getRotaPrincipalPorNivel($nivelUsuario);

        $nomeFuncao = ucfirst($nivelUsuario);
        if ($nivelUsuario === 'recepcao') {
            $nomeFuncao = 'Recepção';
        }

        $_SESSION['flash_aviso'] = [
            'tipo'     => 'danger',
            'mensagem' => "Acesso Negado: Seu perfil ({$nomeFuncao}) não possui autorização para acessar esta página."
        ];

        header("Location: " . $destino);
        exit;
    }
}

/**
 * Renderiza o aviso flash de acesso negado ou alerta de sessão (se existir)
 * Mantém o padrão visual de design system (Dark & Light) do GymControl.
 */
function renderizarAvisoPermissao(): void {
    if (!empty($_SESSION['flash_aviso'])) {
        $aviso = $_SESSION['flash_aviso'];
        unset($_SESSION['flash_aviso']);

        $isDanger = ($aviso['tipo'] === 'danger');
        $bg       = $isDanger ? 'rgba(239, 68, 68, 0.12)' : 'rgba(245, 158, 11, 0.12)';
        $border   = $isDanger ? 'rgba(239, 68, 68, 0.35)' : 'rgba(245, 158, 11, 0.35)';
        $corTexto = $isDanger ? '#f87171' : '#fbbf24';
        $icone    = $isDanger ? 'fa-triangle-exclamation' : 'fa-circle-exclamation';

        echo '
        <div class="alert-rbac" style="background: ' . $bg . '; border: 1px solid ' . $border . '; color: ' . $corTexto . '; padding: 14px 18px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid ' . $icone . '" style="font-size: 18px;"></i>
                <span>' . htmlspecialchars($aviso['mensagem']) . '</span>
            </div>
            <button onclick="this.parentElement.remove();" style="background: none; border: none; color: inherit; font-size: 16px; cursor: pointer; padding: 4px; line-height: 1;">&times;</button>
        </div>';
    }
}
