-- =====================================================
-- Agenda Comunitária - Ajustes de administrador
-- Rode UMA VEZ no phpMyAdmin (aba "Importar" ou "SQL"), depois da migracao-home.sql.
--
-- ANTES DE RODAR: troque SEU_TELEFONE (em todas as linhas abaixo) pelo seu telefone com DDD, só números.
--
-- a) Marca a sua conta como administradora do site.
-- b) Apaga os usuários de exemplo João da Silva (49999990001) e Associação dos
--    Produtores (49999990002), com as publicações e participações deles.
--    Se algum deles for admin de comunidade, o membro mais antigo vira admin;
--    se a comunidade ficar sem ninguém, ela é apagada.
--
-- Trava de segurança: os exemplos só são apagados se a conta SEU_TELEFONE existir
-- e for administradora, para o site nunca ficar sem administrador.
-- =====================================================

USE agenda_comunitaria;

START TRANSACTION;

-- a) Administradora do site
UPDATE usuarios SET is_admin = 1 WHERE telefone = 'SEU_TELEFONE';

-- b) Usuários de exemplo que serão apagados (vazio se a administradora não existir)
CREATE TEMPORARY TABLE ex_usuarios AS
SELECT id FROM usuarios
WHERE telefone IN ('49999990001', '49999990002')
  AND EXISTS (SELECT 1 FROM usuarios WHERE telefone = 'SEU_TELEFONE' AND is_admin = 1);

-- Comunidades de que eles participam
CREATE TEMPORARY TABLE ex_comunidades AS
SELECT DISTINCT comunidade_id FROM membros WHERE usuario_id IN (SELECT id FROM ex_usuarios);

-- Publicações e participações deles
DELETE FROM publicacoes WHERE usuario_id IN (SELECT id FROM ex_usuarios);
DELETE FROM membros     WHERE usuario_id IN (SELECT id FROM ex_usuarios);

-- Comunidades que ficaram sem admin: o membro mais antigo vira admin
CREATE TEMPORARY TABLE novos_admins AS
SELECT c.comunidade_id,
       (SELECT m.usuario_id FROM membros m
         WHERE m.comunidade_id = c.comunidade_id
         ORDER BY m.entrou_em, m.usuario_id LIMIT 1) AS usuario_id
FROM ex_comunidades c
WHERE NOT EXISTS (SELECT 1 FROM membros a WHERE a.comunidade_id = c.comunidade_id AND a.papel = 'admin');

UPDATE membros m
JOIN novos_admins n ON n.comunidade_id = m.comunidade_id AND n.usuario_id = m.usuario_id
SET m.papel = 'admin';

-- Comunidades que ficaram sem nenhum membro são apagadas
DELETE FROM comunidades
WHERE id IN (SELECT comunidade_id FROM ex_comunidades)
  AND id NOT IN (SELECT comunidade_id FROM membros);

-- Cadastros pendentes (WhatsApp) e, por fim, os próprios usuários de exemplo.
-- Comunidades e eventos criados por eles ficam com o criador vazio (ON DELETE SET NULL).
DELETE FROM verificacoes
WHERE telefone IN ('49999990001', '49999990002')
  AND EXISTS (SELECT 1 FROM usuarios WHERE telefone = 'SEU_TELEFONE' AND is_admin = 1);
DELETE FROM usuarios WHERE id IN (SELECT id FROM ex_usuarios);

COMMIT;

-- Resultado
SELECT nome, telefone, IF(is_admin = 1, 'sim', 'não') AS admin_do_site FROM usuarios ORDER BY nome;
