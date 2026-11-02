-- =====================================================
-- Agenda Comunitária - Migração da HOME (estado do usuário, admin do site, eventos do agro)
-- Rode UMA VEZ no phpMyAdmin, depois da migracao-comunidades.sql.
-- Quem for instalar do zero usa só o banco.sql.
-- =====================================================

USE agenda_comunitaria;

-- Estado escolhido na Home ('BR' = Todo o Brasil; NULL = ainda não escolheu, usa o DDD)
-- e administrador do site (cadastra os eventos do agro)
ALTER TABLE usuarios
    ADD COLUMN uf CHAR(2) NULL AFTER localidade,
    ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER senha_hash;

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

-- Eventos de exemplo, só para teste (pode excluir pela página admin-eventos.php)
INSERT INTO eventos_gerais (titulo, descricao, `local`, cidade, uf, data_inicio, data_fim, link) VALUES
('Dia de Campo do Milho (exemplo)', 'Demonstração de variedades de milho e manejo de solo, com técnicos da região.', 'Propriedade da Família Rossi', 'Campos Novos', 'SC', '2026-11-20 08:00:00', '2026-11-20 17:00:00', NULL),
('Feira da Agricultura Familiar (exemplo)', 'Venda direta de produtos coloniais, mudas e artesanato. Entrada gratuita.', 'Parque de Exposições', 'Chapecó', 'SC', '2026-12-05 08:00:00', '2026-12-07 18:00:00', 'https://www.example.com'),
('Seminário de Café Especial (exemplo)', 'Palestras sobre colheita, secagem e venda de cafés especiais.', 'Centro de Eventos', 'Franca', 'SP', '2027-01-22 09:00:00', '2027-01-22 18:00:00', NULL),
('Encontro de Produtores de Leite (exemplo)', 'Troca de experiências sobre qualidade do leite e bem-estar animal.', 'Salão da Cooperativa', 'Castro', 'PR', '2027-02-18 08:30:00', NULL, NULL),
('Mostra de Máquinas Agrícolas (exemplo)', 'Exposição de tratores, plantadeiras e implementos, com demonstrações no campo.', 'Parque da Cidade', 'Passo Fundo', 'RS', '2027-03-10 08:00:00', '2027-03-12 18:00:00', NULL),
('Congresso da Soja (exemplo)', 'Dois dias de palestras sobre produtividade, pragas e mercado da soja.', 'Centro de Convenções', 'Sorriso', 'MT', '2027-04-14 08:00:00', '2027-04-15 18:00:00', NULL);
