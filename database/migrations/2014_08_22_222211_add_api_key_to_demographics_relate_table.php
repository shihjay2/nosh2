<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddApiKeyToDemographicsRelateTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('demographics_relate', function(Blueprint $table)
		{
			$table->string('api_key', 100)->nullable();
			$table->string('url', 100)->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('demographics_relate', function(Blueprint $table)
		{
			$table->dropColumn('api_key');
			$table->dropColumn('url');
		});
	}

}
