<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddAoeCodeAndAoeFieldToOrderslist1Table extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		if (!Schema::hasColumn('orderslist1', 'aoe_code')) {
			Schema::table('orderslist1', function(Blueprint $table) {
				$table->string('aoe_code', 255)->nullable();
				$table->string('aoe_field', 255)->nullable();
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
		Schema::table('orderslist1', function(Blueprint $table) {
			$table->dropColumn('aoe_code');
			$table->dropColumn('aoe_field');
		});
	}

}
