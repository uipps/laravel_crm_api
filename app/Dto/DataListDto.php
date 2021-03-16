<?php

namespace App\Dto;

class DataListDto extends BaseDto
{
    public function Assign($item) {
        parent::Assign($item);
        if (isset($item['total']))
            $this->pagination['total'] = $item['total'];
        if (isset($item['page']))
            $this->pagination['current'] = $item['page'];
        if (isset($item['limit']))
            $this->pagination['pageSize'] = $item['limit'];
    }

    public $list   = []; // 具体数据
    public $pagination = [
        'total' => 0,       // 总条数
        'current' => 0,     // 当前页码
        'pageSize' => 0,    // 每页条数
    ];
    public $meta = [];      // 额外信息，有就加，没有空着
    //public $total  = 0;  // 总条数
    //public $page   = 0;  // 页码
    //public $offset = 0;  // 偏移量
    //public $limit  = 0;  // 每页条数
}
