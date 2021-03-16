<?php

namespace App\Dto;

class OrderDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $month = 0;                          // 分区字段 订单时间取yyyyMM(UTC+8)
    public $order_source = 0;                   // 订单来源,1网盟2分销3shopify => 15网盟,16shopify,17分销 4-crm
    public $order_source_name = '';
    public $order_type = 0;                     // 订单类型 1广告2售前手工3售后手工4手工取消
    public $order_type_name = '';
    public $order_second_type = 0;              // 订单二级分类,1常规单2补发单3重发单
    public $order_second_type_name = '';
    public $order_scope = 0;                    // 订单范围 1内部单2外部单(售前和售后的手工订单都属于内部订单)
    public $order_scope_name = '';
    public $order_no = '';                      // 订单号
    public $repeat_id = 0;                      // 重复单唯一标识id 对应重复订单编码id
    public $customer_id = 0;                    // 客户id
    public $customer_name = '';                 // 客户名称
    public $tel = '';                           // 电话
    public $real_tel = '';                      // 效验电话
    public $email = '';                         // 邮箱
    public $country_id = 0;                     // 国家id
    public $country_name = '';
    public $language_id = 0;                    // 语言id
    public $language_name = '';
    public $zone_prov_name = '';                // 省/州
    public $zone_city_name = '';                // 城市
    public $zone_area_name = '';                // 区域
    public $currency = '';                      // 货币单位
    public $sale_amount = 0;                    // 商品总金额
    public $collect_amount = 0;                 // 代收金额
    public $received_amount = 0;                // 预付金额
    public $discount_amount = 0;                // 优惠金额
    public $premium_amount = 0;                 // 溢价金额
    public $order_amount = 0;                   // 订单总金额
    public $history_pre_sale_id = 0;            // 曾售前id 用户表id
    public $history_pre_sale_name = '';
    public $pre_sale_id = 0;                    // 售前id 用户表id
    public $pre_sale_name = '';
    public $after_sale_id = 0;                  // 售后id 用户表id （数据表中无此字段）
    public $after_sale_name = '';
    public $after_opt_type = 0;                 // 售后处理
    public $address = '';                       // 详细地址
    public $zip_code = '';                      // 邮编
    public $customer_remark = '';               // 客户备注
    public $sms_id = 0;                         // 短信验证id
    public $sms_verify_status = 0;              // 短信验证状态 0验证中-1成功-1失败
    public $sms_verify_status_name = '';
    public $call_num = 0;                       // 呼出次数
    public $call_time = '';                     // 呼出时间 最近一次
    public $call_duration = 0;                  // 呼出时长 秒,最近一次
    public $order_time = '';                    // 订单时间
    public $order_long_time = 0;                // 订单时间戳
    public $distribute_status = 0;              // 分配状态 0未分配1已分配
    public $distribute_status_name = '';
    public $distribute_time = '';               // 分配时间 最近一次
    public $pre_opt_type = 0;                   // 售前处理结果 对应订单处理类别id,最近一次
    public $sale_remark = '';                   //   客服备注
    public $pre_opt_time = '';                  // 售前处理时间 最近一次
    public $audit_status = 0;                   // 审核状态 0未审核1已审核
    public $audit_status_name = '';
    public $audit_time = '';                    // 审核时间 最近一次
    public $order_status = 0;                   // 订单状态 对应订单状态表id
    public $shipping_status = 0;                // 物流状态 对应订单状态表id
    public $invalid_status = 0;                 // 无效状态 0有效1系统判重2审核取消3审核重复
    public $invalid_status_name = '';
    public $creator_id = 0;                     // 创建人
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 更新时间
    public $department_id = 0;                  // 部门id
    public $department_name = '';
    public $submit_type = 1;                    //   提交类型，1-提交 2-保存；数据库没有此字段，取值来自程序判断：例如，audit_status，未审核就是保存2；其他是提交1；

    public $ship_no = '';                       // 运单号
    public $ship_wherehouse = '';               // 发货仓库
    public $ship_time = '';                     // 出库时间
    public $ship_line = '';                     // 物流线路
    public $replenish_redelivery_status = 0;    // 补重状态 0无 1-已补发 2-已重发
    public $replenish_redelivery_order_no = ''; // 补重单号

    //public $order_opt_records = [];             // 订单操作记录
    public $goods_info = [];                    // 商品信息
}
