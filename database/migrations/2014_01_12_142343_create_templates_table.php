<?php

use Illuminate\Database\Migrations\Migration;

class CreateTemplatesTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('templates')) {
			Schema::create('templates', function($table) {
				$table->increments('template_id');
				$table->bigInteger('user_id')->nullable();
				$table->string('default', 100)->nullable();
				$table->string('template_name', 100)->nullable();
				$table->string('age', 100)->nullable();
				$table->string('category', 100)->nullable();
				$table->string('sex', 100)->nullable();
				$table->string('group', 100)->nullable();
				$table->longtext('array')->nullable();
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
        Schema::drop('templates');
    }

}
