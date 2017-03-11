<?php

use Illuminate\Database\Migrations\Migration;

class CreateTagsrelateTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('tags_relate')) {
			Schema::create('tags_relate', function($table) {
				$table->increments('tags_relate_id');
				$table->integer('tags_id')->nullable();
				$table->integer('pid')->nullable();
				$table->bigInteger('eid')->nullable();
				$table->bigInteger('t_messages_id')->nullable();
				$table->integer('message_id')->nullable();
				$table->integer('documents_id')->nullable();
				$table->integer('hippa_id')->nullable();
				$table->integer('appt_id')->nullable();
				$table->integer('tests_id')->nullable();
				$table->integer('mtm_id')->nullable();
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
        Schema::drop('tags_relate');
    }

}
