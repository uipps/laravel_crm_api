<?php
// 批量添加erp商品，需要后端程序处理

namespace App\Jobs;

use App\Services\Goods\GoodsInfoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;


class GoodsAddMultiple implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data_arr;
    protected $goodsInfoService;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        //
        $this->data_arr = $request;
        $this->goodsInfoService = new GoodsInfoService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 批量添加商品，需要用队列处理，默认都是未启用，因为价格、名称需要再次编辑的时候进行填写！图片采用erp系统默认产品图片
        $request = $this->data_arr;
        $request['status'] = 0;         // 默认为未启用状态

        $current_uid = $request['creator_id'] ?? 0;
        $curr_datetime = $request['created_time'] ?? date('Y-m-d H:i:s');

        try {
            foreach ($request['erp_product_ids'] as $erp_goods_id) {
                // 检查数据库是否已经添加过，添加过则不用重复添加，只能编辑
                $exist_db = $this->goodsInfoService->getInfoByErpProductId($erp_goods_id);
                if ($exist_db) {
                    \Log::info('the erp_goods_id ' .$erp_goods_id. ' has exists: ' . print_r($exist_db, true) . ' ');
                    continue;
                }

                // 从erp接口获取数据
                $erp_goods_info = $this->goodsInfoService->erpProductDetail($erp_goods_id);
                if (!$erp_goods_info || !$erp_goods_info->data) {
                    sleep(1);
                    continue; // 未获取到数据，该id可能不存在
                }
                $erp_goods_info = $erp_goods_info->data;
                $v_id = $this->goodsInfoService->insertGoodsAndSkuData($erp_goods_info, $request, $curr_datetime, $current_uid);
                if (!$v_id) {
                    \Log::error('dispatch add goods error: ' . print_r($request, true) . ' erp_goods_info:' . var_export($erp_goods_info, true));
                    // TODO 顺便发一封邮件通知相关人员：
                }
                sleep(1);
            }
        } catch (\Exception $e) {
            \Log::error('add goods multiple error! ' . print_r($request, true) . ' ' . $e->getMessage());
        }
    }
}
