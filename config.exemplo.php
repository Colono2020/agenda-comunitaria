<?php
// =====================================================
// Onde o app está rodando. Troque só esta linha:
//   'local'      = no seu PC, com o XAMPP
//   'hospedagem' = no site hospedado (InfinityFree)
// =====================================================
define('AMBIENTE', 'local');

if (AMBIENTE === 'hospedagem') {
    // Dados do painel da InfinityFree > MySQL Databases (troque pelos seus)
    define('DB_HOST', 'sqlXXX.infinityfree.com');   // "MySQL Host Name"
    define('DB_PORTA', '3306');
    define('DB_NOME', 'if0_00000000_agenda');       // "MySQL DB Name" (com o prefixo if0_...)
    define('DB_USUARIO', 'if0_00000000');           // "MySQL User Name"
    define('DB_SENHA', 'sua-senha-aqui');           // "MySQL Password" (a senha da conta de hospedagem)

    // No site público, os erros do PHP não aparecem para os visitantes
    ini_set('display_errors', '0');
} else {
    // XAMPP: o padrão é usuário "root" e senha vazia
    define('DB_HOST', 'localhost');
    define('DB_PORTA', '3306');
    define('DB_NOME', 'agenda_comunitaria');
    define('DB_USUARIO', 'root');
    define('DB_SENHA', '');
}

// =====================================================
// Envio do código de verificação do cadastro
//   'teste' = não envia nada; o código aparece na própria página
//   'api'   = envia pela WhatsApp Cloud API (Meta)
// =====================================================
define('WHATSAPP_MODO', 'teste'); // no site público, use 'api' (no modo 'teste' qualquer um vê o código)

// Usado só no modo 'api' (pegue no painel da Meta for Developers > WhatsApp)
define('WHATSAPP_TOKEN', '');               // token de acesso
define('WHATSAPP_NUMERO_ID', '');           // ID do número de telefone (Phone number ID)
define('WHATSAPP_TEMPLATE', 'codigo_verificacao'); // nome do modelo de Autenticação aprovado
define('WHATSAPP_TEMPLATE_IDIOMA', 'pt_BR');
define('WHATSAPP_API_VERSAO', 'v21.0');

// =====================================================
// E-mails (confirmação do e-mail e recuperação da conta)
//   'smtp'  = envia pelo Gmail (preencha SMTP_EMAIL e SMTP_SENHA_APP)
//   'teste' = não envia nada; o link aparece num aviso amarelo na página (só para testes)
// =====================================================
define('EMAIL_MODO', 'smtp');
define('SMTP_EMAIL', '');                    // a conta Gmail que envia (ex.: seuemail@gmail.com)
define('SMTP_SENHA_APP', '');                // a "senha de app" de 16 letras do Google (sem espaços)
define('SMTP_NOME', 'Agenda Comunitária');   // nome do remetente

// Endereço do site usado nos links dos e-mails (no XAMPP fica vazio: usa o endereço atual)
define('SITE_URL', AMBIENTE === 'hospedagem' ? 'https://agendacomunitaria.site.je' : '');

// Fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');
