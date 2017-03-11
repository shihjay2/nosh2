<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPsychosocialToOtherHistoryTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('other_history', function(Blueprint $table)
		{
			$table->longtext('oh_psychosocial')->nullable();
			$table->longtext('oh_developmental')->nullable();
			$table->longtext('oh_medtrials')->nullable();
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
			$table->dropColumn('oh_psychosocial');
			$table->dropColumn('oh_developmental');
			$table->dropColumn('oh_medtrials');
		});
	}

}
