<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPracticeApiKeyToPracticeinfoTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('practiceinfo', function(Blueprint $table)
		{
			$table->string('practice_api_key', 100)->nullable();
			$table->string('practice_registration_key', 100)->nullable();
			$table->string('practice_registration_timeout', 100)->nullable()->default('');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('practiceinfo', function(Blueprint $table)
		{
			$table->dropColumn('practice_api_key');
			$table->dropColumn('practice_registration_key');
			$table->dropColumn('practice_registration_timeout');
		});
	}

}
