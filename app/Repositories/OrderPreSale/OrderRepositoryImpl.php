<?php

namespace App\Repositories\OrderPreSale;

use App\Models\OrderPreSale\Order;
use App\Models\OrderPreSale\OrderAbnormal;
use App\Models\OrderPreSale\OrderAudit;
use App\Models\OrderPreSale\OrderCancel;
use App\Models\OrderPreSale\OrderDistribute;
use App\Models\OrderPreSale\OrderInvalid;
use App\Models\OrderPreSale\OrderManual;
use App\Models\OrderPreSale\OrderRepeat;
use App\Repositories\BaseRepository;

class OrderRepositoryImpl extends BaseRepository
{
    protected $model;
    protected $orderAuditModel;
    protected $orderDistributeModel;
    protected $orderManualModel;
    protected $orderRepeatModel;
    protected $orderInvalidModel;
    protected $orderAbnormalModel;
    protected $orderCancelModel;

    public function __construct() {
        $this->model = new Order();
    }

    public function getList($params, $field = ['*']) {
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? $params['limit'] : parent::PAGE_SIZE;
        //if ($limit > parent::PAGE_SIZE_MAX) $limit = parent::PAGE_SIZE; // 是否限制最大数量

        $order_table = $this->model->getTable();
        if (isset($params['job_type']) && 2 == $params['job_type']) {
            // 售后需要连表查询
            if (!$this->orderManualModel) $this->orderManualModel = new OrderManual();
            $the_table = $this->orderManualModel->getTable();
            $builder = $this->orderManualModel::leftJoin($order_table, $the_table . '.order_id', '=', $order_table . '.id')->joinOrderDepartment();
        } else
            $builder = $this->model;

        // 客户名称，模糊查询
        if (isset($_REQUEST['customer_name']) && $_REQUEST['customer_name'])
            $params['customer_name'] = ['like', '%'. ($_REQUEST['customer_name']) . '%'];

        // 订单报表查询拆分 ：user_id =0 ，department_id=0都不参与搜索
        if (isset($params['department_id']) && is_numeric($params['department_id']) && 0 == $params['department_id'])
            unset($params['department_id']);
        if (isset($params['user_id']) && is_numeric($params['user_id']) && $params['user_id'] > 0) {
            $params['pre_sale_id'] = $params['user_id'];
            unset($params['user_id']);
        }
        if (isset($params['call_status']) && 2 == $params['call_status']) {
            // 已呼出
            $params['call_num'] = 0;
        } else if (isset($params['call_status']) && 1 == $params['call_status']) {
            // 未呼出
            $params['call_num'] = ['>', 0];
        }
        if (isset($params['order_time_start']) && isset($params['order_time_end'])) {
            $builder = $builder->whereBetween($order_table.'.order_time', [getDateTimeByTime($params['order_time_start']), getDateTimeByTime($params['order_time_end'])]);
        }
        if (isset($params['pre_opt_time_start']) && isset($params['pre_opt_time_end'])) {
            $builder = $builder->whereBetween($order_table.'.pre_opt_time', [getDateTimeByTime($params['pre_opt_time_start']), getDateTimeByTime($params['pre_opt_time_end'])]);
        }
        if (isset($params['audit_time_start']) && isset($params['audit_time_end'])) {
            $builder = $builder->whereBetween($order_table.'.audit_time', [getDateTimeByTime($params['audit_time_start']), getDateTimeByTime($params['audit_time_end'])]);
        }
        if (isset($params['distribute_time_start']) && isset($params['distribute_time_end'])) {
            $builder = $builder->whereBetween($order_table.'.distribute_time', [getDateTimeByTime($params['distribute_time_start']), getDateTimeByTime($params['distribute_time_end'])]);
        }

        // 自动获取表字段，并自动拼装查询条件
        $params = array_filter($params, function($v){return $v !== '';}); // 空字符不参与搜索条件
        if (isset($params['job_type']) && 2 == $params['job_type']) {
            // 售后部门，只需要查询售后手工单，需要进行连表查询
            $tbl_order_fields = parent::getTheTableFields($this->model);
            $tbl_fields_arr = parent::getTheTableFields($this->orderManualModel);
            if ($tbl_fields_arr) {
                foreach ($params as $l_field => $val) {
                    if (in_array($l_field, $tbl_fields_arr)) {
                        // 针对不同的数据类型，自动拼装
                        $builder = parent::joinTableBuild($builder, $val, $l_field, $the_table);
                    } else if (in_array($l_field, $tbl_order_fields)) {
                        $builder = parent::joinTableBuild($builder, $val, $l_field, $order_table);
                    }
                }
            }
            $builder = $builder->orderBy($the_table . '.id', 'DESC');
        } else {
            $tbl_fields_arr = parent::getTheTableFields();
            if ($tbl_fields_arr) {
                foreach ($params as $l_field => $val) {
                    if (in_array($l_field, $tbl_fields_arr)) {
                        // 针对不同的数据类型，自动拼装
                        $builder = parent::joinTableBuild($builder, $val, $l_field);
                    }
                }
            }
            $builder = $builder->orderBy('id', 'DESC');
        }
        return $this->pager($builder, $page, $limit, $field);
    }

