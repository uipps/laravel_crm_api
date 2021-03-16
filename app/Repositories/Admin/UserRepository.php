<?php

namespace App\Repositories\Admin;


use Illuminate\Support\Facades\Cache;

class UserRepository extends UserRepositoryImpl
{
    const PASSWORD_ERROR_MAX_NUM = 3; // 密码错误3次就需要图片验证码

    const CACHE_EXPIRE_PASSWORD_ERROR = 60;  // 单位秒，缓存失效时间，暂定1小时 // TODO 临时改为1分钟，上线改回来

    const CACHE_EXPIRE = 1;  // 单位秒，缓存失效时间，暂定1小时
    const CACHE_EXPIRE_LIST = 1;

    // 密码输入错误次数
    private static function GetPasswordErrorNumCacheKey($email) {
        return 'db:user:email-password-error-numbers-' . $email;
    }

    private static function GetEmailCacheKey($email) {
        return 'db:user:user-info-by-email-' . $email;
    }

    private static function GetUserCacheKey($user_id) {
        return 'db:user:user-info-by-user_id-' . $user_id;
    }

    private static function GetListCacheKey() {
        return 'db:user:list-all-';
    }

    private static function GetDepartmentUserNumCacheKey($dept_id) {
        return 'db:user:user-num-by-department-id-' . $dept_id;
    }

    // 新增并返回主键ID
    public function insertGetId($data_arr) {
        $user_id = parent::insertGetId($data_arr);
        if (!$user_id)
            return 0;
        // 暂无关联cache需要更新
        return $user_id;
    }

    // 1小时内错误超过3次，需要记录错误次数并返回
    public function addPasswordErrorNumByEmail($email) {
        // 从cache获取数据
        $cache_key = self::GetPasswordErrorNumCacheKey($email);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result) {
            // 计数加1
            \Cache::increment($cache_key);
            return $cached_result + 1;
        }
        // 错误1次，种cache
        $cached_result = 1;
        \Cache::put($cache_key, $cached_result, self::CACHE_EXPIRE_PASSWORD_ERROR);

        return $cached_result;
    }

    // 错误次数是否超过设定值
    public function isOverThrottleByEmail($email) {
        // 从cache获取数据
        $cache_key = self::GetPasswordErrorNumCacheKey($email);
        $cached_result = \Cache::get($cache_key);
        if (!$cached_result)
            return 0;
        return ($cached_result >= self::PASSWORD_ERROR_MAX_NUM) ? 1 : 0;
    }

    // 检查某email是否存在，需要加cache，删除用户的时候记得更新此cache
    public function getByEmail($email) {
        // 先从cache获取数据
        // $cache_key = self::GetEmailCacheKey($email);
        // $cached_result = \Cache::get($cache_key);
        // if ($cached_result)
        //     return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getByEmail($email);
        if (!$db_result)
            return $db_result;
        // \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE);

        return $db_result;
    }

    public function parseJwtToken($token) {
        date_default_timezone_set('Asia/Shanghai'); // 强制时区为东八区
        // 解析token
        $tmp = explode('.', $token); // 分解成三部分 header . payload . signature
        $parse_info = new \stdClass();
        $parse_info->header_orig = $tmp[0];
        $parse_info->header_base_decode = base64_decode($tmp[0]);
        $parse_info->header_urldecode = urldecode($parse_info->header_base_decode);
        $parse_info->payload_orig = $tmp[1];
        $parse_info->payload_base_decode = base64_decode($tmp[1]);
        $payload = json_decode($parse_info->payload_base_decode, true);
        $parse_info->iat = date('Y-m-d H:i:s', $payload['iat']);
        $parse_info->exp = date('Y-m-d H:i:s', $payload['exp']);
        $parse_info->payload_urldecode = urldecode($parse_info->payload_base_decode);;
        $parse_info->signature_orig = $tmp[2];
        $parse_info->signature_jisuan_base_encode = base64_encode(hash_hmac('sha256', $tmp[0] . '.' . $tmp[1], env('JWT_SECRET'), true));
        $parse_info->signature_jisuan_urlencode = urlencode($parse_info->signature_jisuan_base_encode);

        return $parse_info;
    }

    // 修改密码，记得更新或删除用户缓存
    public function setPasswordById($user_id, $new_password) {
        return self::updateData($user_id, ['password' => $new_password]);
    }

    public function updateData($user_id, $data_arr) {
        if (!parent::updateData($user_id, $data_arr)) return false;

        // 读取cache
        $cache_key = self::GetUserCacheKey($user_id);
        $cached_result = \Cache::get($cache_key);

        // 策略1：直接删除该用户对应缓存即可
        //if ($cached_result) \Cache::forget($cache_key);
        //return true;

        // 策略2：更新用户信息
        if (!$cached_result) {
            $db_result = parent::getUserById($user_id);
            if ($db_result)
                \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE);
        } else {
            // 进行更新操作，需要保证事务性、原子性
            $cached_result = array_merge($cached_result, $data_arr);
            $lock = Cache::lock('foo', 10);
            try {
                $lock->block(5);
                \Cache::put($cache_key, $cached_result, self::CACHE_EXPIRE);
            } catch (\LockTimeoutException $e) {
                //
            } finally {
                optional($lock)->release();
            }
        }

        return true;
    }

    // 暂不处理缓存
    public function getList($params, $field = ['*']) {
        return parent::getList($params, $field);
    }

    // 全部客服员工，总数应该不会上万
    public function getAllUser() {
        // 先从cache获取数据
        $cache_key = self::GetListCacheKey();
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getAllUser();
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_LIST);

        return $db_result;
    }

    // 获取该部门下的有效员工数，(不包含子部门，单纯只是跟子部门同级别的员工)
    public function getDepartmentUserNum($dept_id) {
        // 先从cache获取数据
        $cache_key = self::GetDepartmentUserNumCacheKey($dept_id);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache，数量0也可以放到cache中
        $db_result = parent::getDepartmentUserNum($dept_id);
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE);

        return $db_result;
    }
    public function updateDepartmentUserNumCache($data_arr) {
        if (!isset($data_arr['department_id']) || !$data_arr['department_id']) {
            return 1;
        }
        $cache_key = self::GetDepartmentUserNumCacheKey($data_arr['department_id']);
        \Cache::forget($cache_key); // 直接删除，以后有空了可以进行高并发下的更新操作
        return 1;
    }

    // 添加或修改的时候，删除单个和整个的用户列表缓存
    public function updateUserAllCache($data_arr) {
        $cache_key = self::GetListCacheKey();
        \Cache::forget($cache_key); // 直接删除，以后有空了可以进行高并发下的更新操作

        if (isset($data_arr['id'])) {
            $cache_key = self::GetUserCacheKey($data_arr['id']);
            \Cache::forget($cache_key);
        }
        return 1;
    }
}
