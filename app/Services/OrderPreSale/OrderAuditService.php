<?php

namespace App\Services\OrderPreSale;

use App\Dto\OrderStatsPreSaleDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\OrderAuditDto;
use App\Models\Admin\Department;
use App\Models\Admin\User;
use App\Models\OrderPreSale\OrderOptType;
use App\Repositories\Admin\PromotionsGoodsNumRuleRepository;
use App\Repositories\Admin\PromotionsGoodsRepository;
use App\Repositories\Admin\PromotionsRepository;
use App\Repositories\Customer\CustomerAddressRepository;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\OrderPreSale\OrderAuditRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\OrderPreSale\OrderInvalidRepository;
use App\Repositories\OrderPreSale\OrderOptRecordRepository;
use App\Repositories\OrderPreSale\OrderPromotionsGoodsDetailRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;
use App\Repositories\OrderPreSale\OrderRepository;
use App\Repositories\OrderPreSale\OrderDetailRepository;
use App\Dto\OrderDetailDto;
use App\Dto\OrderDto;


class OrderAuditService extends BaseService
{
    protected $theRepository;
    protected $departmentRepository;
    protected $orderRepository;
    protected $orderInvalidRepository;
    protected $customer_repo;
    protected $customer_address_repo;
    protected $orderDetailRepository; // 就是商品信息
    protected $promotionsGoodsDetailRepository; // 满减活动
    protected $promotionsGoodsNumRuleRepository;// 活动规则
    protected $promotionsGoodsRepository;// 活动规则
    protected $promotionsRepository;            // 活动

    public function __construct() {
        $this->theRepository = new OrderAuditRepository();
        $this->orderRepository = new OrderRepository();
        $this->orderInvalidRepository = new OrderInvalidRepository();
        $this->orderDetailRepository = new OrderDetailRepository();
        $this->userRepository = new UserRepository(); // 用于权限检查
        $this->promotionsGoodsDetailRepository = new OrderPromotionsGoodsDetailRepository();    // 满减活动
        $this->promotionsGoodsNumRuleRepository = new PromotionsGoodsNumRuleRepository();       // 活动规则
        //$this->promotionsGoodsRepository = new PromotionsGoodsRepository();                     // 部分商品活动
        $this->promotionsRepository = new PromotionsRepository();                               // 活动详情
    }

    public function getList($request=null) {
        if (!$request) $request = request()->all(); // 参数接收
        $responseDto = new ResponseDto();

        // 参数校验数组
        $rules = [
            'page' => 'sometimes|integer',
            'limit' => 'sometimes|integer',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        // 订单状态的限制，这里只显示售前订单
        $request['job_type'] = Department::JOB_TYPE_PRE_SALE;

        // 主管能看到本部门的全部列表；员工只能看到自己的
        $login_user_info = self::getCurrentLoginUserInfo();
        // 如果是管理员，能看到所有
        $child_dept_list = [];
        if (1 != $login_user_info['role_id']) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $child_dept_list = parent::getChildrenDepartmentByDeptId($login_user_info['department_id']);
                if (!isset($request['department_id'])) $request['department_id'] = ['in', $child_dept_list];
            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的
                if (!isset($request['audit_user_id'])) $request['audit_user_id'] = $login_user_info['id'];
            }
        }

