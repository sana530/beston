<?php
class ArticleModel extends CommonModel{
    protected $pk   = 'article_id';
    protected $tableName =  'article';

	
	public function rands(){
		$news = $this->order(array('article_id' => 'desc'))->limit(0, 45)->select();
		shuffle($news);
		if (empty($news)) {
			return array();
		}

        //判断推荐文章是否普通文章
        foreach ($news as $k => $val) {
            $where = array('cate_id'=> $val['cate_id'] );
            $cate_type = D('Articlecate')->where($where)->getField('cate_type');
            if ($cate_type != 0) {
                unset($news[$k]);
            }
        }

		$num = (3 < count($news) ? 3 : count($news));
		$keys = array_rand($news, $num);
		$return = array();
		foreach ($news as $k => $val ) {
			if (in_array($k, $keys)) {
				$return[] = $val;
			}
		}
		return $return;
	}


	//进入手册首页的权限
	public function checkPriv ($action){
	    $uid = getUid();
	    $where = array('user_id' => $uid, 'audit'=>1, 'closed'=>0);
        $isShangjia = D('Shop')->where($where)->getField('user_id');
	    if ($action == 'shangjia') {
	        if(session('admin') || $isShangjia) {
	            return true;
	        } else {
                return false;
            }
	    } elseif ($action == 'index') {
	        return true;
        }
	}


    //查看文章详情权限
    public function checkType ($article_id) {
	    $where = array('user_id' => getUid(), 'audit'=>1, 'close'=>0);
        $isShangjia = D('Shop')->where($where)->getField('user_id');
        $priv = array(0, 1);
        if (session('admin')) {
            $priv = array(0, 1, 2, 3);
        } elseif ($isShangjia) {
            $priv = array(0, 1, 2);
        }
        $cate_id = $this->where(array('article_id'=>$article_id))->getField('cate_id');
        $cate_type = D('Articlecate')->where(array('cate_id'=>$cate_id))->getField('cate_type');
        if (in_array($cate_type, $priv)) {
            return true;
        } else {
            return false;
        }
    }

}