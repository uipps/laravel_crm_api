<?php
// 请求参数记录日志中间件，部分重要的get也可以记录其中
namespace App\Http\Middleware;

use Closure;
use App\Libs\Logs\IncomingRequest;

class IncomingRequestMiddleware
{
    protected static $importantGets = [
        'user/detail'
    ];

    /**
     * log an incoming request params.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $pathInfo = $request->getRequestUri();
        $method = $request->getMethod();
        if ('GET' == $method && !$this->isUrlImportant($pathInfo)) {
            return $next($request);
        }

        $inputStr = '';
        $params = $request->input();
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $i => $l_v)
                    $inputStr .= $k ."[$i]=". (is_array($l_v)?json_encode($l_v):$l_v) . '&';
            } else {
                $inputStr .= $k ."=". $v . '&';
            }
        }
        $inputStr = trim($inputStr, '&');

        $link_string = (false !== strpos($pathInfo, '?')) ? '&' : '?';
        $requestLogContent = '[' . $method . '] ' .$pathInfo . $link_string .$inputStr;

        $logger = new IncomingRequest();
        $logger->info($requestLogContent);
        return $next($request);
    }

    private function isUrlImportant($url) {
        foreach (self::$importantGets as $uri) {
            if (false !== strpos($uri, $url))
                return true;
        }
        return false;
    }
}
