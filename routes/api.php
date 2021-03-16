<?php

use App\Mappers\RouteMapper;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// 统一前缀v1版本，都需要记录sql和post的中间件
Route::group(['prefix'=>'v1'], function () {

    // 不需要jwt认证
    Route::middleware('api')->group(function () {
        Route::group(['namespace'=>'Admin'],function () {
            Route::post('user/login', 'UserController@loginAdmin')->name('login');
            Route::get('jwt/detail', 'UserController@parseJwt')->name('jwt-parse'); // 小工具

            // 语言列表、国家、货币、省市区等列表后面可能直接调用公共接口
            Route::resource('language', 'LanguageController');

            // 国家
            Route::resource('country', 'CountryController');

            Route::group(['prefix'=>'area', 'as'=>'.area'], function () {
                // 省份列表, 通过国家ID获取省份列表
                Route::get('state', 'AreaController@getStateByCountryId');
                Route::get('city', 'AreaController@getCityByStateName');
                Route::get('district', 'AreaController@getDistrictByCityName');
                Route::get('post_code', 'AreaController@getPostCodeByDistrictName');
            });
        });

        Route::group(['namespace'=>'Common'],function () {
            Route::get('getverifycode', 'CaptchaCodeController@getVerifyCode');
            Route::any('checkverifycode', 'CaptchaCodeController@checkVerifyCode');
            Route::get('fieldmap', 'FieldMapController@fieldsMap'); // 字段各可选值MAP

            Route::post('/uploadpic', 'UploadFileController@uploadPic');    // 图片上传
            Route::get('/getpic/{fileKey}', 'UploadFileController@getPic');   // 查看图片
            //Route::get('/download', 'UploadFileController@download');   // excel文件下载测试
        });
        // 测试mq
        Route::get('test/mq', 'TestController@mq');
        
    });

    // 其他都需要jwt认证
    Route::middleware(['auth:api', 'permit'])->group(function () {
        // 售前
        Route::group(['as' => '.presale', 'namespace'=>'OrderPreSale'],function () {
            // 控制面板 - 售前
            Route::get('main/panel', 'MainController@dashboard')->name('.main.panel');
        });

        // 售后
        Route::group(['prefix'=>'aftersale', 'as' => '.aftersale', 'namespace'=>'OrderAfterSale'], function() {
            // 控制面板 - 售后
            Route::get('/main/panel', 'MainController@dashboard')->name('.main.panel');
        });

        // 系统
        Route::group(['as' => '.system'], function() {
            Route::group(['namespace'=>'Admin'],function () {

                // 员工列表、详情、增加、修改、删除
                //Route::resource('user', 'UserController'); TODO
                Route::group(['prefix'=>'user', 'as'=>'.user'], function () {
                    Route::get('/', 'UserController@index')->name('.list');
                    Route::get('/{id}', 'UserController@show')->name('.detail')->where('id', '[0-9]+'); // 避免路由混淆，强制类型匹配
                    Route::post('/', 'UserController@store')->name('.add');
                    Route::put('/{id}', 'UserController@update')->name('.edit')->where('id', '[0-9]+');
                    Route::delete('/{id}', 'UserController@destroy')->name('.delete');

                    Route::get('/my/', 'UserController@getMySubordinate')->name('.my_subordinate'); // 非所有子部门下属,当前部门直接下属
                    Route::get('/logout', 'UserController@logout')->name('logout');
                    Route::get('/me', 'UserController@me'); // 当前用户
                    Route::put('/setpassword', 'UserController@setPassword')->name('.set_password');
                    Route::put('/change_language', 'UserController@changeWebLanguage');
                    Route::get('/page_action', 'UserController@pageAction')->name('.notify'); // 页面操作事件通知
                    Route::put('/receive_order/', 'UserController@receiveOrder')->name('.receive_order'); // 开始接单/停止接单

                    // 手工下单，选择客服
                    Route::get('/order/manual/', 'UserController@index')->name('.order_manual_user');
                });

                // 部门管理
                // Route::get('/department/my/', 'DepartmentController@myDepartmentList')->name('.my_dept');

                // 部门的分单比例设置
                Route::any('department_weight/get_rate', 'DepartmentWeightController@getDeptOrderRateByCountry')->name('.get-Dept-rate');

                // 系统权限管理
                //Route::resource('sys_privilege', 'SysPrivilegeController');
                Route::group(['prefix'=>'sys_privilege', 'as'=>'.sys_privilege'], function () {
                    Route::get('/', 'SysPrivilegeController@tree')->name('.tree');
                });

                // 系统配置
                //Route::resource('sys_config', 'SysConfigController');

                // 接单超时时间设置
                Route::group(['prefix'=>'timeout_info', 'as'=>'.timeout_info'], function () {
                    Route::get('/', 'SysConfigController@getTimeoutInfo')->name('.detail');
                    Route::put('/{id}', 'SysConfigController@updateOne')->name('.edit');
                });

                // 查询子部门可选国家列表
                Route::get('country/department/{id}', 'CountryController@getListByParentDeptId')->name('.country.department.list')->where('id', '[0-9]+');
                Route::group(['as' => '.'], function(){
                    Route::resource('department', 'DepartmentController');
                    // 角色
                    Route::resource('role', 'RoleController');

                    // 优惠活动
                    Route::resource('promotions', 'PromotionsController');
                    // 查询当前列表
                    Route::get('promotions_able', 'PromotionsController@getListActive');
                    // 查询订单的优惠列表
                    Route::get('order_promotions', 'PromotionsController@order_promotions');
                    Route::get('department_able', 'DepartmentController@index');
                    Route::get('user_able', 'UserController@index');
                    Route::get('role_able', 'RoleController@index');
                });

            });

        });


        Route::group(['namespace'=>'Customer'],function () {
            // 员工关联客户
            Route::group(['prefix'=>'user_customer', 'as'=>'.user_customer'], function () {
                Route::get('/', 'CustomerController@getListByUser')->name('.user_customer_list');
                Route::get('/{id}', 'CustomerServiceRelationController@detail')->name('.detail');
                Route::post('/', 'CustomerController@customerTransfer')->name('.customer_transfer');
                Route::put('/{id}', 'CustomerServiceRelationController@updateOne')->name('.edit');
                Route::delete('/{id}', 'CustomerServiceRelationController@delete')->name('.delete');
            });

            Route::group(['prefix'=>'customer_address', 'as'=>'.customer_address'], function () {
                Route::get('/customer/{id}', 'CustomerAddressController@getListByCid')->name('.by_customer_id_list');
                Route::get('/customer/{id}/all', 'CustomerAddressController@getListByCidAll')->name('.by_customer_id_list.all'); // 全部返回不需要分页
            });

            Route::group(['as' => '.'], function(){
               
                Route::get('customer', 'CustomerController@presale_index');
                // 测试客户列表
                Route::get('customer_presale', 'CustomerController@index');
                Route::get('customer/{id}', 'CustomerController@show');

                // 客户收货地址 customer_address
                Route::resource('customer_address', 'CustomerAddressController');
                Route::resource('customer_label', 'CustomerLabelController');
                Route::resource('customer_remark', 'CustomerRemarkController');
            });

        });

        // 售前
        Route::group(['as' => '.presale'], function() {

            // 订单
            Route::group(['namespace'=>'OrderPreSale'],function () {
                // 查看物流异常
                Route::get('/order/abnormal/shipping/{id}', 'OrderAbnormalController@shipping');

                // TODO 临时，员工关联订单数据
                Route::group(['prefix'=>'user_order', 'as'=>'.user_order'], function () {
                    Route::get('/', 'OrderController@getListByUser')->name('.list');
                    Route::post('/', 'OrderController@orderTransfer')->name('.order_transfer');
                });

                // 售前客服拨打电话, 呼起指定的sipno电脑上的网络电话软件
                Route::group(['prefix'=>'call', 'as'=>'.call'], function () {
                    Route::get('/', 'OrderCallRecordController@callSomeBody')->name('.list');
                });

                // 客户的订单列表
                Route::get('/order/customer/{id}', 'OrderController@customerIdOrderList')->name('.order.customer.list');

                // 分配订单-撤销分配
                Route::put('/order/distribute/', 'OrderDistributeController@distributeOrder')->name('.order.distribute_order');

                Route::group(['prefix'=>'order', 'as'=>'.order'], function () {
                    // 客户的订单列表，所有客户的订单列表，单个客户的订单列表
                    // Route::get('/customer', 'OrderController@customerOrderList')->name('.order.customer.list'); // 所有订单，只要客户id大于0的

                    // 订单报表，包括导出功能，接口一样，用参数区分
                    Route::group(['prefix'=>'report', 'as'=>'.report'], function () {
                        Route::get('/', 'OrderReportController@list')->name('.list'); // TODO 路径改一下
                        Route::get('/export', 'OrderReportController@list')->name('.list.export');
                        // 测试
                        Route::get('/list', 'OrderReportController@list')->name('.list.export');
                        //Route::get('/{id}', 'OrderController@detail')->name('.detail')->where('id', '[0-9]+');
                    });
                });

                // TODO 订单操作记录，同详情接口形式类似
                Route::group(['prefix'=>'/order/opt_record', 'as'=>'.opt_record'], function () {
                    Route::get('/{id}', 'OrderOptRecordController@getListByOrderId')->name('.list')->where('id', '[0-9]+');
                 });

                // 取消订单归档
                Route::put('/order/askforcancel/archive', 'AccessOrderController@cancel_order_archive')->name('.askforcancel.archive');

                // 设置重复单
                Route::put('/order/repeat_setting', 'AccessOrderController@repeat_setting')->name('.repeat_setting');

                // 设置重复单
                Route::get('/order/repeat_list/{id}', 'AccessOrderController@repeat_list')->name('.repeat_list');

                // 在未分配列表，主管点击开始分单按钮
                Route::post('/manager/start_distribute', 'AccessOrderController@manager_start_distribute')->name('.manager.start_distribute');

                Route::put('/order/replenish_able/{id}', 'AccessOrderController@replenishAbleUpdate')->name('.replenish_able.update');

                Route::put('/order/abnormal_redelivery_able/{id}', 'AccessOrderController@abnormalRedeliveryAbleUpdate')->name('.abnormal_redelivery_able.update');

                // 新的路由写法
                $orderTypeArr = array_keys(RouteMapper::orderPreSale());
                foreach($orderTypeArr as $type) {
                    $name = '.order.';
                    $path = 'order/' . $type;

                    Route::group(['as' => $name], function() use($path){
                        Route::resource($path, 'AccessOrderController');
                    });
                }

                

            });

        });

        // 售后
        Route::group(['prefix'=>'aftersale', 'as' => '.aftersale'], function() {
            // 订单
            Route::group(['namespace'=>'OrderAfterSale'],function () {
                Route::put('/replenish_able/order/{id}', 'AccessOrderController@replenishAbleUpdate')->name('.replenish_able.update');

                Route::put('/abnormal_redelivery_able/order/{id}', 'AccessOrderController@abnormalRedeliveryAbleUpdate')->name('.replenish_able.update');

                // 新的路由写法
                $orderTypeArr = array_keys(RouteMapper::orderAfterSale());
                foreach($orderTypeArr as $type) {
                    $name = '.'.$type.'.';
                    $path = $type.'/order';

                    Route::group(['as' => $name], function() use($path){
                        Route::resource($path, 'AccessOrderController');
                    });

                }

                // 取消订单归档
                Route::put('/askforcancel/archive', 'AccessOrderController@cancel_order_archive')->name('.askforcancel.archive');

                // 主管审核驳回订单
                Route::put('/charge/audit', 'AccessOrderController@charge_audit')->name('.charge.audit');

                // 售后订单报表，包括导出功能，接口一样，用参数区分
                Route::group(['prefix'=>'order', 'as'=>'.order'], function () {
                    Route::group(['prefix'=>'report', 'as'=>'.report'], function () {
                        Route::get('/', 'OrderReportController@list')->name('.list');
                        Route::get('/export', 'OrderReportController@list')->name('.list.export');
                        // 测试
                        Route::get('/list', 'OrderReportController@list')->name('.list.export');
                    });
                });
            });

            Route::group(['namespace'=>'Customer'],function () {

                // 客户线索
                Route::group(['prefix'=>'customer_clue', 'as'=>'.customer_clue'], function () {
                    Route::post('/distribute', 'CustomerClueController@distribute')->name('.distribute');           // 分配线索

                    Route::get('/', 'CustomerClueController@index')->name('._list');
                    Route::get('/distribute_not', 'CustomerClueController@distributeNotList')->name('.distribute_not_list');// 未分配
                    Route::get('/distributed', 'CustomerClueController@distributedList')->name('.distributed_list');        // 已分配
                    Route::get('/no_dealwith', 'CustomerClueController@noDealwithList')->name('.no_dealwith_list');     // 未处理
                    Route::get('/dealwith', 'CustomerClueController@dealwithList')->name('.dealwith_list');             // 已处理
                    Route::get('/finished', 'CustomerClueController@finished')->name('.finished');                 // 归档
                });

                Route::group(['as'=>'.'], function(){
                    Route::resource('customer', 'CustomerController');
                    Route::resource('customer_label', 'CustomerLabelController');
                    Route::resource('customer_remark', 'CustomerRemarkController');
                    Route::resource('customer_clue', 'CustomerClueController');
                    Route::resource('customer_distribute', 'CustomerDistributeController');
                    // 不需要配置权限的路由
                    Route::get('customer_clue_able', 'CustomerClueController@getAbleClue');
                });

                Route::get('customer_distributed', 'CustomerController@distributed')->name('.customer_distributed');                 // 已分配客户
                Route::get('customer_distribute_not', 'CustomerController@distributeNot')->name('.customer_distribute_not');         // 未分配客户

                // 线索追踪记录
                Route::group(['prefix'=>'customer_clue_track', 'as'=>'.customer_clue_track'], function () {
                    Route::get('/{id}', 'CustomerClueTrackController@getListByClueId')->name('.list')->where('id', '[0-9]+');
                    Route::post('/', 'CustomerClueTrackController@store')->name('.add');
                });

            });
        });



        Route::group(['namespace'=>'Goods'],function () {
            Route::group(['as'=>'.'], function(){
                Route::resource('goods', 'GoodsInfoController');
                Route::resource('goods_sku', 'GoodsInfoSkuController');     // 可选商品sku列表，只展示状态为非删除的
            });

            Route::group(['prefix'=>'goods', 'as'=>'.goods'], function () {
                Route::get('/erp_product/{id}', 'GoodsInfoController@erpProductDetail')->name('.detail')->where('id', '[0-9]+'); // 拉取erp商品信息
            });
        });



    });
});
