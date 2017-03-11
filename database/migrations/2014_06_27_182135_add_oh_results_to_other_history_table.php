<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOhResultsToOtherHistoryTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('other_history', function(Blueprint $table)
		{
			$table->longtext('oh_results')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('other_history', function(Blueprint $table)
		{
			$table->dropColumn('oh_results');
		});
	}

}
