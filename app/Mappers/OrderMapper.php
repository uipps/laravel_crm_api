<?php 

namespace App\Mappers;

use Illuminate\Support\Arr;

class OrderMapper {
    // 不同类型订单的状态
    const SUBMIT_TYPE_PENDING = 0; //未提交
    const SUBMIT_TYPE_DONE = 1; //已提交
    const SUBMIT_TYPE_ARCHIEVE = 2; //归档
    const SUBMIT_TYPE_CANCLE = -1; //已取消


    const CANCLE_OPT_PENDING = 0; //取消单未处理
    const CANCLE_OPT_SUCCESS = 1; //取消单已处理
    const CANCEL_OPT_FAIL = -1;   //取消单处理失败

    const TYPE_AD = 1; //广告
    const TYPE_PRE_SALE = 2; //售前手工
    const TYPE_AFTER_SALE = 3; //售后手工

    const SECOND_TYPE_GENERAL = 1; //常规单
    const SECOND_TYPE_REPLENISH = 2; //补发单
    const SECOND_TYPE_REDELIVERY = 3; //重发单
    const SECOND_TYPE_CLUE = 4; //线索单
    const SECOND_TYPE_ABNORMAL_REDELIVERY = 5; //异常重发

    const MANUAL_AUDIT_PENDING = 0; //未审核
    const MANUAL_AUDIT_DONE = 1; //已审核
    const MANUAL_AUDIT_REJECT = -1; //已驳回

    const REPEAT_UNPROCESS = 0; //未处理
    const REPEAT_VALID = 1; //有效
    const REPEAT_INVALID = -1; //无效

    const SHIP_UNONLINE = 30;    //物流状态未上线
    const SHIP_REJECTION = 16; // 物流状态拒收
    const SHIP_DELIVERING = 8; // 物流状态配送中
    const SHIP_SIGNED = 9; // 物流状态已签收

    public static function getReplenishRedeliveryStatus()
    {
        return [
            self::SECOND_TYPE_REPLENISH => 1,
            self::SECOND_TYPE_REDELIVERY => 2,
            self::SECOND_TYPE_ABNORMAL_REDELIVERY => 3,
        ];
    }
}