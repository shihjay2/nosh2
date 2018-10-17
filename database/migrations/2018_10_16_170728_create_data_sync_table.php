<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDataSyncTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('data_sync', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('pid')->nullable();
            $table->longtext('action')->nullable();
            $table->string('from', 255)->nullable();
            $table->bigInteger('source_id')->nullable();
            $table->string('source_index')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('data_sync');
    }
}
