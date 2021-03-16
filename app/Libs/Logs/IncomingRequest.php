<?php
// 记录请求参数到日志，主要记录Post请求参数，GET参数在url上就有
namespace App\Libs\Logs;

class IncomingRequest extends BaseLog
{
    public function __construct($channel = 'post-url') {
        parent::__construct($channel);
    }

    protected function getLogPath() {
        return 'logs/post_request/';
    }
}
