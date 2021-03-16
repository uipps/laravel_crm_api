<?php
/**
 * CustomerController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\Customer;

use App\Http\Controllers\CommonController;
use App\Http\Resources\CustomerResource;
use App\Mappers\CommonMapper;
use App\Models\Customer\Customer;
use App\Services\Customer\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new CustomerService();
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

    public function distributed() {
        return $this->response_json($this->theService->distributed());
    }

    public function distributeNot() {
        return $this->response_json($this->theService->distributeNot());
    }

    public function getListByUser() {
        return $this->response_json($this->theService->getListByUser());
    }

    public function customerTransfer() {
        return $this->response_json($this->theService->customerTransfer());
    }

    public function presale_index(Request $request){
        $query = Customer::query(); //创建query对象

        $query->with([
            'country',
            'language',
            'pre_sale',
            'after_sale',
        ]);

        $query->joinDepartment(function($relation){
            $relation->where('relation_type', CommonMapper::PRE_SALE);
        })->filter($request->input());

        $ret = $query->dealList()->paginateFilter($request->input('limit'));
        $ret = CustomerResource::collection($ret)->resource;

        request()->merge(['meta' =>$this->number_stats()]);
        
        return $this->wrapResponse($ret);
    }

    public function aftersale_index(Request $request){
        $query = Customer::query(); //创建query对象

        $query->with([
            'country',
            'language',
            'pre_sale',
            'after_sale',
        ]);

        $ret = $query->dealList()->paginateFilter($request->input('limit'));
        $ret = CustomerResource::collection($ret)->resource;

        request()->merge([
            'meta' => [
                'number_stats' => [
                    "customer_num_total" => 0,
                    "customer_distribute_no" => 0,
                    "customer_distribute_yes" => 0,
                    "clue_num_total" => 0,
                    "clue_distribute_no" => 0,
                    "clue_distribute_yes" => 0,
                    "clue_no_dealwith" => 0,
                    "clue_dealwith" => 0
                ]
            ]
        ]);
        
        return $this->wrapResponse($ret);
    }


    private function number_stats($relation_type = 1)
    {
        if($relation_type = CommonMapper::PRE_SALE){
            $stats = [];
        }else{
            $stats = [
                "customer_num_total" => 0,
                "customer_distribute_no" => 0,
                "customer_distribute_yes" => 0,
                "clue_num_total" => 0,
                "clue_distribute_no" => 0,
                "clue_distribute_yes" => 0,
                "clue_no_dealwith" => 0,
                "clue_dealwith" => 0
            ];
        }
        

        $ret['number_stats'] = $stats;

        return $ret;
    }
}
