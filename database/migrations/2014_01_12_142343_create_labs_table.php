<?php

use Illuminate\Database\Migrations\Migration;

class CreateLabsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('labs')) {
			Schema::create('labs', function($table) {
				$table->bigInteger('eid')->primary();
				$table->bigInteger('pid')->nullable();
				$table->string('encounter_provider', 255)->nullable();
				$table->timestamp('labs_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->string('labs_ua_urobili', 100)->nullable();
				$table->string('labs_ua_bilirubin', 100)->nullable();
				$table->string('labs_ua_ketones', 100)->nullable();
				$table->string('labs_ua_glucose', 100)->nullable();
				$table->string('labs_ua_protein', 100)->nullable();
				$table->string('labs_ua_nitrites', 100)->nullable();
				$table->string('labs_ua_leukocytes', 100)->nullable();
				$table->string('labs_ua_blood', 100)->nullable();
				$table->string('labs_ua_ph', 100)->nullable();
				$table->string('labs_ua_spgr', 100)->nullable();
				$table->string('labs_ua_color', 100)->nullable();
				$table->string('labs_ua_clarity', 100)->nullable();
				$table->string('labs_upt', 100)->nullable();
				$table->string('labs_strep', 100)->nullable();
				$table->string('labs_mono', 100)->nullable();
				$table->string('labs_flu', 100)->nullable();
				$table->string('labs_microscope', 100)->nullable();
				$table->string('labs_glucose', 100)->nullable();
				$table->longtext('labs_other')->nullable();
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
        Schema::drop('labs');
    }

}
