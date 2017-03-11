<?php

use Illuminate\Database\Migrations\Migration;

class CreateSupplementinventoryTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('supplement_inventory')) {
			Schema::create('supplement_inventory', function($table) {
				$table->increments('supplement_id');
				$table->dateTime('date_purchase')->nullable();
				$table->longtext('sup_description')->nullable();
				$table->string('sup_strength', 255)->nullable();
				$table->string('sup_manufacturer', 255)->nullable();
				$table->dateTime('sup_expiration')->nullable();
				$table->string('cpt', 255)->nullable();
				$table->string('charge', 255)->nullable();
				$table->integer('quantity')->nullable();
				$table->string('sup_lot', 255)->nullable();
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
        Schema::drop('supplement_inventory');
    }

}
