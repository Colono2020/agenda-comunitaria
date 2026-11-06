<?php
// Funções comuns usadas em todas as páginas
// Usa o config.php (com as senhas reais, fora do GitHub). Se ele não existir, usa o modelo pronto para o XAMPP.
require_once is_file(__DIR__ . '/../config.php') ? __DIR__ . '/../config.php' : __DIR__ . '/../config.exemplo.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/email.php';

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
        // A conta pode ter sido excluída (por um administrador ou em outra aba) ou recuperada
        // em outro aparelho (sessao_versao mudou): nos dois casos encerra esta sessão
        $st = db()->prepare('SELECT nome, email, email_confirmado_em, sessao_versao FROM usuarios WHERE id = ?');
        $st->execute([$u['id']]);
        $linha = $st->fetch();
        if ($linha === false || (int) $linha['sessao_versao'] !== (int) ($u['sessao_versao'] ?? 0)) {
            $_SESSION = [];
            session_regenerate_id(true);
            $u = null;
        } else {
            // Mantém o nome e o e-mail da sessão em dia (ex.: alterados em outro aparelho)
            $u['nome']     = $_SESSION['usuario']['nome']     = $linha['nome'];
            $u['email']    = $_SESSION['usuario']['email']    = $linha['email'];
            $u['email_ok'] = $_SESSION['usuario']['email_ok'] = $linha['email'] !== null && $linha['email_confirmado_em'] !== null;
        }
    }
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

/**
 * Entra na conta (login, cadastro confirmado ou conta recuperada).
 * $u é a linha da tabela usuarios.
 */
function iniciar_sessao(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'            => (int) $u['id'],
        'nome'          => $u['nome'],
        'telefone'      => $u['telefone'],
        'localidade'    => $u['localidade'],
        'sessao_versao' => (int) ($u['sessao_versao'] ?? 0),
    ];
    unset($_SESSION['comunidade_id'], $_SESSION['uf']); // comunidade e estado vêm de novo a cada login
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

/**
 * Endereço completo de uma página do app, com o endereço que está sendo usado para acessá-lo
 * (localhost no XAMPP, https://agendacomunitaria.site.je na hospedagem).
 */
function url_do_site(string $pagina): string
{
    // Endereço fixo do config.php (usado na hospedagem): os links dos e-mails não dependem do
    // cabeçalho Host da requisição, que pode ser falsificado
    if (defined('SITE_URL') && SITE_URL !== '') return rtrim(SITE_URL, '/') . '/' . $pagina;

    // Na hospedagem o HTTPS pode chegar por um proxy (X-Forwarded-Proto)
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $pasta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $pasta . '/' . $pagina;
}

