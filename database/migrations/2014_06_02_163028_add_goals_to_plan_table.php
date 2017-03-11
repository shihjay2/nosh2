<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddGoalsToPlanTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('plan', function(Blueprint $table)
		{
			$table->longtext('goals')->nullable();
			$table->longtext('tp')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('plan', function(Blueprint $table)
		{
			$table->dropColumn('goals');
			$table->dropColumn('tp');
		});
	}

}
