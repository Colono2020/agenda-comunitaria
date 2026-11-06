<?php
// Envio de e-mails (confirmação do e-mail e recuperação da conta) com o PHPMailer.
// O modo é definido em config.php:
//   EMAIL_MODO = 'smtp'  -> envia pelo SMTP do Gmail (SMTP_EMAIL + SMTP_SENHA_APP)
//   EMAIL_MODO = 'teste' -> não envia nada; o link aparece num aviso amarelo na página (só para testes)

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

const ERRO_EMAIL = 'Não foi possível enviar o e-mail agora. Tente mais tarde.';
const DICA_SPAM  = 'Não recebeu? Olhe também na caixa de spam.';

/** Lê uma constante do config.php (se ela ainda não foi acrescentada, usa o padrão). */
function config_email(string $nome, string $padrao = ''): string
{
    return defined($nome) ? (string) constant($nome) : $padrao;
}

/** Modo de envio: 'smtp' (padrão) ou 'teste'. */
function modo_email(): string
{
    return config_email('EMAIL_MODO', 'smtp') === 'teste' ? 'teste' : 'smtp';
}

/** O envio está pronto para uso? (no modo smtp, o e-mail e a senha de app precisam estar preenchidos) */
function email_configurado(): bool
{
    if (modo_email() === 'teste') return true;
    if (config_email('SMTP_EMAIL') === '' || config_email('SMTP_SENHA_APP') === '') {
        error_log('E-mail: SMTP_EMAIL ou SMTP_SENHA_APP não configurado no config.php.');
        return false;
    }
    return true;
}

/**
 * Envia um e-mail simples com um botão de link. Retorna true se enviou.
 * Em caso de erro, registra no log do PHP e retorna false (a página mostra ERRO_EMAIL).
 */
function enviar_email(string $para, string $nome, string $assunto, string $titulo, string $texto, string $botao, string $link): bool
{
    if (!email_configurado()) return false;

    if (modo_email() === 'teste') {
        // Modo teste: guarda o link para mostrar na próxima página (ver mostrar_email_teste)
        $_SESSION['email_teste'][] = ['para' => $para, 'assunto' => $assunto, 'link' => $link];
        return true;
    }

    $m = new PHPMailer(true);
    try {
        $m->isSMTP();
        $m->Host       = config_email('SMTP_HOST', 'smtp.gmail.com');
        $m->Port       = (int) config_email('SMTP_PORTA', '587');
        $m->SMTPAuth   = true;
        $m->Username   = config_email('SMTP_EMAIL');
        $m->Password   = str_replace(' ', '', config_email('SMTP_SENHA_APP')); // o Google mostra a senha com espaços
        $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS na porta 587
        if (config_email('SMTP_SEM_TLS') === '1') {        // só para testes com servidor local
            $m->SMTPSecure = '';
            $m->SMTPAutoTLS = false;
        }
        $m->Timeout  = 15;
        $m->CharSet  = PHPMailer::CHARSET_UTF8;
        $m->setFrom(config_email('SMTP_EMAIL'), config_email('SMTP_NOME', 'Agenda Comunitária'));
        $m->addAddress($para, $nome);
        $m->isHTML(true);
        $m->Subject = $assunto . ' - Agenda Comunitária';
        $m->Body    = modelo_email($titulo, $texto, $botao, $link);
        $m->AltBody = "Agenda Comunitária\n\n$titulo\n\n$texto\n\n$botao: $link\n\n"
                    . "Se não foi você que pediu, ignore este e-mail.";
        $m->send();
        return true;
    } catch (\Throwable $e) {
        error_log('E-mail: falha ao enviar para ' . $para . ': ' . $m->ErrorInfo . ' ' . $e->getMessage());
        return false;
    }
}

/** HTML do e-mail: simples, com as cores do app (estilos na linha, como os leitores de e-mail exigem). */
function modelo_email(string $titulo, string $texto, string $botao, string $link): string
{
    $t = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#f4f6f1;font-family:Arial,Helvetica,sans-serif;color:#263238;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f1;padding:24px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e0e6dc;">'
        . '<tr><td style="background:#2e7d32;color:#ffffff;padding:16px 22px;font-size:18px;font-weight:bold;">🌱 Agenda Comunitária</td></tr>'
        . '<tr><td style="padding:24px 22px 8px;">'
        . '<h1 style="margin:0 0 12px;font-size:20px;color:#1b5e20;">' . $t($titulo) . '</h1>'
        . '<p style="margin:0 0 22px;font-size:16px;line-height:1.5;">' . $t($texto) . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
        . '<a href="' . $t($link) . '" style="display:inline-block;background:#2e7d32;color:#ffffff;text-decoration:none;font-weight:bold;font-size:16px;padding:14px 26px;border-radius:10px;">' . $t($botao) . '</a>'
        . '</td></tr></table>'
        . '<p style="margin:22px 0 6px;font-size:13px;color:#78909c;">Se o botão não funcionar, copie e cole este endereço no navegador:</p>'
        . '<p style="margin:0 0 18px;font-size:13px;word-break:break-all;"><a href="' . $t($link) . '" style="color:#2e7d32;">' . $t($link) . '</a></p>'
        . '</td></tr>'
        . '<tr><td style="padding:14px 22px;background:#f9fbf7;border-top:1px solid #e0e6dc;font-size:13px;color:#78909c;">'
        . 'Se não foi você que pediu, ignore este e-mail.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Modo teste: mostra (uma vez) os links que seriam enviados por e-mail. */
function mostrar_email_teste(): string
{
    if (empty($_SESSION['email_teste'])) return '';
    $html = '';
    foreach ($_SESSION['email_teste'] as $m) {
        $html .= '<div class="flash flash-aviso">Modo teste (e-mail não enviado) para ' . e($m['para']) . ': '
               . e($m['assunto']) . '<br><a href="' . e($m['link']) . '" class="link-teste">' . e($m['link']) . '</a></div>';
    }
    unset($_SESSION['email_teste']);
    return $html;
}
