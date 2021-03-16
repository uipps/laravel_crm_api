<?php

namespace App\Models\Admin;

use App\Mappers\CommonMapper;
use App\Models\Traits\SoftDeletesEx;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    use SoftDeletesEx;
    const DELETED_AT = 'deleted_time';

    const STATUS_INVALID = 0;
    const STATUS_NORMAL = 1;
    const STATUS_DELETE = -1;

    const ORDER_STATUS_CLOSE = 0;   // 接单状态：0-停止接单, 1-开始接单
    const ORDER_STATUS_OPNE = 1;
    const ORDER_STATUS_DELETE = -1;

    const LEVEL_ADMIN = 1; // 岗位类型 1-管理 2-员工
    const LEVEL_STAFF = 2; // 岗位类型 1-管理 2-员工

    use Notifiable;

    //const CREATED_AT = 'created_time';
    //const UPDATED_AT = 'updated_time';

    protected $table = 'user'; // 默认是model名后加s

    public $timestamps = false;

    protected $fillable = [
        'real_name',                            // 姓名
        'email',                                // 邮箱
        'department_id',                        // 部门id
        'password',                             // 密码
        'phone',                                // 手机
        'level',                                // 岗位 1管理员,2员工
        'status',                               // 状态 0关闭1开启-1已删除
        'remember_token',                       // 用户令牌
        'order_status',                         // 接单状态 0关闭1开启
        'order_failure_time',                   // 接单失效时间
        'last_login_ip',                        // 最后登录ip
        'last_login_time',                      // 最后登录时间
        'is_clue_sale',                         // 是否线索客服 0不是 1是
        'web_language',                         // 用户切换的语言, 默认英文
        'creator_id',                           // 创建人 创建者 id；默认 0，意为系统创建或没有创建者
        'updator_id',                           // 修改人 最后一次编辑记录的用户 id；默认值意义同 creator_id 字段
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
        'deleted_time',                         // 删除时间
        'erp_id',
    ];

    protected $hidden = [
        'password', 'remember_token', 'updator_id', 'updated_time', 'deleted_time',
    ];


    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        //echo __LINE__ . "\r\n<br>";
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return ['xxx'=> \createTimeSequenceNo()]; // payload中会增加一些字段值，如['xxx'=> access_session_id(), 'bbb'=>'']等
    }

    /*public function fromDateTime($value) {
        return date('Y-m-d H:i:s', $value);
    }
    protected function asDateTime($value) {
        return $value;
    }*/

    public function role()
    {
        return $this
            ->hasOneThrough(
                Role::class,
                UserAttr::class,
                'user_id',
                'id',
                'id',
                'work_id'
            )
            ->where(
                'user_attr.type',
                CommonMapper::USER_ATTR_TYPE_ROLE
            );
    }


    public function roles()
    {
        return $this
            ->belongsToMany(Role::class, 'user_attr', 'user_id', 'work_id')
            ->wherePivot('type', CommonMapper::USER_ATTR_TYPE_ROLE);
    }


    public function manager()
    {
        return $this->belongsTo(User::class, 'department_id', 'department_id')->where('level', 1);
    }

    // 部门
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    // 主管才能接单，员工不能接单
    public function getReceiveStatusAttribute()
    {
        $status = false;
        $department = $this->department;

        if($this->level == CommonMapper::LEVEL_SERVICE && $department && $department->job_type == CommonMapper::PRE_SALE){
            $status = true;
        }

        return $status;
    }
    // 判断用户的job_type
    public function getJobTypeAttribute()
    {
        if($this->is_super){
            return 0;
        }

        if($this->department){
            return $this->department->job_type;
        }

        return 3;
    }

    public function getIsSuperAttribute()
    {
        if(!$this->role) return false;
        
        return $this->role->is_super;
    }

    public function getIsFinalDepartmentAttribute()
    {
        $count = Department::where('parent_id', $this->department_id)->count();
        
        return $count ? false : true;
    }

}
