-- =====================================================
-- Agenda Comunitária - Migração da EDIÇÃO de publicações
-- Adiciona a coluna editado_em (data da última alteração feita pelo autor).
--
-- Rode UMA VEZ em cada banco (no XAMPP e na hospedagem):
--   1. No phpMyAdmin, clique primeiro no banco, na lista da esquerda
--      (XAMPP: agenda_comunitaria; hospedagem: o banco if0_...);
--   2. Aba "SQL" (ou "Importar") -> cole/importe este arquivo -> Executar.
-- Se aparecer "Duplicate column name 'editado_em'", a migração já foi feita: não precisa fazer nada.
-- Quem for instalar do zero usa só o banco.sql (ou banco-hospedagem.sql), que já tem a coluna.
-- =====================================================

ALTER TABLE publicacoes
    ADD COLUMN editado_em DATETIME NULL AFTER criado_em;
