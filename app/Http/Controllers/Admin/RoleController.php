<?php
/**
 * RoleController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\Admin;
use App\Http\Requests\Role\IndexRequest;
use App\Http\Requests\Role\StoreRequest;
use App\Http\Requests\Role\UpdateRequest;
use App\Http\Resources\RoleResource;

use App\Http\Controllers\CommonController;
use App\Mappers\CommonMapper;
use App\Models\Admin\Role;
use App\Models\Admin\RolePrivilege;
use App\Services\Admin\RoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RoleController extends CommonController
{
    // protected $theService;

    // public function __construct() {
    //     $this->theService = new RoleService();
    //     parent::__construct();
    // }

    // public function index() {
    //     return $this->response_json($this->theService->getList());
    // }

    // public function store() {
    //     return $this->response_json($this->theService->addOrUpdate());
    // }

    // public function show($id) {
    //     return $this->response_json($this->theService->detail($id));
    // }

    // public function destroy($id) {
    //     return $this->response_json($this->theService->delete($id));
    // }

    // public function update($id) {
    //     return $this->response_json($this->theService->updateOne($id));
    // }

    public function store(StoreRequest $request)
    {
        $request->merge(['status'=>1]); // 默认启用

        $role = Role::create($request->input());
        if ($request->has('privilege_ids')) {
            RolePrivilege::roleCreated($role, $request->privilege_ids);
        }
        $role->load(['privileges']);
        $resource = new RoleResource($role);
        return $this->wrapResponse($resource);
    }

    public function index(IndexRequest $request)
    {
        $level = Auth('api')->user()->level;
        $auth_flag = Auth('api')->user()->role->auth_flag;

        $query = Role::active();
        if (!$auth_flag) {
            if($level == 1){
                $query->where('auth_flag', 0);
            } else {
                $query->where('id', Auth('api')->user()->role->id);
            }
        }

        $roles = $query->orderBy('created_time', 'desc')
            ->paginate($request->page_size);
        $resource = RoleResource::collection($roles)->resource;
        return $this->wrapResponse($resource);
    }

    public function show(Role $role)
    {
        if ($role->status != CommonMapper::STATUS_SHOW ) {
            throw new ModelNotFoundException('角色以失效');
        }
        // $role->load(['privileges']);
        $privilegesIdArr = $role->privileges->pluck('id')->toArray();
        $role->role_privileges = $privilegesIdArr;

        $resource = new RoleResource($role);
        return $this->wrapResponse($resource);
    }

    public function update(Role $role, UpdateRequest $request)
    {
        $role->update($request->input());
        if ($request->has('privilege_ids')) {
            RolePrivilege::roleUpdated($role, $request->privilege_ids);
        }
        $role->load(['privileges']);
        $resource = new RoleResource($role);
        return $this->wrapResponse($resource);
    }

    public function destroy(Role $role)
    {
        if ($role->id == 1) {
            throw new AuthorizationException('此角色为初始预设角色，无法删除');
        }
        $role->update(['status' => CommonMapper::STATUS_HIDE]);
        return $this->wrapResponse(null);
    }

}
