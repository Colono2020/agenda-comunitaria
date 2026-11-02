-- =====================================================
-- Agenda Comunitária - Banco de dados (MySQL / MariaDB)
-- Importe este arquivo no phpMyAdmin (aba "Importar")
-- =====================================================

CREATE DATABASE IF NOT EXISTS agenda_comunitaria
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE agenda_comunitaria;

-- Usuários (produtores, associações etc.)
CREATE TABLE IF NOT EXISTS usuarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    telefone    VARCHAR(20)  NOT NULL UNIQUE,   -- usado para login e contato no WhatsApp
    localidade  VARCHAR(100) NOT NULL,          -- comunidade / linha / bairro rural
    uf          CHAR(2)      NULL,              -- estado escolhido na Home (BR = Todo o Brasil; NULL = usa o DDD)
    senha_hash  VARCHAR(255) NOT NULL,
    is_admin    TINYINT(1)   NOT NULL DEFAULT 0, -- 1 = administrador do site (cadastra os eventos do agro)
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Comunidades (cada uma tem um código de convite e uma senha de acesso)
CREATE TABLE IF NOT EXISTS comunidades (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(80)  NOT NULL,
    descricao   VARCHAR(255) NOT NULL DEFAULT '',
    codigo      CHAR(8)      NOT NULL UNIQUE,     -- usado no link de convite
    senha_hash  VARCHAR(255) NOT NULL,
    criador_id  INT          NULL,                -- fica NULL se o criador excluir a conta
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_com_criador FOREIGN KEY (criador_id)
        REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Quem participa de cada comunidade
CREATE TABLE IF NOT EXISTS membros (
    comunidade_id INT      NOT NULL,
    usuario_id    INT      NOT NULL,
    papel         ENUM('admin','membro') NOT NULL DEFAULT 'membro',
    entrou_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comunidade_id, usuario_id),     -- a mesma pessoa não entra duas vezes
    INDEX idx_membro_usuario (usuario_id),
    CONSTRAINT fk_mem_comunidade FOREIGN KEY (comunidade_id)
        REFERENCES comunidades(id) ON DELETE CASCADE,
    CONSTRAINT fk_mem_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Publicações do mural (cada uma pertence a uma comunidade)
CREATE TABLE IF NOT EXISTS publicacoes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT          NOT NULL,
    comunidade_id INT          NOT NULL,
    tipo          ENUM('aviso','produto','emprestimo','servico','evento') NOT NULL,
    intencao      ENUM('ofereco','preciso') NULL,  -- usado em produto, empréstimo e serviço
    titulo        VARCHAR(120) NOT NULL,
    descricao     TEXT         NOT NULL,
    data_evento   DATETIME     NULL,               -- data do evento ou data em que precisa
    ativo         TINYINT(1)   NOT NULL DEFAULT 1, -- 0 = encerrada (ex.: já conseguiu o empréstimo)
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pub_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_pub_comunidade FOREIGN KEY (comunidade_id)
        REFERENCES comunidades(id) ON DELETE CASCADE,
    INDEX idx_tipo (tipo),
    INDEX idx_criado (criado_em),
    INDEX idx_comunidade (comunidade_id, criado_em)
) ENGINE=InnoDB;

-- Tentativas erradas de entrar em comunidade (limite contra quem fica chutando senha)
CREATE TABLE IF NOT EXISTS tentativas_comunidade (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT      NOT NULL,
    criado_em   DATETIME NOT NULL,
    INDEX idx_tentativa (usuario_id, criado_em),
    CONSTRAINT fk_tent_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Eventos do agro mostrados na Home (cadastrados pelo administrador do site)
CREATE TABLE IF NOT EXISTS eventos_gerais (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    titulo      VARCHAR(150) NOT NULL,
    descricao   TEXT         NOT NULL,
    `local`     VARCHAR(150) NOT NULL,
    cidade      VARCHAR(100) NOT NULL,
    uf          CHAR(2)      NOT NULL,
    data_inicio DATETIME     NOT NULL,
    data_fim    DATETIME     NULL,
    link        VARCHAR(500) NULL,
    criado_por  INT          NULL,
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_evento_uf (uf, data_inicio),
    CONSTRAINT fk_evento_criador FOREIGN KEY (criado_por)
        REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Cadastros aguardando a confirmação do código enviado pelo WhatsApp
CREATE TABLE IF NOT EXISTS verificacoes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    telefone    VARCHAR(20)  NOT NULL UNIQUE,
    nome        VARCHAR(100) NOT NULL,
    localidade  VARCHAR(100) NOT NULL,
    senha_hash  VARCHAR(255) NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL DEFAULT '',  -- o código nunca é salvo em texto puro
    tentativas  TINYINT      NOT NULL DEFAULT 0,   -- erros com o código atual (máximo 5)
    expira_em   DATETIME     NULL,                 -- validade do código (10 minutos)
    enviado_em  DATETIME     NULL,                 -- último envio (para a espera de 60 segundos)
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expira (expira_em)
) ENGINE=InnoDB;

-- Eventos de exemplo, só para teste (pode excluir pela página admin-eventos.php)
INSERT INTO eventos_gerais (titulo, descricao, `local`, cidade, uf, data_inicio, data_fim, link) VALUES
('Dia de Campo do Milho (exemplo)', 'Demonstração de variedades de milho e manejo de solo, com técnicos da região.', 'Propriedade da Família Rossi', 'Campos Novos', 'SC', '2026-11-20 08:00:00', '2026-11-20 17:00:00', NULL),
('Feira da Agricultura Familiar (exemplo)', 'Venda direta de produtos coloniais, mudas e artesanato. Entrada gratuita.', 'Parque de Exposições', 'Chapecó', 'SC', '2026-12-05 08:00:00', '2026-12-07 18:00:00', 'https://www.example.com'),
('Seminário de Café Especial (exemplo)', 'Palestras sobre colheita, secagem e venda de cafés especiais.', 'Centro de Eventos', 'Franca', 'SP', '2027-01-22 09:00:00', '2027-01-22 18:00:00', NULL),
('Encontro de Produtores de Leite (exemplo)', 'Troca de experiências sobre qualidade do leite e bem-estar animal.', 'Salão da Cooperativa', 'Castro', 'PR', '2027-02-18 08:30:00', NULL, NULL),
('Mostra de Máquinas Agrícolas (exemplo)', 'Exposição de tratores, plantadeiras e implementos, com demonstrações no campo.', 'Parque da Cidade', 'Passo Fundo', 'RS', '2027-03-10 08:00:00', '2027-03-12 18:00:00', NULL),
('Congresso da Soja (exemplo)', 'Dois dias de palestras sobre produtividade, pragas e mercado da soja.', 'Centro de Convenções', 'Sorriso', 'MT', '2027-04-14 08:00:00', '2027-04-15 18:00:00', NULL);
