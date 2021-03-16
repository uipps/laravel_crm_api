<?php
/**
 * LanguageController
 * @author dev@xhat.com
 * @since 2020-02-26
 */
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CommonController;
use App\Services\Admin\LanguageService;


class LanguageController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new LanguageService();
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

}
