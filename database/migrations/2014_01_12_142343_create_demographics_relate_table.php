<?php

use Illuminate\Database\Migrations\Migration;

class CreateDemographicsrelateTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('demographics_relate')) {
			Schema::create('demographics_relate', function($table) {
				$table->increments('demographics_relate_id');
				$table->integer('pid')->nullable();
				$table->integer('practice_id')->nullable();
				$table->bigInteger('id')->nullable();
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
        Schema::drop('demographics_relate');
    }

}
