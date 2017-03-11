<?php

use Illuminate\Database\Migrations\Migration;

class CreatePosTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('pos')) {
			Schema::create('pos', function($table) {
				$table->integer('pos_id')->primary();
				$table->string('pos_description', 255)->nullable();
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
        Schema::drop('pos');
    }

}
