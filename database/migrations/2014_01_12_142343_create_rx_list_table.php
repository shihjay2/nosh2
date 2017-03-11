<?php

use Illuminate\Database\Migrations\Migration;

class CreateRxlistTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('rx_list')) {
			Schema::create('rx_list', function($table) {
				$table->increments('rxl_id');
				$table->bigInteger('pid')->nullable();
				$table->dateTime('rxl_date_active')->nullable();
				$table->dateTime('rxl_date_prescribed')->nullable();
				$table->string('rxl_medication', 255)->nullable();
				$table->string('rxl_dosage', 255)->nullable();
				$table->string('rxl_dosage_unit', 255)->nullable();
				$table->string('rxl_sig', 255)->nullable();
				$table->string('rxl_route', 255)->nullable();
				$table->string('rxl_frequency', 255)->nullable();
				$table->string('rxl_instructions', 255)->nullable();
				$table->string('rxl_quantity', 255)->nullable();
				$table->string('rxl_refill', 255)->nullable();
				$table->string('rxl_reason', 255)->nullable();
				$table->dateTime('rxl_date_inactive')->nullable();
				$table->dateTime('rxl_date_old')->nullable();
				$table->string('rxl_provider', 255)->nullable();
				$table->bigInteger('id')->nullable();
				$table->string('rxl_dea', 255)->nullable();
				$table->string('rxl_daw', 255)->nullable();
				$table->string('rxl_license', 255)->nullable();
				$table->integer('rxl_days')->nullable();
				$table->dateTime('rxl_due_date')->nullable();
				$table->string('rcopia_sync', 4)->nullable();
				$table->string('rxl_ndcid', 11)->nullable();
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
        Schema::drop('rx_list');
    }

}
