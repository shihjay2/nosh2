<?php

use Illuminate\Database\Migrations\Migration;

class CreateSnomedprocedureimagingTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('snomed_procedure_imaging')) {
			Schema::create('snomed_procedure_imaging', function($table) {
				$table->increments('id');
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
        Schema::drop('snomed_procedure_imaging');
    }

}
