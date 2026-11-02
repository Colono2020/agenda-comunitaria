<?php
// Funções comuns usadas em todas as páginas
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/whatsapp.php';

session_start();

// Tipos de publicação: chave => [rótulo, ícone]
const TIPOS = [
    'aviso'      => ['Aviso',      '📢'],
    'produto'    => ['Produto',    '🍅'],
    'emprestimo' => ['Empréstimo', '🚜'],
    'servico'    => ['Serviço',    '🔧'],
    'evento'     => ['Evento',     '📅'],
];

// Tipos que usam "Ofereço / Preciso"
const TIPOS_COM_INTENCAO = ['produto', 'emprestimo', 'servico'];

/** Conexão única com o MySQL (PDO). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORTA . ';dbname=' . DB_NOME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USUARIO, DB_SENHA, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            // No site público não mostra os detalhes (nome do servidor, usuário) para os visitantes
            exit('Erro ao conectar no banco de dados. Verifique o MySQL e o arquivo config.php.'
                . (AMBIENTE === 'local' ? '<br><small>' . e($e->getMessage()) . '</small>' : ''));
        }
    }
    return $pdo;
}

/** Escapa texto para exibir no HTML com segurança. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/** Deixa só os números do telefone. */
function limpar_telefone(string $tel): string
{
    return preg_replace('/\D/', '', $tel);
}

/** Formata telefone: 49912345678 -> (49) 91234-5678 */
function formatar_telefone(string $tel): string
{
    $t = limpar_telefone($tel);
    if (strlen($t) === 11) {
        return '(' . substr($t, 0, 2) . ') ' . substr($t, 2, 5) . '-' . substr($t, 7);
    }
    if (strlen($t) === 10) {
        return '(' . substr($t, 0, 2) . ') ' . substr($t, 2, 4) . '-' . substr($t, 6);
    }
    return $tel;
}

/** Link do WhatsApp com mensagem pronta. */
function link_whatsapp(string $tel, string $mensagem): string
{
    return 'https://wa.me/55' . limpar_telefone($tel) . '?text=' . rawurlencode($mensagem);
}

/** Data no formato brasileiro. */
function data_br(?string $data, bool $comHora = true): string
{
    if (!$data) return '';
    $ts = strtotime($data);
    return $comHora ? date('d/m/Y \à\s H:i', $ts) : date('d/m/Y', $ts);
}

/** "há 5 minutos", "há 2 dias"... */
function tempo_atras(string $data): string
{
    $seg = time() - strtotime($data);
    if ($seg < 60)      return 'agora mesmo';
    if ($seg < 3600)    return 'há ' . floor($seg / 60) . ' min';
    if ($seg < 86400)   return 'há ' . floor($seg / 3600) . ' h';
    if ($seg < 2592000) { $d = floor($seg / 86400); return 'há ' . $d . ($d == 1 ? ' dia' : ' dias'); }
    return data_br($data, false);
}

// ---------- Login ----------

