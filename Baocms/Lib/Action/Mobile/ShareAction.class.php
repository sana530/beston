<?php



class ShareAction extends CommonAction {

    public function index() {
        $share = (int)$this->_get('share');
        $uid = $this->uid;
        if (!$share) {
            header('Location: ' . U('share/index', array('share'=>$uid)));
        }
        $qrcode = D('Weixin')->getCode($share, 4);
        $this->assign('me', ($share == $uid) ? true : false); //是否是自己的页面
        $this->assign('nickname', D('Users')->where(array('user_id'=>$uid))->getField('nickname'));
        $this->assign('qrcode', $qrcode);
        $this->display();
    }

}
