<?php

use Illuminate\Database\Migrations\Migration;

class CreateGcTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		if(!Schema::hasTable('gc')) {
			Schema::create('gc', function($table) {
				$table->increments('id');
				$table->string('sex', 100)->nullable();
				$table->string('type', 100)->nullable();
				$table->string('Age', 11)->nullable();
				$table->string('Length', 11)->nullable();
				$table->string('Height', 11)->nullable();
				$table->string('unit', 11)->nullable();
				$table->string('L', 11)->nullable();
				$table->string('M', 11)->nullable();
				$table->string('S', 11)->nullable();
				$table->string('P01', 11)->nullable();
				$table->string('P1', 11)->nullable();
				$table->string('P3', 11)->nullable();
				$table->string('P5', 11)->nullable();
				$table->string('P10', 11)->nullable();
				$table->string('P15', 11)->nullable();
				$table->string('P25', 11)->nullable();
				$table->string('P50', 11)->nullable();
				$table->string('P75', 11)->nullable();
				$table->string('P85', 11)->nullable();
				$table->string('P90', 11)->nullable();
				$table->string('P95', 11)->nullable();
				$table->string('P97', 11)->nullable();
				$table->string('P99', 11)->nullable();
				$table->string('P999', 11)->nullable();
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
        Schema::drop('gc');
    }

}
