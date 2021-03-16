<?php

namespace App\Logics;

abstract class BaseLogic
{
    /*
	 * 设置错误信息
	 * @return array
	 */
    protected $_lastError;

    public function setLastError($code, $message = '')
    {
        $this->_lastError = ['code' => $code, 'info' => $message];
    }

    /*
     * 获取错误信息
     * @return array
     */
    public function getLastError()
    {
        return $this->_lastError ? $this->_lastError : ['code' => 0, 'info' => ''];
    }

    protected function returnFormat($code, $message = '', $data = [])
    {
        return ['code' => $code, 'info' => $message, 'data' => $data];
    }

}
