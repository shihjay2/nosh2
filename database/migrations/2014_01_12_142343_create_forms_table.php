<?php

use Illuminate\Database\Migrations\Migration;

class CreateFormsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('forms')) {
			Schema::create('forms', function($table) {
				$table->increments('forms_id');
				$table->integer('pid')->nullable();
				$table->integer('template_id')->nullable();
				$table->timestamp('forms_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->string('forms_title', 255)->nullable();
				$table->longtext('forms_content')->nullable();
				$table->string('forms_destination', 255)->nullable();
				$table->longtext('forms_content_text')->nullable();
				$table->longtext('array')->nullable();
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
        Schema::drop('forms');
    }

}
