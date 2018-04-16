<?php



class WeixinpushrecordModel extends CommonModel{
    protected $pk   = 'record_id';
    protected $tableName =  'weixin_push_record';
    protected $token = 'weixin_push_record';

    /* 记录到pushrecord中 */
    public function record($shop_id, $push_type, $content_type, $content_id, $uid) {
        $add = array(
            'shop_id'=>$shop_id,
            'push_type'=>$push_type,
            'content_type'=>$content_type,
            'content_id'=>$content_id,
            'receive_uid'=>$uid,
            'push_time'=>NOW_TIME
            );
        $this->add($add);
    }

}