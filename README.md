# Agenda Comunitária

Aplicativo web para celular (PHP + MySQL) que ajuda pequenos produtores rurais a publicar avisos, produtos, empréstimos de equipamentos, serviços e eventos da comunidade.

## Funcionalidades

- Cadastro e login pelo **telefone** (muitos produtores não usam e-mail)
- Confirmação do cadastro por **código de 6 números pelo WhatsApp** (vale 10 min, até 5 erros, reenvio a cada 60 s)
- **Localização** da pessoa: estado, cidade (com sugestões do IBGE), bairro/comunidade e ponto de referência
- **E-mail na conta** (confirmado por link) e **Esqueci minha senha**: link por e-mail (vale 1 h, uso único) para criar senha nova e, se precisar, trocar o telefone
- **Home** com notícias do agro (Google Notícias) e eventos do agro do estado escolhido
- App **restrito a comunidades**: depois de entrar, a pessoa escolhe, cria ou entra numa comunidade
- Cada comunidade tem administrador, link de convite e senha de acesso; a pessoa pode participar de várias
- Mural com publicações de 5 tipos: Aviso, Produto, Empréstimo, Serviço e Evento
- "Ofereço / Preciso" para produtos, empréstimos e serviços
- Mural, filtro, busca e próximos eventos mostram só a comunidade escolhida no topo
- Botões **WhatsApp** e **Ligar** para falar direto com quem publicou
- "Minhas publicações": encerrar, reabrir ou excluir
- **Editar** a própria publicação (título, descrição, data e Ofereço/Preciso) até **24 horas** depois de publicar; publicações alteradas ficam com a marca "✏️ Alterado em …"
- Excluir a própria conta (com todas as publicações), confirmando com a senha
- Pode ser instalado na tela inicial do celular, como um app (PWA), virar APK Android e instalador do Windows

## Como rodar no PC (passo a passo)

Não é preciso configurar nada: o projeto já vem pronto para o XAMPP com as opções padrão.

### 1. Instale os programas

| Programa | Para que serve | Onde baixar |
|---|---|---|
| **XAMPP** (PHP 8.0 ou mais novo) | Servidor (Apache), PHP e banco de dados (MySQL) | https://www.apachefriends.org |
| **VS Code** (opcional) | Só para ver e editar o código; o app roda sem ele | https://code.visualstudio.com |

Instale o XAMPP na pasta padrão `C:\xampp`.

### 2. Baixe o projeto

**Opção A, pelo site:** no GitHub, clique no botão verde **Code → Download ZIP**.
Extraia o ZIP dentro de `C:\xampp\htdocs\` e renomeie a pasta `agenda-comunitaria-main` para `agenda-comunitaria`.

**Opção B, pelo Git:** no terminal, rode:

```
cd C:\xampp\htdocs
git clone https://github.com/Colono2020/agenda-comunitaria.git
```

Confira se ficou assim: `C:\xampp\htdocs\agenda-comunitaria\index.php`.
Se aparecer uma pasta dentro da outra (`agenda-comunitaria\agenda-comunitaria\...`), mova o conteúdo uma pasta para cima.

### 3. Ligue o Apache e o MySQL

Abra o **XAMPP Control Panel** e clique em **Start** em **Apache** e em **MySQL**. Os dois devem ficar verdes.

### 4. Crie o banco de dados

1. No navegador, abra `http://localhost/phpmyadmin`.
2. Clique na aba **Importar**.
3. Em **Escolher arquivo**, selecione `C:\xampp\htdocs\agenda-comunitaria\banco.sql`.
4. Role até o fim e clique em **Importar** (ou **Executar**).

Isso cria o banco `agenda_comunitaria` com todas as tabelas e alguns eventos de exemplo. Nenhum usuário é criado.

> **Configuração:** copie `config.exemplo.php` para `config.php` e preencha (senha do banco, dados da hospedagem,
> Gmail e senha de app, WhatsApp). O `config.php` guarda senhas e **não vai para o GitHub** (está no `.gitignore`).
> No XAMPP com as opções padrão dá para pular este passo: sem `config.php`, o app usa o `config.exemplo.php`.

### 5. Abra o app

Acesse `http://localhost/agenda-comunitaria`.

### 6. Crie uma conta

