<?php
class BurstsmsAction extends CommonAction{

    public function index(){
        $Burstsms = D('Burstsms');
        import('ORG.Util.Page');
        $map = array('closed' => 0);
        $keyword = $this->_param('keyword', 'htmlspecialchars');
        if ($keyword) {
            $map['mobile'] = array('LIKE', '%' . $keyword . '%');
            $this->assign('keyword ', $keyword );
        }
        $count = $Burstsms->where($map)->count();
        $Page = new Page($count, 50);
        $show = $Page->show();
        $list = $Burstsms->where($map)->order(array('sms_id' => 'desc'))->limit($Page->firstRow . ',' . $Page->listRows)->select();
		foreach($list as $k=>$val){
            $val['create_ip_area'] = $this->ipToArea($val['create_ip']);
            $list[$k] = $val;
        }
        $this->assign('list', $list);
        $this->assign('page', $show);
		$this->assign('count', $count);
        $this->display();
    }

    public function delete($sms_id = 0){
        if (is_numeric($sms_id) && ($asms_id = (int) $sms_id)) {
            $obj = D('Burstsms');
            $obj->delete($sms_id);
            $this->baoSuccess('删除成功！', U('Burstsms/index'));
        } else {
            $sms_id = $this->_post('sms_id', false);
            if (is_array($sms_id)) {
                $obj = D('Burstsms');
                foreach ($sms_id as $id) {
                    $obj->delete($id);
                }
                $this->baoSuccess('删除成功！', U('Burstsms/index'));
            }
            $this->baoError('请选择要删除的Burstsms短信记录');
        }
    }
   
}