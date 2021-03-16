<?php
/**
 * OrderOptRecordController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\OrderPreSale;

use App\Http\Controllers\CommonController;
use App\Http\Resources\OrderOptRecordResource;
use App\Models\OrderPreSale\OrderOptRecord;
use App\Services\OrderPreSale\OrderOptRecordService;


class OrderOptRecordController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new OrderOptRecordService();
        parent::__construct();
    }

    public function index() {
        return $this->response_json($this->theService->getList());
    }

    public function store() {
        return $this->response_json($this->theService->addOrUpdate());
    }

    public function show($id) {
        return $this->response_json($this->theService->detail($id));
    }

    public function destroy($id) {
        return $this->response_json($this->theService->delete($id));
    }

    public function update($id) {
        return $this->response_json($this->theService->updateOne($id));
    }

    public function getListByOrderId($id) {
        $list = OrderOptRecord::with([
            'operation',
            'operator',
            'operator.role',
            ])->where('order_id', $id)->orderByDesc('id')->get();
         
        $ret['list'] = OrderOptRecordResource::collection($list)->resource;
        return $this->wrapResponse($ret);
        // return $this->response_json($this->theService->getListByOrderId($id));
    }

}
