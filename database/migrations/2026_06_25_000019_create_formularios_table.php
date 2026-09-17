<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateFormulariosTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `formularios` (
  `id_formulario` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_user` bigint(20) unsigned NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blod_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `height` float DEFAULT NULL,
  `size` float DEFAULT NULL,
  `c_emer` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `relation` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `p_emer` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hospitals` int(50) NOT NULL,
  `hsp1` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `hsp2` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `hsp3` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `hsp4` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `hsp5` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `hsp6` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `hsp7` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `hsp8` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `hsp9` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `hsp10` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `cirugy` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `pathology` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `alergy` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `trauma` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `momdat1` tinyint(1) DEFAULT '0',
  `momdat2` tinyint(1) DEFAULT '0',
  `momdat3` tinyint(1) DEFAULT '0',
  `momdat4` tinyint(1) DEFAULT '0',
  `momdat5` tinyint(1) DEFAULT '0',
  `momdat6` tinyint(1) DEFAULT '0',
  `momdat7` tinyint(1) DEFAULT '0',
  `momdat8` tinyint(1) DEFAULT '0',
  `daddat1` tinyint(1) DEFAULT '0',
  `daddat2` tinyint(1) DEFAULT '0',
  `daddat3` tinyint(1) DEFAULT '0',
  `daddat4` tinyint(1) DEFAULT '0',
  `daddat5` tinyint(1) DEFAULT '0',
  `daddat6` tinyint(1) DEFAULT '0',
  `daddat7` tinyint(1) DEFAULT '0',
  `daddat8` tinyint(1) DEFAULT '0',
  `pers_nopat1` tinyint(1) DEFAULT '0',
  `pers_nopat2` tinyint(1) DEFAULT '0',
  `pers_nopat3` tinyint(1) DEFAULT '0',
  `vacci1` tinyint(1) DEFAULT '0',
  `vacci2` tinyint(1) DEFAULT '0',
  `vacci3` tinyint(1) DEFAULT '0',
  `vacci4` tinyint(1) DEFAULT '0',
  `vacci5` tinyint(1) DEFAULT '0',
  `rythm` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `pregnant` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `prevent1` tinyint(1) DEFAULT '0',
  `prevent2` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `vacci2_date` date DEFAULT NULL,
  PRIMARY KEY (`id_formulario`),
  UNIQUE KEY `formularios_uuid_unique` (`uuid`),
  KEY `id_user` (`id_user`),
  CONSTRAINT `formularios_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('formularios');
    }
}
