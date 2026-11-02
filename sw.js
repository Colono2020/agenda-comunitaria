// Service worker da Agenda Comunitária
// Guarda no aparelho só o estilo, os ícones e a página offline.
// As páginas PHP sempre vêm da internet (dados sempre atualizados); sem internet, aparece a offline.html.
// Os caminhos são relativos a este arquivo: funciona em localhost/agenda-comunitaria e na raiz de um domínio.

const CACHE = 'agenda-v1'; // troque o número para forçar todos os aparelhos a baixarem os arquivos de novo

const ARQUIVOS = [
    'offline.html',
    'manifest.json',
    'assets/estilo.css',
    'assets/icone.svg',
    'assets/icone-192.png',
    'assets/icone-512.png',
    'assets/icone-maskable-192.png',
    'assets/icone-maskable-512.png',
    'assets/apple-touch-icon.png',
];
const GUARDADOS = new Set(ARQUIVOS.map(a => new URL(a, self.location).href));
const OFFLINE = new URL('offline.html', self.location).href;

self.addEventListener('install', ev => {
    ev.waitUntil(caches.open(CACHE).then(c => c.addAll(ARQUIVOS)).then(() => self.skipWaiting()));
});

// Apaga caches de versões antigas
self.addEventListener('activate', ev => {
    ev.waitUntil(
        caches.keys()
            .then(nomes => Promise.all(nomes.filter(n => n !== CACHE).map(n => caches.delete(n))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', ev => {
    const req = ev.request;
    if (req.method !== 'GET') return; // formulários (POST) vão direto para a internet

    // Páginas: sempre da internet; sem conexão, mostra a página offline
    if (req.mode === 'navigate') {
        ev.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
        return;
    }

    // Estilo e ícones: responde na hora com o que está guardado e atualiza em segundo plano
    const url = new URL(req.url);
    url.search = '';
    if (GUARDADOS.has(url.href)) {
        ev.respondWith(caches.open(CACHE).then(cache =>
            cache.match(url.href).then(guardado => {
                const daRede = fetch(req).then(resp => {
                    if (resp.ok) cache.put(url.href, resp.clone());
                    return resp;
                });
                if (guardado) {
                    ev.waitUntil(daRede.catch(() => {}));
                    return guardado;
                }
                return daRede;
            })
        ));
    }
    // Todo o resto (PHP, notícias etc.) segue normal pela internet, sem cache
});
