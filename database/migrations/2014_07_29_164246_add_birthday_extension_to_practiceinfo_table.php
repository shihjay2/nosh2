<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBirthdayExtensionToPracticeinfoTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('practiceinfo', function(Blueprint $table)
		{
			$table->string('birthday_extension', 4)->nullable()->default('n');
			$table->string('birthday_sent_date', 100)->nullable();
			$table->longtext('birthday_message')->nullable()->default('');
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
			$table->dropColumn('birthday_extension');
			$table->dropColumn('birthday_sent_date');
			$table->dropColumn('birthday_message');
		});
	}

}
