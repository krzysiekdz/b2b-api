<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Admin_Eurofast_Company extends Controller {
    private $view;
    public $messageStr;
    public $messageClass = 'alert-success';

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'];
        $this->link=$this->root.'/eurofast/company';


        $this->view->sidebar_active='home';


        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Firma','url'=>$this->link),
        );

        BinUtils::buffer_start();
    }

    public function after() {
        $this->view->content=BinUtils::buffer_end();
        Model_BSX_Core::create_sidebar($this,$this->view);  //menu panelu administracyjnego
        $this->response->body($this->view);
        parent::after();
    }


    public function action_index()
    {
        $this->view->title='Ustawienia firmy';
        $this->view->sidebar_active_menu='company';

        $save = (getGetPost('save') != '');

        $system = Model_BSX_System::init();

        $fields=array('pname','pstreet','ppostcode','pcity','pnip','pemail','pphone1','pcountry','pbank');
        if ($save)
        {
            $dane = array();
            foreach ($_POST as $name => $value) if (in_array($name, $fields)) {
                $dane[$name] = $value;
            }
            sql_update('bs_company', $dane, $system->company['id']);
            $system->loadData();
            $this->messageStr='Informacje o Twojej firmie zostały zapisane!';
        }

        $f=array();
        $f['pname'] = $system->company['pname'];
        $f['pstreet'] = $system->company['pstreet'];
        $f['ppostcode'] = $system->company['ppostcode'];
        $f['pcity'] = $system->company['pcity'];
        $f['pnip'] = $system->company['pnip'];
        $f['pemail'] = $system->company['pemail'];
        $f['pphone1'] = $system->company['pphone1'];
        $f['pcountry'] = $system->company['pcountry'];
        $f['pbank'] = $system->company['pbank'];

        $view = Model_BSX_Core::create_view($this, 'Eurofast/part_form_admin_company', 'padmin');
        $view->set('f', $f);
        echo $view;
    }

}