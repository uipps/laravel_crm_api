<?php
// 其他语言都可以放到这里

return [
    'PARAM_ERROR' => '参数错误',
    'PARAM_FORMAT_ERROR'  => '请求参数格式错误',
    'PARENT_DATA_NOT_EXISTS'  => '父级数据不存在',
    'REQUEST_DATA_INCOMPLETE'  => '数据不全',
    'IP_NOT_ALLOWED'  => 'IP受限',
    'ACTION_NOT_ALLOWED'  => '禁止操作',
    'NO_PRIVILEGE'  => '无权限',
    'UNKNOWN_ERROR'  => '未知错误',
    'NETWORK_ERROR'  => '网络错误',
    'ERP_API_DATA_CHANGE'  => 'ERP接口数据格式有改变或数据错误',
    'DATA_EMPTY'  => '数据为空',
    'UNSUPPORTED_TYPE'  => '不支持的类型',

    'CALL_3CX_ERROR'  => '拨打电话接口返回失败!',

    'authentication_failed'  => '身份验证失败',
    'UN_LOGIN'          => '未登录',
    'USER_SOURCE_NOT_EXISTS'  => '来源用户不存在',
    'USER_TO_NOT_EXISTS'  => '目标用户不存在',
    'USER_NOT_EXISTS'  => '用户不存在',
    'USER_EXISTS'  => '用户已存在',
    'EMAIL_EXISTS'  => '该Email已存在',
    'USER_DELETED'  => '用户已被删除',
    'PASSWORD_ERROR'  => '密码错误',
    'USERNAME_OR_PASSWORD_INCORRECT'  => '用户名或密码不正确',
    'OLD_PASSWORD_ERROR'  => '原密码不正确',
    'SET_PASSWORD_ERROR'  => '设置密码失败',
    'USER_STATUS_CLOSE'  => '该用户已停用',
    'USER_STATUS_ERROR'  => '用户状态异常',

    'ADMIN_NOT_ALLOWED'  => '系统感觉您亲力亲为太辛苦，请找员工代劳',
    'USER_MUST_STAFF'  => '必须是客服员工',

    // 验证码错误提示
    'AUTHCODE_ERROR'  => '验证码错误',
    'AUTHCODE_overdue' => '验证码已过期，请刷新重试.',
    'AUTHCODE_throttle' => '操作太频繁，请休息一下再试.',

    'ADMIN_USER_DEPARTMENT_NOT_MATCH' => '主管和员工的部门不匹配！',
    'TWO_USER_DEPARTMENT_NOT_MATCH' => '员工的部门不匹配！',
    'DEPARTMENT_NEEDED' => '请选择部门',
    'DEPARTMENT_MUST_PRE_SALE' => '只允许售前部门操作',
    'DEPARTMENT_MUST_AFTER_SALE' => '只允许售后部门操作',
    'DEPARTMENT_JOB_TYPE_NOT_MATCH' => '岗位类型不匹配',
    'DEPARTMENT_COUNTRY_OVER' => '分单国家超过上级部门分单国家范围',
    'DEPARTMENT_COUNTRY_NEEDED' => '请添加分单设置',
    'SIPNO_COUNTRY_NOT_SET' => '分机号未设置',
    'DEPARTMENT_JOB_TYPE_CAN_NOT_MODIFY' => '部门类型不能修改',
    'DEPARTMENT_PARENT_CAN_NOT_MODIFY' => '部门层级关系不能修改',
    'DEPARTMENT_JOB_TYPE_ERROR' => '部门售前、售后类型有误',

    'INSERT_DB_FAILED'  => '入库操作失败',
    'UPDATE_DB_FAILED'  => '更新数据失败',
    'DELETE_DB_FAILED'  => '删除数据失败',
    'DATA_NOT_EXISTS'  => '未找到相应数据',

    // 客户相关
    'CUSTOMER_TRANSFER_ONLY_BY_AFTER_SALE'  => '只有售后才能进行客户转移',
    'CUSTOMER_CLUE_CREATE_NOT_ALLOW_BY_ADMIN'  => '暂不允许主管创建线索，请找员工代劳',
    'CUSTOMER_CLUE_CANNOT_DISTRIBUTE_TO_CLUE_SALE'  => '线索不能被分配给线索客服',
    'CUSTOMER_REMARK_CANNOT_MODIFY_BY_OTHER'  => '不能修改别人的客户备注',

    // 商品相关
    'GOODS_EXISTS'  => '该商品已存在!',
    'GOODS_PRICE_ERROR'  => '商品价格有误',
    'GOODS_PRICE_MUST_BIGGER_ZERO'  => '商品价格必须大于0',

    // 订单相关
    'ORDER_TYPE_UNKNOWN'  => '订单类型未知',
    'ORDER_DATA_NOT_EXISTS'  => '该订单号不存在',
    'ORDER_NO_IS_REQUIRED'  => '订单号不能为空',
    'ORDER_STATUS_ERROR'  => '订单状态有误，无法继续操作',
    'ORDER_SHIPPING_STATUS_ERROR'  => '订单物流状态有误，无法继续操作',
    'ORDER_SUBMIT_TYPE_ERROR'  => '订单提交类型错误，只能是保存、提交、取消订单!',
    'ORDER_MANUAL_CREATE_BY_ADMIN'  => '只有主管才可以创建手工单!',
    'ORDER_OPT_NEED_ADMIN'  => '只有主管才可以操作!',
    'ORDER_ON_GOODS'  => '订单缺少商品信息!',
    'ORDER_REPLENIDH_GOODS_OVER'  => '补发订单的商品超出范围!',
    'ORDER_REPLENIDH_HAS_EXISTS'  => '补发订单已经存在，请不要重复创建!',
    'ORDER_REDELIVERY_HAS_EXISTS'  => '重发订单已经存在，请不要重复创建!',
    'ORDER_HAS_DEALWITHED'  => '订单已被处理过了，请不要重复处理!',

    'ORDER_HAS_BEING_AUDITED'  => '该订单已被审核过!',

    // 活动
    'ORDER_PROMOTION_RULE_KEY_INVALID'  => '活动规则key不合法!',
    'ORDER_PROMOTION_OVERLYING_ERROR'  => '活动叠加规则冲突',
    'ORDER_PROMOTION_GOODS_NUM_LESS_MIN'  => '分组中商品实际数量少于活动要求的最少商品数',
    'ORDER_PROMOTION_GOODS_INFO_NO_RULES'  => '商品信息中缺少活动规则信息',
    'ORDER_PROMOTION_STATUS_ABNORMAL'  => '活动状态异常',
    'ORDER_PROMOTION_RULE_CHANGE'  => '活动规则可能被修改',

];
