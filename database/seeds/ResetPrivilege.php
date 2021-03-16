<?php

use App\Models\Admin\SysPrivilege;
use App\Models\Admin\SysRouting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResetPrivilege extends Seeder
{
    private $privileges = [
        [
            'name' => '售前', 'code' => 'presale', 'is_menu' => true,
            'children' => [
                [
                    'name' => '售前控制面板', 'code' => 'panel', 'is_menu' => true,
                    'routes' => [
                        '售前控制面板' => 'api.presale.main.panel',
                    ]
                ],
                [
                    'name' => '订单管理', 'code' => 'order', 'is_menu' => true,
                    'children' => [
                        [
                            'name' => '订单列表', 'code' => 'list', 'is_menu' => true,
                            'routes' => [
                                '订单列表' => 'api.presale.order.advertise.index',
                            ],
                            'children' => [
                                [
                                    'name' => '未审核订单', 'code' => 'audit_not', 'is_menu' => true,
                                    'routes' => [
                                        '未审核订单' => 'api.presale.order.audit_not.index',
                                    ]
                                ],
                                [
                                    'name' => '已审核订单', 'code' => 'audited', 'is_menu' => true,
                                    'routes' => [
                                        '已审核订单' => 'api.presale.order.audited.index ',
                                    ]
                                ],
                                [
                                    'name' => '未分配订单', 'code' => 'distribute_not', 'is_menu' => true,
                                    'routes' => [
                                        '未分配订单' => 'api.presale.order.distribute_not.index ',
                                    ]
                                ],
                                [
                                    'name' => '已分配订单', 'code' => 'distributed', 'is_menu' => true,
                                    'routes' => [
                                        '已分配订单' => 'api.presale.distributed.index',
                                    ]
                                ]
                            ],
                        ],
                        [
                            'name' => '手工下单', 'code' => 'manual', 'is_menu' => true,
                            'routes' => [
                                '手工下单' => 'api.presale.order.manual.index',
                            ]
                        ],
                        [
                            'name' => '重复订单', 'code' => 'repeat', 'is_menu' => true,
                            'routes' => [
                                '重复订单' => 'api.presale.order.repeat.index',
                            ]
                        ],
                        [
                            'name' => '无效订单', 'code' => 'invalid', 'is_menu' => true,
                            'routes' => [
                                '无效订单' => 'api.presale.order.invalid.index',
                            ]
                        ],
                        [
                            'name' => '异常订单', 'code' => 'abnormal', 'is_menu' => true,
                            'routes' => [
                                '异常订单' => 'api.presale.order.abnormal.index',
                            ],
                            'children' => [
                                [
                                    'name' => '未处理异常订单', 'code' => 'no_dealwith', 'is_menu' => true,
                                    'routes' => [
                                        '未处理异常订单' => 'api.presale.order.abnormal_no_dealwith.index',
                                    ]
                                ],
                                [
                                    'name' => '已处理异常订单', 'code' => 'dealwith', 'is_menu' => true,
                                    'routes' => [
                                        '已处理异常订单' => 'api.presale.order.abnormal_dealwith.index',
                                    ]
                                ]
                            ],
                        ],
                        [
                            'name' => '取消订单申请列表', 'code' => 'askforcancel', 'is_menu' => true,
                            'routes' => [
                                '取消订单申请列表' => 'api.presale.order.askforcancel.index',
                            ],
                            'children' => [
                                [
                                    'name' => '待处理申请', 'code' => 'no_dealwith', 'is_menu' => true,
                                    'routes' => [
                                        '待处理申请' => 'api.presale.order.askforcancel_no_dealwith.index',
                                    ]
                                ],
                                [
                                    'name' => '取消成功订单', 'code' => 'cancel_succ', 'is_menu' => true,
                                    'routes' => [
                                        '取消成功订单' => 'api.presale.order.askforcancel_cancel_succ.index',
                                    ]
                                ],
                                [
                                    'name' => '取消失败订单', 'code' => 'cancel_fail', 'is_menu' => true,
                                    'routes' => [
                                        '取消失败订单' => 'api.presale.order.askforcancel_cancel_fail.index',
                                    ]
                                ],
                                [
                                    'name' => '取消订单申请归档', 'code' => 'place_on', 'is_menu' => true,
                                    'routes' => [
                                        '取消订单申请归档' => 'api.presale.order.askforcancel_place_on.index',
                                    ]
                                ]
                            ],
                        ],
                    ],
                ],
                [
                    'name' => '报表管理', 'code' => 'report', 'is_menu' => true,
                    'children' => [
                        [
                            'name' => '售前订单报表', 'code' => 'list', 'is_menu' => true,
                            'routes' => [
                                '售前订单报表' => 'api.presale.order.report.list',
                            ]
                        ]
                    ],
                ],
                [
                    'name' => '客户管理', 'code' => 'customer', 'is_menu' => true,
                    'children' => [
                        [
                            'name' => '客户列表', 'code' => 'index', 'is_menu' => true,
                            'routes' => [
                                '客户列表' => 'api.customer.index',
                            ]
                        ]
                    ],
                ]
            ],
        ],
        [
            'name' => '售后', 'code' => 'aftersale', 'is_menu' => true,
            'children' => [
                [
                    'name' => '售后控制面板', 'code' => 'panel', 'is_menu' => true,
                    'routes' => [
                        '售后控制面板' => 'api.aftersale.main.panel',
                    ]
                ],
                [
                    'name' => '订单管理', 'code' => 'order', 'is_menu' => true,
                    'children' => [
                        [
                            'name' => '手工下单', 'code' => 'manual', 'is_menu' => true,
                            'routes' => [
                                '手工下单' => 'api.aftersale.manual.order.index',
                            ]
                        ],
                        [
                            'name' => '审核订单', 'code' => 'audit', 'is_menu' => true,
                            'routes' => [
                                '审核订单' => 'api.aftersale.audit.order.index',
                            ],
                            'children' => [
                                [
                                    'name' => '待审核订单', 'code' => 'audit_not', 'is_menu' => true,
                                    'routes' => [
                                        '待审核订单' => 'api.aftersale.audit_not.order.index',
                                    ]
                                ],
                                [
                                    'name' => '已审核订单', 'code' => 'audited', 'is_menu' => true,
                                    'routes' => [
                                        '已审核订单' => 'api.aftersale.audited.order.index',
                                    ]
                                ],
                                [
                                    'name' => '已驳回订单', 'code' => 'reject', 'is_menu' => true,
                                    'routes' => [
                                        '已驳回订单' => 'api.aftersale.reject.order.index',
                                    ]
                                ]
                            ],
                        ],
                        [
                            'name' => '异常订单', 'code' => 'abnormal', 'is_menu' => true,
                            'routes' => [
                                '异常订单' => 'api.aftersale.abnormal.order.index',
                            ],
                            'children' => [
                                [
                                    'name' => '未处理异常订单', 'code' => 'no_dealwith', 'is_menu' => true,
                                    'routes' => [
                                        '未处理异常订单' => 'api.aftersale.abnormal_no_dealwith.order.index',
                                    ]
                                ],
                                [
                                    'name' => '已处理异常订单', 'code' => 'dealwith', 'is_menu' => true,
                                    'routes' => [
                                        '已处理异常订单' => 'api.aftersale.abnormal_dealwith.order.index',
                                    ]
                                ]
                            ],
                        ],
                        [
                            'name' => '取消订单申请列表', 'code' => 'askforcancel', 'is_menu' => true,
                            'routes' => [
                                '取消订单申请列表' => 'api.presale.order.askforcancel.list',
                            ],
                            'children' => [
                                [
                                    'name' => '待处理申请', 'code' => 'no_dealwith', 'is_menu' => true,
                                    'routes' => [
                                        '待处理申请' => 'api.aftersale.askforcancel_no_dealwith.order.index',
                                    ]
                                ],
                                [
                                    'name' => '取消成功订单', 'code' => 'cancel_succ', 'is_menu' => true,
                                    'routes' => [
                                        '取消成功订单' => 'api.aftersale.askforcancel_cancel_succ.order.index',
                                    ]
                                ],
                                [
                                    'name' => '取消失败订单', 'code' => 'cancel_fail', 'is_menu' => true,
                                    'routes' => [
                                        '取消失败订单' => 'api.aftersale.askforcancel_cancel_fail.order.index',
                                    ]
                                ],
                                [
                                    'name' => '取消订单申请归档', 'code' => 'place_on', 'is_menu' => true,
                                    'routes' => [
                                        '取消订单申请归档' => 'api.aftersale.askforcancel_place_on.order.index',
                                    ]
                                ]
                            ],
                        ],
                    ],
                ],
                [
                    'name' => '报表管理', 'code' => 'report', 'is_menu' => true,
                    'children' => [
                        [
                            'name' => '售后订单报表', 'code' => 'list', 'is_menu' => true,
                            'routes' => [
                                '售后订单报表' => 'api.aftersale.order.report.list',
                            ]
                        ]
                    ],
                ],
                [
                    'name' => '客户管理', 'code' => 'customer', 'is_menu' => true,
                    'children' => [
                        [
                            'name' => '客户列表', 'code' => 'list', 'is_menu' => true,
                            'routes' => [
                                '客户列表' => 'api.aftersale.customer.index',
                            ],
                            'children' => [
                                [
                                    'name' => '未分配客户', 'code' => 'distribute_not', 'is_menu' => true,
                                    'routes' => [
                                        '未分配客户' => 'api.aftersale.customer_distribute_not',
                                    ]
                                ],
                                [
                                    'name' => '已分配客户', 'code' => 'distributed', 'is_menu' => true,
                                    'routes' => [
                                        '已分配客户' => 'api.aftersale.customer_distributed',
                                    ]
                                ]
                            ],
                        ],
                        [
                            'name' => '线索列表', 'code' => 'clue', 'is_menu' => true,
                            'routes' => [
                                '线索列表' => 'api.aftersale.customer_clue.index',
                            ],
                            'children' => [
                                [
                                    'name' => '未分配线索', 'code' => 'distribute_not', 'is_menu' => true,
                                    'routes' => [
                                        '未分配线索' => 'api.aftersale.customer_clue.distribute_not_list',
                                    ]
                                ],
                                [
                                    'name' => '已分配线索', 'code' => 'distributed', 'is_menu' => true,
                                    'routes' => [
                                        '已分配线索' => 'api.aftersale.customer_clue.distributed_list',
                                    ]
                                ],
                                [
                                    'name' => '未处理线索', 'code' => 'no_dealwith', 'is_menu' => true,
                                    'routes' => [
                                        '未处理线索' => 'api.aftersale.customer_clue.no_dealwith_list',
                                    ]
                                ],
                                [
                                    'name' => '已处理线索', 'code' => 'dealwith', 'is_menu' => true,
                                    'routes' => [
                                        '已处理线索' => 'api.aftersale.customer_clue.dealwith_list',
                                    ]
                                ],
                                [
                                    'name' => '归档线索', 'code' => 'finished', 'is_menu' => true,
                                    'routes' => [
                                        '归档线索' => 'api.aftersale.customer_clue.finished',
                                    ]
                                ]
                            ],
                        ]
                    ],
                ],
                [
                    'name' => '商品管理', 'code' => 'goods', 'is_menu' => true,
                    'children' => [
                        [
                            'name' => '商品列表', 'code' => 'list', 'is_menu' => true,
                            'routes' => [
                                '商品列表' => 'api.goods.index',
                            ]
                        ],
                        [
                            'name' => '活动管理', 'code' => 'promotions', 'is_menu' => true,
                            'children' => [
                                [
                                    'name' => '折扣活动列表', 'code' => 'index', 'is_menu' => true,
                                    'routes' => [
                                        '折扣活动列表' => 'api.system.promotions.index',
                                    ]
                                ],
                            ]
                        ]
                    ],
                ]
            ],
        ],
        [
            'name' => '系统', 'code' => 'system', 'is_menu' => true,
            'children' => [
                [
                    'name' => '部门管理', 'code' => 'department', 'is_menu' => true,
                    'routes' => [
                        '部门管理' => 'api.system.department.index',
                    ]
                ],
                [
                    'name' => '员工管理', 'code' => 'user', 'is_menu' => true,
                    'routes' => [
                        '员工管理' => 'api.system.user.list',
                    ]
                ],
                [
                    'name' => '角色管理', 'code' => 'role', 'is_menu' => true,
                    'routes' => [
                        '角色管理' => 'api.system.role.index',
                    ]
                ]
            ]
        ],
        [
            'name' => '修改密码', 'code' => 'set_password',
            'routes' => [
                '修改密码' => 'api.system.user.set_password',
            ]
        ]
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        SysPrivilege::truncate();
        SysRouting::truncate();

        DB::beginTransaction();
        try {
            foreach ($this->privileges as $privilege) {
                $this->recur($privilege);
            }
        } catch (Exception $e) {
            DB::rollBack();
            echo $e->__toString() . "\n";
            return;
        }
        DB::commit();
    }

    /**
     * 递归的添加权限和路由
     */
    private function recur(array $element, $parentId = 0)
    {
        $isMenu = 0;
        if (
            array_key_exists('is_menu', $element)
            && $element['is_menu']
        ) {
            $isMenu = 1;
        }
        $status = 1;
        if (
            array_key_exists('status', $element)
            && !$element['status']
        ) {
            $status = 0;
        }
        $privilege = SysPrivilege::create([
            'parent_id' => $parentId,
            'name' => $element['name'],
            'code' => $element['code'],
            'sort_no' => 0,
            'is_menu' => $isMenu,
            'icon' => '',
            'status' => $status,
            'creator_id' => 0,
            'created_time' => date('Y-m-d H:i:s'),
            'updator_id' => 0,
            'updated_time' => date('Y-m-d H:i:s'),
        ]);
        if (
            array_key_exists('routes', $element)
            && is_array($element['routes'])
        ) {
            foreach ($element['routes'] as $name => $route) {
                SysRouting::create([
                    'name' => $name,
                    'url' => $route,
                    'privilege_id' => $privilege->id,
                    'sort_no' => 0,
                    'creator_id' => 0,
                    'created_time' => date('Y-m-d H:i:s'),
                    'updator_id' => 0,
                    'updated_time' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        if (
            array_key_exists('children', $element)
            && is_array($element['children'])
        ) {
            foreach ($element['children'] as $child) {
                $this->recur($child, $privilege->id);
            }
        }
    }
}
