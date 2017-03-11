<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHieofoneAsUrlToDemographicsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('demographics', function(Blueprint $table)
		{
			$table->string('hieofone_as_url', 255)->nullable();
			$table->string('hieofone_as_client_id', 255)->nullable();
			$table->string('hieofone_as_client_secret', 255)->nullable();
			$table->string('hieofone_as_name', 255)->nullable();
			$table->string('hieofone_as_picture', 255)->nullable();
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
			$table->dropColumn('hieofone_as_url');
			$table->dropColumn('hieofone_as_client_id');
			$table->dropColumn('hieofone_as_client_secret');
			$table->dropColumn('hieofone_as_name');
			$table->dropColumn('hieofone_as_picture');
		});
	}

}
