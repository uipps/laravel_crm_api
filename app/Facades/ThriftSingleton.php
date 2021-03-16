<?php
namespace App\Facades;
use App\Exceptions\InternalErrorException;
use App\Thrift\Protocol\Order\OrderTraceClient;
use Aws\Exception\InvalidJsonException;
use Illuminate\Support\Facades\Log;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TBufferedTransport;
use Thrift\Transport\TSocket;

class ThriftSingleton
{
    protected $host;
    protected $port;
    protected $tsocket;
    protected $transport;
    protected $protocol;


    public function __construct()
    {
        $this->host = config('common.thrift_rpc.host');
        $this->port = config('common.thrift_rpc.port');
        $this->tsocket = new TSocket($this->host, $this->port);
        $this->transport = new TBufferedTransport($this->tsocket);
        $this->protocol = new TBinaryProtocol($this->transport);
    }

    public function getOrderTrace($shipNo){
        // $thriftProtocol = new TMultiplexedProtocol($protocol, 'OrderTrace');
        $client = new OrderTraceClient($this->protocol);

        $this->transport->open();

        $ret = $client->trace($shipNo);
        $ret = json_decode($ret, true);

        Log::channel('thrift')->info('OrderTraceClient', compact('ret'));

        if (json_last_error() > 0) {
            throw new InvalidJsonException(json_last_error_msg());
        }

        if($ret['code'] != 200){
            return [
                'message' => $ret['message'],
                'list' => [],
            ];
        }

        if(!$ret['data']['list']){
            return [
                'message' => '物流信息为空',
                'list' => [],
            ];
        }
        
        return [
            'message' => $ret['message'],
            'list' => $ret['data']['list'],
        ];
    }

    public function getTestOrderTrace($shipNo){
        // $thriftProtocol = new TMultiplexedProtocol($protocol, 'OrderTrace');
        $client = new OrderTraceClient($this->protocol);

        $this->transport->open();
        $ret = $client->trace($shipNo);
        $ret = json_decode($ret, true);

        return $ret;
    }

}