function usuario_logado(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function exigir_login(): array
{
    $u = usuario_logado();
    if ($u) {
        // A conta pode ter sido excluída (por um administrador ou em outra aba): encerra a sessão
        $st = db()->prepare('SELECT nome FROM usuarios WHERE id = ?');
        $st->execute([$u['id']]);
        $nome = $st->fetchColumn();
        if ($nome === false) {
            $_SESSION = [];
            session_regenerate_id(true);
            $u = null;
        } else {
            // Mantém o nome da sessão em dia (ex.: alterado em outro aparelho)
            $u['nome'] = $_SESSION['usuario']['nome'] = $nome;
        }
    }
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

/**
 * Depois do login/cadastro, volta para o convite que a pessoa abriu (se houver).
 * Só aceita a página entrar.php do próprio app, para não virar redirecionamento aberto.
 */
function ir_para_volta(): void
{
    $url = $_SESSION['voltar_para'] ?? '';
    unset($_SESSION['voltar_para']);
    if (!preg_match('/^entrar\.php\?c=[A-Z0-9]{1,16}$/', $url)) $url = 'home.php';
    header('Location: ' . $url);
    exit;
}

// ---------- Estados (Home) ----------

// 'BR' = Todo o Brasil
const UFS = [
    'BR' => 'Todo o Brasil',
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia',
    'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás',
    'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
    'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
    'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
    'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
    'SE' => 'Sergipe', 'TO' => 'Tocantins',
];

/** Estado pelo DDD do telefone (ex.: 49 -> SC). 'BR' se não reconhecer. */
function uf_pelo_ddd(string $telefone): string
{
    $ddd = (int) substr(limpar_telefone($telefone), 0, 2);
    $faixas = [
        'SP' => [11, 12, 13, 14, 15, 16, 17, 18, 19], 'RJ' => [21, 22, 24], 'ES' => [27, 28],
        'MG' => [31, 32, 33, 34, 35, 37, 38], 'PR' => [41, 42, 43, 44, 45, 46], 'SC' => [47, 48, 49],
        'RS' => [51, 53, 54, 55], 'DF' => [61], 'GO' => [62, 64], 'TO' => [63], 'MT' => [65, 66],
        'MS' => [67], 'AC' => [68], 'RO' => [69], 'BA' => [71, 73, 74, 75, 77], 'SE' => [79],
        'PE' => [81, 87], 'AL' => [82], 'PB' => [83], 'RN' => [84], 'CE' => [85, 88], 'PI' => [86, 89],
        'PA' => [91, 93, 94], 'AM' => [92, 97], 'RR' => [95], 'AP' => [96], 'MA' => [98, 99],
    ];
    foreach ($faixas as $uf => $ddds) {
        if (in_array($ddd, $ddds, true)) return $uf;
    }
    return 'BR';
}

/**
 * Estado escolhido na Home: o da sessão; senão o salvo no cadastro; senão o do DDD do telefone.
 */
function uf_atual(array $usuario): string
{
    if (isset(UFS[$_SESSION['uf'] ?? ''])) return $_SESSION['uf'];
    $st = db()->prepare('SELECT uf FROM usuarios WHERE id = ?');
    $st->execute([$usuario['id']]);
    $uf = $st->fetchColumn();
    if (!isset(UFS[$uf ?: ''])) $uf = uf_pelo_ddd($usuario['telefone']);
    return $_SESSION['uf'] = $uf;
}

/** Administrador do site (cadastra os eventos do agro). Conferido no banco a cada página. */
function admin_do_site(int $usuarioId): bool
{
    static $cache = [];
    if (!isset($cache[$usuarioId])) {
        $st = db()->prepare('SELECT is_admin FROM usuarios WHERE id = ?');
        $st->execute([$usuarioId]);
        $cache[$usuarioId] = (int) $st->fetchColumn() === 1;
    }
    return $cache[$usuarioId];
}

/** Para as páginas e ações de administração do site: 403 para quem não é administrador. */
function exigir_admin_do_site(int $usuarioId): void
{
    if (!admin_do_site($usuarioId)) {
        http_response_code(403);
        exit('Acesso restrito ao administrador do site.');
    }
}

/**
 * Exclui um usuário: sai de cada comunidade (passando a administração ao membro mais antigo ou
 * apagando a comunidade que ficar vazia), apaga as publicações, o cadastro pendente e a conta.
 * Usada pelo excluir-conta.php e pelo admin-usuarios.php. Retorna false se o usuário não existe.
 */
function excluir_usuario(int $usuarioId): bool
{
    $pdo = db();
    $st  = $pdo->prepare('SELECT telefone FROM usuarios WHERE id = ?');
    $st->execute([$usuarioId]);
    $telefone = $st->fetchColumn();
    if ($telefone === false) return false;

    $pdo->beginTransaction();
    foreach (minhas_comunidades($usuarioId) as $c) {
        remover_da_comunidade($c['id'], $usuarioId);
    }
    // As publicações já saem pelo ON DELETE CASCADE; apagamos aqui também por garantia
    $pdo->prepare('DELETE FROM publicacoes WHERE usuario_id = ?')->execute([$usuarioId]);
    $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$usuarioId]);
    $pdo->commit();

    // Cadastro pendente do WhatsApp (a tabela pode não existir em instalações antigas)
    try {
        $pdo->prepare('DELETE FROM verificacoes WHERE telefone = ?')->execute([$telefone]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) != 1146) throw $e; // 1146 = tabela não existe
    }
    return true;
}

// ---------- Comunidades ----------

const ACESSO_MAX_ERROS    = 5;  // senhas/códigos errados permitidos...
const ACESSO_JANELA_MIN   = 15; // ...a cada 15 minutos
const CODIGO_LETRAS       = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // sem 0/O, 1/I/L

