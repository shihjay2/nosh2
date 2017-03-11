<?php

use Illuminate\Database\Migrations\Migration;

class CreateCurrattributevaluerefsetfTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('curr_attributevaluerefset_f')) {
			Schema::create('curr_attributevaluerefset_f', function($table) {
				$table->string('id', 36)->index();
				$table->char('effectivetime', 8)->index();
				$table->char('active', 1)->index();
				$table->string('moduleid', 18)->index();
				$table->string('refsetid', 18)->index();
				$table->string('referencedcomponentid', 18)->index();
				$table->string('valueid', 18)->index();
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
        Schema::drop('curr_attributevaluerefset_f');
    }

}
