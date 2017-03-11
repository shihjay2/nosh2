<?php

use Illuminate\Database\Migrations\Migration;

class CreateRxTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('rx')) {
			Schema::create('rx', function($table) {
				$table->bigInteger('eid')->primary();
				$table->bigInteger('pid')->nullable();
				$table->string('encounter_provider', 255)->nullable();
				$table->timestamp('rx_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->longtext('rx_rx')->nullable();
				$table->longtext('rx_supplements')->nullable();
				$table->longtext('rx_immunizations')->nullable();
				$table->longtext('rx_orders_summary')->nullable();
				$table->longtext('rx_supplements_orders_summary')->nullable();
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
        Schema::drop('rx');
    }

}
