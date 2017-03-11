<?php

use Illuminate\Database\Migrations\Migration;

class CreateAllergiesTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('allergies')) {
			Schema::create('allergies', function($table) {
				$table->increments('allergies_id');
				$table->bigInteger('pid')->nullable();
				$table->dateTime('allergies_date_active')->nullable();
				$table->dateTime('allergies_date_inactive')->nullable();
				$table->string('allergies_med', 255)->nullable();
				$table->string('allergies_reaction', 255)->nullable();
				$table->string('allergies_provider', 255)->nullable();
				$table->string('rcopia_sync', 4)->nullable();
				$table->string('meds_ndcid', 11)->nullable();
				$table->string('allergies_severity', 255)->nullable();
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
        Schema::drop('allergies');
    }

}
