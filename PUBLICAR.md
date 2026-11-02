# Publicar a Agenda Comunitária

Três partes: **1)** colocar o site no ar na InfinityFree, **2)** gerar o APK Android no PWABuilder e
**3)** gerar o instalador `.exe` para Windows. Faça na ordem: o APK e o `.exe` abrem o site hospedado.

---

## 1. Site na InfinityFree (grátis)

### 1.1 Criar a conta e o site
1. Entre em **infinityfree.com** → **Register**, cadastre seu e-mail e confirme pelo link que chega.
2. No painel (Client Area), clique em **Create Account**.
3. Escolha **um subdomínio grátis** (ex.: `agendacomunitaria.infinityfreeapp.com`) e confirme.
4. Abra a conta criada e anote, em **Account details**:
   - **Username** (algo como `if0_12345678`)
   - **Password** (clique em *Show*): é a senha usada no FTP e no MySQL.

### 1.2 Ativar o SSL grátis (https)
O app instalável (APK, "Adicionar à tela inicial") **só funciona com https**.
1. Na conta, abra **SSL Certificates**. Se o seu domínio já aparecer com o certificado ativo, pule para o 1.3.
2. Se não aparecer, clique em **New SSL Certificate**, escolha o seu domínio e o certificado grátis.
3. Se ele pedir registros **CNAME**, use a opção de configurar automaticamente (nos subdomínios da InfinityFree ela faz sozinha).
4. Clique em **Request Certificate**, espere ficar pronto (de minutos a algumas horas) e depois em **Install**.
5. Teste abrindo `https://seu-dominio` (com **s**). O arquivo `.htaccess` do app já manda quem entrar por `http` para o `https`.

### 1.3 Criar o banco MySQL
1. Na conta, abra o **Control Panel** → **MySQL Databases**.
2. Em *New Database* digite `agenda` e clique em **Create Database**. O nome final fica com o prefixo, ex.: `if0_12345678_agenda`.
3. Anote os dados mostrados na lista (ou em **MySQL Details** no Client Area):
   - **MySQL Host Name**, ex.: `sql123.infinityfree.com` (não é `localhost`!)
   - **MySQL DB Name**, ex.: `if0_12345678_agenda`
   - **MySQL User Name**, ex.: `if0_12345678`
   - **MySQL Password**: a mesma senha da conta (item 1.1)

### 1.4 Importar o banco
1. Na lista de bancos, clique em **Admin** (abre o phpMyAdmin).
2. Na coluna da esquerda, clique no **seu banco** (`if0_..._agenda`).
3. Aba **Importar** → **Escolher arquivo** → `banco-hospedagem.sql` → **Importar**.
   (Use este arquivo, não o `banco.sql`: na hospedagem não dá para criar banco pelo SQL.)

### 1.5 Ajustar o config.php
No `config.php` do seu PC:
1. Preencha o bloco `if (AMBIENTE === 'hospedagem')` com os dados do item 1.3.
2. Troque a linha `define('AMBIENTE', 'local');` para `define('AMBIENTE', 'hospedagem');`.
3. Envie o arquivo (item 1.6) e **volte a linha para `'local'`** no seu PC, para o XAMPP continuar funcionando.

Dica: depois do primeiro envio, dá para mudar só essa linha direto no **File Manager** da InfinityFree (botão de editar).

### 1.6 Enviar os arquivos
Pelo **File Manager** do painel (mais fácil) ou pelo **FileZilla** (FTP: servidor `ftpupload.net`, porta 21,
usuário e senha do item 1.1):
1. Abra a pasta **htdocs** e apague o arquivo de boas-vindas da InfinityFree (`index2.html`), se existir.
2. Envie para dentro de `htdocs` **todo o conteúdo** da pasta `agenda-comunitaria`, **menos**:
   - a pasta `app-windows`
   - os arquivos `.sql` e `.md`
3. Confira se foram juntos os arquivos que começam com ponto: **`.htaccess`** (na raiz) e **`cache/.htaccess`**.

Deve ficar assim: `htdocs/home.php`, `htdocs/sw.js`, `htdocs/manifest.json`, `htdocs/assets/...`, `htdocs/inc/...`, `htdocs/cache/...`.

### 1.7 Primeiro acesso
1. Abra `https://seu-dominio` e **cadastre a sua conta**. No modo `WHATSAPP_MODO = 'teste'`, o código aparece na própria página.
2. Para virar administrador do site, no phpMyAdmin (aba **SQL**) rode:
   ```sql
   UPDATE usuarios SET is_admin = 1 WHERE telefone = 'SEU_TELEFONE';  -- seu telefone com DDD, só números
   ```
