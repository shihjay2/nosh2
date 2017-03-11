<?php
use Illuminate\Database\Seeder;

class OAuthUsersSeeder extends Seeder {

    public function run() {
        DB::table('oauth_users')->insert(array(
            'username' => "mikey",
            'password' => sha1('lalo37'),
            'first_name' => "Michael",
            'last_name' => "Chen",
        ));
    }
}
