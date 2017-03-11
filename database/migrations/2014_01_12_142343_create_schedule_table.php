<?php

use Illuminate\Database\Migrations\Migration;

class CreateScheduleTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('schedule')) {
			Schema::create('schedule', function($table) {
				$table->increments('appt_id');
				$table->integer('pid')->nullable();
				$table->integer('start')->nullable();
				$table->integer('end')->nullable();
				$table->string('title', 255)->nullable();
				$table->string('visit_type', 255)->nullable();
				$table->string('reason', 255)->nullable();
				$table->string('status', 100)->nullable();
				$table->bigInteger('provider_id')->nullable();
				$table->bigInteger('user_id')->nullable();
				$table->timestamp('timestamp');
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
        Schema::drop('schedule');
    }

}
