<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class RolePrivilege extends Model
{
    protected $table = 'role_privilege';
    public $timestamps = false;

    protected $fillable = [
        'role_id',                              // 角色id
        'privilege_id',                         // 权限id
    ];

    public static function roleCreated(Role $role, array $privilegeIds)
    {
        $roleId = $role->id;
        $closure = function ($id) use ($roleId) {
            return ['role_id' => $roleId, 'privilege_id' => $id];
        };
        $data = array_map($closure, $privilegeIds);
        RolePrivilege::insert($data);
    }

    public static function roleUpdated(Role $role, array $privilegeIds)
    {
        $presetCount = count($privilegeIds);

        $roleId = $role->id;
        $relations = RolePrivilege::where('role_id', $roleId)->get();
        $existsCount = $relations->count();

        $minCount = min($presetCount, $existsCount);
        for ($i = 0; $i < $minCount; $i++) {
            $data = ['privilege_id' => $privilegeIds[$i]];
            $relations->get($i)->update($data);
        }

        if ($presetCount != $existsCount) {
            if ($presetCount > $existsCount) {
                $closure = function ($id) use ($roleId) {
                    return ['role_id' => $roleId, 'privilege_id' => $id];
                };
                $arr = array_slice($privilegeIds, $minCount);
                $data = array_map($closure, $arr);
                RolePrivilege::insert($data);
            } else {
                $ids = $relations->slice($minCount)->pluck('id');
                RolePrivilege::whereIn('id', $ids)->delete();
            }
        }
    }

}
