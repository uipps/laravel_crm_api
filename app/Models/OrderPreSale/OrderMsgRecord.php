<?php

namespace App\Models\OrderPreSale;

use Illuminate\Database\Eloquent\Model;

class OrderMsgRecord extends Model
{
    protected $table = 'order_msg_record';
    public $timestamps = false;

    protected $fillable = [
        'month',                                // 分区字段
        'order_no',                             // 订单号
        'msg_channel',                          // 消息通道
        'msg_topic',                            // 消息队列
        'action_type',                          // 动作类型 1接收2发送
        'msg_type',                             // 消息类别
        'msg_content',                          // 消息内容
        'result',                               // 处理结果 0无状态 200成功 其他失败
        'callback_content',                     // 回调内容
        'redo_num',                             // 重试次数
        'msg_time',                             // 消息时间
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 修改时间
    ];
}
