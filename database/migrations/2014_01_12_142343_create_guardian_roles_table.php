<?php

use Illuminate\Database\Migrations\Migration;

class CreateGuardianrolesTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('guardian_roles')) {
			Schema::create('guardian_roles', function($table) {
				$table->increments('guardian_roles_id');
				$table->string('code', 255)->nullable();
				$table->longtext('description')->nullable();
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
        Schema::drop('guardian_roles');
    }

}
