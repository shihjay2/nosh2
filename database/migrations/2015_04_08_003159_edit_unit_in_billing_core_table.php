<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class EditUnitInBillingCoreTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('billing_core', function(Blueprint $table)
		{
			$table->string('unit', 255)->nullable()->change();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('billing_core', function(Blueprint $table)
		{
			$table->string('unit', 1)->nullable()->change();
		});
	}

}
