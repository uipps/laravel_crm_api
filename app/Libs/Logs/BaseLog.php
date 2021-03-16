<?php
namespace App\Libs\Logs;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;

abstract class BaseLog
{
    const MAX_LOG_FILE_NUM = 30; // the max preserved log file count

    private $channel;
    private $log;
    //private $maxlogNum;

    abstract protected function getLogPath();

    public function __construct($channel = 'DB') {
        $this->channel = $channel;
        $this->log = new Logger($this->channel, [$this->getMonologHandler()]);
        //$this->maxlogNum = env('MAX_LOG_FILE_NUM', self::MAX_LOG_FILE_NUM);
    }

    public function error($message, array $context = array()) {
        if (is_array($message)) {
            $message = $this->arrayToString($message);
        }
        $this->log->error($message, $context);
    }

    public function info($message, array $context = array()) {
        if (is_array($message)) {
            $message = $this->arrayToString($message);
        }
        $this->log->info($message, $context);
    }

    public function warning($message, array $context = []) {
        if (is_array($message)) {
            $message = $this->arrayToString($message);
        }
        $this->log->warning($message, $context);
    }

    private function arrayToString(array $data) {
        $str = '';
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $val = var_export($val, true);
            }
            $str .= $key . '=>' . $val ."\n";
        }
        $str .= "\n".str_pad('$', "128", '$')."\n";
        return $str;
    }

    public function getMaxFileNum() {
        $max_file = (int)env('MAX_LOG_FILE_NUM', self::MAX_LOG_FILE_NUM);
        if ($max_file <= 0) $max_file = self::MAX_LOG_FILE_NUM;
        return $max_file;
    }

    /**
     * Get the Monolog handler for the application.
     *
     * @return \Monolog\Handler\AbstractHandler
     */
    private function getMonologHandler() {
        return (new RotatingFileHandler(storage_path($this->getLogPath(). $this->channel . '.log'), $this->getMaxFileNum(), Logger::DEBUG))
            ->setFormatter(new LineFormatter(null, null, true, true));
    }

    private function getSystemLogHandler()
    {
        return (new StreamHandler(storage_path('logs/laravel_log.log'), Logger::ERROR))
        ->setFormatter(new LineFormatter(null, null, false, true));
    }
}
