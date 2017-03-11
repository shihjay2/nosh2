<?php

use Illuminate\Database\Migrations\Migration;

class CreateScansTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('scans')) {
			Schema::create('scans', function($table) {
				$table->increments('scans_id');
				$table->string('fileName', 255)->nullable();
				$table->string('filePath', 255)->nullable();
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
        Schema::drop('scans');
    }

}
