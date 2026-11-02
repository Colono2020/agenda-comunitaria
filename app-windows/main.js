// Agenda Comunitária para Windows: uma janela que abre o site hospedado.
const { app, BrowserWindow, Menu, shell } = require('electron');
const path = require('path');

// =====================================================
// Endereço do site. Troque aqui e gere o instalador de novo.
// =====================================================
const ENDERECO_DO_SITE = 'https://seusite.infinityfreeapp.com/home.php';

const ORIGEM = new URL(ENDERECO_DO_SITE).origin;
let janela = null;

// Só uma janela aberta: clicar no atalho de novo traz a janela para frente
if (!app.requestSingleInstanceLock()) {
    app.quit();
} else {
    app.on('second-instance', () => {
        if (janela) {
            if (janela.isMinimized()) janela.restore();
            janela.focus();
        }
    });
    app.whenReady().then(criarJanela);
}

function criarJanela() {
    Menu.setApplicationMenu(null); // sem a barra de menu

    janela = new BrowserWindow({
        width: 480,          // tamanho de celular grande / tablet em pé
        height: 860,
        minWidth: 360,
        minHeight: 560,
        title: 'Agenda Comunitária',
        icon: path.join(__dirname, 'build', 'icon.png'),
        backgroundColor: '#f6f4ef',
        autoHideMenuBar: true,
        webPreferences: {
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: true,
        },
    });

    // A barra da janela mostra sempre o nome do app (e não o título de cada página)
    janela.on('page-title-updated', ev => ev.preventDefault());

    janela.loadURL(ENDERECO_DO_SITE);

    // Links de fora (WhatsApp, notícias, ligação) abrem no navegador do computador
    janela.webContents.setWindowOpenHandler(({ url }) => {
        abrirFora(url);
        return { action: 'deny' };
    });
    janela.webContents.on('will-navigate', (ev, url) => {
        if (new URL(url).origin !== ORIGEM) {
            ev.preventDefault();
            abrirFora(url);
        }
    });

    // Sem internet na primeira vez (antes de o site ficar guardado): mostra a página do próprio app
    janela.webContents.on('did-fail-load', (ev, codigo, descricao, url, principal) => {
        if (principal && codigo !== -3) { // -3 = carregamento cancelado (normal)
            janela.loadFile(path.join(__dirname, 'offline.html'), { query: { voltar: ENDERECO_DO_SITE } });
        }
    });
}

function abrirFora(url) {
    if (/^(https?|mailto|tel|whatsapp):/i.test(url)) shell.openExternal(url);
}

app.on('window-all-closed', () => app.quit());
