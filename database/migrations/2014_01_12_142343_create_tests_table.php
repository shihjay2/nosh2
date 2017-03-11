<?php

use Illuminate\Database\Migrations\Migration;

class CreateTestsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('tests')) {
			Schema::create('tests', function($table) {
				$table->increments('tests_id');
				$table->bigInteger('pid')->nullable();
				$table->string('test_name', 255)->nullable();
				$table->dateTime('test_datetime')->nullable();
				$table->longtext('test_result')->nullable();
				$table->string('test_units', 100)->nullable();
				$table->longtext('test_reference')->nullable();
				$table->string('test_flags', 100)->nullable();
				$table->bigInteger('test_provider_id')->nullable();
				$table->longtext('test_unassigned')->nullable();
				$table->longtext('test_from')->nullable();
				$table->string('test_type', 255)->nullable();
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
        Schema::drop('tests');
    }

}
