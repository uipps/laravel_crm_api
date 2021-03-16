<?php

namespace App\Repositories;

use App\Dto\BaseDto;
use Illuminate\Support\Facades\Schema;

abstract class BaseRepository
{
    const DATETIME_NOT_NULL_DEFAULT = '0000-01-01 00:00:00';

    const PAGE_SIZE = 20;
    const PAGE_SIZE_MAX = 5000; // 每页最大条数

    // 获取字段列表
    public function getTheTableFields($model = null) {
        if (!$model)
            $field_arr = $this->model->getModel()->getFillable();
        else
            $field_arr = $model->getModel()->getFillable();
        array_unshift($field_arr, 'id');    // 开头插入id字段
        return $field_arr;
    }

    public function filterFields4InsertOrUpdate($data_arr) {
        $field_list = $this->getTheTableFields();
        foreach ($data_arr as $key => $value) {
            if (!in_array($key, $field_list))
                unset($data_arr[$key]);
        }
        return $data_arr;
    }

    public function pager($builder, $page = 1, $limit = 10, $field = ['*'], $orderBy = '', $rank = 'DESC')
    {
        $offset = ($page > 1) ? ($page - 1) * $limit : 0; // 计算得出
        //$info[BaseDto::DTO_FIELD_OFFSET] = $offset;
        $info[BaseDto::DTO_FIELD_PAGE] = $page;
        $info[BaseDto::DTO_FIELD_LIMIT] = $limit;

        // 去掉查询所有，影响性能
        $info[BaseDto::DTO_FIELD_TOTOAL] = $builder->count();

        //if ($orderBy) $builder = $builder->orderBy($orderBy, $rank);

        $info[BaseDto::DTO_FIELD_LIST] = $builder->offset($offset)->take($limit)->get($field)->toArray();

        return $info;
    }

    // 获取全部数据，但是不需要页码
    protected function listNoPager($builder, $field = ['*'], $orderBy = '', $rank = 'DESC') {
        $info[BaseDto::DTO_FIELD_PAGE] = 1;
        $info[BaseDto::DTO_FIELD_TOTOAL] = 0;
        $info[BaseDto::DTO_FIELD_LIMIT] = ($info[BaseDto::DTO_FIELD_TOTOAL] > self::PAGE_SIZE) ? $info[BaseDto::DTO_FIELD_TOTOAL] : self::PAGE_SIZE;
        //if ($orderBy) $builder = $builder->orderBy($orderBy, $rank);

        $info[BaseDto::DTO_FIELD_LIST] = $builder->get($field)->toArray();
        if ($info[BaseDto::DTO_FIELD_LIST])
            $info[BaseDto::DTO_FIELD_TOTOAL] = count($info[BaseDto::DTO_FIELD_LIST]);
        return $info;
    }

    // 多表联合查询(如：leftjoin)，需要带上表名前缀
    protected function joinTableBuild($builder, $val, $l_field, $tbl_name='') {
        if ($tbl_name) {
            $tbl_name = trim($tbl_name, ' `');
            //$tbl_name_yh = '`' . $tbl_name . '`';       // 带上引号
            $tbl_name_yh = $tbl_name;       // 带上引号
            $l_field = $tbl_name_yh . '.' . $l_field;   // 带上表名
        }

        // 针对不同的数据类型，自动拼装
        if (is_array($val)) {
            if (is_numeric($val[0])) {
                $builder = $builder->whereIn($l_field, $val);
            } else {
                if (is_array($val[1])) {
                    $not_in = strtolower($val[0]);
                    // 形如：$params['status'] = ['in', [0, 1,2]];，表示不等关系的时候
                    if ('notin' == $not_in)
                        $builder = $builder->whereNotIn($l_field, $val[1]);
                    else if ('in' == $not_in)
                        $builder = $builder->whereIn($l_field, $val[1]);
                } else {
                    // 形如：$params['status'] = ['>', 0]; , > , < , >=, <= 表示不等关系的时候
                    $builder = $builder->where($l_field, $val[0], $val[1]);
                }
            }
        } else {
            $builder = $builder->where($l_field, '=', $val);
        }
        return $builder;
    }
}
