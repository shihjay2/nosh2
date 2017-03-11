<?php

use Illuminate\Database\Migrations\Migration;

class CreateBillingTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('billing')) {
			Schema::create('billing', function($table) {
				$table->bigInteger('bill_id')->primary();
				$table->bigInteger('eid')->nullable();
				$table->bigInteger('pid')->nullable();
				$table->integer('insurance_id_1')->nullable();
				$table->integer('insurance_id_2')->nullable();
				$table->timestamp('bill_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->string('bill_complex', 255)->nullable();
				$table->string('bill_Box11C', 29)->nullable();
				$table->string('bill_payor_id', 5)->nullable();
				$table->string('bill_ins_add1', 29)->nullable();
				$table->string('bill_ins_add2', 29)->nullable();
				$table->string('bill_Box1', 45)->nullable();
				$table->string('bill_Box1P', 45)->nullable();
				$table->string('bill_Box1A', 29)->nullable();
				$table->string('bill_Box2', 28)->nullable();
				$table->string('bill_Box3A', 10)->nullable();
				$table->string('bill_Box3B', 6)->nullable();
				$table->string('bill_Box3BP', 6)->nullable();
				$table->string('bill_Box4', 29)->nullable();
				$table->string('bill_Box5A', 28)->nullable();
				$table->string('bill_Box6', 15)->nullable();
				$table->string('bill_Box6P', 15)->nullable();
				$table->string('bill_Box7A', 29)->nullable();
				$table->string('bill_Box5B', 24)->nullable();
				$table->string('bill_Box5C', 3)->nullable();
				$table->string('bill_Box8A', 13)->nullable();
				$table->string('bill_Box8AP', 13)->nullable();
				$table->string('bill_Box7B', 23)->nullable();
				$table->string('bill_Box7C', 4)->nullable();
				$table->string('bill_Box5D', 12)->nullable();
				$table->string('bill_Box5E', 14)->nullable();
				$table->string('bill_Box8B', 13)->nullable();
				$table->string('bill_Box8BP', 13)->nullable();
				$table->string('bill_Box7D', 12)->nullable();
				$table->string('bill_Box7E', 14)->nullable();
				$table->string('bill_Box9', 28)->nullable();
				$table->string('bill_Box11', 29)->nullable();
				$table->string('bill_Box9A', 28)->nullable();
				$table->string('bill_Box10', 19)->nullable();
				$table->string('bill_Box10A', 7)->nullable();
				$table->string('bill_Box10AP', 7)->nullable();
				$table->string('bill_Box11A1', 10)->nullable();
				$table->string('bill_Box11A2', 8)->nullable();
				$table->string('bill_Box11A2P', 8)->nullable();
				$table->string('bill_Box9B1', 10)->nullable();
				$table->string('bill_Box9B2', 7)->nullable();
				$table->string('bill_Box9B2P', 7)->nullable();
				$table->string('bill_Box10B1', 7)->nullable();
				$table->string('bill_Box10B1P', 7)->nullable();
				$table->string('bill_Box10B2', 3)->nullable();
				$table->string('bill_Box11B', 29)->nullable();
				$table->string('bill_Box9C', 28)->nullable();
				$table->string('bill_Box10C', 7)->nullable();
				$table->string('bill_Box10CP', 7)->nullable();
				$table->string('bill_Box9D', 28)->nullable();
				$table->string('bill_Box11D', 6)->nullable();
				$table->string('bill_Box11DP', 6)->nullable();
				$table->string('bill_Box17', 26)->nullable();
				$table->string('bill_Box17A', 17)->nullable();
				$table->string('bill_Box21_1', 8)->nullable();
				$table->string('bill_Box21_2', 8)->nullable();
				$table->string('bill_Box21_3', 8)->nullable();
				$table->string('bill_Box21_4', 8)->nullable();
				$table->string('bill_DOS1F', 8)->nullable();
				$table->string('bill_DOS1T', 8)->nullable();
				$table->string('bill_DOS2F', 8)->nullable();
				$table->string('bill_DOS2T', 8)->nullable();
				$table->string('bill_DOS3F', 8)->nullable();
				$table->string('bill_DOS3T', 8)->nullable();
				$table->string('bill_DOS4F', 8)->nullable();
				$table->string('bill_DOS4T', 8)->nullable();
				$table->string('bill_DOS5F', 8)->nullable();
				$table->string('bill_DOS5T', 8)->nullable();
				$table->string('bill_DOS6F', 8)->nullable();
				$table->string('bill_DOS6T', 8)->nullable();
				$table->string('bill_Box24B1', 5)->nullable();
				$table->string('bill_Box24B2', 5)->nullable();
				$table->string('bill_Box24B3', 5)->nullable();
				$table->string('bill_Box24B4', 5)->nullable();
				$table->string('bill_Box24B5', 5)->nullable();
				$table->string('bill_Box24B6', 5)->nullable();
				$table->string('bill_Box24D1', 6)->nullable();
				$table->string('bill_Box24D2', 6)->nullable();
				$table->string('bill_Box24D3', 6)->nullable();
				$table->string('bill_Box24D4', 6)->nullable();
				$table->string('bill_Box24D5', 6)->nullable();
				$table->string('bill_Box24D6', 6)->nullable();
				$table->string('bill_Modifier1', 11)->nullable();
				$table->string('bill_Modifier2', 11)->nullable();
				$table->string('bill_Modifier3', 11)->nullable();
				$table->string('bill_Modifier4', 11)->nullable();
				$table->string('bill_Modifier5', 11)->nullable();
				$table->string('bill_Modifier6', 11)->nullable();
				$table->string('bill_Box24E1', 4)->nullable();
				$table->string('bill_Box24E2', 4)->nullable();
				$table->string('bill_Box24E3', 4)->nullable();
				$table->string('bill_Box24E4', 4)->nullable();
				$table->string('bill_Box24E5', 4)->nullable();
				$table->string('bill_Box24E6', 4)->nullable();
				$table->string('bill_Box24F1', 8)->nullable();
				$table->string('bill_Box24F2', 8)->nullable();
				$table->string('bill_Box24F3', 8)->nullable();
				$table->string('bill_Box24F4', 8)->nullable();
				$table->string('bill_Box24F5', 8)->nullable();
				$table->string('bill_Box24F6', 8)->nullable();
				$table->string('bill_Box24G1', 5)->nullable();
				$table->string('bill_Box24G2', 5)->nullable();
				$table->string('bill_Box24G3', 5)->nullable();
				$table->string('bill_Box24G4', 5)->nullable();
				$table->string('bill_Box24G5', 5)->nullable();
				$table->string('bill_Box24G6', 5)->nullable();
				$table->string('bill_Box24J1', 11)->nullable();
				$table->string('bill_Box24J2', 11)->nullable();
				$table->string('bill_Box24J3', 11)->nullable();
				$table->string('bill_Box24J4', 11)->nullable();
				$table->string('bill_Box24J5', 11)->nullable();
				$table->string('bill_Box24J6', 11)->nullable();
				$table->string('bill_Box25', 15)->nullable();
				$table->string('bill_Box26', 14)->nullable();
				$table->string('bill_Box27', 6)->nullable();
				$table->string('bill_Box27P', 6)->nullable();
				$table->string('bill_Box28', 9)->nullable();
				$table->string('bill_Box29', 8)->nullable();
				$table->string('bill_Box30', 8)->nullable();
				$table->string('bill_Box31', 21)->nullable();
				$table->string('bill_Box32A', 26)->nullable();
				$table->string('bill_Box32B', 26)->nullable();
				$table->string('bill_Box32C', 26)->nullable();
				$table->string('bill_Box32D', 10)->nullable();
				$table->string('bill_Box33A', 14)->nullable();
				$table->string('bill_Box33B', 29)->nullable();
				$table->string('bill_Box33C', 29)->nullable();
				$table->string('bill_Box33D', 29)->nullable();
				$table->string('bill_Box33E', 10)->nullable();
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
        Schema::drop('billing');
    }

}
