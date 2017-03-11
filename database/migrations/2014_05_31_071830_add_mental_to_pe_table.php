<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMentalToPeTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('pe', function(Blueprint $table)
		{
			$table->longtext('pe_constitutional1')->nullable();
			$table->longtext('pe_mental1')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('pe', function(Blueprint $table)
		{
			$table->dropColumn('pe_constitutional1');
			$table->dropColumn('pe_mental1');
		});
	}

}
