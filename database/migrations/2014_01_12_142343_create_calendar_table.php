<?php

use Illuminate\Database\Migrations\Migration;

class CreateCalendarTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('calendar')) {
			Schema::create('calendar', function($table) {
				$table->increments('calendar_id');
				$table->string('visit_type', 255)->nullable();
				$table->integer('duration')->nullable();
				$table->string('classname', 20)->nullable();
				$table->string('active', 4)->nullable();
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
        Schema::drop('calendar');
    }

}
