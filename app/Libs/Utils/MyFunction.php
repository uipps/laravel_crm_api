<?php

use Illuminate\Support\Facades\Redis;

//////////////////// 一些通用方法
// 简易发号器 - 基于秒
function createTimeSequenceNo() {
    $curr_time = time();
    $key = 'sequence:' . $curr_time; // 统一前缀
    $value = Redis::incr($key);
    Redis::expire($key, 600);
    return $curr_time . '_' . $value;
}

// 基于订单号的发号器
function createOrderNoSequenceNo($order_no) {
    $key = 'sequence-order:' . $order_no; // 统一前缀
    $value = Redis::incr($key);
    return $value;
}

// 后面的赋值给前面的数组，但是前面的数组字段不能增多
function array_assign(array $dto_arr, array $db_arr) {
    if (!$db_arr) return $dto_arr;
    foreach ($db_arr as $property => $value) {
        if (null !== $value && array_key_exists($property, $dto_arr))
            $dto_arr[$property] = $value;
    }
    return $dto_arr;
}

// 时间戳转成东八区日期、时间
function getDateTimeByTime($time){
    return date('Y-m-d H:i:s', $time);
}

////////////////////  部门、员工等
// 一维数组，包含层级关系，返回某节点所有子节点id（不包括自己）
function getAllChildIdByParentId($data, $pid = 0, $parent_field='parent_id') {
    $arr = [];
    if (!$data) return $arr;

    foreach ($data as $v) {
        if ($v[$parent_field] == $pid) {
            $arr[] = $v;
            $arr = array_merge($arr, \getAllChildIdByParentId($data, $v['id'], $parent_field)); // 用+或merge都一样
        }
    }
    return $arr;
}




////////////////////  订单相关

// 简易订单号生成，来自建站系统
function generateOrderSn(){
    $order_sn = env('order_prefix', 'IV') . date('ymd') . rand(1000000,9999999);
    return $order_sn;
}

// 无效状态值
function getInvalidStatusByOptType($pre_opt_type) {
    $invalid_type = 11;                             // 其他未知，应该不会出现这个情况
    if (in_array($pre_opt_type, [15,16,17,18])) {
        $invalid_type = 2;                          // 表示客服审核为审核取消
    } else if (19 == $pre_opt_type) {
        $invalid_type = 3;                          // 表示客服审核为重复
    }
    return $invalid_type;
}



//////////////////// 导出相关

// csv文件字段格式化，双引号、科学计数法等处理
function format_csv_field($str) {
    if (is_numeric($str)) {
        $int = explode('.', $str);  // 整数长度小于11位的显示原数据
        if (strlen($int[0]) <= 11)
            return $str;
        return '="' . $str . '"'; // 防止科学计数法显示数字
    }
    // 如果有双引号，单个双引号变成两个双引号，避免两边加了双引号后转义错误
    if(false !== strpos($str, '"'))
        $str = str_replace('"', '""', $str) ;
    $str = '"' . $str . '"'; // 数据前后加双引号
    return $str;
}

/**
 * 可以全部数据导出csv
 * @param $input
 * @param $callback
 * @param string $prefix
 */
function exportCsv($input, $callback, $prefix = '') {
    $filename = $prefix . date('YmdHis') . '.csv'; // 设置文件名

    header("Content-type: text/x-csv; charset=utf-8");
    header("Content-Disposition:attachment;filename=" . $filename);
    header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
    header('Expires:0');
    header('Pragma:public');

    $headArr  = array_values($input);
    $fieldArr = array_keys($input);

    $header = implode(',', $headArr);
    echo chr(0xEF) . chr(0xBB) . chr(0xBF);
    echo $header . PHP_EOL;// 输出第一行头信息

    $list = true;
    while ($list) {
        $list = $callback(); // 执行回调函数，获取列表数据
        if(!$list)
            continue;

        $exeCount = 0;
        foreach ($list as $item) {
            $outputString = '';
            foreach ($fieldArr as $field) {
                $outputString .= \format_csv_field(@$item[$field]) . ',';
            }
            $outputString = substr($outputString, 0, -1) . PHP_EOL;  // 删除行末尾的逗号,，换成换行符号；
            echo $outputString; // 输出单行

            $exeCount++;
            // 重新刷新缓冲区，可避免页面超时
            if ($exeCount % 100 == 0) {
                ob_flush();
                flush();
            }
        }
        usleep(2000);
    }

    //$endExecTime = microtime(true);
    // Log::write($filename."导出文件" . $filename . "执行耗时：" . ($endExecTime - $startExecTime) . "s", 'INFO');
    exit;
}
