<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCreditcardToDemographicsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('demographics', function(Blueprint $table)
		{
			$table->string('creditcard_number', 255)->nullable();
			$table->string('creditcard_expiration', 255)->nullable();
			$table->string('creditcard_type', 255)->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('demographics', function(Blueprint $table)
		{
			$table->dropColumn('creditcard_number');
			$table->dropColumn('creditcard_expiration');
			$table->dropColumn('creditcard_type');
		});
	}

}
