<?php

use Illuminate\Database\Migrations\Migration;

class CreateTmessagesTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('t_messages')) {
			Schema::create('t_messages', function($table) {
				$table->increments('t_messages_id');
				$table->bigInteger('pid')->nullable();
				$table->string('t_messages_to', 255)->nullable();
				$table->string('t_messages_from', 255)->nullable();
				$table->string('t_messages_provider', 255)->nullable();
				$table->string('t_messages_signed', 4)->nullable();
				$table->timestamp('t_messages_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->dateTime('t_messages_dos')->nullable();
				$table->string('t_messages_subject', 255)->nullable();
				$table->longtext('t_messages_message')->nullable();
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
        Schema::drop('t_messages');
    }

}
