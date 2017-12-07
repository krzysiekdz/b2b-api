<?php defined('SYSPATH') or die('No direct script access.');

class BSXController extends Controller {
    public $view;
    public $ajaxResult=array();

    public function before() {
        parent::before();
        BinUtils::buffer_start();
    }

    public function after() {
        $this->view->content=BinUtils::buffer_end();

        if (Model_BSX_Core::global_variable('ajax'))
        {
            if (count($this->ajaxResult)>0) echo json_encode($this->ajaxResult);
            else echo json_encode(array('data'=>$this->view->content));
        } else {
            $this->response->body($this->view);
        }
        parent::after();
    }
}