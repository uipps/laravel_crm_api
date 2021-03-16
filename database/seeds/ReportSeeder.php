<?php

use App\Models\Admin\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $faker = \Faker\Factory::create();
        $countryIdArr = [81, 97, 99];
        $count = 60;

        $userCollects = User::query()->with('manager')->where('level', 2)->get();

        foreach($countryIdArr as $countryId){
            for($i=1; $i<$count; $i++){
                $user = $userCollects->random();

                $timestamp = (time() - 24*60*60*($count-$i));

                $finished = $faker->numberBetween(30, 100);
                $unfinished = $faker->numberBetween(0, 30);

                if($user->manager){
                    DB::table('user_order_report')->insert([
                        'date' => date('Ymd', $timestamp),
                        'user_id' => $user->id,
                        'manager_id' => $user->manager->id,
                        'department_id' => $user->department_id,
                        'country_id' => $countryId,
                        'user_type' => $faker->randomElement([1,2]),
                        'order_total_num' => $finished + $unfinished,
                        'order_finished_num' => $finished,
                        'order_unfinished_num' => $unfinished,
                        'order_received_num' => $faker->numberBetween(0, $finished),
                        'order_upsales_num' => $faker->numberBetween(0, 30),
                        'order_refused_num' => $faker->numberBetween(0, 10),
                        'order_unreceived_num' => $faker->numberBetween(0, 30),
                        'order_received_money' => $faker->randomFloat(2, 100, 1000),
                        'created_time' => $faker->dateTime(),
                        'creator_id' => $faker->numberBetween(0, 30),
                        'updated_time' => $faker->dateTime(),
                        'updator_id' => $faker->numberBetween(0, 30),
                    ]);
    
                    DB::table('user_customer_report')->insert([
                        'date' => date('Ymd', $timestamp),
                        'user_id' => $user->id,
                        'manager_id' => $user->manager->id,
                        'department_id' => $user->department_id,
                        'country_id' => $countryId,
                        'user_type' => $faker->randomElement([1,2]),
                        'customer_level' => $faker->randomElement([0, 1, 2, 3, 4]),
                        'customer_num' => $faker->numberBetween(0, 30),
                        'created_time' => $faker->dateTime(),
                        'creator_id' => $faker->numberBetween(0, 30),
                        'updated_time' => $faker->dateTime(),
                        'updator_id' => $faker->numberBetween(0, 30),
                    ]);  
                }
                 
            }
        }
        
        
    }
}
