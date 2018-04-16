<?php



class WeixintipsModel extends CommonModel{
    protected $pk   = 'tips_id';
    protected $tableName =  'weixin_tips';
    protected $token = 'weixin_tips';

    public function checkKeyword($keyword){
        $words = $this->fetchAll();
        foreach($words as $val){
            if($val['keyword'] == $keyword)
                return  $val;
        }
        return false;
    }

    /* 通过客服接口发送Tips消息 */
    public function sendTips($data, $user){
        $openid = $data['FromUserName'];

        //查询Tips信息
        $tips = D('Weixintips')->fetchAll();
        $tips_num = count($tips);

        //如果用户有未读的Tips，则给用户发送相应Tips
        if ($tips_num > $user['tips_count']) {
            $tips_send = $tips[$user['tips_count'] + 1];
            D('Weixin')->custom_send($tips_send['contents'], $openid, 'text');
            $tips_count = $user['tips_count'] + 1;
        } else {
            $tips_count = $user['tips_count'];
        }

        //更新用户数据
        $save_user = array (
            'user_id' => $user['user_id'],
            'tips_count' => $tips_count,
            'push_tips_times' => $user['push_tips_times'] + 1
        );
        D('Users')->save($save_user);
    }

    /* 通过Response发送Tips消息 */
    public function responseTips($user, $open_location = true) {
        //查询Tips信息
        $tips = D('Weixintips')->fetchAll();
        $tips_num = count($tips);

        //如果用户有未读的Tips，则给用户发送相应Tips
        if ($tips_num > $user['tips_count']) {
            $is_send = true;   //记录是否要发送Tips
            $tips_send = $tips[$user['tips_count'] + 1];
            $tips_count = $user['tips_count'] + 1;
            $push_tips_times = $user['push_tips_times'] + 1;
        } elseif ((2*$tips_num) > $user['push_tips_times']) {
            $is_send = true;   //记录是否要发送Tips
            $tips_send = $tips[$user['push_tips_times'] - $tips_num + 1];
            $tips_count = $user['tips_count'];
            $push_tips_times = $user['push_tips_times'] + 1;
        } else {
            $tips_count = $user['tips_count'];
            $push_tips_times = $user['push_tips_times'];
        }

        //更新用户数据
        $save_user = array (
            'user_id' => $user['user_id'],
            'tips_count' => $tips_count,
            'push_tips_times' => $push_tips_times
        );
        D('Users')->save($save_user);

        //发送Tips或返回值
        if ($is_send && $open_location) {
            D('Weixin')->response($tips_send['contents'], 'text');
        } elseif ($is_send && !$open_location) {
            D('Weixin')->response('注意：为了更好的为您提供服务，请点击屏幕右上角👤标志，点击打开位置信息' . '

' . $tips_send['contents'], 'text');
        } elseif (!$open_location) {
            D('Weixin')->response('注意：为了更好的为您提供服务，请点击屏幕右上角👤标志，点击打开位置信息', 'text');
        } else {
            return false;
        }
    }

}