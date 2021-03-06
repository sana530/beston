<?php



class  LifeloveModel extends  CommonModel{
    protected $pk   = 'id';
    protected $tableName =  'life_love';
    
    public function check($life_id,$user_id){
        $life_id = (int)$life_id;
        $user_id = (int)$user_id;
        
        return $this->find(array('where'=>array(
            'user_id'   =>$user_id,
            'life_id'   => $life_id
        )));
    }
    
}
