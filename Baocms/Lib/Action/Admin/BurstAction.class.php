<?php
/**
* Burst短信管理
*/
class BurstAction extends CommonAction
{
    private $create_fields = array('burst_key', 'burst_explain', 'burst_tmpl');
    private $edit_fields = array('burst_key', 'burst_explain', 'burst_tmpl');

	public function index()
	{
		$all_tag = D('Burst')->select();
		$this -> assign('tag', $all_tag);
		$this -> display();
	}

	public function create()
	{
		if($this->isPost()){
            $data = $this->createCheck();
            $obj = D('Burst');
            if ($obj->add($data)) {
                $this->baoSuccess('添加成功', U('burst/index'));
            }
            $obj->cleanCache();
			$this->baoError('操作失败！');
		}else{
			$this -> display();
		}
	}

    private function createCheck() {
        $data = $this->checkFields($this->_post('data', false), $this->create_fields);
        $data['burst_key'] = htmlspecialchars($data['burst_key']);
        $data['burst_explain'] = htmlspecialchars($data['burst_explain']);
        $data['burst_tmpl'] = htmlspecialchars($data['burst_tmpl']);
        if (empty($data['burst_key'])) {
            $this->baoError('标签不能为空');
        }
        if (empty($data['burst_explain'])) {
            $this->baoError('说明不能为空');
        }
        if (empty($data['burst_tmpl'])) {
            $this->baoError('模版不能为空');
        }
        return $data;
    }

	public function edit($burst_id)
	{
        if ($burst_id = (int) $burst_id) {
            $obj = D('Burst');
            if (!$detail = $obj->find($burst_id)) {
                $this->baoError('请选择要编辑的短信模版');
            }
            if ($this->isPost()) {
                $data = $this->editCheck();
                $data['burst_id'] = $burst_id;
                if (false !== $obj->save($data)) {
                    $obj->cleanCache();
                    $this->baoSuccess('操作成功', U('burst/index'));
                }
                $this->baoError('操作失败');
            } else {
                $this->assign('detail', $detail);
                $this->display();
            }
        } else {
            $this->baoError('请选择要编辑的短信模版');
        }
	}

    private function editCheck() {
        $data = $this->checkFields($this->_post('data', false), $this->edit_fields);
        $data['burst_key'] = htmlspecialchars($data['burst_key']);
        $data['burst_explain'] = htmlspecialchars($data['burst_explain']);
        $data['burst_tmpl'] = htmlspecialchars($data['burst_tmpl']);
        if (empty($data['burst_key'])) {
            $this->baoError('标签不能为空');
        }
        if (empty($data['burst_explain'])) {
            $this->baoError('说明不能为空');
        }
        if (empty($data['burst_tmpl'])) {
            $this->baoError('模版不能为空');
        }
        return $data;
    }

	public function delete($burst_id = 0)
	{
		if (is_numeric($burst_id) && ($burst_id = (int) $burst_id)) {
			if(D('Burst')->save(array('burst_id' => $burst_id, 'is_open' => 0))){
				$this->baoSuccess('操作成功！', U('burst/index'));
			}
			$this->baoError('操作失败！');
		}else{
			$burst_ids = $this->_post('burst_id', false);
            if (is_array($burst_ids)) {
                foreach ($burst_ids as $id) {
					D('Burst')->save(array('burst_id' => $id, 'is_open' => 0));
                }
                $this->baoSuccess('操作成功！', U('burst/index'));
            }
            $this->baoError('操作失败！');
		}
	}
	
	 public function audit($burst_id = 0) {
        if (is_numeric($burst_id) && ($burst_id = (int) $burst_id)) {
            $obj = D('Burst');
            $obj->save(array('burst_id' => $burst_id, 'is_open' => 1));
            $obj->cleanCache();
            $this->baoSuccess('开启成功！', U('burst/index'));
        } else {
            $burst_id = $this->_post('burst_id', false);
            if (is_array($burst_id)) {
                $obj = D('Burst');
                foreach ($burst_id as $id) {
                    $obj->save(array('burst_id' => $id, 'is_open' => 1));
                }
                $obj->cleanCache();
                $this->baoSuccess('开启成功！', U('burst/index'));
            }
            $this->baoError('请选择要开启的短信模版');
        }
    }
}