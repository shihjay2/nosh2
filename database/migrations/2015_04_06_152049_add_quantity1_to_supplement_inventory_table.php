<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddQuantity1ToSupplementInventoryTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('supplement_inventory', function(Blueprint $table)
		{
			$table->string('quantity1', 255)->nullable();
		});
		$sup = DB::table('supplement_inventory')->get();
		if ($sup) {
			foreach ($sup as $sup_row) {
				$data['quantity1'] = $sup_row->quantity;
				DB::table('supplement_inventory')->where('supplement_id', '=', $sup_row->supplement_id)->update($data);
			}
		}
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('supplement_inventory', function(Blueprint $table)
		{
			$table->dropColumn('quantity1');
		});
	}

}
