<?php

use Illuminate\Database\Migrations\Migration;

class CreateIcd9Table extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('icd9')) {
			Schema::create('icd9', function($table) {
				$table->increments('icd9_id');
				$table->string('icd9', 255)->nullable();
				$table->string('icd9_description', 255)->nullable();
				$table->boolean('icd9_common')->nullable();
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
        Schema::drop('icd9');
    }

}
