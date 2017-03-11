<?php

use Illuminate\Database\Migrations\Migration;

class CreateSuplistTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('sup_list')) {
			Schema::create('sup_list', function($table) {
				$table->increments('sup_id');
				$table->bigInteger('pid')->nullable();
				$table->dateTime('sup_date_active')->nullable();
				$table->dateTime('sup_date_prescribed')->nullable();
				$table->string('sup_supplement', 255)->nullable();
				$table->string('sup_dosage', 255)->nullable();
				$table->string('sup_dosage_unit', 255)->nullable();
				$table->string('sup_sig', 255)->nullable();
				$table->string('sup_route', 255)->nullable();
				$table->string('sup_frequency', 255)->nullable();
				$table->string('sup_instructions', 255)->nullable();
				$table->string('sup_quantity', 255)->nullable();
				$table->string('sup_reason', 255)->nullable();
				$table->dateTime('sup_date_inactive')->nullable();
				$table->dateTime('sup_date_old')->nullable();
				$table->string('sup_provider', 255)->nullable();
				$table->bigInteger('id')->nullable();
				$table->integer('supplement_id')->nullable();
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
        Schema::drop('sup_list');
    }

}