    // 通过id获取信息
    public function getInfoById($id) {
        $db_result = $this->model->find($id);
        if (!$db_result)
            return $db_result;
        return $db_result->toArray();
    }

    // 新增并返回主键ID
    public function insertGetId($data_arr) {
        $insertId = $this->model->create($data_arr);
        return $insertId->id;
    }

    public function updateData($id, $data_arr) {
        $sql_where = [
            'id' => $id,
        ];
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        if (isset($data_arr['id'])) unset($data_arr['id']);

        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }

    // 通过 order_id 列表，获取订单信息
    public function getOrderInfosByOrderIds($order_ids) {
        $db_result = $this->model->whereIn('id', $order_ids)->get()->toArray();
        if (!$db_result)
            return $db_result;
        return array_column($db_result, null, 'id');
    }

    // 通过条件更新数据
    public function updateMultiByCondition($sql_where, $data_arr) {
        $builder = $this->model;
        foreach ($sql_where as $l_field => $val) {
            // 针对不同的数据类型，自动拼装
            $builder = parent::joinTableBuild($builder, $val, $l_field);
        }

        // 逐条更新，才能使用事件监听
        $l_list = $builder->get()->toArray();
        if (!$l_list)
            return 1;
        $num = 0;
        foreach ($l_list as $v_info) {
            $rlt = $this->model->find($v_info['id'])->update($data_arr);
            if ($rlt) $num++;
        }
        return $num;
    }

    public function getCommonBuild($builder, $sql_where, $the_table_model, $order_table_model) {
        $tbl_order_fields = parent::getTheTableFields($order_table_model);
        $tbl_fields_arr = parent::getTheTableFields($the_table_model);
        $the_table = $the_table_model->getTable();
        $order_table = $order_table_model->getTable();

        // 自动获取表字段，并自动拼装查询条件
        $sql_where = array_filter($sql_where, function($v){return $v !== '';}); // 空字符不参与搜索条件
        foreach ($sql_where as $l_field => $val) {
            if (in_array($l_field, $tbl_fields_arr)) {
                // 针对不同的数据类型，自动拼装
                $builder = parent::joinTableBuild($builder, $val, $l_field, $the_table);
            } else if (in_array($l_field, $tbl_order_fields)) {
                $builder = parent::joinTableBuild($builder, $val, $l_field, $order_table);
            }
        }
        return $builder;
    }

    // 某主管或员工能看到的订单相关统计数据
    //   level 岗位 1管理员,2员工