/** Comunidades de que o usuário participa, com o papel dele em cada uma. */
function minhas_comunidades(int $usuarioId): array
{
    $st = db()->prepare(
        'SELECT c.id, c.nome, c.descricao, c.codigo, m.papel
         FROM membros m JOIN comunidades c ON c.id = m.comunidade_id
         WHERE m.usuario_id = ?
         ORDER BY c.nome'
    );
    $st->execute([$usuarioId]);
    return $st->fetchAll();
}

/** 'admin', 'membro' ou null se o usuário não participa da comunidade. */
function papel_na_comunidade(int $comunidadeId, int $usuarioId): ?string
{
    $st = db()->prepare('SELECT papel FROM membros WHERE comunidade_id = ? AND usuario_id = ?');
    $st->execute([$comunidadeId, $usuarioId]);
    return $st->fetchColumn() ?: null;
}

/**
 * Comunidade escolhida (guardada na sessão), conferida no banco a cada página.
 * O administrador do site pode abrir qualquer comunidade sem ser membro: nesse caso o papel vem como 'site'.
 * Null = nenhuma escolhida, ou a pessoa não é mais membro dela.
 */
function comunidade_atual(int $usuarioId): ?array
{
    $id = (int) ($_SESSION['comunidade_id'] ?? 0);
    foreach (minhas_comunidades($usuarioId) as $c) {
        if ((int) $c['id'] === $id) return $c;
    }
    if ($id && admin_do_site($usuarioId)) {
        $st = db()->prepare('SELECT id, nome, descricao, codigo FROM comunidades WHERE id = ?');
        $st->execute([$id]);
        if ($c = $st->fetch()) {
            $c['papel'] = 'site';
            return $c;
        }
    }
    unset($_SESSION['comunidade_id']);
    return null;
}

/** Pode administrar a comunidade: é o admin dela ou é administrador do site. */
function pode_administrar(int $comunidadeId, int $usuarioId): bool
{
    return papel_na_comunidade($comunidadeId, $usuarioId) === 'admin' || admin_do_site($usuarioId);
}

/** Para páginas que só funcionam dentro de uma comunidade: sem comunidade escolhida, vai para a tela de escolha. */
function exigir_comunidade(int $usuarioId): array
{
    $atual = comunidade_atual($usuarioId);
    if (!$atual) {
        header('Location: comunidades.php');
        exit;
    }
    return $atual;
}

/** Código aleatório e único de 8 caracteres para o link de convite. */
function gerar_codigo_comunidade(): string
{
    $st = db()->prepare('SELECT 1 FROM comunidades WHERE codigo = ?');
    do {
        $codigo = '';
        for ($i = 0; $i < 8; $i++) {
            $codigo .= CODIGO_LETRAS[random_int(0, strlen(CODIGO_LETRAS) - 1)];
        }
        $st->execute([$codigo]);
    } while ($st->fetch());
    return $codigo;
}

/** Link completo do convite, com o endereço que está sendo usado para acessar o app. */
function link_convite(string $codigo): string
{
    // Na hospedagem o HTTPS pode chegar por um proxy (X-Forwarded-Proto)
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $pasta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $pasta . '/entrar.php?c=' . $codigo;
}

/** Senhas/códigos errados do usuário nos últimos 15 minutos. */
function erros_de_acesso(int $usuarioId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM tentativas_comunidade WHERE usuario_id = ? AND criado_em > ?');
    $st->execute([$usuarioId, date('Y-m-d H:i:s', time() - ACESSO_JANELA_MIN * 60)]);
    return (int) $st->fetchColumn();
}

/** Minutos que faltam para poder tentar de novo (0 = liberado). */
function acesso_bloqueado(int $usuarioId): int
{
    if (erros_de_acesso($usuarioId) < ACESSO_MAX_ERROS) return 0;
    // Libera quando o erro mais antigo da janela sair dela
    $st = db()->prepare('SELECT MIN(criado_em) FROM (SELECT criado_em FROM tentativas_comunidade
                         WHERE usuario_id = ? ORDER BY criado_em DESC LIMIT ' . ACESSO_MAX_ERROS . ') t');
    $st->execute([$usuarioId]);
    return max(1, (int) ceil((strtotime($st->fetchColumn()) + ACESSO_JANELA_MIN * 60 - time()) / 60));
}

