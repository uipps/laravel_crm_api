<?php

namespace App\Libs\Utils;

class ErrorMsg {
    // 定义错误信息
    const PARAM_ERROR = 101;
    const PARAM_FORMAT_ERROR = 105;
    const PARENT_DATA_NOT_EXISTS = 106;
    const REQUEST_DATA_INCOMPLETE = 110;
    const IP_NOT_ALLOWED = 120;
    const ACTION_NOT_ALLOWED = 122;
    const NO_PRIVILEGE = 124;
    const UNKNOWN_ERROR = 130;
    const NETWORK_ERROR = 131;
    const ERP_API_DATA_CHANGE = 132;
    const DATA_EMPTY = 135;
    const UNSUPPORTED_TYPE = 137;

    // 拨打电话
    const CALL_3CX_ERROR = 140;

    const AUTHENTICATION_FAILED = 401;
    const UN_LOGIN = 402;
    const USER_SOURCE_NOT_EXISTS = 403;
    const USER_TO_NOT_EXISTS = 404;
    const USER_NOT_EXISTS = 405;
    const USER_EXISTS = 406;
    const EMAIL_EXISTS = 407;
    const USER_DELETED = 408;
    const PASSWORD_ERROR = 410;
    const USERNAME_OR_PASSWORD_INCORRECT = 415;
    const OLD_PASSWORD_ERROR = 416;
    const SET_PASSWORD_ERROR = 417;

    const USER_STATUS_CLOSE = 419;
    const USER_STATUS_ERROR = 420;
    const ADMIN_NOT_ALLOWED = 422;
    const USER_MUST_STAFF = 423;

    const AUTHCODE_ERROR = 425;
    const AUTHCODE_overdue = 427;
    const AUTHCODE_throttle = 429;

    // 主管和员工部门不匹配
    const ADMIN_USER_DEPARTMENT_NOT_MATCH = 450;
    const TWO_USER_DEPARTMENT_NOT_MATCH = 451;
    const DEPARTMENT_NEEDED = 458;
    const DEPARTMENT_MUST_PRE_SALE = 460;
    const DEPARTMENT_MUST_AFTER_SALE = 461;
    const DEPARTMENT_JOB_TYPE_NOT_MATCH = 462;
    const DEPARTMENT_COUNTRY_OVER = 463;
    const DEPARTMENT_COUNTRY_NEEDED = 463;
    const SIPNO_COUNTRY_NOT_SET = 465;
    const DEPARTMENT_JOB_TYPE_CAN_NOT_MODIFY = 470;
    const DEPARTMENT_PARENT_CAN_NOT_MODIFY = 472;
    const DEPARTMENT_JOB_TYPE_ERROR = 474;


    const INSERT_DB_FAILED = 505;
    const UPDATE_DB_FAILED = 510;
    const DELETE_DB_FAILED = 511;
    const DATA_NOT_EXISTS = 515;

    const CUSTOMER_TRANSFER_ONLY_BY_AFTER_SALE = 530;
    const CUSTOMER_CLUE_CREATE_NOT_ALLOW_BY_ADMIN = 532;
    const CUSTOMER_CLUE_CANNOT_DISTRIBUTE_TO_CLUE_SALE = 533;

    const CUSTOMER_REMARK_CANNOT_MODIFY_BY_OTHER = 536;

    // 商品相关
    const GOODS_EXISTS = 550;
    const GOODS_PRICE_ERROR = 555;
    const GOODS_PRICE_MUST_BIGGER_ZERO = 556;

    const ORDER_TYPE_UNKNOWN = 604;
    const ORDER_DATA_NOT_EXISTS = 605;
    const ORDER_NO_IS_REQUIRED = 610;
    const ORDER_STATUS_ERROR = 615;
    const ORDER_SHIPPING_STATUS_ERROR = 616;
    const ORDER_SUBMIT_TYPE_ERROR = 620;
    const ORDER_MANUAL_CREATE_BY_ADMIN = 625;
    const ORDER_OPT_NEED_ADMIN = 626;
    const ORDER_ON_GOODS = 630;
    const ORDER_REPLENIDH_GOODS_OVER = 635;
    const ORDER_REPLENIDH_HAS_EXISTS = 636;
    const ORDER_REDELIVERY_HAS_EXISTS = 640;
    const ORDER_HAS_DEALWITHED = 645;

    const ORDER_HAS_BEING_AUDITED = 650;

    const ORDER_PROMOTION_RULE_KEY_INVALID = 700;
    const ORDER_PROMOTION_OVERLYING_ERROR = 705;
    const ORDER_PROMOTION_GOODS_NUM_LESS_MIN = 710;
    const ORDER_PROMOTION_GOODS_INFO_NO_RULES = 720;
    const ORDER_PROMOTION_STATUS_ABNORMAL= 721;
    const ORDER_PROMOTION_RULE_CHANGE= 722;


