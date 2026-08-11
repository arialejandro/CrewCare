<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateUsuariosnotificacionesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `usuariosnotificaciones` (
  `id_usernotificacion` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) DEFAULT NULL,
  `correo` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `activo` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id_usernotificacion`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('usuariosnotificaciones');
    }
}
