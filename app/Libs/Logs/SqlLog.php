<?php
// 记录SQL语句到日志
namespace App\Libs\Logs;

class SqlLog extends BaseLog
{
    public function __construct($channel = 'sql') {
        parent::__construct($channel);
    }

    protected function getLogPath() {
        return 'logs/sql/';
    }
}
