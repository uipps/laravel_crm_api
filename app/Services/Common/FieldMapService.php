<?php

namespace App\Services\Common;

use App\Dto\ResponseDto;
use App\Repositories\OrderPreSale\OrderOptTypeRepository;
use App\Repositories\OrderPreSale\OrderStatusRepository;
use App\Services\BaseService;


class FieldMapService extends BaseService
{
    protected $orderStatusRepository;
    protected $orderOptTypeRepository;

    public function __construct() {}

    public function fieldsMap(){
        $responseDto = new ResponseDto();

        // 依据语言返回
        $lang_field = 'name'; // 默认中文，英文没人维护，需要依据请求中的语言设置，显示对应语言
        if (!in_array(app()->getLocale(), ['zh_CN', 'zh-CN'])) {
            $lang_field = 'en_name';
        }

        // 按照数据表、字段含义，拼装信息
        // TODO 多语言支持的话，只做了几个示例，其他有待产品找人翻译
        $v_info = [
            'sys_department' => [
                'status'   => ['name'=>trans('msg.department_status'), 'enum'=>[0=>trans('msg.status_0'), 1=>trans('msg.status_1')]],  // 状态
                'job_type' => ['name'=>trans('msg.department_type_name'), 'enum'=>[1=>trans('msg.job_type_presale'), 2=>trans('msg.job_type_aftersale')]],     // 部门类型
                'distribute_type' => ['name'=>__('msg.distribute_type'), 'enum'=>[0=>__('msg.manual'), 1=>__('msg.automatic')]],     // 订单分配方式
            ],
            'user' => [
                'status'   => ['name'=>trans('msg.status_name'), 'enum'=>[0=>trans('msg.status_0'), 1=>trans('msg.status_1')]],  // 状态
                'level_type' => ['name'=>__('msg.level_type'), 'enum'=>[1=>__('msg.Supervisor'), 2=>__('msg.Staff')]],
            ],
            'order' => [
                'status'   => ['name'=>__('msg.status_name'), 'enum'=>[0=>__('msg.无效'), 1=>__('msg.有效')]],  // 状态
                'status_manual'   => ['name'=>__('msg.手工单状态'), 'enum'=>[0=>__('msg.未提交'), 1=>__('msg.已提交'), -1=>__('msg.已取消')]],
                'status_abnormal'   => ['name'=>'异常状态', 'enum'=>[0=>__('msg.未处理'), 1=>__('msg.已处理')]],
                'status_repeat'   => ['name'=>'重复单状态', 'enum'=>[0=>__('msg.未处理'), 1=>__('msg.有效'), -1=>__('msg.无效')]],
                'status_cancel'   => ['name'=>'取消申请', 'enum'=>[0=>__('msg.未提交'),1=>__('msg.已提交'),2=>__('msg.已归档')]],
                'opt_result' => ['name'=>'处理结果', 'enum'=>[0=>__('msg.未处理'),1=>__('msg.成功'),-1=>__('msg.失败')]],  // 取消订单申请，处理结果
                'invalid_type'   => ['name'=>'无效原因', 'enum'=>[0=>__('msg.有效'),1=>__('msg.系统判重'),2=>__('msg.审核取消'),3=>__('msg.审核重复')]],

                'order_source' => ['name'=>'订单来源', 'enum'=>[4=>'CRM', 15=>__('msg.网盟'), 16=>'Shopify', 17=>__('msg.分销')]],
                'order_type' => ['name'=>'订单类型', 'enum'=>[1=>__('msg.广告'), 2=>__('msg.售前手工'), 3=>__('msg.售后手工')]],
                'order_second_type' => ['name'=>'订单二级分类', 'enum'=>[1=>__('msg.常规订单'), 2=>__('msg.补发订单'), 3=>__('msg.重发订单'), 4=>__('msg.线索订单'), 5=>__('msg.异常重发订单')]],
                'order_scope' => ['name'=>'订单范围', 'enum'=>[1=>__('msg.内部单'), 2=>__('msg.外部单')]],
                'sms_verify_status' => ['name'=>'短信验证状态', 'enum'=>[0=>__('msg.验证中'), 1=>__('msg.成功'), -1=>__('msg.失败')]],
                'distribute_status' => ['name'=>'分配状态', 'enum'=>[0=>__('msg.未分配'), 1=>__('msg.已分配')]],
                'audit_status' => ['name'=>'审核状态', 'enum'=>[0=>__('msg.待审核'), 1=>__('msg.已审核'), -1=>__('msg.已驳回')]],
                'invalid_status' => ['name'=>'无效状态', 'enum'=>[0=>__('msg.有效'), 1=>__('msg.系统判重'), 2=>__('msg.审核取消'), 3=>__('msg.审核重复')]],
                'order_status' => ['name'=>'订单状态', 'enum'=>[]], // 订单状态的取值，从数据库获取 1订单状态2物流状态
                'shipping_status' => ['name'=>'物流状态', 'enum'=>[]], // 同上
                'submit_type' => ['name'=>'提交类型', 'enum'=>[1=>__('msg.提交'), 2=>__('msg.保存')]], // 表单提交类型
                'source_type' => ['name'=>'客户来源', 'enum'=>[1=>__('msg.广告'), 2=>__('msg.咨询'), 3=>__('msg.复购')]],
                'quality_level' => ['name'=>'客户质量', 'enum'=>[0=>'-', 1=>'A', 2=>'B', 3=>'C', 4=>'D']],

                'call_status' => ['name'=>'呼出情况', 'enum'=>[1=>__('msg.已呼出'), 2=>__('msg.未呼出')]],
                'pre_opt_type' => ['name'=>'售前处理', 'enum'=>[]],
                'report_pre_opt_type' => ['name'=>'售前处理', 'enum'=>[]],
                'replenish_redelivery_status' => ['name'=>'已补发重发状态', 'enum'=>[
                    0 => __('msg.none'),1 => __('msg.has_replenish'),2 => __('msg.has_redelivery'),3 => __('msg.has_abnormal_redelivery'),
                ]],
            ],
            'customer_clue' => [
                'quality_level' => ['name'=>'线索质量', 'enum'=>[1=>'A', 2=>'B', 3=>'C', 4=>'D']],
                'advisory_type' => ['name'=>'咨询类型', 'enum'=>[1=>'Diet', 2=>'ED/Muscle', 3=>'Skin', 4=>'Other']],
                'opt_status' => ['name'=>'处理状态', 'enum'=>[0=>__('msg.未处理'), 1=>__('msg.已处理')]],
                'distribute_status' => ['name'=>'分配状态', 'enum'=>[0=>__('msg.未分配'), 1=>__('msg.已分配')]],
                'finish_status' => ['name'=>'归档状态', 'enum'=>[0=>__('msg.未成交'), 1=>__('msg.已成交')]],
                'social_account_type' => ['name'=>'社媒账号类型', 'enum'=>['facebook_id'=>'Facebook ID', 'whatsapp_id'=>'Whatsapp ID', 'line_id'=>'Line ID']],
            ],
            'customer_label' => [
                'status'   => ['name'=>trans('msg.status_name'), 'enum'=>[0=>trans('msg.status_0'), 1=>trans('msg.status_1')]],  // 状态 0无效1有效
                'label_type' => ['name'=>'标签类别', 'enum'=>[1=>__('msg.新客户'), 2=>__('msg.复购客户'), 3=>__('msg.高签收客户'), 4=>__('msg.高拒收客户'), 5=>__('msg.客户等级')]],// 标签类别 1新客户,2复购客户,3高签收客户,4高拒收客户,5客户等级
                'label_style' => ['name'=>'标签形式', 'enum'=>[1=>__('msg.单值标签'), 2=>__('msg.多值标签')]],// 标签形式 1单值标签,2多值标签
            ],
            'goods_info' => [
                'status'   => ['name'=>trans('msg.status_name'), 'enum'=>[0=>trans('msg.status_0'), 1=>trans('msg.status_1')]],  // 状态 0停用1启用
            ],
            'promotion' => [
                'status'   => ['name'=>trans('msg.status_name'), 'enum'=>[0=>trans('msg.status_0'), 1=>trans('msg.status_1')]],  // 状态 0停用1启用
                'type'        => ['name'=>'活动类型', 'enum'=>[1=>__('msg.满数量折扣')]],              // 活动类型 1-满数量折扣
                'rule_attr'   => ['name'=>'规则类别', 'enum'=>[1=>__('msg.可叠加'),  2=>__('msg.不叠加')]],    // 规则类别 1可叠加2不叠加
                'rule_scope'  => ['name'=>'规则范围', 'enum'=>[1=>__('msg.所有商品'), 2=>__('msg.单个商品')]],  // 规则范围 1所有商品2单个商品
                'goods_scope' => ['name'=>'商品范围', 'enum'=>[1=>__('msg.全部商品'), 2=>__('msg.部分商品')]],  // 商品范围 1全部商品2部分商品

            ],
        ];
        $v_info['order']['job_type'] = $v_info['sys_department']['job_type']; // 岗位类别 1售前2售后

        // 订单状态，从数据库获取订单状态列表；包含物流状态：shipping_status的取值
        if (!$this->orderStatusRepository) $this->orderStatusRepository = new OrderStatusRepository();
        if (!$this->orderOptTypeRepository) $this->orderOptTypeRepository = new OrderOptTypeRepository();
        $order_status_list = $this->orderStatusRepository->getAllOrderStatus(1);
        $ship_status_list = $this->orderStatusRepository->getAllOrderStatus(2);
        $v_info['order']['order_status']['enum'] = array_column($order_status_list, $lang_field, 'id');
        $v_info['order']['shipping_status']['enum'] = array_column($ship_status_list, $lang_field, 'id');

        // 售前处理状态 从数据库
        $opt_type_list = $this->orderOptTypeRepository->getPreOptTypeList();
        if ($opt_type_list)
            $v_info['order']['pre_opt_type']['enum'] = array_column($opt_type_list, $lang_field, 'id');

        $reportTypeList = $this->orderOptTypeRepository->getReportPreOptTypeList();
        if($reportTypeList)
            $v_info['order']['report_pre_opt_type']['enum'] = array_column($reportTypeList, $lang_field, 'id');

        $responseDto->data = $v_info;

        return $responseDto;
    }
}
