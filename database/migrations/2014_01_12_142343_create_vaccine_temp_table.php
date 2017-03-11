<?php

use Illuminate\Database\Migrations\Migration;

class CreateVaccinetempTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('vaccine_temp')) {
			Schema::create('vaccine_temp', function($table) {
				$table->increments('temp_id');
				$table->dateTime('date')->nullable();
				$table->string('temp', 100)->nullable();
				$table->longtext('action')->nullable();
				$table->integer('practice_id')->nullable();
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
        Schema::drop('vaccine_temp');
    }

}
