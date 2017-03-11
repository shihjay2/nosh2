<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddOrdersPendingDateToOrdersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		if (!Schema::hasColumn('orders', 'orders_pending_date')) {
			Schema::table('orders', function(Blueprint $table) {
				$table->dateTime('orders_pending_date')->nullable();
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
		Schema::table('orders', function(Blueprint $table) {
			$table->dropColumn('orders_pending_date');
		});
	}

}
