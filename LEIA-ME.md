# Agenda Comunitária

Aplicativo web para celular (PHP + MySQL) que ajuda pequenos produtores rurais a publicar avisos, produtos, empréstimos de equipamentos, serviços e eventos da comunidade.

## Funcionalidades

- Cadastro e login pelo **telefone** (muitos produtores não usam e-mail)
- Confirmação do cadastro por **código de 6 números pelo WhatsApp** (vale 10 min, até 5 erros, reenvio a cada 60 s)
- **Home** com notícias do agro (Google Notícias) e eventos do agro do estado escolhido
- App **restrito a comunidades**: depois de entrar, a pessoa escolhe, cria ou entra numa comunidade
- Cada comunidade tem administrador, link de convite e senha de acesso; a pessoa pode participar de várias
- Mural com publicações de 5 tipos: Aviso, Produto, Empréstimo, Serviço e Evento
- "Ofereço / Preciso" para produtos, empréstimos e serviços
- Mural, filtro, busca e próximos eventos mostram só a comunidade escolhida no topo
- Botões **WhatsApp** e **Ligar** para falar direto com quem publicou
- "Minhas publicações": encerrar, reabrir ou excluir
- Excluir a própria conta (com todas as publicações), confirmando com a senha
- Pode ser instalado na tela inicial do celular, como um app (PWA), virar APK Android e instalador do Windows

## Estrutura

```
agenda-comunitaria/
├── banco.sql          ← cria o banco e as tabelas (com exemplos)
├── banco-hospedagem.sql ← o mesmo, para importar na hospedagem (sem CREATE DATABASE/USE)
├── config.php         ← dados de acesso ao MySQL (troca entre 'local' e 'hospedagem')
├── PUBLICAR.md        ← passo a passo: InfinityFree, APK (PWABuilder) e instalador do Windows
├── .htaccess          ← força https na hospedagem e bloqueia .sql/.md
├── index.php          ← leva para a Home
├── home.php           ← Home: notícias e eventos do agro por estado
├── mural.php          ← mural da comunidade / minhas publicações
├── admin-eventos.php  ← cadastro de eventos do agro (só administrador do site)
├── admin-comunidades.php, admin-usuarios.php ← administração de comunidades e usuários (só administrador do site)
├── ajustes-admin.sql  ← marca a conta administradora e apaga os usuários de exemplo (rodar uma vez)
├── publicar.php       ← nova publicação
├── migracao-comunidades.sql ← atualiza um banco antigo para comunidades (rodar uma vez)
├── migracao-home.sql  ← adiciona estado, admin do site e eventos do agro (rodar uma vez, depois da anterior)
├── cache/             ← notícias guardadas por 30 min (bloqueada para o navegador)
├── acao.php           ← encerrar / reabrir / excluir / remover (admin)
├── comunidades.php    ← tela de escolha de comunidade (primeira página após o login)
├── comunidade.php     ← membros, convite, senha, sair e excluir comunidade
├── criar-comunidade.php, entrar.php ← criar / entrar por link ou código
├── conta.php          ← minha conta
├── login.php, cadastro.php, sair.php
├── verificar.php      ← confirma o código enviado no cadastro
├── excluir-conta.php  ← exclui a própria conta (pede a senha)
├── manifest.json      ← permite "instalar" no celular (PWA)
├── sw.js              ← service worker: guarda estilo/ícones e mostra offline.html sem internet
├── offline.html       ← página "Sem internet"
├── inc/               ← funções, topo e rodapé
├── assets/            ← estilo e ícones (SVG e PNG 192/512, normais e maskable)
└── app-windows/       ← app do Windows (Electron) que abre o site hospedado (não enviar para a hospedagem)
```

## Navegação

Menu de baixo: **🏠 Home**, **👥 Comunidade** (lista de comunidades → mural, com o botão "＋ Publicar") e **👤 Eu**
(Minhas publicações, Minhas comunidades, Minha conta). Depois do login a pessoa cai na Home,
exceto quando veio de um link de convite.

## Home: notícias e eventos do agro

- O estado escolhido no topo fica salvo no cadastro (`usuarios.uf`). Antes da primeira escolha, usa o DDD do telefone.
- **Notícias:** RSS do Google Notícias, guardado na pasta `cache/` por 30 minutos por estado.
  Sem internet, mostra as últimas notícias guardadas ou o aviso "Não foi possível carregar as notícias agora".
- **Eventos do agro:** cadastrados pelo administrador do site em `admin-eventos.php`.
  Os eventos com "(exemplo)" no título são só para teste e podem ser excluídos por essa página.

## Administrador do site

Quem tem `is_admin = 1` vê, no menu **Eu**, a área **🛠 Administração**:

- **Eventos do agro** (`admin-eventos.php`): cadastrar, editar e excluir os eventos da Home.
- **Comunidades** (`admin-comunidades.php`): todas as comunidades, com criador, admin, membros e publicações.
  O administrador abre o mural de qualquer uma sem senha e sem virar membro (aparece o aviso
  "Você está vendo como administrador do site") e pode remover publicações e membros, gerar novo link,
  trocar a senha e excluir a comunidade. Para publicar, precisa entrar na comunidade como membro.
