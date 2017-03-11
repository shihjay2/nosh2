<?php

use Illuminate\Database\Migrations\Migration;

class CreateDocumentsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('documents')) {
			Schema::create('documents', function($table) {
				$table->increments('documents_id');
				$table->bigInteger('pid')->nullable();
				$table->string('documents_type', 255)->nullable();
				$table->string('documents_url', 255)->nullable();
				$table->string('documents_desc', 255)->nullable();
				$table->string('documents_from', 255)->nullable();
				$table->string('documents_viewed', 20)->nullable();
				$table->dateTime('documents_date')->nullable();
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
        Schema::drop('documents');
    }

}
