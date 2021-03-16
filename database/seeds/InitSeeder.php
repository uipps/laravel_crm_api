<?php

use Illuminate\Database\Seeder;

class InitSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // 超级管理员账户
        DB::table('user')->insert([
            'id' => 1,
            'real_name' => '超级管理员',
            'email' => 'admin@xhat.com',
            'department_id' => 0,
            'password' => bcrypt(md5('idvert888')),
            'phone' => '18301357705',
            'level' => 0,
            'status' => 1,
            'last_login_ip' => '',
            'creator_id' => 0,
            'updator_id' => 0,
            'created_time' => date('Y-m-d H:i:s'),
            'updated_time' => date('Y-m-d H:i:s'),
            'deleted_time' => date('Y-m-d H:i:s'),
        ]);

        // 角色
        DB::table('role')->insert([
            'id' => 1,
            'name' => '超级管理员角色',
            'remark' => '超管角色',
            'status' => 1,
            'auth_flag' => 1,
            'creator_id' => 0,
            'updator_id' => 0,
            'created_time' => date('Y-m-d H:i:s'),
            'updated_time' => date('Y-m-d H:i:s'),
        ]);

        DB::table('user_attr')->insert([
            'id' => 1,
            'type' => 3,
            'user_id' => 1,
            'work_id' => 1,
            'created_time' => date('Y-m-d H:i:s'),
            'creator_id' => 0,
        ]);

    }
}
