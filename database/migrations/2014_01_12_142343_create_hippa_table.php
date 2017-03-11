<?php

use Illuminate\Database\Migrations\Migration;

class CreateHippaTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('hippa')) {
			Schema::create('hippa', function($table) {
				$table->increments('hippa_id');
				$table->bigInteger('pid')->nullable();
				$table->dateTime('hippa_date_release')->nullable();
				$table->string('hippa_reason', 255)->nullable();
				$table->string('hippa_provider', 255)->nullable();
				$table->bigInteger('eid')->nullable();
				$table->integer('t_messages_id')->nullable();
				$table->integer('documents_id')->nullable();
				$table->integer('other_hippa_id')->nullable();
				$table->string('hippa_role', 100)->nullable();
				$table->integer('practice_id')->nullable();
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
        Schema::drop('hippa');
    }

}