function registrar_erro_de_acesso(int $usuarioId): void
{
    db()->prepare('DELETE FROM tentativas_comunidade WHERE criado_em < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
    db()->prepare('INSERT INTO tentativas_comunidade (usuario_id, criado_em) VALUES (?, ?)')->execute([$usuarioId, date('Y-m-d H:i:s')]);
}

function limpar_erros_de_acesso(int $usuarioId): void
{
    db()->prepare('DELETE FROM tentativas_comunidade WHERE usuario_id = ?')->execute([$usuarioId]);
}

/**
 * Tira o usuário da comunidade e apaga as publicações dele nela.
 * Se ficar sem administrador, o membro mais antigo vira admin; se não sobrar ninguém, a comunidade é apagada.
 * Retorna o nome do novo admin, '' se o admin não mudou, ou null se a comunidade foi apagada.
 */
function remover_da_comunidade(int $comunidadeId, int $usuarioId): ?string
{
    $pdo = db();
    $propria = !$pdo->inTransaction();
    if ($propria) $pdo->beginTransaction();

    $pdo->prepare('DELETE FROM publicacoes WHERE comunidade_id = ? AND usuario_id = ?')->execute([$comunidadeId, $usuarioId]);
    $pdo->prepare('DELETE FROM membros WHERE comunidade_id = ? AND usuario_id = ?')->execute([$comunidadeId, $usuarioId]);

    $resultado = '';
    $st = $pdo->prepare("SELECT COUNT(*) FROM membros WHERE comunidade_id = ? AND papel = 'admin'");
    $st->execute([$comunidadeId]);
    if ((int) $st->fetchColumn() === 0) {
        $st = $pdo->prepare('SELECT m.usuario_id, u.nome FROM membros m JOIN usuarios u ON u.id = m.usuario_id
                             WHERE m.comunidade_id = ? ORDER BY m.entrou_em, m.usuario_id LIMIT 1 FOR UPDATE');
        $st->execute([$comunidadeId]);
        if ($novo = $st->fetch()) {
            $pdo->prepare("UPDATE membros SET papel = 'admin' WHERE comunidade_id = ? AND usuario_id = ?")
                ->execute([$comunidadeId, $novo['usuario_id']]);
            $resultado = $novo['nome'];
        } else {
            $pdo->prepare('DELETE FROM comunidades WHERE id = ?')->execute([$comunidadeId]);
            $resultado = null;
        }
    }

    if ($propria) $pdo->commit();
    return $resultado;
}

// ---------- Verificação do cadastro por código ----------

const CODIGO_VALIDADE_MIN = 10; // minutos que o código vale
const CODIGO_MAX_ERROS    = 5;  // erros permitidos por código
const CODIGO_ESPERA_SEG   = 60; // espera entre envios

/** Cadastro pendente de verificação do telefone (ou null). */
function buscar_verificacao(string $telefone): ?array
{
    $st = db()->prepare('SELECT * FROM verificacoes WHERE telefone = ?');
    $st->execute([$telefone]);
    return $st->fetch() ?: null;
}

/** Segundos que faltam para poder reenviar o código (0 = já pode). */
function espera_reenvio(array $v): int
{
    if (!$v['enviado_em']) return 0;
    return max(0, strtotime($v['enviado_em']) + CODIGO_ESPERA_SEG - time());
}

/** Gera um novo código de 6 números, salva (com hash) e envia pelo WhatsApp. */
function enviar_novo_codigo(string $telefone): bool
{
    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $st = db()->prepare('UPDATE verificacoes SET codigo_hash = ?, tentativas = 0, expira_em = ?, enviado_em = ? WHERE telefone = ?');
    $st->execute([
        password_hash($codigo, PASSWORD_DEFAULT),
        date('Y-m-d H:i:s', time() + CODIGO_VALIDADE_MIN * 60),
        date('Y-m-d H:i:s'),
        $telefone,
    ]);

    if (enviar_codigo_whatsapp($telefone, $codigo)) return true;

    // Não conseguiu enviar: libera o reenvio na hora
    db()->prepare('UPDATE verificacoes SET enviado_em = NULL WHERE telefone = ?')->execute([$telefone]);
    return false;
}

// ---------- Proteção de formulários (CSRF) ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function validar_csrf(): void
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Formulário expirado. Volte e tente novamente.');
    }
}

// ---------- Mensagens rápidas ----------

function avisar(string $msg, string $tipo = 'ok'): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo];
}

function mostrar_aviso(): string
{
    if (empty($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="flash flash-' . e($f['tipo']) . '">' . e($f['msg']) . '</div>';
}
