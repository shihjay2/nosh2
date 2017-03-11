<?php

use Illuminate\Database\Migrations\Migration;

class CreateSnomedprocedurepathTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('snomed_procedure_path')) {
			Schema::create('snomed_procedure_path', function($table) {
				$table->increments('id');
				$table->string('conceptid', 255)->nullable();
				$table->longtext('path')->nullable();
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
        Schema::drop('snomed_procedure_path');
    }

}
