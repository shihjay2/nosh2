<?php

use Illuminate\Database\Migrations\Migration;

class CreateNpiTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('npi')) {
			Schema::create('npi', function($table) {
				$table->increments('npi_id');
				$table->string('code', 255)->nullable();
				$table->longtext('type')->nullable();
				$table->longtext('classification')->nullable();
				$table->longtext('specialization')->nullable();
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
        Schema::drop('npi');
    }

}