    public static function GetErrorMsgArray() {
        $data_arr = [
            self::PARAM_ERROR => trans('message.PARAM_ERROR'),
            self::AUTHENTICATION_FAILED => trans('message.authentication_failed'),
            self::PARAM_FORMAT_ERROR  => trans('message.PARAM_FORMAT_ERROR'),
            self::PARENT_DATA_NOT_EXISTS  => trans('message.PARENT_DATA_NOT_EXISTS'),
            self::REQUEST_DATA_INCOMPLETE  => trans('message.REQUEST_DATA_INCOMPLETE'),
            self::IP_NOT_ALLOWED  => trans('message.IP_NOT_ALLOWED'),
            self::ACTION_NOT_ALLOWED  => trans('message.ACTION_NOT_ALLOWED'),
            self::NO_PRIVILEGE  => trans('message.NO_PRIVILEGE'),
            self::UNKNOWN_ERROR  => trans('message.UNKNOWN_ERROR'),
            self::DATA_EMPTY  => trans('message.DATA_EMPTY'),
            self::UNSUPPORTED_TYPE  => trans('message.UNSUPPORTED_TYPE'),

            self::CALL_3CX_ERROR  => trans('message.CALL_3CX_ERROR'),

            self::UN_LOGIN         => trans('message.UN_LOGIN'),
            self::USER_SOURCE_NOT_EXISTS  => trans('message.USER_SOURCE_NOT_EXISTS'),
            self::USER_TO_NOT_EXISTS  => trans('message.USER_TO_NOT_EXISTS'),
            self::USER_NOT_EXISTS  => trans('message.USER_NOT_EXISTS'),
            self::USER_EXISTS  => trans('message.USER_EXISTS'),
            self::EMAIL_EXISTS  => trans('message.EMAIL_EXISTS'),
            self::USER_DELETED  => trans('message.USER_DELETED'),
            self::PASSWORD_ERROR  => trans('message.PASSWORD_ERROR'),
            self::USERNAME_OR_PASSWORD_INCORRECT  => trans('message.USERNAME_OR_PASSWORD_INCORRECT'),
            self::OLD_PASSWORD_ERROR  => trans('message.OLD_PASSWORD_ERROR'),
            self::SET_PASSWORD_ERROR  => trans('message.SET_PASSWORD_ERROR'),
            self::USER_STATUS_CLOSE  => trans('message.USER_STATUS_CLOSE'),
            self::USER_STATUS_ERROR  => trans('message.USER_STATUS_ERROR'),

            self::ADMIN_NOT_ALLOWED  => trans('message.ADMIN_NOT_ALLOWED'),
            self::USER_MUST_STAFF  => trans('message.USER_MUST_STAFF'),

            // 验证码错误相关
            self::AUTHCODE_ERROR => trans('message.AUTHCODE_ERROR'),
            self::AUTHCODE_overdue => trans('message.AUTHCODE_overdue'),
            self::AUTHCODE_throttle => trans('message.AUTHCODE_throttle'),

            // 主管和员工部门不匹配
            self::ADMIN_USER_DEPARTMENT_NOT_MATCH => trans('message.ADMIN_USER_DEPARTMENT_NOT_MATCH'),
            self::TWO_USER_DEPARTMENT_NOT_MATCH => trans('message.TWO_USER_DEPARTMENT_NOT_MATCH'),
            self::DEPARTMENT_NEEDED => trans('message.DEPARTMENT_NEEDED'),
            self::DEPARTMENT_MUST_PRE_SALE => trans('message.DEPARTMENT_MUST_PRE_SALE'),
            self::DEPARTMENT_MUST_AFTER_SALE => trans('message.DEPARTMENT_MUST_AFTER_SALE'),
            self::DEPARTMENT_JOB_TYPE_NOT_MATCH => trans('message.DEPARTMENT_JOB_TYPE_NOT_MATCH'),
            self::DEPARTMENT_COUNTRY_OVER => trans('message.DEPARTMENT_COUNTRY_OVER'),
            self::DEPARTMENT_COUNTRY_NEEDED => trans('message.DEPARTMENT_COUNTRY_NEEDED'),
            self::SIPNO_COUNTRY_NOT_SET => trans('message.SIPNO_COUNTRY_NOT_SET'),
            self::DEPARTMENT_JOB_TYPE_CAN_NOT_MODIFY => trans('message.DEPARTMENT_JOB_TYPE_CAN_NOT_MODIFY'),
            self::DEPARTMENT_PARENT_CAN_NOT_MODIFY => trans('message.DEPARTMENT_PARENT_CAN_NOT_MODIFY'),
            self::DEPARTMENT_JOB_TYPE_ERROR => trans('message.DEPARTMENT_JOB_TYPE_ERROR'),

            self::INSERT_DB_FAILED  => trans('message.INSERT_DB_FAILED'),
            self::UPDATE_DB_FAILED  => trans('message.UPDATE_DB_FAILED'),
            self::DELETE_DB_FAILED  => trans('message.DELETE_DB_FAILED'),
            self::DATA_NOT_EXISTS  => trans('message.DATA_NOT_EXISTS'),

            self::CUSTOMER_TRANSFER_ONLY_BY_AFTER_SALE  => trans('message.CUSTOMER_TRANSFER_ONLY_BY_AFTER_SALE'),
            self::CUSTOMER_CLUE_CREATE_NOT_ALLOW_BY_ADMIN  => trans('message.CUSTOMER_CLUE_CREATE_NOT_ALLOW_BY_ADMIN'),
            self::CUSTOMER_CLUE_CANNOT_DISTRIBUTE_TO_CLUE_SALE  => trans('message.CUSTOMER_CLUE_CANNOT_DISTRIBUTE_TO_CLUE_SALE'),
            self::CUSTOMER_REMARK_CANNOT_MODIFY_BY_OTHER  => trans('message.CUSTOMER_REMARK_CANNOT_MODIFY_BY_OTHER'),

            // 商品相关
            self::GOODS_EXISTS  => trans('message.GOODS_EXISTS'),
            self::GOODS_PRICE_ERROR  => trans('message.GOODS_PRICE_ERROR'),
            self::GOODS_PRICE_MUST_BIGGER_ZERO  => trans('message.GOODS_PRICE_MUST_BIGGER_ZERO'),

            // 订单相关
            self::ORDER_TYPE_UNKNOWN  => trans('message.ORDER_TYPE_UNKNOWN'),
            self::ORDER_DATA_NOT_EXISTS  => trans('message.ORDER_DATA_NOT_EXISTS'),
            self::ORDER_NO_IS_REQUIRED  => trans('message.ORDER_NO_IS_REQUIRED'),
            self::ORDER_STATUS_ERROR  => trans('message.ORDER_STATUS_ERROR'),
            self::ORDER_SHIPPING_STATUS_ERROR  => trans('message.ORDER_SHIPPING_STATUS_ERROR'),
            self::ORDER_SUBMIT_TYPE_ERROR  => trans('message.ORDER_SUBMIT_TYPE_ERROR'),
            self::ORDER_MANUAL_CREATE_BY_ADMIN  => trans('message.ORDER_MANUAL_CREATE_BY_ADMIN'),
            self::ORDER_OPT_NEED_ADMIN  => trans('message.ORDER_OPT_NEED_ADMIN'),
            self::ORDER_ON_GOODS  => trans('message.ORDER_ON_GOODS'),
            self::ORDER_REPLENIDH_GOODS_OVER  => trans('message.ORDER_REPLENIDH_GOODS_OVER'),
            self::ORDER_REPLENIDH_HAS_EXISTS  => trans('message.ORDER_REPLENIDH_HAS_EXISTS'),
            self::ORDER_REDELIVERY_HAS_EXISTS  => trans('message.ORDER_REDELIVERY_HAS_EXISTS'),
            self::ORDER_HAS_DEALWITHED  => trans('message.ORDER_HAS_DEALWITHED'),

            self::ORDER_HAS_BEING_AUDITED  => trans('message.ORDER_HAS_BEING_AUDITED'),

            // 活动相关
            self::ORDER_PROMOTION_RULE_KEY_INVALID  => trans('message.ORDER_PROMOTION_RULE_KEY_INVALID'),
            self::ORDER_PROMOTION_OVERLYING_ERROR  => trans('message.ORDER_PROMOTION_OVERLYING_ERROR'),
            self::ORDER_PROMOTION_GOODS_NUM_LESS_MIN  => trans('message.ORDER_PROMOTION_GOODS_NUM_LESS_MIN'),
            self::ORDER_PROMOTION_GOODS_INFO_NO_RULES  => trans('message.ORDER_PROMOTION_GOODS_INFO_NO_RULES'),
            self::ORDER_PROMOTION_STATUS_ABNORMAL  => trans('message.ORDER_PROMOTION_STATUS_ABNORMAL'),
            self::ORDER_PROMOTION_RULE_CHANGE  => trans('message.ORDER_PROMOTION_RULE_CHANGE'),

        ];
        return $data_arr;
    }


