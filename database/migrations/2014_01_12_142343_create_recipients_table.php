<?php

use Illuminate\Database\Migrations\Migration;

class CreateRecipientsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('recipients')) {
			Schema::create('recipients', function($table) {
				$table->increments('sendlist_id');
				$table->integer('job_id')->nullable();
				$table->string('faxrecipient', 255)->nullable();
				$table->string('faxnumber', 255)->nullable();
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
        Schema::drop('recipients');
    }

}
