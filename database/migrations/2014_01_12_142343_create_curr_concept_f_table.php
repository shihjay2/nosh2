<?php

use Illuminate\Database\Migrations\Migration;

class CreateCurrconceptfTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('curr_concept_f')) {
			Schema::create('curr_concept_f', function($table) {
				$table->string('id', 18)->index();
				$table->char('effectivetime', 8)->index();
				$table->char('active', 1)->index();
				$table->string('moduleid', 18)->index();
				$table->string('definitionstatusid', 18)->index();
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
        Schema::drop('curr_concept_f');
    }

}
