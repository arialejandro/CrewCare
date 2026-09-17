<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMaterialityPhotosTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `materiality_photos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `image_path` varchar(255) NOT NULL,
  `note` varchar(500) DEFAULT NULL,
  `captured_by_id` bigint(20) unsigned DEFAULT NULL,
  `consultation_id` bigint(20) unsigned DEFAULT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `materiality_photos_created_at_idx` (`created_at`),
  KEY `materiality_photos_captured_by_idx` (`captured_by_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('materiality_photos');
    }
}
