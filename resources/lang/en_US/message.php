<?php
// 其他语言都可以放到这里

return [
    'PARAM_ERROR' => 'The params error!',
    'PARAM_FORMAT_ERROR'  => 'params format errror',
    'PARENT_DATA_NOT_EXISTS'  => 'parent data not exists!',
    'REQUEST_DATA_INCOMPLETE'  => 'request data incomplete',
    'IP_NOT_ALLOWED'  => 'ip not allowed',
    'ACTION_NOT_ALLOWED'  => 'action not allowed',
    'NO_PRIVILEGE'  => 'no privilege',
    'UNKNOWN_ERROR'  => 'unknown error',
    'NETWORK_ERROR'  => 'network error',
    'ERP_API_DATA_CHANGE'  => 'erp api data change!',
    'DATA_EMPTY'  => 'data empty',
    'UNSUPPORTED_TYPE'  => 'the type is not supported!',

    'CALL_3CX_ERROR'  => 'call 3cx return error!',

    'authentication_failed' => 'authentication failed.',
    'UN_LOGIN'         => 'unlogin',
    'USER_SOURCE_NOT_EXISTS'  => 'the source user not exists.',
    'USER_TO_NOT_EXISTS'  => 'the target user not exists.',
    'USER_NOT_EXISTS'  => 'user not exists.',
    'USER_EXISTS'  => 'user exists',
    'EMAIL_EXISTS'  => 'email exists.',
    'USER_DELETED'  => 'the user has being deleted',
    'PASSWORD_ERROR'  => 'password error.',
    'USERNAME_OR_PASSWORD_INCORRECT'  => 'username or password incorrect.',
    'OLD_PASSWORD_ERROR'  => 'The original password is incorrect.',
    'SET_PASSWORD_ERROR'  => 'set password failed.',
    'USER_STATUS_CLOSE'  => 'this user is closed',
    'USER_STATUS_ERROR'  => 'USER STATUS ERROR',
    'ADMIN_NOT_ALLOWED'  => 'admin is not allowed',
    'USER_MUST_STAFF'  => 'user must be staff',

    // 验证码错误提示
    'AUTHCODE_ERROR'  => 'verify code error!',
    'AUTHCODE_overdue' => 'verify code is overdue，please refresh it.',
    'AUTHCODE_throttle' => 'Too many verify attempts. Please try again later.',

    'ADMIN_USER_DEPARTMENT_NOT_MATCH' => 'admin and staff department not matches!',
    'TWO_USER_DEPARTMENT_NOT_MATCH' => 'staff\'s department not matches!',
    'DEPARTMENT_NEEDED' => 'department is needed',
    'DEPARTMENT_MUST_PRE_SALE' => 'this need pre sale department!',
    'DEPARTMENT_MUST_AFTER_SALE' => 'this need after sale department!',
    'DEPARTMENT_JOB_TYPE_NOT_MATCH' => 'job_type not matches',
    'DEPARTMENT_COUNTRY_OVER' => 'country is over',
    'DEPARTMENT_COUNTRY_NEEDED' => 'department country is needed',
    'SIPNO_COUNTRY_NOT_SET' => 'sipno is not set',
    'DEPARTMENT_JOB_TYPE_CAN_NOT_MODIFY' => 'job_type can not be modified',
    'DEPARTMENT_PARENT_CAN_NOT_MODIFY' => 'parent can not be modified',
    'DEPARTMENT_JOB_TYPE_ERROR' => 'department type error',

    'INSERT_DB_FAILED'  => 'INSERT DB FAILED',
    'UPDATE_DB_FAILED'  => 'UPDATE DB FAILED',
    'DELETE_DB_FAILED'  => 'delete data failed.',
    'DATA_NOT_EXISTS'  => 'DATA NOT EXISTS',

    // 客户相关
    'CUSTOMER_TRANSFER_ONLY_BY_AFTER_SALE'  => 'only after sale can transfer customer',
    'CUSTOMER_CLUE_CREATE_NOT_ALLOW_BY_ADMIN'  => 'customer clue can not be created by admin',
    'CUSTOMER_CLUE_CANNOT_DISTRIBUTE_TO_CLUE_SALE'  => 'customer clue can not be distributed to clue sale',
    'CUSTOMER_REMARK_CANNOT_MODIFY_BY_OTHER'  => 'customer remark can not be edit by others',

    // 商品相关
    'GOODS_EXISTS'  => 'the product is exists!',
    'GOODS_PRICE_ERROR'  => 'the product price is error!',
    'GOODS_PRICE_MUST_BIGGER_ZERO'  => 'the product price must bigger than zero!',

    // 订单相关
    'ORDER_TYPE_UNKNOWN'  => 'order type is unknown!',
    'ORDER_DATA_NOT_EXISTS'  => 'this order is not exist!',
    'ORDER_NO_IS_REQUIRED'  => 'order_no is required!',
    'ORDER_STATUS_ERROR'  => 'the order status is error!',
    'ORDER_SHIPPING_STATUS_ERROR'  => 'the order shipping status is error!',
    'ORDER_SUBMIT_TYPE_ERROR'  => 'the submit_type is error!',
    'ORDER_MANUAL_CREATE_BY_ADMIN'  => 'the manual order just can be created by admin role!',
    'ORDER_OPT_NEED_ADMIN'  => 'this just can be operated by admin role!',
    'ORDER_ON_GOODS'  => 'the order no goods info!',
    'ORDER_REPLENIDH_GOODS_OVER'  => 'the goods to be replenished is over!',
    'ORDER_REPLENIDH_HAS_EXISTS'  => 'the replenish order has exists!',
    'ORDER_REDELIVERY_HAS_EXISTS'  => 'the redelivery order has exists!',
    'ORDER_HAS_DEALWITHED'  => 'the order has being deal with!',

    'ORDER_HAS_BEING_AUDITED'  => 'this order has being audited!',

    // 活动
    'ORDER_PROMOTION_RULE_KEY_INVALID'  => 'the order promotion rule key is invalid!',
    'ORDER_PROMOTION_OVERLYING_ERROR'  => 'the order promotion overlying rule is wrong!',
    'ORDER_PROMOTION_GOODS_NUM_LESS_MIN'  => 'the goods total number is less than the minimum num!',
    'ORDER_PROMOTION_GOODS_INFO_NO_RULES'  => 'the goods info no rule\'s info!',
    'ORDER_PROMOTION_STATUS_ABNORMAL'  => 'the promotion\'s status is abnormal!',
    'ORDER_PROMOTION_RULE_CHANGE'  => 'the promotion\'s rule is changed!',

];