1. Clique em **Cadastre-se** e preencha nome, telefone com DDD (pode ser inventado, ex.: `49 99999-0000`) e senha.
2. O código de confirmação **aparece num aviso amarelo na própria tela** (não é preciso WhatsApp). Digite-o para concluir.
3. Pronto: crie uma comunidade e faça publicações no mural.

### 7. (Opcional) Vire administrador do site

No phpMyAdmin, aba **SQL**, rode o comando abaixo trocando o número pelo telefone cadastrado (só números, com DDD):

```sql
UPDATE agenda_comunitaria.usuarios SET is_admin = 1 WHERE telefone = '49999990000';
```

Recarregue a página do app: a área **🛠 Administração** aparece no menu **Eu**.

### Se der erro

| O que aparece | Causa provável | Como resolver |
|---|---|---|
| Apache não fica verde | A porta 80 está em uso por outro programa (Skype, IIS) | Feche o outro programa, ou no XAMPP clique em **Config → httpd.conf**, troque `Listen 80` por `Listen 8080` e acesse `http://localhost:8080/agenda-comunitaria` |
| MySQL não fica verde | Já existe outro MySQL instalado no PC usando a porta 3306 | Aperte `Win + R`, digite `services.msc`, ache o serviço **MySQL** (ex.: `MySQL80`) e clique em **Parar**. Depois dê Start no MySQL do XAMPP |
| "Erro ao conectar no banco de dados" | MySQL desligado, ou o usuário `root` do MySQL tem senha | Ligue o MySQL. Se o `root` tiver senha, copie `config.exemplo.php` com o nome `config.php` e coloque a senha em `DB_SENHA`, na parte do XAMPP (`else`) |
| "Unknown database 'agenda_comunitaria'" | O `banco.sql` não foi importado | Faça o passo 4 |
| "Not Found" / "Objeto não encontrado" | Pasta com outro nome ou uma pasta dentro da outra | Confira o passo 2 |
| As notícias da Home não aparecem | O PC está sem internet | É normal: o resto do app funciona sem internet |

## Extensões do VS Code

**Nenhuma extensão é obrigatória**: quem roda o app é o XAMPP, não o VS Code.
Para ler e editar o código com mais conforto, recomendamos estas (ao abrir a pasta, o VS Code sugere instalá-las):

| Extensão | Identificador | Para que serve |
|---|---|---|
| **PHP Intelephense** | `bmewburn.vscode-intelephense-client` | Cores, autocompletar e aviso de erros no código PHP |
| **Portuguese (Brazil) Language Pack** | `MS-CEINTL.vscode-language-pack-pt-BR` | Deixa o VS Code em português |

Para instalar: no VS Code, abra **Extensões** (`Ctrl+Shift+X`), pesquise pelo nome e clique em **Instalar**.

## Arquivos do projeto

**Para rodar no PC você só usa o `banco.sql` (passo 4).** Os demais `.sql`, o `PUBLICAR.md` e a pasta `app-windows` são para publicar na internet ou atualizar bancos antigos, e podem ser ignorados.

