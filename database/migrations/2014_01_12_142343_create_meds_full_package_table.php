<?php

use Illuminate\Database\Migrations\Migration;

class CreateMedsfullpackageTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('meds_full_package')) {
			Schema::create('meds_full_package', function($table) {
				$table->string('PRODUCTNDC', 255)->nullable();
				$table->string('NDCPACKAGECODE', 255)->primary();
				$table->string('PACKAGEDESCRIPTION', 255)->nullable();
			});
		}
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('meds_full_package');
    }

}