    // 获取"广告单"订单总数
    public function sqlCountOrderTotalAds($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $sql_where['department_id'] = ['in', $child_dept_list];
            } else {
                // 员工只能看到自己的
                $sql_where['pre_sale_id'] = $login_user_info['id'];
            }
        }
        $sql_where['order_type'] = 1;                   // order表, 订单类型 1广告2售前手工3售后手工；只显示广告单，不显示手工单（包括售前、售后）
        $sql_where['order_second_type'] = 1;            // 这里的列表，只显示常规单，不显示补发和重发
        $sql_where['order_status'] = ['>', 0];          // 订单状态，必须给一个值 1以上的值

        $builder = $this->model;
        foreach ($sql_where as $l_field => $val) {
            $builder = parent::joinTableBuild($builder, $val, $l_field);
        }
        return $builder->count();
    }

    // 获取"未审核"订单数
    public function sqlCountAuditNo($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $sql_where['department_id'] = ['in', $child_dept_list];
            } else {
                // 员工只能看到自己的
                $sql_where['audit_user_id'] = $login_user_info['id'];
            }
        }

        $sql_where['job_type'] = 1;               // order_audit 表 岗位类别 1售前2售后
        $sql_where['audit_status'] = 0;           // order/order_audit 两表共有 审核状态 0未审核1已审核-1已驳回
        $sql_where['order_type'] = 1;             // order表, 订单类型 1广告2售前手工3售后手工

        if (!$this->orderAuditModel) $this->orderAuditModel = new OrderAudit();
        /*$this->orderDistributeModel = new OrderDistribute();
        $this->orderManualModel = new OrderManual();
        $this->orderRepeatModel = new OrderRepeat();
        $this->orderInvalidModel = new OrderInvalid();
        $this->orderAbnormalModel = new OrderAbnormal();
        $this->orderCancelModel = new OrderCancel();*/

        // 连表查询
        $the_table = $this->orderAuditModel->getTable();
        $order_table = $this->model->getTable();

        $builder = $this->orderAuditModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderAuditModel, $this->model);

        return $builder->count();
    }

    // 已审核
    public function sqlCountAuditYes($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $sql_where['department_id'] = ['in', $child_dept_list];
            } else {
                // 员工只能看到自己的
                $sql_where['audit_user_id'] = $login_user_info['id'];
            }
        }

        $sql_where['job_type'] = 1;               // order_audit 表 岗位类别 1售前2售后
        $sql_where['audit_status'] = 1;           // order/order_audit 两表共有 审核状态 0未审核1已审核-1已驳回
        $sql_where['order_type'] = 1;             // order表, 订单类型 1广告2售前手工3售后手工

        if (!$this->orderAuditModel) $this->orderAuditModel = new OrderAudit();

        // 连表查询
        $the_table = $this->orderAuditModel->getTable() ;
        $order_table = $this->model->getTable();

        $builder = $this->orderAuditModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderAuditModel, $this->model);

        return $builder->count();
    }

    public function sqlCountDistributeNo($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                //$sql_where['distributed_dep_id'] = ['in', $child_dept_list];
                $sql_where['distributed_dep_id'] = $login_user_info['department_id'];
            } else {
                // 员工没有分配权限
                return 0;
                //$sql_where['distributed_user_id'] = $login_user_info['id'];
            }
        }

        $sql_where['job_type'] = 1;               // order_distribute 表 岗位类别 1售前2售后
        //$sql_where['status'] = 1;                 // order_distribute 表 状态 0无效 1有效
        $sql_where['distribute_status'] = 0;      // order/order_distribute 两表共有 分配状态 0未分配1已分配-1已撤销
        $sql_where['order_type'] = 1;             // order表, 订单类型 1广告2售前手工3售后手工

        if (!$this->orderDistributeModel) $this->orderDistributeModel = new OrderDistribute();

        // 连表查询
        $the_table = $this->orderDistributeModel->getTable() ;
        $order_table = $this->model->getTable();

        $builder = $this->orderDistributeModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderDistributeModel, $this->model);

        return $builder->count();
    }

    public function sqlCountDistributeYes($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                //$sql_where['distributed_dep_id'] = ['in', $child_dept_list];
                $sql_where['distributed_dep_id'] = $login_user_info['department_id'];
            } else {
                // 员工没有分配权限
                return 0;
                //$sql_where['distributed_user_id'] = $login_user_info['id'];
            }
        }

        $sql_where['job_type'] = 1;               // order_distribute 表 岗位类别 1售前2售后
        //$sql_where['status'] = 1;                 // order_distribute 表 状态 0无效 1有效
        $sql_where['distribute_status'] = 1;      // order/order_distribute 两表共有 分配状态 0未分配1已分配-1已撤销
        $sql_where['order_type'] = 1;             // order表, 订单类型 1广告2售前手工3售后手工

        if (!$this->orderDistributeModel) $this->orderDistributeModel = new OrderDistribute();

        // 连表查询
        $the_table = $this->orderDistributeModel->getTable() ;
        $order_table = $this->model->getTable();

        $builder = $this->orderDistributeModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderDistributeModel, $this->model);

        return $builder->count();
    }

    // 手工单
    public function sqlCountManualOrder($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $sql_where['department_id'] = ['in', $child_dept_list];
            } else {
                // 员工只能看到自己的
                $sql_where['order_sale_id'] = $login_user_info['id'];
            }
        }

        $sql_where['job_type'] = 1;                 // order_manual 表 岗位类别 1售前2售后
        //$sql_where['order_type'] = ['in', [1,2]]; // order表, 订单类型 1广告2售前手工3售后手工

        if (!$this->orderManualModel) $this->orderManualModel = new OrderManual();

        // 连表查询
        $the_table = $this->orderManualModel->getTable() ;
        $order_table = $this->model->getTable();

        $builder = $this->orderManualModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderManualModel, $this->model);

        return $builder->count();
    }

    // 重复单
    public function sqlCountRepeatOrder($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $sql_where['department_id'] = ['in', $child_dept_list];
            } else {
                // 员工只能看到自己的
                $sql_where['pre_sale_id'] = $login_user_info['id'];
            }
        }

        if (!$this->orderRepeatModel) $this->orderRepeatModel = new OrderRepeat();

        // 连表查询
        $the_table = $this->orderRepeatModel->getTable() ;
        $order_table = $this->model->getTable();

        $builder = $this->orderRepeatModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderRepeatModel, $this->model);

        return $builder->count();
    }

    // 无效单
    public function sqlCountInvalidOrder($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $sql_where['department_id'] = ['in', $child_dept_list];
            } else {
                // 员工只能看到自己的
                $sql_where['order_sale_id'] = $login_user_info['id'];
            }
        }

        if (!$this->orderInvalidModel) $this->orderInvalidModel = new OrderInvalid();

        // 连表查询
        $the_table = $this->orderInvalidModel->getTable() ;
        $order_table = $this->model->getTable();

        $builder = $this->orderInvalidModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderInvalidModel, $this->model);

        return $builder->count();
    }

    // 异常单-未处理
    public function sqlCountAbnormalNo($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $sql_where['department_id'] = ['in', $child_dept_list];
            } else {
                // 员工只能看到自己的
                $sql_where['order_sale_id'] = $login_user_info['id'];
            }
        }

        $sql_where['job_type'] = 1;               // order_abnormal 表 岗位类别 1售前2售后
        $sql_where['status'] = 0;                 // order_abnormal 表 状态 0未处理1已处理

        if (!$this->orderAbnormalModel) $this->orderAbnormalModel = new OrderAbnormal();

        // 连表查询
        $the_table = $this->orderAbnormalModel->getTable() ;
        $order_table = $this->model->getTable();

        $builder = $this->orderAbnormalModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderAbnormalModel, $this->model);

        return $builder->count();
    }

    // 异常单-已经处理
    public function sqlCountAbnormalYes($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $sql_where['department_id'] = ['in', $child_dept_list];
            } else {
                // 员工只能看到自己的
                $sql_where['order_sale_id'] = $login_user_info['id'];
            }
        }

        $sql_where['job_type'] = 1;               // order_abnormal 表 岗位类别 1售前2售后
        $sql_where['status'] = 1;                 // order_abnormal 表 状态 0未处理1已处理

        if (!$this->orderAbnormalModel) $this->orderAbnormalModel = new OrderAbnormal();

        // 连表查询
        $the_table = $this->orderAbnormalModel->getTable() ;
        $order_table = $this->model->getTable();

        $builder = $this->orderAbnormalModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderAbnormalModel, $this->model);

        return $builder->count();
    }

    // 取消订单申请 - 未处理
    public function sqlCountAskforcancelNoDealwith($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $sql_where['department_id'] = ['in', $child_dept_list];
            } else {
                // 员工只能看到自己的，order表中的客服字段
                $sql_where['pre_sale_id'] = $login_user_info['id'];
            }
        }

        $sql_where['job_type'] = 1;                 // order_cancel 表 岗位类别 1售前2售后
        $sql_where['status'] = 0;                   // 状态 0未提交1已提交 2已归档
        $sql_where['opt_result'] = 0;               // 处理结果 0未处理1成功-1失败

        if (!$this->orderCancelModel) $this->orderCancelModel = new OrderCancel();

        // 连表查询
        $the_table = $this->orderCancelModel->getTable() ;
        $order_table = $this->model->getTable();

        $builder = $this->orderCancelModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderCancelModel, $this->model);

        return $builder->count();
    }

    // 取消订单申请 - 成功
    public function sqlCountAskforcancelSucc($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $sql_where['department_id'] = ['in', $child_dept_list];
            } else {
                // 员工只能看到自己的，order表中的客服字段
                $sql_where['pre_sale_id'] = $login_user_info['id'];
            }
        }

        $sql_where['job_type'] = 1;                 // order_cancel 表 岗位类别 1售前2售后
        $sql_where['status'] = 1;                   // 状态 0未提交1已提交 2已归档
        $sql_where['opt_result'] = 1;               // 处理结果 0未处理1成功-1失败

        if (!$this->orderCancelModel) $this->orderCancelModel = new OrderCancel();

        // 连表查询
        $the_table = $this->orderCancelModel->getTable() ;
        $order_table = $this->model->getTable();

        $builder = $this->orderCancelModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderCancelModel, $this->model);

        return $builder->count();
    }

    // 取消订单申请 - 失败
    public function sqlCountAskforcancelFail($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $sql_where['department_id'] = ['in', $child_dept_list];
            } else {
                // 员工只能看到自己的，order表中的客服字段
                $sql_where['pre_sale_id'] = $login_user_info['id'];
            }
        }

        $sql_where['job_type'] = 1;                 // order_cancel 表 岗位类别 1售前2售后
        $sql_where['status'] = 1;                   // 状态 0未提交1已提交 2已归档
        $sql_where['opt_result'] = -1;               // 处理结果 0未处理1成功-1失败

        if (!$this->orderCancelModel) $this->orderCancelModel = new OrderCancel();

        // 连表查询
        $the_table = $this->orderCancelModel->getTable() ;
        $order_table = $this->model->getTable();

        $builder = $this->orderCancelModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderCancelModel, $this->model);

        return $builder->count();
    }

    // 取消订单申请 - 归档
    public function sqlCountAskforcancelFinish($uid, $login_user_info, $child_dept_list=[]) {
        $sql_where = [];  // sql查询条件
        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $sql_where['department_id'] = ['in', $child_dept_list];
            } else {
                // 员工只能看到自己的，order表中的客服字段
                $sql_where['pre_sale_id'] = $login_user_info['id'];
            }
        }

        $sql_where['job_type'] = 1;                 // order_cancel 表 岗位类别 1售前2售后
        $sql_where['status'] = 2;                   // 状态 0未提交1已提交 2已归档

        if (!$this->orderCancelModel) $this->orderCancelModel = new OrderCancel();

        // 连表查询
        $the_table = $this->orderCancelModel->getTable() ;
        $order_table = $this->model->getTable();

        $builder = $this->orderCancelModel::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');
        $builder = self::getCommonBuild($builder, $sql_where, $this->orderCancelModel, $this->model);

        return $builder->count();
    }

    // 订单数据
    public function getOrderByUserAndTime($login_user_info, $child_dept_list, $between_datetime=[]) {
        $sql_where = [];  // sql查询条件

        $builder = $this->model;

        // 非管理员，就是主管和员工了
        if (1 != $login_user_info['role_id']) {
            if (1 == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $builder = $builder->whereIn('department_id', $child_dept_list);
            } else {
                // 员工只能看到自己的，order表中的客服字段
                $sql_where['pre_sale_id'] = $login_user_info['id'];
                $builder = $builder->where($sql_where);
            }
        }

        if ($between_datetime && isset($between_datetime[0]) && isset($between_datetime[1]) && $between_datetime[1])
            $builder = $builder->whereBetween('order_time', $between_datetime);
        $db_result = $builder->get()->toArray();
        return $db_result;
    }

    // 通过id获取信息
    public function getInfoByOrderNo($order_no) {
        $db_result = $this->model->where('order_no', $order_no)->first();
        if (!$db_result)
            return $db_result;
        return $db_result->toArray();
    }

}
