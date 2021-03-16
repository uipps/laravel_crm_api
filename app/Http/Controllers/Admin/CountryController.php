<?php
/**
 * CountryController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CommonController;
use App\Models\Admin\Country;
use App\Services\Admin\CountryService;


class CountryController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new CountryService();
        parent::__construct();
    }

    public function index() {
        $ret = Country::active()->with('currency')->get();

        return $this->wrapResponse($ret);
        // dd($ret->toArray());
        // return $this->response_json($this->theService->getList());
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

    public function getListByParentDeptId($dept_id) {
        return $this->response_json($this->theService->getListByParentDeptId($dept_id));
    }

    public function getListCache() {
        return $this->response_json($this->theService->getList());
    }

}
