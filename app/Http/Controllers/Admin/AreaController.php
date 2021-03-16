<?php
/**
 * AreaController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CommonController;
use App\Http\Requests\Area\CityIndexRequest;
use App\Http\Requests\Area\DistrictIndexRequest;
use App\Http\Requests\Area\IndexRequest;
use App\Http\Requests\Area\PostCodeIndexRequest;
use App\Http\Requests\Area\StateIndexRequest;
use App\Mappers\CommonMappper;
use App\Models\Admin\Area;
use App\Models\Admin\Country;
use App\Models\Admin\PostCode;
use App\Services\Admin\AreaService;


class AreaController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new AreaService();
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

    public function getStateByCountryId(StateIndexRequest $request) {
        $code = $request->country->simple_code;
        $ret = Area::state()->where('country_code', $code)->get();

        return $this->wrapResponse($ret);
    }

    public function getCityByStateName(CityIndexRequest $request) {
        
        $ret = Area::city()->where('parent_id',$request->state->id)->get();

        return $this->wrapResponse($ret);
    }

    public function getDistrictByCityName(DistrictIndexRequest $request) {
        $ret = Area::district()->where('parent_id',$request->city->id)->get();

        return $this->wrapResponse($ret);
    }

    public function getPostCodeByDistrictName(PostCodeIndexRequest $request) {
        $ret = PostCode::where('area_id',$request->district->id)->get();

        return $this->wrapResponse($ret);
    }


}
