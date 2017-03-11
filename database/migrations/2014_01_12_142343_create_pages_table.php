<?php

use Illuminate\Database\Migrations\Migration;

class CreatePagesTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('pages')) {
			Schema::create('pages', function($table) {
				$table->increments('pages_id');
				$table->integer('job_id')->nullable();
				$table->string('file_original', 255)->nullable();
				$table->string('file_size', 100)->nullable();
				$table->string('file', 255)->nullable();
				$table->integer('pagecount')->nullable();
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
        Schema::drop('pages');
    }

}
