<?php

use Illuminate\Database\Migrations\Migration;

class CreateLangTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('lang')) {
			Schema::create('lang', function($table) {
				$table->increments('lang_id');
				$table->string('code', 255)->nullable();
				$table->longtext('description')->nullable();
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
        Schema::drop('lang');
    }

}
