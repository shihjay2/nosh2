<?php

use Illuminate\Database\Migrations\Migration;

class CreateImagesTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('images')) {
			Schema::create('images', function($table) {
				$table->bigInteger('eid')->primary();
				$table->bigInteger('pid')->nullable();
				$table->string('encounter_provider', 255)->nullable();
				$table->timestamp('image_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->string('image_location', 255)->nullable();
				$table->longtext('image_description')->nullable();
			});
		}
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('images');
    }

}
