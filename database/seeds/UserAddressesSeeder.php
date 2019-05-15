<?php

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserAddress;

class UserAddressesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::all()->each(function (User $user) {
            factory(UserAddress::class, random_int(1, 5))->create(['user_id' => $user->id]);
        });
    }
}
