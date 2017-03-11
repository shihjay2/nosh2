<?php

use Illuminate\Database\Migrations\Migration;

class CreateCptrelateTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('cpt_relate')) {
			Schema::create('cpt_relate', function($table) {
				$table->increments('cpt_relate_id');
				$table->integer('practice_id')->nullable();
				$table->string('cpt', 255)->nullable();
				$table->longtext('cpt_description')->nullable();
				$table->string('cpt_charge', 255)->nullable();
				$table->boolean('favorite')->nullable();
				$table->integer('unit')->nullable();
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
        Schema::drop('cpt_relate');
    }

}
