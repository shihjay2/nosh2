<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateImageTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		if(Schema::hasTable('images')) {
			Schema::drop('images');
		}
		Schema::create('image', function(Blueprint $table)
		{
			$table->bigInteger('image_id')->primary();
			$table->bigInteger('eid')->nullable();
			$table->bigInteger('pid')->nullable();
			$table->bigInteger('id')->nullable();
			$table->string('encounter_provider', 255)->nullable();
			$table->timestamp('image_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
			$table->string('image_location', 255)->nullable();
			$table->longtext('image_description')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('image');
	}

}
