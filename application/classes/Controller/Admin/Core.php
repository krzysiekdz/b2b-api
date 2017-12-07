<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Kontrolery podstawowe - obsługujące różne funkcje paneu administratora, m.in.:
  - logowanie do chmury
  - logowanie na konto użytkownika
  - wylogowywanie
************************************************************************************/

class Controller_Admin_Core extends Controller {


    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        parent::before();
    }

    public function after() {
        parent::after();
    }


    public function action_index()
    {
        if (!Model_BSX_Admin::test_login($this)) return;

        Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/home');
        exit;
    }

    public function action_login()
    {
        //jak jest włączona chmura, a nie jesteśmy w niej zalogowany - to wybór chmury
        if (isset(Model_BSX_Core::$bsx_cfg['options']['option_bsxcloudselect']) && (Model_BSX_Core::$bsx_cfg['options']['option_bsxcloudselect']==true) && empty($_SESSION['bsxcloud']['nhost']))
        {
            Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/core/cloud');
            exit;
        }

        $username = getPost('username', '');
        $password = getPost('password', '');

        $ul=Cookie::get('bsxal');
        $up=Cookie::get('bsxap');
        $us=Cookie::get('bsxas');
        if ($ul!='' && $up!='' && $us!='')
        {
            $us=sha1($us).'bsx'.md5($us);
            $enc = new Encrypt($us,MCRYPT_MODE_ECB,MCRYPT_BLOWFISH);
            $username=$enc->decode($ul);
            $password=$enc->decode($up);
        }

        if($username!='' && $password!='')
        {
            $res=Model_BSX_Admin::login($username,$password,getPost('remember', '')==1);
            if ($res>0)
            {
                Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix']);
                exit;
            } else
            {
                Cookie::delete('bsxal');
                Cookie::delete('bsxap');
                Cookie::delete('bsxas');
                $this->params['incorrect']=$res;
            }
        }
        $this->params['username']=$username;
        $view=Model_BSX_Core::create_view($this,'index_login','padmin');
        $this->response->body($view);
    }

    public function action_cloud()
    {
        //jak nie ma włączonej chmury - przełączamy do logowania
        if (isset($_SESSION['bsxcloud']) || !isset(Model_BSX_Core::$bsx_cfg['options']['option_bsxcloudselect']) || (Model_BSX_Core::$bsx_cfg['options']['option_bsxcloudselect']==false))
        {
            Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/core/login');
            exit;
        }

        $bsxcloud = getGetPost('bsxcloud', '');


        $ukey=Cookie::get('bsxcloudkey');
        $useed=Cookie::get('bsxcloudseed');
        if ($ukey!=='' && $useed!='' && $bsxcloud=='')
        {
            $useed=sha1($useed).'bsx'.md5($useed);
            $enc = new Encrypt($useed,MCRYPT_MODE_ECB,MCRYPT_BLOWFISH);
            $bsxcloud=$enc->decode($ukey);
        }

        if ($bsxcloud!='')
        {
            $res=Model_BSX_Admin::cloudSelect($bsxcloud,getPost('remember', '')==1);
            if ($res>0)
            {
                Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/core/login');
                exit;
            } else
            {
                $this->params['incorrect']=$res;
                if (isset($_SESSION['bsxcloud'])) unset($_SESSION['bsxcloud']);
                Cookie::delete('bsxcloudkey');
                Cookie::delete('bsxcloudseed');
            }
        }
        $this->params['bsxcloud']=$bsxcloud;
        $view=Model_BSX_Core::create_view($this,'index_cloud','padmin');
        $this->response->body($view);
    }

    public function action_logout()
    {
        $_SESSION['admin_user']=array('id'=>0);
        Cookie::delete('bsxal');
        Cookie::delete('bsxap');
        Cookie::delete('bsxas');
        Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix']);
        exit;
    }

    public function action_cloudLogout()
    {
        if (isset($_SESSION['bsxcloud'])) unset($_SESSION['bsxcloud']);
        Cookie::delete('bsxcloudkey');
        Cookie::delete('bsxcloudseed');
        Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix']);
        exit;
    }

    public function action_lock()
    {
        if (empty($_SESSION['admin_user']['id']) || $_SESSION['admin_user']['id']<=0)
        {
            Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/core/login');
            exit;
        }
        $password = getPost('password', '');
        if ($password!='')
        {
            if (sql_row('SELECT id FROM bs_users WHERE id=:id AND ppass=:pass',array(':id'=>$_SESSION['admin_user']['id'],':pass'=>sha1($password))))
            {
                unset($_SESSION['admin_user']['lock']);
                Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix']);
                exit;
            } else
            {
                $this->params['incorrect']=-1;
            }
        }
        $_SESSION['admin_user']['lock']=true;
        $view=Model_BSX_Core::create_view($this,'index_lock','padmin');
        $this->response->body($view);
    }
}