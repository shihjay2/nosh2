<?php

use Illuminate\Database\Migrations\Migration;

class CreateCurrrelationshipfTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('curr_relationship_f')) {
			Schema::create('curr_relationship_f', function($table) {
				$table->string('id', 18)->index();
				$table->char('effectivetime', 8)->index();
				$table->char('active', 1)->index();
				$table->string('moduleid', 18)->index();
				$table->string('sourceid', 18)->index();
				$table->string('destinationid', 18)->index();
				$table->string('relationshipgroup', 18)->index();
				$table->string('typeid', 18)->index();
				$table->string('characteristictypeid', 18)->index();
				$table->string('modifierid', 18)->index();
				$table->longtext('lineage');
				$table->integer('deep');
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
        Schema::drop('curr_relationship_f');
    }

}
