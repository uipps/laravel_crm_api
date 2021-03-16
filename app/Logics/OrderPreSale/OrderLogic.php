<?php
// 依据参数不同，调用不同的服务进行处理
namespace App\Logics\OrderPreSale;

use App\Dto\ResponseDto;
use App\Libs\Utils\ErrorMsg;
use App\Logics\BaseLogic;
use App\Services\OrderPreSale\OrderAbnormalService;
use App\Services\OrderPreSale\OrderAuditService;
use App\Services\OrderPreSale\OrderCancelService;
use App\Services\OrderPreSale\OrderDistributeService;
use App\Services\OrderPreSale\OrderInvalidService;
use App\Services\OrderPreSale\OrderManualService;
use App\Services\OrderPreSale\OrderOptRecordService;
use App\Services\OrderPreSale\OrderRepeatService;
use App\Services\OrderPreSale\OrderService;

class OrderLogic extends BaseLogic
{
    //protected $orderService;
    public function __construct() {}

    // 依据action_type参数调用不同的服务
    public function updateOrderByType($action_type, $order_id) {
        switch ($action_type) {
            case 'abnormal':  // 异常单，需要添加异常备注
                return (new OrderAbnormalService())->updateOne($order_id);
                break;
            case 'audit_not':
            case 'audit': // 广告单的审核，可能要修改产品数量，添加活动等
                return (new OrderAuditService())->updateOne($order_id);
                break;
            case 'replenish':   // 补发
                return (new OrderManualService())->addReplenish($order_id);
                break;
            case 'redelivery':  // 重发
                return (new OrderManualService())->addRedelivery($order_id);
                break;
            case 'manual':    // 手工单，保存 -> 提交或继续保存
                return (new OrderManualService())->updateOne($order_id);
                break;
            case 'askforcancel':
                return (new OrderCancelService())->updateOne($order_id);
                break;
            case 'repeat':  // 重复单详情
                return (new OrderRepeatService())->updateOne($order_id);
                break;
        }

        // 暂不支持的方法
        $responseDto = new ResponseDto();
        ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UNKNOWN_ERROR);
        return $responseDto;
    }

    // 详情页统一
    public function detailByType($action_type, $order_id) {
        switch ($action_type) {
            case 'audit': // 广告单的审核，可能要修改产品数量，添加活动等
                return (new OrderAuditService())->detail($order_id);
                break;
            case 'distribute': // 订单分配详情
                return (new OrderDistributeService())->detail($order_id);
                break;
            case 'replenish':   // 补发
            case 'redelivery':  // 重发
            case 'manual':      // 手工单详情（包括补发、重发详情）
                return (new OrderManualService())->detail($order_id);
                break;
            case 'repeat':  // 重复单详情
                return (new OrderRepeatService())->detail($order_id);
                break;
            case 'invalid': // 无效单详情
                return (new OrderInvalidService())->detail($order_id);
                break;
            case 'abnormal':  // 异常单详情
                return (new OrderAbnormalService())->detail($order_id);
                break;
            case 'askforcancel':  // 取消订单申请详情
                return (new OrderCancelService())->detail($order_id);
                break;
            case 'replenish_able':  // 可选重发订单详情
            case 'redelivery_able':
            case 'askforcancel_able':
            case 'original':
                return (new OrderService())->detail($order_id);
                break;
            case 'customer':    // 某客户的订单列表
                return (new OrderService())->customerIdOrderList($order_id);
                break;
            case 'opt_record':  // 订单操作记录，返回列表
                return (new OrderOptRecordService())->getListByOrderId($order_id);
            /*default :
                return (new OrderService())->detail($order_id);
                break;*/
        }

        // 暂不支持的方法
        $responseDto = new ResponseDto();
        ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_TYPE_UNKNOWN);
        return $responseDto;
    }
}
