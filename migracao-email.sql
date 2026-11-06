-- =====================================================
-- Agenda Comunitária - Migração da RECUPERAÇÃO DE CONTA POR E-MAIL
-- Adiciona o e-mail na conta e a tabela dos links enviados por e-mail.
--
-- Rode UMA VEZ em cada banco (no XAMPP e na hospedagem):
--   1. No phpMyAdmin, clique primeiro no banco, na lista da esquerda
--      (XAMPP: agenda_comunitaria; hospedagem: o banco if0_...);
--   2. Aba "Importar" -> escolha este arquivo -> Importar.
-- Se aparecer "Duplicate column name 'email'", a migração já foi feita: não precisa fazer nada.
-- Quem for instalar do zero usa só o banco.sql (ou banco-hospedagem.sql), que já tem tudo isto.
-- =====================================================

SET NAMES utf8mb4;

-- E-mail da conta (para recuperar a conta) e controle das sessões abertas
ALTER TABLE usuarios
    ADD COLUMN email VARCHAR(190) NULL UNIQUE AFTER is_admin,
    ADD COLUMN email_confirmado_em DATETIME NULL AFTER email,
    ADD COLUMN sessao_versao INT NOT NULL DEFAULT 0 AFTER email_confirmado_em;

-- O cadastro pendente (aguardando o código do WhatsApp) também guarda o e-mail
ALTER TABLE verificacoes
    ADD COLUMN email VARCHAR(190) NOT NULL DEFAULT '' AFTER localidade;

-- Links enviados por e-mail: confirmar o e-mail e recuperar a conta (o link nunca é salvo, só o hash)
CREATE TABLE IF NOT EXISTS links_email (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT          NOT NULL,
    tipo        ENUM('confirmar','recuperar') NOT NULL,
    email       VARCHAR(190) NOT NULL,           -- e-mail para onde o link foi enviado
    token_hash  CHAR(64)     NOT NULL UNIQUE,    -- SHA-256 do token do link
    expira_em   DATETIME     NOT NULL,
    usado_em    DATETIME     NULL,               -- preenchido quando o link é usado ou invalidado
    criado_em   DATETIME     NOT NULL,
    INDEX idx_link_usuario (usuario_id, tipo),
    INDEX idx_link_email (email, tipo, criado_em),
    CONSTRAINT fk_link_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
