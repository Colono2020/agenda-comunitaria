<?php
// Notícias do agro pelo RSS do Google Notícias, com cache em arquivo (pasta cache/) por estado.

const NOTICIAS_QTD        = 10;
const NOTICIAS_CACHE_SEG  = 1800; // 30 minutos
const NOTICIAS_ESPERA_SEG = 300;  // depois de uma falha, espera 5 min antes de tentar de novo

/**
 * Notícias do estado ('BR' = Todo o Brasil).
 * Retorna ['itens' => [...], 'antigas' => bool] ou null se não deu para carregar nada.
 * Cada item: titulo, fonte, link, data. 'antigas' = veio do cache vencido porque a busca falhou.
 */
function buscar_noticias(string $uf): ?array
{
    if (!isset(UFS[$uf])) $uf = 'BR';
    $pasta   = __DIR__ . '/../cache';
    $arquivo = $pasta . '/noticias-' . $uf . '.json';
    $falha   = $pasta . '/falha-' . $uf;

    $cache = is_file($arquivo) ? json_decode((string) file_get_contents($arquivo), true) : null;
    if ($cache && filemtime($arquivo) > time() - NOTICIAS_CACHE_SEG) {
        return ['itens' => $cache, 'antigas' => false];
    }

    // Se falhou há pouco (ex.: sem internet), não tenta de novo a cada página, para não ficar lento
    $falhouHaPouco = is_file($falha) && filemtime($falha) > time() - NOTICIAS_ESPERA_SEG;
    $itens = $falhouHaPouco ? [] : baixar_noticias($uf);

    if (!is_dir($pasta)) @mkdir($pasta, 0775, true);
    if ($itens) {
        @file_put_contents($arquivo, json_encode($itens), LOCK_EX);
        @unlink($falha);
        return ['itens' => $itens, 'antigas' => false];
    }
    if (!$falhouHaPouco) @touch($falha);

    // Não conseguiu baixar: mostra as últimas notícias guardadas, se houver
    return $cache ? ['itens' => $cache, 'antigas' => true] : null;
}

/** Baixa e lê o RSS. Retorna [] em qualquer erro. */
function baixar_noticias(string $uf): array
{
    $termo = $uf === 'BR' ? 'agronegócio' : '(agronegócio OR agricultura) "' . UFS[$uf] . '"';
    $url   = 'https://news.google.com/rss/search?q=' . rawurlencode($termo) . '&hl=pt-BR&gl=BR&ceid=BR:pt-419';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'AgendaComunitaria/1.0',
    ]);
    $xml    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro   = curl_error($ch);
    curl_close($ch);

    if ($xml === false || $status !== 200) {
        error_log('Notícias: falha ao buscar o RSS (HTTP ' . $status . ') ' . $erro);
        return [];
    }

    libxml_use_internal_errors(true);
    $rss = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
    if (!$rss || !isset($rss->channel->item)) return [];

    $itens = [];
    foreach ($rss->channel->item as $item) {
        $link = trim((string) $item->link);
        if (!preg_match('#^https?://#i', $link)) continue;

        $fonte  = trim((string) $item->source);
        $titulo = trim((string) $item->title);
        // O Google coloca " - Fonte" no fim do título; a fonte já aparece separada
        if ($fonte !== '' && str_ends_with($titulo, ' - ' . $fonte)) {
            $titulo = substr($titulo, 0, -strlen(' - ' . $fonte));
        }
        $ts = strtotime((string) $item->pubDate);

        $itens[] = ['titulo' => $titulo, 'fonte' => $fonte, 'link' => $link, 'data' => $ts ? date('Y-m-d H:i:s', $ts) : null];
    }

    // O RSS vem por relevância: mostra as mais recentes primeiro
    usort($itens, fn($a, $b) => strcmp((string) $b['data'], (string) $a['data']));
    return array_slice($itens, 0, NOTICIAS_QTD);
}
