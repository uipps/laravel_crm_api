<?php

namespace App\Libs\Utils;

class Func{

    public static function remoteFileUrl($url){
        if(!$url){
            return '';
        }
    
        if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $url)) {
            $awsUrl = 'https://'.config('aws.bucket').'.s3.'.config('aws.region').'.amazonaws.com';
            $awsUrl =  config('aws.cdn_url') ?: $awsUrl;
            $url = $awsUrl.'/'.$url;
        }
    
        return $url;
    }
    
    public static function remoteFileKey($url){
        if(!$url){
            return '';
        }
        
        $awsUrl = 'https://'.config('aws.bucket').'.s3.'.config('aws.region').'.amazonaws.com/';
        $fileKey = str_replace($awsUrl, '', $url);
    
        return $fileKey;
    }

    public static function cyclelife(){
    
        // if(isset($_SERVER['start_time'])){
        //     $startTime = $_SERVER['start_time'];
        //     $endTime = microtime(true);
        //     $exeTime = round($endTime - $startTime, 5);
        //     $trace = debug_backtrace(2,1);
    
        //     print_r(['execute_time' => $exeTime, 'file' => $trace[0]['file'], 'line' => $trace[0]['line']]);
        // }
    
        // $_SERVER['start_time'] = microtime(true);
        
    }
}