<?php

namespace App\Models\Customer;

use App\ModelFilters\CustomerFilter;
use App\Models\Traits\HasActionTrigger;
use App\Models\Traits\HasAfterSale;
use App\Models\Traits\HasBase;
use App\Models\Traits\HasCountry;
use App\Models\Traits\HasLanguage;
use App\Models\Traits\HasPreSale;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static Customer joinDepartment
 */
class Customer extends Model
{
    use HasActionTrigger;
    use HasBase;
    use Filterable;
    use HasCountry, HasLanguage, HasPreSale, HasAfterSale;
    
    protected $table = 'customer';
    public $timestamps = false;

    protected $fillable = [
        'customer_name',                        // 客户名称 最近使用名称
        'tel',                                  // 电话号码
        'customer_key',                         // 生成方式为"国家id+电话号码"MD5值
        'country_id',                           // 国家id
        'pre_sale_id',                          // 售前客服id 对应用户id
        'after_sale_id',                        // 售后客服id 对应用户id
        'order_num',                            // 订单数量
        'last_contact_time',                    // 最近联系客户时间
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
        'language_id',                          // 语言id
        'source_type',                          // 来源类别,1广告2咨询3复购
        'quality_level',                        // 客户质量,1A2B3C4D
        'distribution_status',                  // 分配状态,0未分配1已分配
        'received_flag',                        // 签收标识,0未签收1已签收
    ];

    public function getLastContactTimeAttribute($value)
    {
        return $value == '0000-01-01 00:00:00' ? '' : $value;
    }

    public function setLastContactTimeAttribute($value)
    {
        if(!$value){
            $value = '0000-01-01 00:00:00';
        }

        $this->attributes['last_contact_time'] = $value;
    }

    public function modelFilter()
    {
        return $this->provideFilter(CustomerFilter::class);
    }

    public function service_relation()
    {
        return $this->hasMany(CustomerServiceRelation::class, 'customer_id');
    }

    public function scopeJoinDepartment($query, $callback = null) {

        $existsQuery = CustomerServiceRelation::departmentPemission()->whereRaw('customer_service_relation.customer_id = customer.id');
        if (!is_null($callback)){
            $callback($existsQuery);
        }

        $query->addWhereExistsQuery($existsQuery->getQuery());
        

        return $query;
    }



}
