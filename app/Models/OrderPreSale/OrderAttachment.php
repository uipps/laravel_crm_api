<?php

namespace App\Models\OrderPreSale;

use App\Libs\Utils\Func;
use Illuminate\Database\Eloquent\Model;

class OrderAttachment extends Model
{
    protected $table = 'order_attachment';
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    protected $fillable = [
        'order_id',                             // 订单id
        'type',                                 // 类别 1预付款截图
        'file_url',                             // 相对路径 文件相对路径
        'status',                               // 状态 0无效1有效-1删除
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 修改时间
    ];


    /**
     * 获取完整的图片，视频地址.
     */
    public function getFileUrlAttribute($fileKey)
    {
        return Func::remoteFileUrl($fileKey);
    }

    public function setFileUrlAttribute($url)
    {
        $this->attributes['file_url'] = Func::remoteFileKey($url);
    }

    /**
     * 动态注册事件
     */
    public static function boot(){
        parent::boot();

        // 创建中订单触发的事件
        static::creating(function ($model) {
            $model->type = 1;
            $model->status = 1;
            $model->creator_id = Auth('api')->id();
        });

        // 创建中订单触发的事件
        static::updating(function ($model) {
            $model->updator_id = Auth('api')->id();
        });
    }
}