- **Usuários** (`admin-usuarios.php`): todos os usuários, com busca. Pode tornar/retirar outro
  administrador e excluir contas (pedindo a senha do administrador; a administração das comunidades
  passa para o membro mais antigo). Por essa página ninguém exclui nem tira o admin da própria conta.

## Comunidades

- Não existe comunidade pronta: todas são criadas pelos usuários. O mural e Publicar só abrem com uma
  comunidade escolhida; sem isso, a pessoa vai para a lista de comunidades. Minhas publicações funciona sempre.
- Quem cria a comunidade vira **administrador** e recebe um link de convite (`entrar.php?c=CODIGO`).
  A senha da comunidade deve ser passada à parte.
- Para entrar é preciso o link (ou o código) **e** a senha. Depois de 5 erros, a pessoa espera 15 minutos.
- Quem sai ou é removido tem as publicações naquela comunidade apagadas.
- Se o administrador sair (ou excluir a conta), o membro mais antigo vira administrador.
  Se não sobrar ninguém, a comunidade é apagada.
- O link de convite usa o endereço que está no navegador. Para convidar alguém pelo celular,
  abra o app pelo IP do PC (ex.: `http://192.168.0.15/agenda-comunitaria`), não por `localhost`.

**Já tinha o app instalado antes?** Rode, uma vez cada e nesta ordem, pelo phpMyAdmin (aba **Importar**):
1. `migracao-comunidades.sql`: cria as comunidades e **apaga as publicações antigas** (os usuários são mantidos);
2. `migracao-home.sql`: adiciona o estado e o admin do site em `usuarios` e cria os eventos do agro;
3. `ajustes-admin.sql` (só para o banco que tinha os usuários de exemplo): marca a conta administradora e apaga os exemplos.

## Como rodar no PC (XAMPP)

1. Copie a pasta `agenda-comunitaria` para `C:\xampp\htdocs\`.
2. No painel do XAMPP, inicie **Apache** e **MySQL**.
3. Abra `http://localhost/phpmyadmin`, vá em **Importar**, escolha `banco.sql` e clique em **Executar**.
4. Se não existir o `config.php`, copie o `config.exemplo.php` com o nome `config.php` (o `config.php` não vai para o GitHub, para proteger as senhas). Se o seu MySQL tiver senha ou outro usuário, ajuste o `config.php`.
5. Acesse `http://localhost/agenda-comunitaria`.

O banco começa **sem nenhum usuário**. Depois de instalar:

6. Crie a sua conta pelo app (**Cadastre-se**). Com `WHATSAPP_MODO = 'teste'`, o código de confirmação aparece na própria tela.
7. No phpMyAdmin, aba **SQL**, marque a sua conta como administradora do site (telefone só com números, com DDD):

   ```sql
   UPDATE usuarios SET is_admin = 1 WHERE telefone = '...';
   ```

8. Recarregue a página do app: a área **🛠 Administração** aparece no menu **Eu**.

## Código de verificação pelo WhatsApp

No `config.php`, `WHATSAPP_MODO` define como o código é enviado:

- `'teste'` (padrão): nada é enviado; o código aparece num aviso amarelo na página de verificação.
- `'api'`: envia pela WhatsApp Cloud API da Meta. Preencha `WHATSAPP_TOKEN` e `WHATSAPP_NUMERO_ID`
  e crie no painel da Meta um modelo de mensagem da categoria **Autenticação** (com botão "Copiar código")
  com o nome definido em `WHATSAPP_TEMPLATE`. Se o envio falhar, o erro vai para o log do Apache (`C:\xampp\apache\logs\error.log`).

## Como abrir no celular

1. O celular e o PC precisam estar no **mesmo Wi-Fi**.
2. No PC, abra o Prompt de Comando e digite `ipconfig`. Anote o **Endereço IPv4** (ex.: `192.168.0.15`).
3. No navegador do celular, acesse `http://192.168.0.15/agenda-comunitaria`.
4. Para virar "app": no Chrome, menu ⋮ → **Adicionar à tela inicial**.

Se não abrir no celular, libere o Apache no Firewall do Windows (porta 80).

Pelo IP do PC (`http://`) o celular só cria um atalho: o service worker e a instalação completa
exigem `https`, o que acontece no site hospedado.

## App instalável (PWA), hospedagem, APK e Windows

Passo a passo completo em **[PUBLICAR.md](PUBLICAR.md)**.

- `manifest.json` e `sw.js` usam caminhos relativos: funcionam em `localhost/agenda-comunitaria` e na raiz de um domínio.
- O service worker guarda só o estilo, os ícones e a `offline.html`. As páginas PHP sempre vêm da internet.
  Mudanças no `estilo.css` chegam sozinhas na visita seguinte. Se acrescentar arquivos à lista do `sw.js`,
  troque `agenda-v1` por `agenda-v2`.
- No `config.php`, a linha `define('AMBIENTE', ...)` escolhe entre os dados do XAMPP (`'local'`) e os da hospedagem (`'hospedagem'`).
