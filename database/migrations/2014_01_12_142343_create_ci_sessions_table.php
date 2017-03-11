<?php

use Illuminate\Database\Migrations\Migration;

class CreateCisessionsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('ci_sessions')) {
			Schema::create('ci_sessions', function($table) {
				$table->string('session_id', 40)->primary();
				$table->string('ip_address', 16)->nullable();
				$table->string('user_agent', 50)->nullable();
				$table->integer('last_activity')->nullable();
				$table->text('user_data')->nullable();
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
        Schema::drop('ci_sessions');
    }

}