3. **Importante:** no modo `'teste'` qualquer pessoa vê o código e pode cadastrar qualquer telefone.
   Quando a API do WhatsApp estiver pronta, troque para `'api'` no `config.php` do site.

### Se algo der errado
- **"Erro ao conectar no banco de dados"**: confira os 4 dados do item 1.3 no `config.php` (o host é `sqlXXX.infinityfree.com`).
- **Acentos estranhos**: o banco foi importado com o `banco.sql` em vez do `banco-hospedagem.sql`. Apague as tabelas e importe de novo.
- **Notícias não aparecem**: a hospedagem grátis pode bloquear ou demorar nas conexões para fora (Google Notícias).
  O resto do app funciona normalmente; a Home mostra o aviso "Não foi possível carregar as notícias agora".

---

## 2. APK Android (PWABuilder)

**Alternativa recomendada na InfinityFree:** o projeto do Android Studio em
`C:\Users\Colono2020\AndroidStudioProjects\app-android` (veja o `LEIA-ME.md` de lá). Ele não depende do
service worker nem do `assetlinks.json`. Isso importa porque a proteção anti-robô da InfinityFree bloqueia
o arquivo do service worker (o navegador recebe uma página HTML no lugar do `sw.js`). Na InfinityFree, então,
o site não fica "instalável" de verdade e a página offline do site não aparece. Numa hospedagem sem essa
proteção, tudo funciona como foi testado no XAMPP.

1. Abra o site no Chrome do celular uma vez e confira se aparece "Instalar app" / "Adicionar à tela inicial".
2. Entre em **pwabuilder.com**, cole `https://seu-dominio/home.php` e clique em **Start**.
3. Ele mostra a nota do manifest e do service worker (o app já tem os dois, com ícones 192/512 e maskable).
4. Clique em **Package For Stores** → **Android** → **Generate Package**.
   - *Package ID*: ex.: `br.agendacomunitaria.app`
   - *Signing key*: **Create new** (preencha seu nome e uma senha).
5. Baixe o `.zip`. Dentro dele:
   - **`.apk`**: para instalar direto nos celulares (pode precisar permitir "fontes desconhecidas").
   - **`.aab`**: para enviar à Google Play.
   - **`signing.keystore` e `signing-key-info.txt`**: **guarde em lugar seguro**. Sem eles você não consegue atualizar o app depois.
   - **`assetlinks.json`**: envie para `htdocs/.well-known/assetlinks.json` (crie a pasta `.well-known`).
     Assim o app abre em tela cheia, sem a barra de endereço.

**Atenção (InfinityFree):** a hospedagem grátis tem uma proteção anti-robô que, às vezes, impede o PWABuilder
de ler o site ("manifest não encontrado") ou impede o Android de conferir o `assetlinks.json` (o app abre
com uma barrinha de endereço em cima). Isso é limitação da hospedagem, não do app. Se acontecer, tente de novo
mais tarde; se continuar, uma hospedagem paga simples resolve.

---

## 3. Instalador para Windows (.exe)

### 3.1 Instalar o Node.js
1. Entre em **nodejs.org** e baixe a versão **LTS** (precisa ser **22.12 ou mais nova**; o Electron não funciona com versões antigas).
2. Instale com as opções padrão (Next, Next, Install).
3. Abra o **PowerShell** (menu Iniciar → "PowerShell") e confira: `node -v` deve mostrar `v22.12` ou maior.

### 3.2 Colocar o endereço do site
Abra `app-windows/main.js` e troque o endereço na linha:
```js
const ENDERECO_DO_SITE = 'https://seusite.infinityfreeapp.com/home.php';
```

### 3.3 Gerar o instalador
No PowerShell (use uma janela normal do PowerShell, fora do VS Code):
```
cd C:\xampp\htdocs\agenda-comunitaria\app-windows
npm install
npm start
npm run dist
```
- `npm install`: baixa o Electron (uns 150 MB, só na primeira vez).
- `npm start`: abre o app para você testar. Feche a janela para continuar.
- `npm run dist`: gera **`app-windows\instalador\Agenda-Comunitaria-Instalador-1.0.0.exe`**.

Esse `.exe` é o instalador: pode mandar para outras pessoas. Ele cria atalho na área de trabalho e no menu Iniciar.

**Aviso do Windows:** como o instalador não tem assinatura digital (que é paga), o Windows pode mostrar
"O Windows protegeu o computador". Clique em **Mais informações** → **Executar assim mesmo**.

**Nova versão:** troque `"version"` no `app-windows/package.json` (ex.: `1.0.1`) e rode `npm run dist` de novo.
Mudanças no site (PHP, estilo) **não** precisam de instalador novo: o app sempre abre o site atualizado.
