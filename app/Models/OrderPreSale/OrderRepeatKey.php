<?php

namespace App\Models\OrderPreSale;

use Illuminate\Database\Eloquent\Model;

class OrderRepeatKey extends Model
{
    protected $table = 'order_repeat_key';
    public $timestamps = false;

    protected $fillable = [
        'tel_part',                             // 分区字段 手机号码最后一位数字
        'repeat_key',                           // 重复单唯一标识 生成规则:对(电话+商品)进行md5取值
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
    ];
}
