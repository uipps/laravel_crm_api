<?php
/**
 * AreaController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers;

use App\Http\Controllers\CommonController;
use App\Models\Customer\CustomerDistribute;
use App\Models\OrderPreSale\OrderAudit;

class TestController extends CommonController
{
    
    public function mq(){
        // CustomerDistribute::find(1)->update(['part' => 1, 'status'=>1]);
        OrderAudit::find(33)->update(['status' => 1]);
        return 'test mq';
    }

}
