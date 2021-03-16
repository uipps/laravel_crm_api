<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use App\Dto\ResponseDto;
use App\Libs\Utils\ErrorMsg;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class Handler extends ExceptionHandler
{
    const EXCEPTION_VALIDATION = 2; //校验报错
    const EXCEPTION_MODEL_NOT_FOND = 3; //model没找到
    const EXCEPTION_UNAUTHORIZED = 4; //未授权

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        // api接口jwt过期或错误避免跳转新增代码
        if ($exception instanceof AuthenticationException) {
            $responseDto = new ResponseDto();
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::AUTHENTICATION_FAILED); // $exception->getMessage();
            return response(json_encode($responseDto), 401)->header('Content-Type', 'application/json');
        }

        if ($exception instanceof AuthorizationException) {
            $ret = [
                'status' => self::EXCEPTION_UNAUTHORIZED,
                'msg' => $exception->getMessage(),
            ];
            return response()->json($ret);
        }

        if($exception instanceof ValidationException){
            $ret = [
                'status' => self::EXCEPTION_VALIDATION,
                'msg' => $exception->validator->errors()->first(),
            ];
            return response()->json($ret);
        }

        if($exception instanceof ModelNotFoundException){
            $ret = [
                'status' => self::EXCEPTION_MODEL_NOT_FOND,
                'msg' => $exception->getMessage(),
            ];
            return response()->json($ret);
        }

        if($exception instanceof EmptyResultException){
            $ret = [
                'status' => 0,
                'msg' => $exception->getMessage(),
            ];
            return response()->json($ret);
        }

        if($exception instanceof InvalidException){
            $ret = [
                'status' => self::EXCEPTION_VALIDATION,
                'msg' => $exception->getMessage(),
            ];
            return response()->json($ret);
        }

        return parent::render($request, $exception);
    }
}