        return $this->formatListByRequest($request, $login_user_info, $child_dept_list);
    }

    private function formatListByRequest($request, $login_user_info, $child_dept_list) {
        $responseDto = new ResponseDto();

        // 获取数据，包含总数字段
        $list = $this->theRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 所有的 order_id 列表
            $order_ids = [];
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $v_detail) {
                $order_ids[] = $v_detail['order_id'];
            }
            // 通过 order_id，获取对应的订单详情数据
            $order_info_list = $this->orderRepository->getOrderInfosByOrderIds($order_ids); // 批量获取
            // 通过 order_id，获取订单对应的商品详情数据
            $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds($order_ids); // 三维数组

            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $list[$responseDto::DTO_FIELD_LIST][$key] = self::getOneOrderInfo($v_detail, $order_info_list, $goods_info_list);
            }
        }
        $data_list = new DataListDto();
        if (!isset($request[$responseDto::WITHOUT_ORDER_STATS]) || !$request[$responseDto::WITHOUT_ORDER_STATS]) {
            // 默认情况，所有订单列表都要带上订单统计数据；如果设置不需要携带统计数据则跳过
            $redis_stats_data = $this->orderRepository->getOrderNumStatsByUserId($login_user_info['id'], $login_user_info, $child_dept_list);
            $order_stat = new OrderStatsPreSaleDto();
            $order_stat->Assign($redis_stats_data); // 从redis缓存获取数据
            $data_list->meta[$responseDto::DTO_FIELD_ORDER_STATS] = $order_stat;
        }
        $data_list->Assign($list);
        $responseDto->data = $data_list;

        return $responseDto;
    }

    public function addOrUpdate($request = null) {
        if (!$request) $request = request()->all();
        $responseDto = new ResponseDto();

        $curr_datetime = date('Y-m-d H:i:s');
        $login_user_info = self::getCurrentLoginUserInfo();
        $current_uid = $login_user_info['id'];
        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        //$field_id = 'order_id';
        $rules = [
            'order_id' => 'sometimes|integer|min:1',
            'submit_type' => 'required|integer|min:1',
            'pre_opt_type' => 'required|integer|min:10',
        ];
        if (1 == $request['submit_type']) {
            //$rules['pre_sale_id'] = 'required|integer|min:1'; // 审单，售前客服已经分配（自动或手动），
            //$rules['language_id'] = 'required|integer|min:1';
            $rules['country_id'] = 'required|integer|min:1'; // 国家id
            $rules['zone_prov_name'] = 'required|string';
            $rules['zone_city_name'] = 'required|string';
            $rules['zone_area_name'] = 'required|string';
            $rules['goods_info'] = 'required';
            $rules['customer_name'] = 'required|string';
            $rules['tel'] = 'required';
        }
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        // 依据不同的提交类型，修改状态值
        if (1 == $request['submit_type']) {
            // 提交, status=1 ，需要产生客户信息，还有客户地址信息
            //$request['status'] = 1;
            // 依据审核意见，得出审核状态是已审核还是已驳回
            if ($request['pre_opt_type'] <= 10 || $request['pre_opt_type'] >= 20) {
                $request['audit_status'] = 0;
            } else if ($request['pre_opt_type'] >= 11 && $request['pre_opt_type'] <= 14) {
                // 电话确认，审核通过
                $request['audit_status'] = 1;
                $request['audit_time'] = $curr_datetime;
                $request['status'] = 1;    // 有效
            } else if ($request['pre_opt_type'] >= 15 && $request['pre_opt_type'] <= 19) {
                // 审核无效
                $request['audit_status'] = 1;
                $request['audit_time'] = $curr_datetime;
                $request['invalid_status'] = \getInvalidStatusByOptType($request['pre_opt_type']);
                $request['status'] = 0;    // 无效
            }
            //
        } else if (2 == $request['submit_type']) {
            // 保存, status=0，未提交
            //$request['status'] = 0; // 字段被占用
            $request['audit_status'] = 0;
        } else {
            // 无取消
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UNSUPPORTED_TYPE);
            return $responseDto;
        }
        //$request['sale_remark'] = $request['sale_remark'] ?? '';
        $request['pre_opt_time'] = $curr_datetime;


        $data_arr = $request;
        if (isset($request['order_id']) && $request['order_id']) {
            $data_arr['updator_id'] = $current_uid;
        } else {
            $data_arr['creator_id'] = $current_uid;
            $data_arr['updator_id'] = $data_arr['creator_id'];
            $data_arr['created_time'] = $curr_datetime;
        }
        // 数据增加几个默认值
        $data_arr['updated_time'] = $curr_datetime;

        if (isset($request['order_id']) && $request['order_id']) {
            // 审核订单
            $data_arr['order_id'] = $request['order_id'];
            // 检查该记录是否存在
            $v_detail = $this->theRepository->getInfoById($request['order_id']);
            if (!$v_detail) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_DATA_NOT_EXISTS);
                return $responseDto;
            }
            $v_order_detail = $this->orderRepository->getInfoById($request['order_id']);


            // 检查订单审核状态
            if (1 == $v_detail['audit_status']) {
                // 已经审核过都就不能再审核
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_HAS_BEING_AUDITED);
                return $responseDto;
            }

            if (1 == $request['submit_type'] && in_array($request['pre_opt_type'], OrderOptType::AUDIT_PASS_ID_LIST)) {
                // 如果是提交审核通过，需要验证满减活动是否符合规则（除非数据库活动发生变化，否则不提示前端，订单要入库但是返回成功）
                //     @2020.4.29除非活动规则发生变化，否则，不提示错误信息，直接提交
                $checkPromotion = $this->checkPromotionChange($request['goods_info']);
                if (2 == $checkPromotion) {
                    ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_PROMOTION_GOODS_INFO_NO_RULES);
                    return $responseDto;
                } else if (3 == $checkPromotion) {
                    ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_PROMOTION_RULE_KEY_INVALID);
                    return $responseDto;
                } else if (4 == $checkPromotion) {
                    ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_PROMOTION_GOODS_NUM_LESS_MIN);
                    return $responseDto;
                }
                // 规则没有发生变化，活动也没有删除或停用，则不返回错误，直接入库，不符合规则的不参与活动，并要删除rule_ids字段内容
            }

            // 更新数据，分为提交和继续保存
            $rlt = $this->updateOrderAndAuditData($data_arr, $request, $v_order_detail, $v_detail, $curr_datetime, $login_user_info);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else {
            $v_id = 0; // $this->theRepository->insertGetId($data_arr);
            if (!$v_id) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::INSERT_DB_FAILED);
                return $responseDto;
            }
            // 暂不返回详情，前端跳列表页
            $responseDto->data = ['id'=>$v_id];
        }
        return $responseDto;
    }

    public function detail($id) {
        $request['order_id'] = $id;
        $responseDto = new ResponseDto();

        // uid参数校验; 当前登录用户是否有权限暂不验证，后面统一处理
        $field_id = 'order_id';
        $rules = [
            $field_id => 'required|integer|min:1'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        $v_detail = $this->theRepository->getInfoById($request[$field_id]);
        if (!$v_detail) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        // 通过 order_id，获取对应的订单详情数据
        $order_info_list = $this->orderRepository->getOrderInfosByOrderIds([$id]); // 批量获取
        // 通过 order_id，获取订单对应的商品详情数据
        $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds([$id]); // 三维数组

        // 成功，返回信息
        $v_info = self::getOneOrderInfo($v_detail, $order_info_list, $goods_info_list);
        $responseDto->data = $v_info;

        return $responseDto;
    }

    public function delete($id) {
        // 进行软删除，更新状态即可
        $request['status'] = '-1';
        $request['order_id'] = $id;
        return self::addOrUpdate($request);
    }

    // 更新单条, 广告单的审核，可能要修改产品数量，添加活动等，审核意见等
    public function updateOne($id) {
        $request = request()->all();
        $request['order_id'] = $id;

        return self::addOrUpdate($request);
    }

    // 未审核订单列表
    public function auditNotList() {
        $request = request()->all();
        $request['job_type'] = 1;               // order_audit 表 岗位类别 1售前2售后
        //$request['status'] = 1;                 // order_audit 表 状态 0无效 1有效
        $request['audit_status'] = 0;           // order/order_audit 两表共有 审核状态 0未审核1已审核-1已驳回
        $request['order_type'] = 1;             // order表, 订单类型 1广告2售前手工3售后手工
        return self::getList($request);
    }

    // 已审核订单列表
    public function auditedList() {
        $request = request()->all();
        $request['job_type'] = 1;               // order_audit 表 岗位类别 1售前2售后
        //$request['status'] = 1;                 // order_audit 表 状态 0无效 1有效
        $request['audit_status'] = 1;           // order/order_audit 两表共有 审核状态 0未审核1已审核-1已驳回
        $request['order_type'] = 1;             // order表, 订单类型 1广告2售前手工3售后手工
        return self::getList($request);
    }

    private function getOneOrderInfo($v_detail, $order_info_list, $goods_info_list) {
        $order_id = $v_detail['order_id'];
        // 字段映射，数据表中只有audit_status字段
        if (isset($v_detail['audit_status']) && 0 == $v_detail['audit_status']) {
            $v_detail['submit_type'] = 2;
        }

        // DTO合并方法一，验证可行
        $v_info = array_merge((array)(new OrderAuditDto()), (array)(new OrderDto()));
        if (isset($v_info['audit_user_id'])) {
            // 售前客服字段订单表中有
            unset($v_info['audit_user_id']);
            // $v_info['job_type'] = 1; // 售前
        }
        $v_info = \array_assign($v_info, $v_detail); // 赋值

        // 订单详情赋值
        if (isset($order_info_list[$order_id]))
            $v_info = \array_assign($v_info, $this->addAllAttrName2Data($order_info_list[$order_id]));

        // 商品详情赋值
        if (isset($goods_info_list[$order_id])) {
            foreach ($goods_info_list[$order_id] as $goods_val) {
                $goods_dto = new OrderDetailDto();
                $goods_dto->Assign($goods_val);
                $v_info['goods_info'][] = $goods_dto; // 可能有多条商品信息，放到字段 goods_info 上
            }
        }
        return $v_info;
    }

    //  @2020.4.29除非活动规则发生变化，否则，不提示错误信息，直接提交，不满足规则的删除规则按照原价计算
    public function checkPromotionChange($goods_info_list) {
        if (!$goods_info_list)
            return 0;

        // 全部规则（携带活动设置信息的）
        $rules_arr = $this->promotionsGoodsNumRuleRepository->getAllPromotionsRulesWithPromotions();

        // 汇总当前参与的活动规则列表
        $old_rule_list = [];
        $old_rule_info_list = [];
        foreach ($goods_info_list as $l_info) {
            if (!isset($l_info['rules']) || !$l_info['rules'] || !isset($l_info['rule_ids']) || !$l_info['rule_ids']) {
                continue;
            }
            $l_info['rules'] = array_column($l_info['rules'], null, 'rule_id');

            foreach ($l_info['rule_ids'] as $l_rule_id) {
                if (!isset($l_info['rules'][$l_rule_id])) {
                    return 2; // 提交的数据缺少rules对应的旧规则详情, 则直接返回错误
                }
                if (!in_array($l_rule_id, $old_rule_list)) {
                    $old_rule_list[] = $l_rule_id;
                }
                if (!isset($old_rule_info_list[$l_rule_id])) {
                    $old_rule_info_list[$l_rule_id] = $l_info['rules'][$l_rule_id];
                }
            }
        }
        if (!$old_rule_list || !$old_rule_info_list) return 0;

        // 检查活动规则是否禁用或规则发生变化，规则变化，则返回错误提示
        foreach ($old_rule_info_list as $l_rule_info) {
            if (!isset($rules_arr[$l_rule_info['rule_id']]) || 1 != $rules_arr[$l_rule_info['rule_id']]['status']) {
                return 3;   // 停用或删除状态的活动；
            }
            // 逐个规则校验： 最少商品数、折扣、是否可叠加、规则范围、商品范围，任何一项有变化，则认为活动有变更
            $db_rule_info = $rules_arr[$l_rule_info['rule_id']];
            if ($l_rule_info['min_num'] != $db_rule_info['min_num'] ||
                $l_rule_info['discount'] != $db_rule_info['discount'] ||
                $l_rule_info['rule_attr'] != $db_rule_info['rule_attr'] ||
                $l_rule_info['rule_scope'] != $db_rule_info['rule_scope'] ||
                $l_rule_info['goods_scope'] != $db_rule_info['goods_scope'] ) {
                return 4;   // 活动规则有变化
            }
        }
        return 0;   // 正常返回0，有错误返回非0
    }

    // 检查活动规则是否有缺少，不能叠加的活动不能组合出现，并删除分组，放到无分组中
    public function groupPromotionGoods(&$goods_info_list, $rules_arr, $promotion_goods_all) {
        if (!$goods_info_list)
            return [];

        $group_rule_ids_arr = [];   // key逗号分隔的，如果是多个叠加活动
        $no_group_arr = [];         // 未分组商品
        foreach ($goods_info_list as &$goods_info) {
            if (!isset($goods_info['rule_ids']) || !$goods_info['rule_ids']) {
                $no_group_arr[] = $goods_info; // 未分组商品
                continue;
            }

            // 检查商品范围是否符合规则, 主要是针对活动的商品范围为部分商品的情况
            if (!self::checkPromotionGoodsScopeValid($goods_info, $promotion_goods_all)) {
                $goods_info['rule_ids'] = [];   // 直接清空分组，并放到未分组商品中计算总价
                $no_group_arr[] = $goods_info; // 放到未分组商品
                continue;
            }

            // 单规则分组
            if (1 == count($goods_info['rule_ids'])) {
                $group_rule_ids_arr[$goods_info['rule_ids'][0]][] = $goods_info;
            } else {
                // 叠加规则的情况，最多只能有一个不可叠加活动存在
                if (!self::checkDieJiaPromotionValid($goods_info['rule_ids'], $rules_arr)) {
                    // 出现多个不可叠加的活动分组，直接删除整个分组
                    $goods_info['rule_ids'] = [];   // 直接清空分组，并放到未分组商品中计算总价
                    $no_group_arr[] = $goods_info;
                    continue;
                }

                sort($goods_info['rule_ids']);  // 排序
                $rule_str = implode(',', $goods_info['rule_ids']);
                $group_rule_ids_arr[$rule_str][] = $goods_info;
            }
        }


        return [$no_group_arr, $group_rule_ids_arr];
    }

    // 校验分组中的商品范围是否符合规则：检查各个分组内的商品数量是否符合规则的最小商品数要求
    public function checkGroupValid(&$goods_info_list, &$group_rule_ids_arr, &$no_group_arr, $rules_arr, $promotion_goods_all) {
        if (!$group_rule_ids_arr) return 1;

        $goods_info_list = array_column($goods_info_list, null, 'sku');

        // rule_scope, 规则范围 1所有商品 2单个商品
        foreach ($group_rule_ids_arr as $rule_str => $goods_list) {
            // 单规则分组还是叠加规则分组？
            if (false === strpos($rule_str, ',')) {
                // 检查规则的范围是针对单个商品数量满足还是针对全部多个商品的数量满足

                $rule_info = $rules_arr[$rule_str];
                if (2 == $rule_info['rule_scope']) {
                    // 每个单品的商品数量必须满足条件，否则该单品的 rule_ids 要删除，并且放到无分组中
                    foreach ($goods_list as $row) {
                        if ($row['num'] < $rule_info['min_num']) {
                            $no_group_arr[] = $row;
                            // 删除 $goods_info_list 中 sku相同的
                            $goods_info_list[$row['sku']]['rule_ids'] = [];
                        }
                    }
                } else {
                    // 全部商品数量要满足
                    $num_goods = 0;
                    foreach ($goods_list as $row) {
                        $num_goods += $row['num'];
                    }
                    if ($num_goods < $rule_info['min_num']) {
                        foreach ($goods_list as $row) {
                            $no_group_arr[] = $row;
                            // 删除 $goods_info_list 中 sku相同的
                            $goods_info_list[$row['sku']]['rule_ids'] = [];
                        }
                    }
                }
            } else {
                // 叠加规则, 有多个规则id，分别对每个规则进行处理：
                $rule_list = explode(',', $rule_str);
                foreach ($rule_list as $rule_id) {
                    $rule_info = $rules_arr[$rule_id];
                    if (2 == $rule_info['rule_scope']) {
                        // 每个单品的商品数量必须满足条件，否则该单品的 rule_ids 要删除，并且放到无分组中
                        foreach ($goods_list as $row) {
                            if ($row['num'] < $rule_info['min_num']) {
                                $no_group_arr[] = $row;
                                // 删除 $goods_info_list 中 sku相同的
                                if (isset($goods_info_list[$row['sku']])) $goods_info_list[$row['sku']]['rule_ids'] = [];
                            }
                        }
                    } else {
                        // 全部商品数量要满足
                        $num_goods = 0;
                        foreach ($goods_list as $row) {
                            $num_goods += $row['num'];
                        }
                        if ($num_goods < $rule_info['min_num']) {
                            foreach ($goods_list as $row) {
                                $no_group_arr[] = $row;
                                // 删除 $goods_info_list 中 sku相同的
                                if (isset($goods_info_list[$row['sku']])) $goods_info_list[$row['sku']]['rule_ids'] = [];
                            }
                        }
                    }
                }
            }
        }

        return 1;
    }

    // 检查商品分组情况，活动规则是否有缺少，不能叠加的活动不能组合出现，删除 rule_ids 字段内容，不符合规则的不参与优惠，但数据直接入库，不用提示前端！
    public function calculationSaleAmount(&$request) {
        $sale_amount = 0;
        if (!$request || !isset($request['goods_info']) || !$request['goods_info'])
            return $sale_amount;

        // 活动的商品范围，如果是部分商品，需要判断活动商品是否一致
        if (!$this->promotionsGoodsRepository) $this->promotionsGoodsRepository = new PromotionsGoodsRepository();
        $promotion_goods_all = $this->promotionsGoodsRepository->getAllPromotionsGoodsSku();  // TODO 数据量大的话，只获取指定活动id的商品

        // 全部规则（携带活动设置信息的）
        $rules_arr = $this->promotionsGoodsNumRuleRepository->getAllPromotionsRulesWithPromotions();

        // 找出属于同一组活动的商品，一种商品只能属于一个活动分组，重新组织一下数据
        $group_info = self::groupPromotionGoods($request['goods_info'], $rules_arr, $promotion_goods_all);
        $no_group_arr = $group_info ? $group_info[0] : [];         // 未分组商品
        $group_rule_ids_arr = $group_info ? $group_info[1] : [];   // key逗号分隔的，如果是多个叠加活动

        // 分组好之后，还要检查各个分组内的商品数量是否符合规则的最小商品数要求，不符合要求，不能享受优惠，直接删除分组放到未分组中
        self::checkGroupValid($request['goods_info'], $group_rule_ids_arr, $no_group_arr, $rules_arr, $promotion_goods_all);

        // 使用活动优惠，还有叠加活动，计算总价格
        foreach ($request['goods_info'] as $goods_info) {
            // 没有活动的，直接计算
            if (!isset($goods_info['rule_ids']) || !$goods_info['rule_ids']) {
                $sale_amount = bcadd($sale_amount, bcmul($goods_info['unit_price'], $goods_info['num']));
            } else {
                if (1 == count($goods_info['rule_ids'])) {
                    // 单活动， discount
                    $rule_info = $rules_arr[$goods_info['rule_ids'][0]];

                    $orig_amount = bcmul($goods_info['unit_price'], $goods_info['num']);
                    $discount_amount = bcmul($orig_amount, bcdiv($rule_info['discount'], 10)); // 9折，返回的discount是9.0
                    $sale_amount = bcadd($sale_amount, $discount_amount);
                } else {
                    $orig_amount = bcmul($goods_info['unit_price'], $goods_info['num']);

                    // 叠加活动, 享受折上折
                    foreach ($goods_info['rule_ids'] as $rule_id) {
                        $rule_info = $rules_arr[$rule_id];
                        $discount_amount = bcmul($orig_amount, bcdiv($rule_info['discount'], 10)); // 9折，返回的discount是9.0
                    }
                    $sale_amount = bcadd($sale_amount, $discount_amount);
                }
            }
        }

        /*echo __LINE__ . " goods_info_list: \n";
        print_r($request['goods_info']);  echo __LINE__ . " promotion_goods_all: \n";
        print_r($promotion_goods_all);  echo __LINE__ . " rules_arr: \n";
        print_r($rules_arr); echo __LINE__ . " group_rule_ids_arr: \n";
        print_r($group_rule_ids_arr);echo __LINE__ . " no_group_arr: \n";
        print_r($no_group_arr);
        exit;
        $request['goods_info'] = $group_rule_ids_arr;*/

        return $sale_amount;
    }

    // 检查叠加活动是否有效
    public function checkDieJiaPromotionValid($rule_ids, $rules_arr) {
        $bu_diejia_num = 0;

        foreach ($rule_ids as $rule_id) {
            if (2 == $rules_arr[$rule_id]['rule_attr'])
                $bu_diejia_num++;
        }

        if ($bu_diejia_num >= 2)
            return false;
        return true;
    }

    // 检查商品范围活动是否有效 0-无效 1-有效
    public function checkPromotionGoodsScopeValid($goods_info, $promotion_goods_all) {
        if (!isset($goods_info['rule_ids']) || !isset($goods_info['rules']) || !$goods_info['rule_ids'] || !$goods_info['rules'])
            return 1;

        // 活动的商品范围
        foreach ($goods_info['rules'] as $l_item) {
            // 如果商品范围是部分商品，需要验证提交的sku跟活动设置的商品sku是否相符
            if (2 == $l_item['goods_scope']) {
                if (!isset($promotion_goods_all[$l_item['promotion_id']]) || !$promotion_goods_all[$l_item['promotion_id']]) {
                    return 0;   // 活动范围无效
                }

                $sku_list = array_column($promotion_goods_all[$l_item['promotion_id']], 'sku');
                if (!in_array($goods_info['sku'], $sku_list)) {
                    return 0;   // 活动范围无效
                }
            }
        }
        return 1;
    }

    // 更新audit表和订单总表，需要进行事务处理
    public function updateOrderAndAuditData($data_arr, $request, $db_order_detail, $db_audit_order_detail, $create_time='', $curr_user_info=[]) {
        if (2 == $request['submit_type']) {
            // 保存的情况，只需要维护 order, order_detail, order_audit 3张表，
            //    还有活动表：order_promotions_group, order_promotions_goods_detail
            return $this->updateSaveAuditOrder($data_arr, $request, $create_time, $curr_user_info);
        } else if (1 == $request['submit_type']) {
            // 提交的情况，再走一遍类似手工下单的逻辑，可能需要创建新客户地址
            return $this->updateSubmitAuditOrder($data_arr, $request, $db_order_detail, $db_audit_order_detail, $create_time, $curr_user_info);
        }
        return false;
    }

    // 保存（非提交）订单数据，继续保存，只需要维护 order, order_detail, order_audit，活动等几张表
    public function updateSaveAuditOrder($data_arr, $request, $create_time='', $curr_user_info=0) {
        $order_id = $request['order_id'];

        \DB::beginTransaction();
        try {
            // 保存的情况，还没提交，审核状态还是未审核；
            $save_data_arr = $data_arr;
            if (isset($save_data_arr['order_id'])) unset($save_data_arr['order_id']);
            if (isset($save_data_arr['order_no'])) unset($save_data_arr['order_no']);

            // order_audit表相关字段
            //$save_data_arr['audit_status'] = 0; // 审核状态，还是未审核，不变
            $save_data_arr['audit_result_id'] = $data_arr['pre_opt_type'] ?? 0; // 审核结果ID
            // order表，涉及到地址信息的更新
            if (isset($data_arr['remark']) && $data_arr['remark'])
                $save_data_arr['sale_remark'] = $data_arr['remark'];

            $this->orderRepository->updateData($order_id, $save_data_arr, $create_time, $curr_user_info['id']);
            $this->theRepository->updateData($order_id, $save_data_arr);
            $this->orderDetailRepository->updateMultiGoodsByOrderId($order_id, $data_arr); // 更新商品信息
            // 更新活动数据，保存，先不管活动信息；原样保存，不做判断
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, order, order_audit error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }

        // 记录操作日志
        $data_arr['opt_type_id'] = 41;    // 审单保存
        $data_arr['optator_id'] = $curr_user_info['id'];    // 操作人
        (new OrderOptRecordRepository())->insertOrderOptRecord($order_id, $data_arr);

        return true;
    }

    // 提交的情况，再走一遍创建订单的逻辑，审核通过，可能需要创建新客户地址
    public function updateSubmitAuditOrder($data_arr, $request, $db_order_detail, $db_audit_order_detail, $create_time='', $curr_user_info=[]) {
        $order_id = $request['order_id'];

        // 提交，并且审核结果为通过，可能需要生成客户信息
        if (1 == $request['submit_type'] && in_array($request['pre_opt_type'], OrderOptType::AUDIT_PASS_ID_LIST)) {
            // 插入客户之前，先检查一下客户是否存在，以及地址库是否存在相同的记录，检查的步骤不要放到事务中
            if (!$this->customer_repo) $this->customer_repo = new CustomerRepository();
            if (!$this->customer_address_repo) $this->customer_address_repo = new CustomerAddressRepository();
            // 这里先查地址库，再查客户表，因为手机号可能已经存在于地址库，是某用户的多个手机号中的一个，因此采用反向查找方式
            $customer_info = $this->customer_repo->getInfoByCountryIdAndTel($data_arr['country_id'], $data_arr['tel']);
            $customer_address_info = $this->customer_address_repo->getInfoByCountryIdAndTel($data_arr['country_id'], $data_arr['tel']);
            if ($customer_address_info) {
                // 搜到了对应客户ID信息，还需要进一步确认，该收货信息是否完全匹配，因为客户姓名、详细地址哪怕加一个空格都算新地址
                if (!isset($data_arr['customer_id'])) $data_arr['customer_id'] = $customer_address_info['customer_id'];
                $customer_address_info = $this->customer_address_repo->getInfoByMultiFields($data_arr); // 完成的信息请求
            }
        }

        // 提交的情况，审核状态依赖审核结果：
        $save_data_arr = $data_arr;
        $save_data_arr['audit_status'] = 1;                 // 审核状态 0未审核1已审核-1已驳回，提交了就不能修改，所以都应该是已审核
        $save_data_arr['status'] = 1;   // 待跟进，暂定有效, 只有无效的设置未无效
        if (in_array($request['pre_opt_type'], OrderOptType::AUDIT_PASS_ID_LIST)) {
            // 审核通过的情况

            // 1. order_audit表相关字段
            //unset($save_data_arr['audit_user_id']);    // 待审核人ID , 一堆字段不能更新
            if (isset($data_arr['department_id'])) unset($save_data_arr['department_id']);
            if (isset($data_arr['order_id'])) unset($save_data_arr['order_id']);
            if (isset($data_arr['order_no'])) unset($save_data_arr['order_no']);
            //$save_data_arr['job_type'] = 1;                   // 1-售前 2-售后
            $save_data_arr['status'] = 1;                       // 0-无效 1-有效

            $save_data_arr['audit_result_id'] = $data_arr['pre_opt_type']; // 审核结果ID

            // 2. order表，涉及到地址信息的更新
            if (isset($data_arr['remark']) && $data_arr['remark'])
                $save_data_arr['sale_remark'] = $data_arr['remark'];
            if ($customer_address_info) $save_data_arr['customer_id'] = $customer_address_info['customer_id'];
            // 计算商品价格：TODO
            if (!isset($save_data_arr['collect_amount'])) $save_data_arr['collect_amount'] = $db_order_detail['collect_amount'];    // 代收金额
            if (!isset($save_data_arr['received_amount'])) $save_data_arr['received_amount'] = $db_order_detail['received_amount']; // 预付金额
            if (!isset($save_data_arr['discount_amount'])) $save_data_arr['discount_amount'] = $db_order_detail['discount_amount']; // 优惠金额
            if (!isset($save_data_arr['premium_amount'])) $save_data_arr['premium_amount'] = $db_order_detail['premium_amount'];     // 溢价金额

            // 商品分组情况，只要规则没有发生变化，活动也没有删除或停用，则不返回错误，直接入库，不符合规则的不参与活动，并要删除rule_ids字段内容

            // sale_amount 商品总金额，通过商品价格*num计算得出；叠加优惠活动
            $sale_amount = 0;
            if (isset($request['goods_info']) && $request['goods_info']) {
                // 活动优惠折扣计算方法，按照分组进行计算金额
                $sale_amount = $this->calculationSaleAmount($request);
            }

            $request['sale_amount'] = $sale_amount;
            $save_data_arr['sale_amount'] = $request['sale_amount'];  // 商品总金额
            // 其他金额在上面已经赋初值
            // 订单总金额为 = 商品总金额 + 溢价金额 - 优惠金额 - 预付金额
            $request['order_amount'] = $request['sale_amount'] + $save_data_arr['premium_amount'] - $save_data_arr['discount_amount'] - $save_data_arr['received_amount'];
            $save_data_arr['order_amount'] = $request['order_amount'];
            //$save_data_arr['audit_status'] = 1; // 审核状态 0未审核1已审核，手工下单直接审核通过
            $save_data_arr['audit_time'] = $create_time;
            $save_data_arr['order_status'] = 22; // 已经审核
            $save_data_arr['shipping_status'] = 0; // 0没有物流信息 物流状态未上线 30
            $save_data_arr['invalid_status'] = 0; // 无效状态 0有效1系统判重2审核取消3审核重复
            $save_data_arr['department_id'] = $curr_user_info['department_id'];
            // 超级管理员没有部门，则暂时用售前客服的部门id，上线后应该不会出现这个情况
            if (0 == $curr_user_info['department_id']) {
                $pre_sale_info = $this->userRepository->getUserById($request['pre_sale_id']);
                $save_data_arr['department_id'] = $pre_sale_info['department_id'];
            }

            // 3. order_detail商品表，拼装商品分组情况
            /*
            // 获取全部分组 type=1满减活动全部分组
            $group_list = $this->promotionsGroupRepository->getAllPromotionsGroup(1);
            // "0"非分组商品, "1"是分组商品
            $add_groups = [];                                       // 需要新创建的分组数据
            if ($group_rule_ids_arr[1]) {
                // 有分组，则需要插入 order_promotions_ 活动相关表
                foreach ($group_rule_ids_arr[1] as $rule_str => $item) {
                    if (!isset($group_list[$rule_str])) {
                        $add_groups[] = $rule_str;
                    }
                }
            }*/


        } else if (in_array($request['pre_opt_type'], OrderOptType::AUDIT_INVALID_TYPE_ID_LIST)) {
            // 审核为无效单的情况，需要插入到无效单中

            // 1. order_audit表相关字段
            //unset($save_data_arr['audit_user_id']);    // 待审核人ID , 一堆字段不能更新
            if (isset($data_arr['department_id'])) unset($save_data_arr['department_id']);
            if (isset($data_arr['order_id'])) unset($save_data_arr['order_id']);
            if (isset($data_arr['order_no'])) unset($save_data_arr['order_no']);
            //$save_data_arr['job_type'] = 1;                   // 1-售前 2-售后
            $save_data_arr['status'] = 0;                       // 0-无效 1-有效

            $save_data_arr['audit_result_id'] = $data_arr['pre_opt_type']; // 审核结果ID

            // 2. order表，涉及到地址信息的更新
            if (isset($data_arr['remark']) && $data_arr['remark'])
                $save_data_arr['sale_remark'] = $data_arr['remark'];
            $save_data_arr['audit_time'] = $create_time;        // 审核时间
            $save_data_arr['invalid_status'] = \getInvalidStatusByOptType($request['pre_opt_type']);
            $save_data_arr['order_status'] = 29;                // 无效

            // 3. 插入到无效单数据表
            $this->orderInvalidRepository->insertInvalidOne($request['pre_opt_type'], $curr_user_info['id'], $db_order_detail);

        } else {
            // 有待跟进的订单，可以，审核状态依然是未审核，跟保存差不多的逻辑
            if (isset($save_data_arr['order_id'])) unset($save_data_arr['order_id']);
            if (isset($save_data_arr['order_no'])) unset($save_data_arr['order_no']);


            // order_audit表相关字段
            //$save_data_arr['audit_status'] = 1; // 提交了就都是已审核
            $save_data_arr['audit_result_id'] = $data_arr['pre_opt_type'] ?? 0; // 审核结果ID
            // order表，涉及到地址信息的更新
            if (isset($data_arr['remark']) && $data_arr['remark'])
                $save_data_arr['sale_remark'] = $data_arr['remark'];
        }


        \DB::beginTransaction();
        try {
            // 如果审核通过、并且是新客户
            if (1 == $request['submit_type'] && in_array($request['pre_opt_type'], OrderOptType::AUDIT_PASS_ID_LIST) && !$customer_address_info) {
                // 提交, status=1 ，需要产生客户信息和客户地址信息
                if (!$customer_info) {
                    $customer_id = $this->customer_repo->insertGetId($this->customer_repo->formatDataForInsertByManualOrder($data_arr));
                    $customer_info = ['id' => $customer_id];
                }
                // 客户的ID
                $save_data_arr['customer_id'] = $customer_info['id'];
                $this->customer_address_repo->insertGetId($this->customer_address_repo->formatDataForInsertByManualOrder($customer_info['id'], $data_arr));
                $this->customer_repo->updateData($customer_info['id'], $this->customer_repo->formatDataForUpdateByManualOrder($data_arr, $create_time, $curr_user_info['id']));
            }

            // 继续上面代码处理，涉及入库操作
            if (in_array($request['pre_opt_type'], OrderOptType::AUDIT_PASS_ID_LIST)) {
                // 需要插入分组数据
                /*if ($add_groups) {
                    $this->promotionsGroupRepository->insertMultiByRuleStrs($add_groups, $create_time, $creator_id);
                    //usleep(200);
                    //$group_list = $this->promotionsGroupRepository->getAllPromotionsGroup(1);   // 去掉缓存后，重新获取数据
                }*/
                // 插入订单商品表，活动明细表
                //$this->orderDetailRepository->updateMultiGoodsByOrderId($order_id, $data_arr); // 更新商品信息
                // TODO 优惠活动明细也记录一下
            }

            $this->orderRepository->updateData($order_id, $data_arr, $create_time, $curr_user_info['id']);
            $this->theRepository->updateData($order_id, $data_arr);
            $this->orderDetailRepository->updateMultiGoodsByOrderId($order_id, $data_arr); // 更新商品信息

            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, order, order_audit error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }

        // 删除统计缓存
        $this->orderRepository->deleteCacheByUidAndType($curr_user_info['id'], 'audit');

        // 记录操作日志
        $data_arr['opt_type_id'] = $request['pre_opt_type'];    // 审单类型
        $data_arr['optator_id'] = $curr_user_info['id'];        // 操作人
        (new OrderOptRecordRepository())->insertOrderOptRecord($order_id, $data_arr);

        return true;
    }

}
