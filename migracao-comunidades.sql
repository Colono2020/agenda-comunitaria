-- =====================================================
-- Agenda Comunitária - Migração para COMUNIDADES
-- Para quem já tem o banco instalado (antes das comunidades).
-- Rode UMA VEZ no phpMyAdmin. Quem for instalar do zero usa só o banco.sql.
-- Funciona tanto num banco ainda não migrado quanto num que já rodou a versão
-- antiga desta migração (a que criava a "Comunidade Geral").
--
-- ATENÇÃO: apaga TODAS as publicações antigas (eram só exemplos de teste) e a
-- "Comunidade Geral", se existir. Os usuários são mantidos. Nenhuma comunidade
-- é criada: cada usuário cria ou entra na sua depois de fazer login.
-- =====================================================

USE agenda_comunitaria;

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

-- Tentativas erradas de entrar em comunidade (limite contra quem fica chutando senha)
CREATE TABLE IF NOT EXISTS tentativas_comunidade (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT      NOT NULL,
    criado_em   DATETIME NOT NULL,
    INDEX idx_tentativa (usuario_id, criado_em),
    CONSTRAINT fk_tent_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Apaga as publicações antigas (exemplos de teste)
DELETE FROM publicacoes;

-- Remove a antiga "Comunidade Geral", caso uma versão anterior desta migração já tenha sido rodada
-- (os membros dela saem junto pelo ON DELETE CASCADE)
DELETE FROM comunidades WHERE codigo = 'GERAKQ7M';

-- Toda publicação passa a pertencer a uma comunidade
-- (só adiciona a coluna se ela ainda não existir, para não dar erro em quem já migrou)
SET @tem_coluna := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publicacoes' AND COLUMN_NAME = 'comunidade_id');
SET @sql := IF(@tem_coluna = 0,
    'ALTER TABLE publicacoes
        ADD COLUMN comunidade_id INT NOT NULL AFTER usuario_id,
        ADD CONSTRAINT fk_pub_comunidade FOREIGN KEY (comunidade_id)
            REFERENCES comunidades(id) ON DELETE CASCADE,
        ADD INDEX idx_comunidade (comunidade_id, criado_em)',
    'SELECT ''A coluna comunidade_id já existe'' AS aviso');
PREPARE comando FROM @sql;
EXECUTE comando;
DEALLOCATE PREPARE comando;
