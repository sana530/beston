<?php
class ScanloginkeyModel extends CommonModel{
    protected $pk   = 'id';
    protected $tableName =  'scan_login_key';

    function checkkey($key,$backurl=NULL) {

        $info = $this->where(array('key'=>$key))->find();
        $result = array();
        $now = time();
        //未传递密钥或找不到相应结果
        if (empty($info) || empty($key)) {
            $result['error'] = 1;
            $result['errormsg'] = '请先申请获得密钥';
            return $result;
        }

        //密钥过期
        if ($now > $info['expiry']) {
            $result['error'] = 2;
            $result['errormsg'] = '该密钥已过期，请重新申请';
            return $result;
        }

        //检查返回地址是否合理
        if ((strpos($backurl, ('http://'.$_SERVER['HTTP_HOST'])) !== 0) && ($backurl !== NULL)) {
            $domain = explode('|', $info['domain']);
            $domain_test = false;
            foreach ($domain as $k => $v) {
                if ((strpos($backurl, $v) == 7) || (strpos($backurl, $info['domain']) == 8)) {
                    $domain_test = true;
                    $result['domain'] = $v;
                }
            }
            if (!$domain_test) {
                $result['error'] = 3;
                $result['errormsg'] = '返回地址域名不正确';
                return $result;
            }
        }

        //返回正确状态
        $result['error'] = 0;
        return $result;

    }
		
}