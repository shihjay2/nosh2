<?php

use Illuminate\Database\Migrations\Migration;

class CreateProvidersTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('providers')) {
			Schema::create('providers', function($table) {
				$table->bigInteger('id')->primary();
				$table->string('license', 100)->nullable();
				$table->string('license_state', 100)->nullable();
				$table->string('npi', 100)->nullable();
				$table->string('npi_taxonomy', 100)->nullable();
				$table->string('upin', 100)->nullable();
				$table->string('dea', 100)->nullable();
				$table->string('medicare', 100)->nullable();
				$table->string('specialty', 100)->nullable();
				$table->string('tax_id', 100)->nullable();
				$table->string('signature', 100)->nullable();
				$table->integer('timeslotsperhour')->nullable();
				$table->string('sun_o', 10)->nullable();
				$table->string('sun_c', 10)->nullable();
				$table->string('mon_o', 10)->nullable();
				$table->string('mon_c', 10)->nullable();
				$table->string('tue_o', 10)->nullable();
				$table->string('tue_c', 10)->nullable();
				$table->string('wed_o', 10)->nullable();
				$table->string('wed_c', 10)->nullable();
				$table->string('thu_o', 10)->nullable();
				$table->string('thu_c', 10)->nullable();
				$table->string('fri_o', 10)->nullable();
				$table->string('fri_c', 10)->nullable();
				$table->string('sat_o', 10)->nullable();
				$table->string('sat_c', 10)->nullable();
				$table->string('rcopia_username', 100)->nullable();
				$table->string('schedule_increment', 100)->default("20")->nullable();
				$table->integer('practice_id')->nullable();
				$table->string('peacehealth_id', 100)->nullable();
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
        Schema::drop('providers');
    }

}
