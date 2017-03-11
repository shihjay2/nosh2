<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixOhIdToOtherHistoryTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('other_history', function(Blueprint $table)
		{
			DB::statement('ALTER TABLE other_history CHANGE oh_id oh_id BIGINT( 20 ) NOT NULL AUTO_INCREMENT');
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
			//
		});
	}

}
