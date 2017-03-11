<?php

use Illuminate\Database\Migrations\Migration;

class CreateReceivedTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('received')) {
			Schema::create('received', function($table) {
				$table->increments('received_id');
				$table->string('fileName', 255)->nullable();
				$table->string('filePath', 255)->nullable();
				$table->string('fileFrom', 255)->nullable();
				$table->dateTime('fileDateTime')->nullable();
				$table->integer('filePages')->nullable();
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
        Schema::drop('received');
    }

}
