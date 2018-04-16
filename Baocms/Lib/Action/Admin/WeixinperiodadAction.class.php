<?php



class WeixinperiodadAction extends CommonAction {

    private $create_fields = array('keyword', 'type', 'title', 'contents', 'url', 'photo', 'start_date', 'start_time', 'end_date', 'end_time');
    private $edit_fields = array('keyword', 'type', 'title', 'contents', 'url', 'photo', 'start_date', 'start_time', 'end_date', 'end_time');

    public function index() {
        $Weixinperiodad = D('Weixinperiodad');
        import('ORG.Util.Page'); // 导入分页类
        $map = array();
        if ($keyword = $this->_param('keyword', 'htmlspecialchars')) {
            $map['keyword'] = array('LIKE', '%' . $keyword . '%');
        }
        $count = $Weixinperiodad->where($map)->count(); // 查询满足要求的总记录数
        $Page = new Page($count, 15); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show(); // 分页显示输出
        $list = $Weixinperiodad->where($map)->order(array('ad_id' => 'desc'))->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出
        $this->display(); // 输出模板
    }

    public function create() {
        if ($this->isPost()) {
            $data = $this->createCheck();
            $obj = D('Weixinperiodad');
            if ($obj->add($data)) {
                $obj->cleanCache();
                $this->baoSuccess('添加成功', U('Weixinperiodad/index'));
            }
            $this->baoError('操作失败！');
        } else {
            $this->display();
        }
    }

    private function createCheck() {
        $data = $this->checkFields($this->_post('data', false), $this->create_fields);
        $data['keyword'] = htmlspecialchars($data['keyword']);
        if (empty($data['keyword'])) {
            $this->baoError('时段广告关键字不能为空');
        }
        if (D('Weixinperiodad')->checkKeyword($data['keyword'])) {
            $this->baoError('时段广告关键字已经存在');
        }

        if (empty($data['type'])) {
            $this->baoError('类型不能为空');
        }
        $data['title'] = htmlspecialchars($data['title']);


        if (empty($data['contents'])) {
            $this->baoError('回复内容不能为空');
        }
        if ($words = D('Sensitive')->checkWords($data['contents'])) {
            $this->baoError('内容含有敏感词：' . $words);
        }
        $data['url'] = htmlspecialchars($data['url']);
        $data['photo'] = htmlspecialchars($data['photo']);
        if (!empty($data['photo']) && !isImage($data['photo'])) {
            $this->baoError('缩略图格式不正确');
        }
        $data['start_date'] = htmlspecialchars($data['start_date']);
        $data['start_time'] = htmlspecialchars($data['start_time']);
        if(empty($data['start_date']) || empty($data['start_time'])){
            $this->baoError('开始日期不能为空');
        }
        if(!isDate($data['start_date'])){
            $this->baoError('开始日期格式错误！');
        }
        $data['start_from'] = strtotime($data['start_time'].' '.$data['start_date']);
        unset($data['start_date']);
        unset($data['start_time']);
        $data['end_date'] = htmlspecialchars($data['end_date']);
        $data['end_time'] = htmlspecialchars($data['end_time']);
        if(empty($data['end_date']) || empty($data['end_time'])){
            $this->baoError('结束日期不能为空');
        }
        if(!isDate($data['end_date'])){
            $this->baoError('结束日期格式错误！');
        }
        $data['end_at'] = strtotime($data['end_time'].' '.$data['end_date']);
        unset($data['end_date']);
        unset($data['end_time']);
        return $data;
    }

    public function edit($ad_id = 0) {
        if ($ad_id = (int) $ad_id) {
            $obj = D('Weixinperiodad');
            if (!$detail = $obj->find($ad_id)) {
                $this->baoError('请选择要编辑的微信时段广告');
            }
            if ($this->isPost()) {
                $data = $this->editCheck();
                $data['ad_id'] = $ad_id;
                $local = $obj->checkKeyword($data['keyword']);
                if ($local && $local['ad_id'] != $ad_id) {
                    $this->baoError('关键字已经存在');
                }

                if (false !== $obj->save($data)) {
                    $obj->cleanCache();
                    $this->baoSuccess('操作成功', U('Weixinperiodad/index'));
                }
                $this->baoError('操作失败');
            } else {
                $detail['start_date'] = date('Y-m-d', $detail['start_from']);
                $detail['start_time'] = date('H:i', $detail['start_from']);
                $detail['end_date'] = date('Y-m-d', $detail['end_at']);
                $detail['end_time'] = date('H:i', $detail['end_at']);
                $this->assign('detail', $detail);
                $this->display();
            }
        } else {
            $this->baoError('请选择要编辑的微信时段广告');
        }
    }

    private function editCheck() {
        $data = $this->checkFields($this->_post('data', false), $this->edit_fields);
        $data['keyword'] = htmlspecialchars($data['keyword']);
        if (empty($data['keyword'])) {
            $this->baoError('关键字不能为空');
        }
        if (empty($data['type'])) {
            $this->baoError('类型不能为空');
        }
        $data['title'] = htmlspecialchars($data['title']);

        if (empty($data['contents'])) {
            $this->baoError('回复内容不能为空');
        }
        if ($words = D('Sensitive')->checkWords($data['contents'])) {
            $this->baoError('内容含有敏感词：' . $words);
        }
        $data['url'] = htmlspecialchars($data['url']);
        $data['photo'] = htmlspecialchars($data['photo']);
        if (!empty($data['photo']) && !isImage($data['photo'])) {
            $this->baoError('缩略图格式不正确');
        }
        $data['start_date'] = htmlspecialchars($data['start_date']);
        $data['start_time'] = htmlspecialchars($data['start_time']);
        if(empty($data['start_date']) || empty($data['start_time'])){
            $this->baoError('开始日期不能为空');
        }
        if(!isDate($data['start_date'])){
            $this->baoError('开始日期格式错误！');
        }
        $data['start_from'] = strtotime($data['start_time'].' '.$data['start_date']);
        unset($data['start_date']);
        unset($data['start_time']);
        $data['end_date'] = htmlspecialchars($data['end_date']);
        $data['end_time'] = htmlspecialchars($data['end_time']);
        if(empty($data['end_date']) || empty($data['end_time'])){
            $this->baoError('结束日期不能为空');
        }
        if(!isDate($data['end_date'])){
            $this->baoError('结束日期格式错误！');
        }
        $data['end_at'] = strtotime($data['end_time'].' '.$data['end_date']);
        unset($data['end_date']);
        unset($data['end_time']);
        return $data;
    }

    public function delete($ad_id = 0) {
        if (is_numeric($ad_id) && ($ad_id = (int) $ad_id)) {
            $obj = D('Weixinperiodad');
            $obj->delete($ad_id);
            $obj->cleanCache();
            $this->baoSuccess('删除成功！', U('Weixinperiodad/index'));
        } else {
            $ad_id = $this->_post('ad_id', false);
            if (is_array($ad_id)) {
                $obj = D('Weixinperiodad');
                foreach ($ad_id as $id) {
                    $obj->delete($id);
                }
                $obj->cleanCache();
                $this->baoSuccess('删除成功！', U('Weixinperiodad/index'));
            }
            $this->baoError('请选择要删除的微信时段广告');
        }
    }

}
