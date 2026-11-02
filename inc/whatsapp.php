<?php
// Envio do código de verificação pelo WhatsApp.
// O modo é definido em config.php (WHATSAPP_MODO = 'teste' ou 'api').

/**
 * Envia o código de 6 números para o telefone (só números, com DDD).
 * Retorna true se enviou (ou se está em modo teste).
 */
function enviar_codigo_whatsapp(string $telefone, string $codigo): bool
{
    if (WHATSAPP_MODO !== 'api') {
        // Modo teste: não envia nada, só guarda o código para mostrar na página de verificação
        $_SESSION['codigo_teste'][$telefone] = $codigo;
        return true;
    }

    if (WHATSAPP_TOKEN === '' || WHATSAPP_NUMERO_ID === '') {
        error_log('WhatsApp: WHATSAPP_TOKEN ou WHATSAPP_NUMERO_ID não configurado no config.php.');
        return false;
    }

    // WhatsApp Cloud API (Meta). Mensagens iniciadas pela empresa precisam de um
    // modelo (template) aprovado. Aqui usamos um modelo de "Autenticação", que tem
    // o código no corpo e um botão "Copiar código".
    $url = 'https://graph.facebook.com/' . WHATSAPP_API_VERSAO . '/' . WHATSAPP_NUMERO_ID . '/messages';
    $corpo = [
        'messaging_product' => 'whatsapp',
        'to'                => '55' . limpar_telefone($telefone),
        'type'              => 'template',
        'template'          => [
            'name'       => WHATSAPP_TEMPLATE,
            'language'   => ['code' => WHATSAPP_TEMPLATE_IDIOMA],
            'components' => [
                [
                    'type'       => 'body',
                    'parameters' => [['type' => 'text', 'text' => $codigo]],
                ],
                [
                    'type'       => 'button',
                    'sub_type'   => 'url',
                    'index'      => '0',
                    'parameters' => [['type' => 'text', 'text' => $codigo]],
                ],
            ],
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . WHATSAPP_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($corpo),
    ]);
    $resposta = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    if ($resposta === false || $status < 200 || $status >= 300) {
        error_log('WhatsApp: falha ao enviar código (HTTP ' . $status . ') ' . ($erroCurl ?: $resposta));
        return false;
    }
    return true;
}
