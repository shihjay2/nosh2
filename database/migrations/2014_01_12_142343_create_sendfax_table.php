<?php

use Illuminate\Database\Migrations\Migration;

class CreateSendfaxTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('sendfax')) {
			Schema::create('sendfax', function($table) {
				$table->increments('job_id');
				$table->string('user', 255)->nullable();
				$table->string('faxsubject', 255)->nullable();
				$table->string('faxcoverpage', 10)->nullable();
				$table->longtext('faxmessage')->nullable();
				$table->string('faxschedule', 10)->nullable();
				$table->date('datepicker')->nullable();
				$table->time('time')->nullable();
				$table->string('faxdraft', 10)->nullable();
				$table->date('sentdate')->nullable();
				$table->boolean('success')->nullable();
				$table->boolean('attempts')->nullable();
				$table->boolean('ready_to_send')->nullable();
				$table->longtext('command')->nullable();
				$table->dateTime('last_attempt')->nullable();
				$table->dateTime('senddate')->nullable();
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
        Schema::drop('sendfax');
    }

}
