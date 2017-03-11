<?php

use Illuminate\Database\Migrations\Migration;

class CreateCvxTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('cvx')) {
			Schema::create('cvx', function($table) {
				$table->integer('cvx_code')->nullable();
				$table->string('description', 255)->nullable();
				$table->string('vaccine_name', 255)->nullable();
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
        Schema::drop('cvx');
    }

}
