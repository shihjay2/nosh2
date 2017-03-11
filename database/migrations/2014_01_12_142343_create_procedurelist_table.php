<?php

use Illuminate\Database\Migrations\Migration;

class CreateProcedurelistTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('procedurelist')) {
			Schema::create('procedurelist', function($table) {
				$table->increments('procedurelist_id');
				$table->bigInteger('user_id')->nullable();
				$table->string('procedure_type', 255)->nullable();
				$table->longtext('procedure_description')->nullable();
				$table->longtext('procedure_complications')->nullable();
				$table->string('cpt', 255)->nullable();
				$table->string('procedure_ebl', 100)->nullable();
				$table->integer('practice_id')->nullable();
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
        Schema::drop('procedurelist');
    }

}
