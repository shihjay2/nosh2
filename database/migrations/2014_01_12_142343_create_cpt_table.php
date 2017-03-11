<?php

use Illuminate\Database\Migrations\Migration;

class CreateCptTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('cpt')) {
			Schema::create('cpt', function($table) {
				$table->increments('cpt_id');
				$table->string('cpt', 255)->nullable();
				$table->longtext('cpt_description')->nullable();
				$table->string('cpt_charge', 255)->nullable();
				$table->boolean('cpt_common')->nullable();
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
        Schema::drop('cpt');
    }

}
