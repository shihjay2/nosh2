<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeTimestampToScheduleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('schedule', function (Blueprint $table) {
             $table->timestamp('event_timestamp')->useCurrent();
        });
        $schedules = DB::table('schedule')->get();
        if ($schedules->count()) {
            foreach ($schedules as $schedule) {
                $schedule_data['event_timestamp'] = $schedule->timestamp;
                    DB::table('schedule')->where('appt_id', '=', $schedule->appt_id)->update($schedule_data);
            }
        }
        if (config('database.default') == 'mysql') {
            $date_null_arr = [
                ['alerts', 'alert_date_complete'],
                ['issues', 'issue_date_inactive'],
                ['allergies', 'allergies_date_inactive'],
                ['rx_list', 'rxl_date_inactive'],
                ['rx_list', 'rxl_date_old'],
                ['rx_list', 'rxl_date_prescribed'],
                ['sup_list', 'sup_date_inactive']
            ];
            foreach ($date_null_arr as $date_null) {
                $date_null_data = [];
                $date_null_data[$date_null[1]] = null;
                DB::table($date_null[0])->where($date_null[1], '=', '0000-00-00 00:00:00')->update($date_null_data);
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
        Schema::table('schedule', function (Blueprint $table) {
            $table->dropColumn('event_timestamp');
        });
        if (config('database.default') == 'mysql') {
            $date_null_arr = [
                ['alerts', 'alert_date_complete'],
                ['issues', 'issue_date_inactive'],
                ['allergies', 'allergies_date_inactive'],
                ['rx_list', 'rxl_date_inactive'],
                ['rx_list', 'rxl_date_old'],
                ['rx_list', 'rxl_date_prescribed'],
                ['sup_list', 'sup_date_inactive']
            ];
            foreach ($date_null_arr as $date_null) {
                $date_null_data = [];
                $date_null_data[$date_null[1]] = '0000-00-00 00:00:00';
                DB::table($date_null[0])->whereNull($date_null[1])->update($date_null_data);
            }
        }
    }
}
