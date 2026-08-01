-- =====================================================================================
--  PIEZA 3 (corrida 1/2) · Cuestionario de salud — CORRECTIVO DEL ESQUEMA
--  Fecha: 2026-07-24
--  Tabla: `formularios` (el expediente clínico del crew)
--
--  CONTENIDO: 4 cambios en `formularios`.
--    1) `height` FLOAT NOT NULL  →  FLOAT NULL DEFAULT NULL   (peso, en Kg)
--    2) `size`   FLOAT NOT NULL  →  FLOAT NULL DEFAULT NULL   (talla, en Mts)
--    3) + `vacci2_date` DATE NULL                             (fecha de la influenza)
--    4) - `crt19`                                             (resto del COVID, sin input)
--
--  POR QUÉ CADA UNO
--
--  1+2) 🐞 BUG QUE BLOQUEABA A USUARIOS REALES. En el formulario, peso y talla son los
--       ÚNICOS dos campos sin asterisco — se ven opcionales, y lo son. Pero en BD eran
--       FLOAT NOT NULL SIN DEFAULT, así que al dejarlos vacíos llegaba '' y MySQL en
--       modo estricto (STRICT_TRANS_TABLES, activo en este servidor) respondía
--       "1265 Data truncated for column 'height'" → 500 en blanco. El camino MÁS
--       PROBABLE de un usuario real terminaba en pantalla de error.
--
--       Se eligió NULLABLE y no "obligatorio". Razón: peso y talla alimentan el IMC, que
--       INFORMA pero no decide una urgencia — a diferencia de la alergia, que contraindica.
--       Volverlos obligatorios no produce el dato: produce un "60" tecleado para poder
--       enviar el formulario, y un IMC calculado sobre un peso inventado es peor que un
--       IMC ausente. Misma doctrina que el reporte de wrap: EL HUECO SE DECLARA, NO SE
--       ESTIMA. Cuando falten, la ficha imprime "—" y el IMC no se calcula.
--       (Las 3 filas existentes SÍ traen valor: no las toca.)
--
--    3) La casilla dice "Influenza H1N1 (No mayor a un año)" — la etiqueta afirma una
--       VIGENCIA que el dato no puede sostener, porque se guardaba como sí/no sin fecha.
--       Un "sí" de hace tres años se leía idéntico a uno de hace un mes. La fecha la
--       convierte en verificable. Nace NULL: los expedientes viejos no mienten, declaran
--       que no se preguntó.
--
--    4) `crt19` era el certificado COVID-19. NO tiene input en el formulario desde el
--       desacople (2026-06-25): entra siempre con su default 'N'. Se retira AHORA, antes
--       de que el expediente empiece a sellarse (corrida 2/2), porque una columna muerta
--       dentro del hash queda ahí para siempre. Es el último momento barato para sacarla.
--
--  ⚠ ORDEN RESPECTO AL SELLO. Este SQL va ANTES del sellado del expediente. Si se aplicara
--    después, cambiar el tipo de `height`/`size` o quitar `crt19` movería el payload
--    firmado y los expedientes ya sellados se auto-acusarían de "ALTERADO". Por eso la
--    Pieza 3 se partió en dos: primero se estabiliza el esquema, luego se congela.
--
--  APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). MySQL 5.7 no tiene
--  ADD/DROP COLUMN IF EXISTS → wrapper information_schema dentro de un PROCEDURE.
--  Idempotente: correrlo dos veces no hace nada la segunda vez.
--
--  REVERSIÓN:
--    ALTER TABLE `formularios`
--      MODIFY `height` FLOAT NOT NULL,
--      MODIFY `size`   FLOAT NOT NULL,
--      DROP COLUMN `vacci2_date`,
--      ADD COLUMN `crt19` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT 'N';
--    ⚠ El MODIFY a NOT NULL falla si para entonces hay filas con peso/talla en NULL.
--      En ese caso, primero: UPDATE `formularios` SET `height`=0 WHERE `height` IS NULL;
--      (y lo mismo con `size`) — asumiendo que se prefiere un 0 explícito a perder la fila.
--    Los 3 expedientes existentes quedan intactos en cualquier caso.
-- =====================================================================================

DROP PROCEDURE IF EXISTS crewcare_health_record_fixes_2026_07_24;
DELIMITER //
CREATE PROCEDURE crewcare_health_record_fixes_2026_07_24()
BEGIN
    -- 1) peso → nullable
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='formularios'
          AND COLUMN_NAME='height' AND IS_NULLABLE='NO') THEN
        ALTER TABLE `formularios` MODIFY `height` FLOAT NULL DEFAULT NULL;
    END IF;

    -- 2) talla → nullable
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='formularios'
          AND COLUMN_NAME='size' AND IS_NULLABLE='NO') THEN
        ALTER TABLE `formularios` MODIFY `size` FLOAT NULL DEFAULT NULL;
    END IF;

    -- 3) fecha de aplicación de la influenza
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='formularios'
          AND COLUMN_NAME='vacci2_date') THEN
        ALTER TABLE `formularios` ADD COLUMN `vacci2_date` DATE NULL DEFAULT NULL;
    END IF;

    -- 4) retiro del resto COVID
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='formularios'
          AND COLUMN_NAME='crt19') THEN
        ALTER TABLE `formularios` DROP COLUMN `crt19`;
    END IF;
END //
DELIMITER ;
CALL crewcare_health_record_fixes_2026_07_24();
DROP PROCEDURE IF EXISTS crewcare_health_record_fixes_2026_07_24;
