<?php


class WeixinhongbaorecordModel {

    protected $pk = 'record_id';
    protected $tableName = 'weixin_hongbao_record';

    public function get_record($user_id, $type) {
        return $this->where(array('user_id'=>$user_id, 'type'=>$type))->find();
    }

    public function write_record($user_id, $type, $money, $create_time, $create_ip) {
        return $this->add(array('user_id'=>$user_id, 'type'=>$type, 'money'=>$money, 'create_time'=>$create_time, 'create_ip'=>$create_ip));
    }

}