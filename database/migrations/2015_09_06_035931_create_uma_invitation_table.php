<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUmaInvitationTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('uma_invitation', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('email', 255)->nullable();
			$table->string('invitation_timeout', 255)->nullable();
			$table->string('resource_set_ids', 255)->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('uma_invitation');
	}

}