```
agenda-comunitaria/
├── README.md          ← este arquivo
├── banco.sql          ← cria o banco e as tabelas, com eventos de exemplo (importar no passo 4)
├── config.exemplo.php ← configuração padrão (XAMPP: usuário "root", sem senha, código na tela)
│                        Para mudar algo, copie como config.php (o config.php não vai para o GitHub)
├── index.php          ← página inicial: leva para a Home
├── home.php           ← Home: notícias e eventos do agro por estado
├── cadastro.php       ← criar conta
├── verificar.php      ← confirma o código enviado no cadastro
├── login.php, sair.php ← entrar e sair
├── esqueci-senha.php  ← pede o link de recuperação por e-mail
├── redefinir.php      ← recuperar a conta pelo link: senha nova e telefone novo (opcional)
├── confirmar-email.php ← confirma o e-mail da conta pelo link
├── comunidades.php    ← escolha de comunidade (primeira página após o login)
├── criar-comunidade.php, entrar.php ← criar / entrar numa comunidade por link ou código
├── comunidade.php     ← membros, convite, senha, sair e excluir comunidade
├── mural.php          ← mural da comunidade / minhas publicações
├── publicar.php       ← nova publicação
├── editar.php         ← editar a própria publicação (até 24 h depois de publicar)
├── acao.php           ← encerrar / reabrir / excluir / remover publicação
├── conta.php          ← minha conta
├── excluir-conta.php  ← exclui a própria conta (pede a senha)
├── admin-eventos.php  ← cadastro de eventos do agro (só administrador do site)
├── admin-comunidades.php, admin-usuarios.php ← administração de comunidades e usuários (só administrador do site)
├── inc/               ← partes comuns: funções e conexão com o banco (funcoes.php), topo, rodapé,
│                        notícias (noticias.php), envio do código pelo WhatsApp (whatsapp.php),
│                        envio de e-mails (email.php) e a biblioteca PHPMailer (pasta PHPMailer)
├── assets/            ← estilo (estilo.css), ícones e sugestões de cidades do IBGE (localizacao.js)
├── cache/             ← notícias guardadas por 30 min (criadas sozinhas; bloqueada para o navegador)
├── manifest.json      ← permite "instalar" no celular (PWA)
├── sw.js              ← service worker: guarda estilo/ícones e mostra offline.html sem internet
├── offline.html       ← página "Sem internet"
├── .htaccess          ← regras do Apache: força https na hospedagem e bloqueia .sql/.md
├── .vscode/           ← extensões recomendadas do VS Code
│
│   Não precisa para rodar no PC:
├── banco-hospedagem.sql ← o mesmo que banco.sql, para importar na hospedagem
├── migracao-comunidades.sql, migracao-home.sql, migracao-editar.sql, migracao-email.sql, migracao-localizacao.sql ← atualizam um banco de uma versão antiga (rodar uma vez)
├── ajustes-admin.sql  ← marca a conta administradora e apaga usuários de exemplo de bancos antigos
├── PUBLICAR.md        ← passo a passo: hospedagem InfinityFree, APK (PWABuilder) e instalador do Windows
└── app-windows/       ← app do Windows (Electron) que abre o site hospedado
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
3. `ajustes-admin.sql` (só para o banco que tinha os usuários de exemplo): marca a conta administradora e apaga os exemplos;
4. `migracao-editar.sql`: adiciona a coluna `editado_em` em `publicacoes` (edição de publicações). Clique no banco antes de rodar.
5. `migracao-email.sql`: adiciona o e-mail na conta e a tabela `links_email` (recuperação da conta). Clique no banco antes de rodar.
6. `migracao-localizacao.sql`: separa a localização em cidade, bairro e referência (a antiga "localidade" vira a cidade). Clique no banco antes de rodar.

## Código de verificação pelo WhatsApp

No `config.php` (ou `config.exemplo.php`), `WHATSAPP_MODO` define como o código é enviado:

- `'teste'` (padrão): nada é enviado; o código aparece num aviso amarelo na página de verificação.
- `'api'`: envia pela WhatsApp Cloud API da Meta. Preencha `WHATSAPP_TOKEN` e `WHATSAPP_NUMERO_ID`
  e crie no painel da Meta um modelo de mensagem da categoria **Autenticação** (com botão "Copiar código")
  com o nome definido em `WHATSAPP_TEMPLATE`. Se o envio falhar, o erro vai para o log do Apache (`C:\xampp\apache\logs\error.log`).

## E-mails (confirmação do e-mail e recuperação da conta)

Os e-mails são enviados pelo Gmail com o PHPMailer (já está na pasta `inc/PHPMailer`, não precisa de Composer).
No `config.php`:

- `EMAIL_MODO = 'smtp'` (padrão): envia pelo `smtp.gmail.com` (porta 587, TLS). Preencha `SMTP_EMAIL` (a conta Gmail)
  e `SMTP_SENHA_APP` (a "senha de app" de 16 letras criada em https://myaccount.google.com/apppasswords;
  a conta precisa ter a verificação em duas etapas ligada).
- `EMAIL_MODO = 'teste'`: nada é enviado; o link aparece num aviso amarelo na página (só para testes).
- `SITE_URL`: endereço do site usado nos links dos e-mails. Na hospedagem é `https://agendacomunitaria.site.je`;
  no XAMPP fica vazio (usa o endereço atual).

Se o e-mail e a senha de app estiverem vazios, ou o envio falhar, a pessoa vê "Não foi possível enviar o e-mail agora"
e o motivo vai para o log do PHP (no XAMPP: `C:
mpp\apache\logsrror.log`).

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
  Sem `config.php`, o app usa o `config.exemplo.php`, que já vem com `'local'`.
