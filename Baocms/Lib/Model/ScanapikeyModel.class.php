<?php
class ScanapikeyModel extends CommonModel{
    protected $pk   = 'id';
    protected $tableName =  'scan_api_key';

    function checkkey($appid, $appkey) {

        $info = $this->where(array('appid'=>$appid, 'appkey'=>$appkey))->find();
        $result = array();
        $now = time();
        //未传递密钥或找不到相应结果
        if (empty($info)) {
            $result['error'] = 1;
            $result['code'] = 5;
            $result['msg'] = 'Wrong AppID or AppKey.';
            return $result;
        }

        //密钥过期
        if (NOW_TIME > $info['expiry']) {
            $result['error'] = 1;
            $result['code'] = 6;
            $result['msg'] = 'This AppID has expired. Please apply again.';
            return $result;
        }

        //检查使用次数是否超过使用上限
        if ($info['num_count'] >= $info['num_limit']) {
            $result['error'] = 1;
            $result['code'] = 7;
            $result['msg'] = 'You have reached the maximum limit. Please contact with the administrator.';
            return $result;
        }

        //返回正确状态
        $result['error'] = 0;
        $result['info'] = $info;
        return $result;

    }
		
}