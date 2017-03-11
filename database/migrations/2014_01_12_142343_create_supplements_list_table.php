<?php

use Illuminate\Database\Migrations\Migration;

class CreateSupplementslistTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('supplements_list')) {
			Schema::create('supplements_list', function($table) {
				$table->increments('supplements_id');
				$table->string('supplement_name', 100)->nullable();
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
        Schema::drop('supplements_list');
    }

}
