<?php

namespace App\Http\Middleware;

use App\Exceptions\EmptyResultException;
use App\Libs\Utils\BaseDataMq;
use App\Models\Admin\SysRouting;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;

class Permit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $routeName = $request->route()->getName();
        Log::channel('test')->info($routeName);
        // throw new EmptyResultException();

        // 验证登录
        if (!auth('api')->check()) {
            throw new AuthenticationException('当前用户未登录');
        }

        // 当前访问接口的路由名称
        // 以及如果没有命名那就直接放行
        $routeName = $request->route()->getName();
        if (!$routeName) {
            return $next($request);
        }

        // 判断当前路由是否被加入了管理
        // 如果没有直接放行
        $routing = SysRouting::routeName($routeName)
            ->with('privilege')->first();
        if (!$routing || !$routing->privilege) {
            return $next($request);
        }

        // 当前用户被赋予了哪些角色
        // 如果用户被赋予的角色中存在超级管理员角色
        // 则当前用户通过权限验证
        // 如果当前用户没有被赋予角色
        // 则无法通过验证
        $role = auth('api')->user()->role;
        if ($role) {
            if ($role->is_super) {
                return $next($request);
            }
        } else {
            $msg = '当前登录用户没有请求此接口的权限';
            $exception = new AuthorizationException($msg);
            $exception->detail = [
                "当前登录用户没有被赋予角色，故而没有被赋予任何权限",
            ];
            throw $exception;
        }

        // 51客服接单失效时间刷新
        if(auth('api')->user()->receive_status){
            
            $type = 51;
            $ret = BaseDataMq::writeMq(compact('type'));
        }

        // 查询当前登录用户角色所拥有的权限
        // 如果包含当前路由要求的权限则通过验证
        $privileges = $role->privileges;
        // dd($privileges);
        if ($privileges->pluck('id')->contains($routing->privilege->id)) {
            return $next($request);
        }

        // 如果以上验证都没有通过
        // 抛出异常
        $msg = '当前登录用户没有请求此接口的权限';
        $exception = new AuthorizationException($msg);
        $exception->detail = [
            "请求此接口需要 {$routing->privilege->name} 权限",
        ];
        throw $exception;
    }
}
