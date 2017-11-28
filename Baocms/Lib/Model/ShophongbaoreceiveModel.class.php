<?php
class ShophongbaoreceiveModel extends CommonModel{
    protected $pk   = 'receive_id';
    protected $tableName =  'shop_hongbao_receive';
    
    public function CallDataForMat($items){ //专门针对CALLDATA 标签处理的
        if(empty($items)) return array();
        $obj = D('Hongbao');
        $hongbao_ids = array();
        foreach($items as $k=>$val){
            $hongbao_ids[$val['hongbao_id']] = $val['hongbao_id'];
        }       
        $hongbaos = $obj->itemsByIds($hongbao_ids);
        foreach($items as $k=>$val)
        {
            $val['hongbao'] = $hongbaos[$val['hongbao_id']];
            $items[$k] = $val;
        }
        return $items;
    }
}