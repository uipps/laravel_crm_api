<?php

namespace App\Repositories\Admin;

use App\Models\Admin\CallUserConfig;
use App\Repositories\BaseRepository;

class CallUserConfigRepositoryImpl extends BaseRepository
{
    protected $model ;

    public function __construct() {
        $this->model = new CallUserConfig();
    }

    // 批量插入
    public function insertMultipleCallUserConfigByCountryId($user_id, $department_weight_info, $create_time, $creator_id) {
        if (!$department_weight_info) return 1;

        $insert_arr = [];
        $row['user_id'] = $user_id;
        $row['account_id'] = 1;     // TODO 当前只有一个 call_account,
        $row['status'] = 1;         // 默认启用
        $row['created_time'] = $create_time ;
        $row['updated_time'] = $row['created_time'];

        foreach ($department_weight_info as $weight_info) {
            $row['country_id'] = $weight_info['country_id'];
            $insert_arr[] = $row;
        }
        $rlt = $this->model->insert($insert_arr);
        return $rlt;
    }

}
