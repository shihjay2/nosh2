<?php

use Illuminate\Database\Migrations\Migration;

class CreateVitalsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('vitals')) {
			Schema::create('vitals', function($table) {
				$table->bigInteger('eid')->primary();
				$table->bigInteger('pid')->nullable();
				$table->string('encounter_provider', 255)->nullable();
				$table->dateTime('vitals_date')->nullable();
				$table->string('vitals_age', 3)->nullable();
				$table->string('pedsage', 100)->nullable();
				$table->string('weight', 10)->nullable();
				$table->string('height', 10)->nullable();
				$table->string('headcircumference', 10)->nullable();
				$table->string('BMI', 10)->nullable();
				$table->string('temp', 10)->nullable();
				$table->string('temp_method', 100)->nullable();
				$table->string('bp_systolic', 10)->nullable();
				$table->string('bp_diastolic', 10)->nullable();
				$table->string('bp_position', 100)->nullable();
				$table->string('pulse', 10)->nullable();
				$table->string('respirations', 10)->nullable();
				$table->string('o2_sat', 10)->nullable();
				$table->longtext('vitals_other')->nullable();
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
        Schema::drop('vitals');
    }

}
