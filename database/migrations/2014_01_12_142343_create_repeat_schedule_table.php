<?php

use Illuminate\Database\Migrations\Migration;

class CreateRepeatscheduleTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('repeat_schedule')) {
			Schema::create('repeat_schedule', function($table) {
				$table->increments('repeat_id');
				$table->string('repeat_day', 20)->nullable();
				$table->string('repeat_start_time', 20)->nullable();
				$table->string('repeat_end_time', 20)->nullable();
				$table->integer('repeat')->nullable();
				$table->integer('until')->nullable();
				$table->string('title', 255)->nullable();
				$table->string('reason', 255)->nullable();
				$table->bigInteger('provider_id')->nullable();
				$table->integer('start')->nullable();
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
        Schema::drop('repeat_schedule');
    }

}
