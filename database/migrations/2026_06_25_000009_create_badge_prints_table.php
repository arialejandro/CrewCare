<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateBadgePrintsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `badge_prints` (
  `user_id` bigint(20) unsigned NOT NULL,
  `printed_at` timestamp NULL DEFAULT NULL,
  `printed_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('badge_prints');
    }
}
