<?php
/**
 * FieldMapController
 * @author dev@xhat.com
 * @since 2020-04-09
 */
namespace App\Http\Controllers\Common;

use App\Http\Controllers\CommonController;
use App\Services\Common\FieldMapService;


class FieldMapController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new FieldMapService();
        parent::__construct();
    }

    public function fieldsMap() {
        return $this->response_json($this->theService->fieldsMap(), JSON_FORCE_OBJECT); // 这个要求0数字索引转为对象
    }
}
