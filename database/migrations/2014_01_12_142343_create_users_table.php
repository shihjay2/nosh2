<?php

use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('users')) {
			Schema::create('users', function($table) {
				$table->increments('id');
				$table->string('username', 255)->nullable();
				$table->string('email', 255)->nullable();
				$table->string('displayname', 255)->nullable();
				$table->string('firstname', 100)->nullable();
				$table->string('lastname', 100)->nullable();
				$table->string('middle', 100)->nullable();
				$table->string('title', 100)->nullable();
				$table->string('password', 255)->nullable();
				$table->integer('group_id')->default("100")->nullable();
				$table->string('token', 255)->nullable();
				$table->string('identifier', 255)->nullable();
				$table->integer('active')->nullable();
				$table->string('secret_question', 255)->nullable();
				$table->string('secret_answer', 255)->nullable();
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
        Schema::drop('users');
    }

}
