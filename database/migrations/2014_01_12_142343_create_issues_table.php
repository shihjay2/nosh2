<?php

use Illuminate\Database\Migrations\Migration;

class CreateIssuesTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('issues')) {
			Schema::create('issues', function($table) {
				$table->increments('issue_id');
				$table->bigInteger('pid')->nullable();
				$table->string('issue', 255)->nullable();
				$table->dateTime('issue_date_active')->nullable();
				$table->dateTime('issue_date_inactive')->nullable();
				$table->string('issue_provider', 255)->nullable();
				$table->string('rcopia_sync', 4)->nullable();
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
        Schema::drop('issues');
    }

}
