-- =====================================================
-- Agenda Comunitária - Migração da LOCALIZAÇÃO do usuário
-- A localização passa a ter: Estado (uf), Cidade, Bairro/Comunidade/Linha e Ponto de referência.
-- O que estava em "localidade" vai para "cidade". A coluna localidade continua no banco,
-- mas o app não usa mais.
--
-- Rode UMA VEZ em cada banco (no XAMPP e na hospedagem):
--   1. No phpMyAdmin, clique primeiro no banco, na lista da esquerda
--      (XAMPP: agenda_comunitaria; hospedagem: o banco if0_...);
--   2. Aba "Importar" -> escolha este arquivo -> Importar.
-- Se aparecer "Duplicate column name 'cidade'", a migração já foi feita: não precisa fazer nada.
-- Quem for instalar do zero usa só o banco.sql (ou banco-hospedagem.sql), que já tem tudo isto.
-- =====================================================

SET NAMES utf8mb4;

-- Localização da conta (o estado é a coluna uf, que já existe)
ALTER TABLE usuarios
    ADD COLUMN cidade VARCHAR(100) NOT NULL DEFAULT '' AFTER localidade,
    ADD COLUMN bairro VARCHAR(100) NOT NULL DEFAULT '' AFTER cidade,
    ADD COLUMN referencia VARCHAR(80) NOT NULL DEFAULT '' AFTER bairro,
    MODIFY localidade VARCHAR(100) NOT NULL DEFAULT '';

-- O cadastro pendente (aguardando o código do WhatsApp) também guarda a localização
ALTER TABLE verificacoes
    ADD COLUMN uf CHAR(2) NULL AFTER localidade,
    ADD COLUMN cidade VARCHAR(100) NOT NULL DEFAULT '' AFTER uf,
    ADD COLUMN bairro VARCHAR(100) NOT NULL DEFAULT '' AFTER cidade,
    MODIFY localidade VARCHAR(100) NOT NULL DEFAULT '';

-- Dados atuais: a localidade vira a cidade
UPDATE usuarios SET cidade = TRIM(localidade) WHERE cidade = '';

-- Estado: antes a coluna uf guardava a escolha da Home (podia ser 'BR' = Todo o Brasil).
-- Agora ela é o estado da localização; quem não tem um estado de verdade recebe o do DDD do telefone.
UPDATE usuarios SET uf = CASE
        WHEN LEFT(telefone, 2) IN ('11','12','13','14','15','16','17','18','19') THEN 'SP'
        WHEN LEFT(telefone, 2) IN ('21','22','24') THEN 'RJ'
        WHEN LEFT(telefone, 2) IN ('27','28') THEN 'ES'
        WHEN LEFT(telefone, 2) IN ('31','32','33','34','35','37','38') THEN 'MG'
        WHEN LEFT(telefone, 2) IN ('41','42','43','44','45','46') THEN 'PR'
        WHEN LEFT(telefone, 2) IN ('47','48','49') THEN 'SC'
        WHEN LEFT(telefone, 2) IN ('51','53','54','55') THEN 'RS'
        WHEN LEFT(telefone, 2) = '61' THEN 'DF'
        WHEN LEFT(telefone, 2) IN ('62','64') THEN 'GO'
        WHEN LEFT(telefone, 2) = '63' THEN 'TO'
        WHEN LEFT(telefone, 2) IN ('65','66') THEN 'MT'
        WHEN LEFT(telefone, 2) = '67' THEN 'MS'
        WHEN LEFT(telefone, 2) = '68' THEN 'AC'
        WHEN LEFT(telefone, 2) = '69' THEN 'RO'
        WHEN LEFT(telefone, 2) IN ('71','73','74','75','77') THEN 'BA'
        WHEN LEFT(telefone, 2) = '79' THEN 'SE'
        WHEN LEFT(telefone, 2) IN ('81','87') THEN 'PE'
        WHEN LEFT(telefone, 2) = '82' THEN 'AL'
        WHEN LEFT(telefone, 2) = '83' THEN 'PB'
        WHEN LEFT(telefone, 2) = '84' THEN 'RN'
        WHEN LEFT(telefone, 2) IN ('85','88') THEN 'CE'
        WHEN LEFT(telefone, 2) IN ('86','89') THEN 'PI'
        WHEN LEFT(telefone, 2) IN ('91','93','94') THEN 'PA'
        WHEN LEFT(telefone, 2) IN ('92','97') THEN 'AM'
        WHEN LEFT(telefone, 2) = '95' THEN 'RR'
        WHEN LEFT(telefone, 2) = '96' THEN 'AP'
        WHEN LEFT(telefone, 2) IN ('98','99') THEN 'MA'
        ELSE NULL
    END
WHERE uf IS NULL OR uf = 'BR' OR uf = '';
