<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use stdClass;
use App\Exceptions\Handler;

trait Responsable
{
    /**
     * 格式化返回数据
     * 返回一个处理过的数组
     * 
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @param array|string $detail
     * @return array
     */
    public function wrapResponse(
        $data = null,
        $message = '',
        $status = 0
    ) {
        $message = $status ? '失败' : '成功';

        $dataValue = new stdClass;

        if ($data instanceof Model) {
            $dataValue = $data->toArray();
        } elseif ($data instanceof Paginator) {
            $dataValue = [
                'list' => $data->getCollection()->toArray(),
                'pagination' => [
                    'total' => $data->total(),
                    'current' => $data->currentPage(),
                    'pageSize' => $data->perPage(),
                    
                ],
            ];

        } elseif ($data instanceof Collection) {
            $dataValue = [
                'list' => $data->toArray(),
            ];
        } elseif (!empty($data)) {
            $dataValue = $data;
        }

        if(request()->has('meta')){
            $dataValue['meta'] = request('meta');
        }

        if(request()->has('distribute_btn_status')){
            $dataValue['distribute_btn_status'] = request('distribute_btn_status');
        }

        return [
            'status' => $status,
            'msg' => $message,
            'data' => $dataValue,
        ];
    }
}
