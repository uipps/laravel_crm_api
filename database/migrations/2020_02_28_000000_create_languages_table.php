<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLanguagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 主机
        /*Schema::create('sys_language', function (Blueprint $table) {
            $table->increments('id')->comment('自增ID');
            $table->string('name', 50)->comment('主机卷标');
            $table->string('cn_name', 200)->comment('主机名称');
            $table->string('en_name', 200)->unique()->comment('主机的操作系统');
            $table->string('simple_en_name', 15)->comment('主机的ip');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态, 1-使用、0-停用等');

            $table->dateTime('created_time')->default('0000-01-01 00:00:00')->comment('创建时间');
            $table->timestamp('updated_time')->useCurrent()->comment('修改时间'); // Laravel 5.1.25 以后，可以使用 useCurrent()
            $table->engine = 'InnoDB';
        });
        DB::statement("ALTER TABLE `sys_language` CHANGE `updated_time` `updated_time` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '修改时间'");
        DB::statement("ALTER TABLE `sys_language` comment '语言'");*/
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sys_language');
    }
}
