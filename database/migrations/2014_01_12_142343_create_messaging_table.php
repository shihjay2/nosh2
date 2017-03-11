<?php

use Illuminate\Database\Migrations\Migration;

class CreateMessagingTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('messaging')) {
			Schema::create('messaging', function($table) {
				$table->increments('message_id');
				$table->integer('pid')->nullable();
				$table->string('message_to', 255)->nullable();
				$table->integer('message_from')->nullable();
				$table->timestamp('date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->string('cc', 255)->nullable();
				$table->string('subject', 255)->nullable();
				$table->longtext('body')->nullable();
				$table->string('patient_name', 255)->nullable();
				$table->string('status', 255)->nullable();
				$table->integer('t_messages_id')->nullable();
				$table->integer('mailbox')->nullable();
				$table->integer('practice_id')->nullable();
				$table->string('read', 4)->nullable();
				$table->integer('documents_id')->nullable();
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
        Schema::drop('messaging');
    }

}
