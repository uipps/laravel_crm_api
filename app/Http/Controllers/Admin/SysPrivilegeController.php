<?php
/**
 * SysPrivilegeController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CommonController;
use App\Http\Controllers\Responsable;
use App\Models\Admin\SysPrivilege;
use App\Services\Admin\SysPrivilegeService;


class SysPrivilegeController extends CommonController
{
    use Responsable;
    
    protected $theService;

    public function __construct() {
        $this->theService = new SysPrivilegeService();
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



    public function tree()
    {
        $this->privileges = SysPrivilege::with('roles')
            ->active()->get();
        
        $children = $this->recur();

        return $this->wrapResponse(['list' => $children]);
    }

    /**
     * 根据 parentId 递归生成子集合
     * 
     * @param int $parentId
     * @return array
     */
    private function recur($parentId = 0)
    {
        $children = [];
        foreach ($this->privileges as $privilege) {
            if ($privilege->parent_id == $parentId) {
                $privilege->append('is_permitted');
                $privilege = $privilege->makeHidden(['roles'])->toArray();
                $privilege['children'] = $this->recur($privilege['id']);
                $children[] = $privilege;
            }
        }
        return $children;
    }

}
