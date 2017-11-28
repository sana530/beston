<?php



class LoginAction extends CommonAction {

    public function index() {
        if(cookie('DL') == true){
            header("Location: " . U('index/index'));
        }else{
           $this->display(); 
        }          
    }
    
    public function handle(){
        
        if(IS_AJAX){
            
            $username = I('username','','trim,htmlspecialchars');
            $password = I('password');
            
            if(!$username){
                $this->ajaxReturn(array('status'=>'error','message'=>'请输入帐号！'));
            }
            if(!$password){
                $this->ajaxReturn(array('status'=>'error','message'=>'请输入密码！'));
            }
            
            $dv = D('Delivery');
            $r = D('Users') -> where('account ="'.$username.'"')-> find();
            $dvr = $dv->where(array('user_id'=>$r['user_id']))->find();
            if((!$r) || (!$dvr)){
                $this->ajaxReturn(array('status'=>'error','message'=>'错误的帐号！'));
            }else{
                if($r['password'] != md5($password)){
                    $this->ajaxReturn(array('status'=>'error','message'=>'密码错误！'));
                }else{
                    $this->cookid($dvr['id']); // 指定cookie保存时间
                    $this->ajaxReturn(array('status'=>'success','message'=>'登录成功！'));
                    
                }
            }
  
        }
        
    }
    
    
    public function logout(){
        
        cookie('DL',null);
        D('Passport')->logout();
        header("Location: " . U('login/index'));
        
    }


}