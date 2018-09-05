<?php


class WeixinhongbaoModel {

    /**
     * 微信红包配置数据
     */
    private $uid = 21888;
    private $create_hongbao_api = 'http://www.yaoyaola.cn/index.php/exapi/hbticket?';
    private $get_hongbao_api = 'http://www.yaoyaola.cn/index.php/exapi/gethb?';
    private $sendname = '';
    private $apikey = '';
    private $config = null;

    /**
     * 构造方法
     */
    public function __construct() {
        $this->config = D('Setting')->fetchAll();
    }

    /**
     * 红包领取地址
     * @param  int      $money
     * @param  int      $orderid    提现id
     * @param  String   $title      红包活动名称
     * @param  String   $wishing    红包祝福语
     * @return Array    $return
     */
    public function get_hongbao_url($money, $orderid, $title, $wishing) {
        //生成红包配置项
        $config = $this->config;
        $this->sendname = $config['site']['sitename'];
        $this->apikey = $config['weixin']['hongbao_apikey'];
        $type = ($money > 20000)? 1 : 0;
        $reqtick = NOW_TIME;
        $sign = md5($this->uid . $type . $orderid . $money . $reqtick . $this->apikey);

        //生成创建红包url
        $create_url = $this->create_hongbao_api . 'uid=' . $this->uid . '&type=' . $type . '&orderid=' . $orderid . '&money=' .
            $money . '&reqtick=' . $reqtick . '&sign=' . $sign . '&title=' . $title . '&sendname=' . $this->sendname .
            '&wishing=' . $wishing;

        //取得红包创建返回结果，并返回领取红包url
        $result = file_get_contents_url($create_url);
        $return = ($result->errcode == 0)? $this->get_hongbao_api . 'uid=' . $this->uid . '&ticket=' . $result->ticket : false;
        if ($result->errcode == 0) {
            $return['error'] = 0;
            $return['result'] = $this->get_hongbao_api . 'uid=' . $this->uid . '&ticket=' . $result->ticket;
        } else {
            $return['error'] = $result->errcode;
            $return['result'] = $result->errmsg;
        }
        return $return;
    }

    /**
     * 获得红包奖励
     * @param  int      $user_id    获得奖励的用户id
     * @param  int      $type       奖励类型（1:关注奖励 2:分享给好友奖励 3:阅读文章奖励 4:评论奖励）
     * @return Array    $return
     */
    public function get_reward($user_id, $type) {
        if (!is_weixin()) {
            return false;
        }
        //todo 利用https://api.weixin.qq.com/cgi-bin/user/info接口，判断用户是否关注了公众号，没关注直接返还false
        $hongbaorecord = D('Weixinhongbaorecord');
        if ($type == 1) {
            $record = $hongbaorecord->get_record($user_id, $type);  //查看该用户是否有关注送红包奖励记录
            if ($record) {
                return false;
            }
            $hongbao_amount = $this->get_reward_amount($type);  //得到红包奖励金额
            $notice_msg = '关注公众号奖励';
        }

        if ($hongbao_amount > 0) {
            //增加用户余额，并微信通知用户
            if (D('Users')->addMoney($user_id, $hongbao_amount, $notice_msg)) {
                $log_id = $hongbaorecord->write_record($user_id, $type, $hongbao_amount, NOW_TIME, get_client_ip());
                D('Weixintmpl')->weixin_reward_balance_user($log_id);   //微信通知会员账户余额变动
            }

            //返利给分享者
            if ($type == 1) {
                $recommend_uid = D('Connect')->where(array('uid'=>$user_id, 'follow_type'=>1, 'type'=>'weixin'))->getField('follow_id');
                $recommend_hongbao_amount = $hongbao_amount/2;
                if (D('Users')->addMoney($recommend_uid, $recommend_hongbao_amount, $notice_msg)) {
                    $recommend_log_id = $hongbao_amount->write_record($recommend_uid, 2, $recommend_hongbao_amount, NOW_TIME, get_client_ip());
                    D('Weixintmpl')->weixin_reward_balance_user($recommend_log_id);   //微信通知分享者账户余额变动
                }
            }
        }

    }

    /**
     * 红包奖励金额
     * @param  int  $type               奖励类型（同get_reward的type）
     * @return int  $hongbao_amount     奖励金额
     */
    public function get_reward_amount($type) {
        $country = D('Cloud')->GetCountryCookie();
        if ($type == 1) {
            //随机红包（利用幂函数，抽中低的概率大，高的概率小）
            $number = (rand(0,100)/100) + 1;
            $hongbao_amount = round(pow($number, -$this->config['hongbao_difficulty']) * $this->config['hongbao_max_amount']);
            $hongbao_amount = ($hongbao_amount < $this->config['hongbao_mini_amount']) ? $this->config['hongbao_mini_amount'] : $hongbao_amount;
            if ($country != 'New Zealand') {
                $hongbao_amount = $hongbao_amount/3;
            }
        } else {
            $hongbao_amount = 0;
        }
        return $hongbao_amount;
    }


}