/** Link completo do convite. */
function link_convite(string $codigo): string
{
    return url_do_site('entrar.php?c=' . $codigo);
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

// ---------- Edição de publicações ----------

const EDICAO_PRAZO_HORAS = 24; // o autor pode editar até 24 horas depois de publicar

/**
 * Segundos que ainda faltam para poder editar (0 = prazo terminou), calculados pelo relógio do
 * MySQL, o mesmo que gravou o criado_em. Use como coluna no SELECT: ... AS seg_editar
 */
const SQL_SEG_EDITAR = 'GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), p.criado_em + INTERVAL ' . EDICAO_PRAZO_HORAS . ' HOUR))';

/** "Pode editar por mais 5 h" / "Pode editar por mais 40 min". */
function texto_prazo_edicao(int $segundos): string
{
    if ($segundos >= 3600) return 'Pode editar por mais ' . floor($segundos / 3600) . ' h';
    return 'Pode editar por mais ' . max(1, (int) ceil($segundos / 60)) . ' min';
}

/**
 * Publicação do próprio usuário (só se ele ainda participa da comunidade dela), com o tempo
 * que falta para editar em 'seg_editar'. Null se não existe ou é de outra pessoa.
 */
function publicacao_do_autor(int $id, int $usuarioId): ?array
{
    $st = db()->prepare(
        'SELECT p.*, c.nome AS comunidade_nome, ' . SQL_SEG_EDITAR . ' AS seg_editar
         FROM publicacoes p
         JOIN comunidades c ON c.id = p.comunidade_id
         JOIN membros m ON m.comunidade_id = p.comunidade_id AND m.usuario_id = p.usuario_id
         WHERE p.id = ? AND p.usuario_id = ?'
    );
    $st->execute([$id, $usuarioId]);
    $p = $st->fetch();
    if (!$p) return null;
    $p['seg_editar'] = (int) $p['seg_editar'];
    return $p;
}

// ---------- E-mail da conta e links enviados por e-mail ----------

const LINK_CONFIRMAR_HORAS = 24; // validade do link de confirmação do e-mail
const LINK_RECUPERAR_MIN   = 60; // validade do link de recuperação da conta
const LINKS_POR_HORA       = 3;  // máximo de e-mails do mesmo tipo por e-mail por hora

/** E-mail em minúsculas e sem espaços; '' se for inválido. */
function normalizar_email(string $email): string
{
    $email = mb_strtolower(trim($email));
    return mb_strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

/** O e-mail já está na conta de outra pessoa (confirmado ou não)? */
function email_em_uso(string $email, int $excetoUsuarioId = 0): bool
{
    $st = db()->prepare('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?');
    $st->execute([$email, $excetoUsuarioId]);
    return (bool) $st->fetchColumn();
}

/** Quantos links deste tipo foram enviados para o e-mail na última hora. */
function links_na_ultima_hora(string $email, string $tipo): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM links_email WHERE email = ? AND tipo = ? AND criado_em > ?');
    $st->execute([$email, $tipo, date('Y-m-d H:i:s', time() - 3600)]);
    return (int) $st->fetchColumn();
}

/**
 * Cria um link de uso único (token aleatório; no banco fica só o hash) e invalida os
 * links anteriores do mesmo tipo dessa conta. Retorna o token, que vai só no e-mail.
 */
function criar_link_email(int $usuarioId, string $tipo, string $email, int $validadeSeg): string
{
    $token = bin2hex(random_bytes(32)); // 64 caracteres
    $agora = date('Y-m-d H:i:s');
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE links_email SET usado_em = ? WHERE usuario_id = ? AND tipo = ? AND usado_em IS NULL')
        ->execute([$agora, $usuarioId, $tipo]);
    $pdo->prepare('INSERT INTO links_email (usuario_id, tipo, email, token_hash, expira_em, criado_em) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$usuarioId, $tipo, $email, hash('sha256', $token), date('Y-m-d H:i:s', time() + $validadeSeg), $agora]);
    // Limpeza: links vencidos há mais de 7 dias não servem para mais nada
    $pdo->prepare('DELETE FROM links_email WHERE expira_em < ?')->execute([date('Y-m-d H:i:s', time() - 7 * 86400)]);
    $pdo->commit();
    return $token;
}

/**
 * Procura o link pelo token. Retorna null se não existe; senão a linha do link com os dados
 * da conta e 'situacao' = 'ok', 'usado' ou 'vencido'.
 */
function buscar_link_email(string $token, string $tipo): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $st = db()->prepare(
        'SELECT l.*, u.nome, u.telefone, u.localidade, u.email AS email_conta, u.email_confirmado_em, u.sessao_versao
         FROM links_email l JOIN usuarios u ON u.id = l.usuario_id
         WHERE l.token_hash = ? AND l.tipo = ?'
    );
    $st->execute([hash('sha256', $token), $tipo]);
    $l = $st->fetch();
    if (!$l) return null;
    $l['situacao'] = $l['usado_em'] ? 'usado' : (strtotime($l['expira_em']) < time() ? 'vencido' : 'ok');
    return $l;
}

/** Envia o link de confirmação para o e-mail (novo ou trocado). */
function enviar_confirmacao_email(int $usuarioId, string $nome, string $email): bool
{
    if (!email_configurado()) return false; // não cria link à toa
    $token = criar_link_email($usuarioId, 'confirmar', $email, LINK_CONFIRMAR_HORAS * 3600);
    return enviar_email(
        $email, $nome,
        'Confirme seu e-mail',
        'Confirme seu e-mail',
        'Olá, ' . $nome . '! Toque no botão abaixo para confirmar este e-mail na sua conta da Agenda Comunitária. '
            . 'Com ele confirmado, você consegue recuperar sua conta se esquecer a senha ou trocar de número. '
            . 'O link vale por ' . LINK_CONFIRMAR_HORAS . ' horas.',
        'Confirmar meu e-mail',
        url_do_site('confirmar-email.php?token=' . $token)
    );
}

/** Link de confirmação ainda pendente da conta (e-mail que espera confirmação), ou null. */
function confirmacao_pendente(int $usuarioId): ?array
{
    $st = db()->prepare("SELECT email, criado_em FROM links_email
                         WHERE usuario_id = ? AND tipo = 'confirmar' AND usado_em IS NULL AND expira_em > ?
                         ORDER BY id DESC LIMIT 1");
    $st->execute([$usuarioId, date('Y-m-d H:i:s')]);
    return $st->fetch() ?: null;
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
