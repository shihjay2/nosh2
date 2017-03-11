<?php

use Illuminate\Database\Migrations\Migration;

class CreateAuditTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('audit')) {
			Schema::create('audit', function($table) {
				$table->increments('audit_id');
				$table->integer('user_id')->nullable();
				$table->string('displayname', 255)->nullable();
				$table->integer('group_id')->nullable();
				$table->bigInteger('pid')->nullable();
				$table->string('action', 255)->nullable();
				$table->longtext('query')->nullable();
				$table->timestamp('timestamp')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
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
        Schema::drop('audit');
    }

}
