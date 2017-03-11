<?php

use Illuminate\Database\Migrations\Migration;

class CreateVaccineinventoryTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('vaccine_inventory')) {
			Schema::create('vaccine_inventory', function($table) {
				$table->increments('vaccine_id');
				$table->dateTime('date_purchase')->nullable();
				$table->string('imm_immunization', 255)->nullable();
				$table->string('imm_lot', 255)->nullable();
				$table->string('imm_manufacturer', 255)->nullable();
				$table->dateTime('imm_expiration')->nullable();
				$table->string('imm_brand', 255)->nullable();
				$table->string('imm_cvxcode', 255)->nullable();
				$table->string('cpt', 255)->nullable();
				$table->integer('quantity')->nullable();
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
        Schema::drop('vaccine_inventory');
    }

}
