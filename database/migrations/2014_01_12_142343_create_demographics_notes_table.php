<?php

use Illuminate\Database\Migrations\Migration;

class CreateDemographicsnotesTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('demographics_notes')) {
			Schema::create('demographics_notes', function($table) {
				$table->increments('demographics_notes_id');
				$table->integer('pid')->nullable();
				$table->integer('practice_id')->nullable();
				$table->longtext('billing_notes')->nullable();
				$table->longtext('imm_notes')->nullable();
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
        Schema::drop('demographics_notes');
    }

}