    private function __construct() {}

    public static function GetErrorMsg() {
        $argc = func_num_args();
        $error_msg_array = self::GetErrorMsgArray();
        if (0 == $argc)
            return NULL;
        $argv = func_get_args();
        if (array_key_exists($argv[0], $error_msg_array)) {
            if (1 == $argc)
                return $error_msg_array[$argv[0]];
            $argv[0] = $error_msg_array[$argv[0]];
            return call_user_func_array('sprintf', $argv);
        } //else
        //Log::alert('Invalid errno:' . $argv[0]);
        return NULL;
    }

    /**
     * 填充response数组并记录日志
     *
     * @param $response
     * @param string $status
     * @param array $other_info
     */
    public static function FillResponseAndLog(&$response, $status, $other_info=array()) {
        $response->status = $status;

        // 如果有超过两个或更多的参数则都传递给如下方法
        array_unshift($other_info, $status); // 在数组插入一个单元
        $response->msg = call_user_func_array('App\Libs\Utils\ErrorMsg::GetErrorMsg', $other_info);

        $call_info = debug_backtrace();
        $log_title = isset($call_info[1]) ? $call_info[0]['file'] . ' ' .
            $call_info[0]['line'] . ': ' . $call_info[1]['function'] .
            ' failed:' :
            $call_info[0]['file'] . ' ' . $call_info[0]['line'] . ': ';
        //Log::alert($log_title . $response->msg, false);
    }
}
