<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  
************************************************************************************/

class Controller_Api_B2B extends BSXController {

        
    private $b2b_statuses_a=array(1,10,20,30);//mozliwe statusy do nadawania przez admina: admin, admin oddziału, zwykly user, oddział
    private $b2b_statuses_abr=array(10,20,30); //mozliwe statusy do nadawania przez admina oddziału: admin, admin oddziału, zwykly user, oddział
    private $err='';
    private $err_code=0;
    private $fields=array();

    public function before() {
        parent::before();
    }

    public function after() {
        if(((int)getPost('ajax'))==1) {
            $this->ajaxResult['err']=$this->err;
            $this->ajaxResult['err_code']=$this->err_code;
            $this->ajaxResult['fields']=$this->fields;
        }
        parent::after();
    }


    public function action_index()
    {
        $this->showLogin();
    }

    public function action_detect()
    {
        $m = $this->request->param('modrewrite');
        $w=explode('/',$m);
        while (count($w)<=5) $w[]='';

        if ($w[0]=='login') return $this->showLogin($w);//logowanie uzytkownika
        else if ($w[0]=='logout') return $this->showLogout($w);//wylogowywanie
        else if ($w[0]=='create_update_user') return $this->showCreateUpdateUser($w);//tworzenie i modyfikacja uzytkownika
        else if ($w[0]=='create_update_company') return $this->showCreateUpdateCompany($w);//tworzenie oddziału
        else if ($w[0]=='get_users_by_branch') return $this->showCompanyEmployees($w);//uzytkownicy przypisani do danego oddzialu
        else if ($w[0]=='get_branches') return $this->showCompanies($w);//wszystkie oddzialy
        else if ($w[0]=='users_all') return $this->showAllUsers($w);//wszyscy uzytkownicy
        else if ($w[0]=='delete') return $this->showDelete($w);//usuwanie kontrahenta (pracownika lub oddzialu)
        else if ($w[0]=='companybyid') return $this->showCompanyById($w);//pobranie oddzialu po id
        else if ($w[0]=='userbyid') return $this->showUserById($w);//pobranie uzytkownika po id
        else if ($w[0]=='update_company') return $this->showUpdateCompany($w);//update dla oddzialu
        else if ($w[0]=='update_user') return $this->showUpdateUser($w);//update dla uzytkownika
        else {
            $this->err="Podany adres nie istnieje";
        }
    }

    private function a() {
        echo 'hello';
    }

    
    // /create_update_user
    private function createUpdateUser($w) // tworzenie i modyfikacja uzytkownika  
    {
        $ajax=(int)getPost('ajax');
        $uid=(int)getPost('uid');
        $id=(int)getPost('id');
        $create=(int)getPost('create');
        $update=(int)getPost('update');
        $admin=Model_BSX_Account::testPermission('admin', $uid);
        $admin_br=Model_BSX_Account::testPermission('admin_br', $uid);

        if($ajax==1) {
            //if user['b2b_status'] == 1 or 10  -> admin glowny lub kierownik oddzialu
            if($admin || $admin_br) {
                $fields=array('id'=>'','pname'=>'','b2b_status'=>'','pemail'=>'','b2b_branch'=>'', 'ppass1'=>'', 'ppass2'=>'', 'setpass'=>'', 'idparent'=>'');
                foreach ($fields as $name=>$value) if (isset($_POST[$name])) $fields[$name]=getPost($name);
                $err = '';
                $err_code = 0;
                $set_pass=(int)$fields['setpass'];
                $fields['idparent']=(int)$fields['idparent'];
                $fields['b2b_status']=(int)$fields['b2b_status'];
                $fields['b2b_branch']=(int)$fields['b2b_branch'];
                $fields['id']=(int)$fields['id'];

                if ($fields['pname'] == '') $err = 'Musisz podać Imie i Nazwisko użytkownika';
                else if ($fields['pemail'] == '') $err = 'Pole e-mail nie może być puste';
                else if ($admin && !in_array($fields['b2b_status'], $this->b2b_statuses_a)) $err = 'Nieprawidlowy status - admin';
                else if ($admin_br && !in_array($fields['b2b_status'], $this->b2b_statuses_abr)) $err = 'Nieprawidlowy status - admin oddziału';
                else if ($fields['b2b_branch'] == 0) $err = 'Nieprawidłowy oddział';
                else if ($set_pass && $fields['ppass1'] == '') $err = 'Podaj hasło';
                else if ($set_pass && $fields['ppass1'] !== $fields['ppass2'] ) $err = 'Musisz podać 2 razy to samo hasło';
                else if ($update && !sql_row('SELECT id FROM bs_contractors WHERE id=:i and idparentbr is null', array(':i' => $id))) 
                    { $err = 'Nie  istnieje taki użytkownik';  $err_code=2;}
                else if ($update && sql_row('SELECT id FROM bs_contractors WHERE pemail=:email and idparentbr is null and id!=:i', array(':email' => $fields['pemail'], ':i'=>$id))) 
                    { $err = 'Istnieje już użytkownik o tym adresie e-mail';  $err_code=1;}
                 else if ($create && sql_row('SELECT id FROM bs_contractors WHERE pemail=:email or cms_email=:email and idparentbr is null', array(':email' => $fields['pemail'])))
                    { $err = 'Istnieje już użytkownik o tym adresie e-mail!';  $err_code=1;}

                $this->fields=$fields;
                $this->err=$err;
                $this->err_code=$err_code;

                if($create && $err=='') {
                    Model_BSX_Account::createUser($fields);
                } else if ($update && $err=='') {
                    Model_BSX_Account::updateUser($id, $fields, $set_pass);
                }
            } else {
                $this->err='Brak uprawnień';
            }
        }
    }

    private function showCreateUpdateCompany($w) //tworzenie oddzialu
    {
        $ajax=(int)getPost('ajax');
        $fields=array('pname'=>'','pcity'=>'','ppostcode'=>'','pstreet'=>'','idparentbr'=>'', 'pphone1'=>'', 'pstreet_n1'=>'');

        if ($ajax==1) {
            foreach ($fields as $name=>$value) if (isset($_POST[$name])) $fields[$name]=getPost($name);

            $err = '';
            $err_code = 0;
            if ($fields['pname'] == '') $err = 'Podaj nazwę oddziału';
            else if ($fields['pstreet'] == '') $err = 'Podaj adres';
            else if ($fields['pcity'] == '') $err = 'Podaj miejscowość';
            else if ($fields['ppostcode'] == '') $err = 'Podaj kod pocztowy';
            else if (sql_row('SELECT id FROM bs_contractors WHERE pname=:name and idparentbr is not null', array(':name' => $fields['pname']))) 
                { $err = 'Istnieje już taki oddział. Musisz podać inną nazwę';  $err_code=1;}

            // if($err=='') $err="wymuszony blad";
            if ($err != '') {
                $this->ajaxResult=array('err'=>$err, 'err_code'=>$err_code);
            } else {
                Model_BSX_Account::createCompany($fields);
                $this->ajaxResult=array('err'=>'');
            }
        }
    }

    private function showUpdateCompany($w) {
        $ajax=(int)getPost('ajax');
        $uid=(int)getPost('uid');
        $id=(int)getPost('id');
        if($ajax==1) {
            //if user['b2b_status'] == 1 -> admin glowny
            if(Model_BSX_Account::testPermission('admin', $uid)) {//tylko admin moze robic update

                $fields=array('id'=>'','pname'=>'','pcity'=>'','ppostcode'=>'','pstreet'=>'', 'pphone1'=>'', 'pstreet_n1'=>'');
                foreach ($fields as $name=>$value) if (isset($_POST[$name])) $fields[$name]=getPost($name);
                $err = '';
                $err_code = 0;
                if ($fields['pname'] == '') $err = 'Podaj nazwe oddziału';
                else if ($fields['pstreet'] == '') $err = 'Podaj adres!';
                else if ($fields['pcity'] == '') $err = 'Podaj miejscowość!';
                else if ($fields['ppostcode'] == '') $err = 'Podaj kod pocztowy!';
                else if (!sql_row('SELECT id FROM bs_contractors WHERE id=:i and idparentbr is not null', array(':i' => $id))) 
                    { $err = 'Nie  istnieje taki odział';  $err_code=2;}
                else if (sql_row('SELECT id FROM bs_contractors WHERE pname=:name and idparentbr is not null and id!=:i', array(':name' => $fields['pname'], ':i'=>$id))) 
                    { $err = 'Istnieje już taki oddział. Musisz podać inną nazwę';  $err_code=1;}

                if ($err != '') $this->ajaxResult=array('err'=>$err, 'err_code'=>$err_code);
                else {
                    $row=Model_BSX_Account::updateCompany($id, $fields);
                    $this->ajaxResult=array('err'=>'','row'=>$row);    
                }
            } else {
                $this->ajaxResult=array('err'=>'Brak uprawnień');
            }
        }
    }

    private function showUpdateUser($w) {
        $ajax=(int)getPost('ajax');
        $uid=(int)getPost('uid');
        $id=(int)getPost('id');
        if($ajax==1) {
            //if user['b2b_status'] == 1 -> admin glowny
            if(Model_BSX_Account::testPermission('admin', $uid)) {//tylko admin moze robic update

                $fields=array('id'=>'','pname'=>'','b2b_status'=>'','pemail'=>'','idbranch'=>'', 'ppass1'=>'', 'ppass2'=>'', 'setpass'=>'');
                foreach ($fields as $name=>$value) if (isset($_POST[$name])) $fields[$name]=getPost($name);
                $err = '';
                $err_code = 0;
                $set_pass=(int)$fields['set_pass'];
                if ($fields['pname'] == '') $err = 'Musisz podać Imie i Nazwisko użytkownika';
                else if ($fields['pemail'] == '') $err = 'Podaj email';
                else if ($fields['b2b_status'] == '') $err = 'Podaj status';
                else if ($fields['idbranch'] == '') $err = 'Podaj oddział';
                else if ($set_pass==1 && $fields['ppass1'] == '') $err = 'Podaj hasło';
                else if ($set_pass==1 && $fields['ppass2'] == '') $err = 'Powtórz hasło';
                else if ($set_pass==1 && $fields['ppass1'] !== $fields['ppass2'] ) $err = 'Musisz podać 2 razy to samo hasło';
                else if (!sql_row('SELECT id FROM bs_contractors WHERE id=:i and idparentbr is null', array(':i' => $id))) 
                    { $err = 'Nie  istnieje taki użytkownik';  $err_code=2;}
                else if (sql_row('SELECT id FROM bs_contractors WHERE pemail=:email and idparentbr is null and id!=:i', array(':email' => $fields['pemail'], ':i'=>$id))) 
                    { $err = 'Istnieje już użytkownik o tym adresie e-mail';  $err_code=1;}

                if ($err != '') $this->ajaxResult=array('err'=>$err, 'err_code'=>$err_code);
                else {
                    $row=Model_BSX_Account::updateUser($id, $fields, $set_pass);
                    $this->ajaxResult=array('err'=>'','row'=>$row);    
                }
            } else {
                $this->ajaxResult=array('err'=>'Brak uprawnień');
            }
        }
    }

    
    //B2B
    //wyswietla kontrahentow (pracownikow firmy) przynaleznych do danego oddziału
    //nalezy sprawdzic czy uzytkownik zalogowany
    private function showCompanyEmployees($w) {
        $ajax=(int)getPost('ajax');
        $uid=(int)getPost('uid');
        $idbr=(int)getPost('idbr');
        if($ajax==1) {
            // admin glowny lub admin_br i admin_br musi miec idbranch rowne tej z ktorej chce pobrac pracownikow
            if(Model_BSX_Account::testPermission('admin', $uid) || Model_BSX_Account::testPermission('admin_br', $uid)) {
                $this->ajaxResult=array('err'=>'', 'users'=>Model_BSX_Account::getCompanyEmployees($idbr));
            } else {
                $this->ajaxResult=array('err'=>'Brak uprawnień');
            }
        }
    }

    private function showCompanies() {
        $ajax=(int)getPost('ajax');
        $uid=(int)getPost('uid');
        if($ajax==1) {
            //if user['b2b_status'] == 1 -> admin glowny
            if(Model_BSX_Account::testPermission('admin', $uid)) {
                $this->ajaxResult=array('err'=>'', 'companies'=>Model_BSX_Account::getCompanies());
            } else {
                $this->ajaxResult=array('err'=>'Brak uprawnień');
            }
        }
    }


    private function showCompanyById($w) {
        $ajax=(int)getPost('ajax');
        $uid=(int)getPost('uid');
        $cid=(int)getPost('cid');
        if($ajax==1) {
            //if user['b2b_status'] == 1 -> admin glowny
            if(Model_BSX_Account::testPermission('admin', $uid)) {
                $this->ajaxResult=array('err'=>'', 'company'=>Model_BSX_Account::getCompany($cid));
            } else {
                $this->ajaxResult=array('err'=>'Brak uprawnień');
            }
        }
    }

    private function showUserById($w) {
        $ajax=(int)getPost('ajax');
        $uid=(int)getPost('uid');
        $id=(int)getPost('id');
        if($ajax==1) {
            //if user['b2b_status'] == 1 -> admin glowny
            if(Model_BSX_Account::testPermission('admin', $uid)) {
                $this->ajaxResult=array('err'=>'', 'user'=>Model_BSX_Account::getUser($id));
            } else {
                $this->ajaxResult=array('err'=>'Brak uprawnień');
            }
        }
    }

    //wyswietlenie wszystkich uzytkownikow
    private function showAllUsers($w) {
        $ajax=(int)getPost('ajax');
        $uid=(int)getPost('uid');
        $start=(int)getPost('start');
        $count=(int)getPost('count');
        if($ajax==1) {
            //if user['b2b_status'] == 1 -> admin glowny
            if(Model_BSX_Account::testPermission('admin', $uid)) {
                $this->ajaxResult=array('err'=>'', 'res'=>Model_BSX_Account::getUsers($start, $count));
            } else {
                $this->ajaxResult=array('err'=>'Brak uprawnień');
            }
        }
    }

    private function showDelete($w) {
        $ajax=(int)getPost('ajax');
        $uid=(int)getPost('uid');
        $cid=(int)getPost('contrid');
        if($ajax==1) {
            //if user['b2b_status'] == 1 -> admin glowny
            if(Model_BSX_Account::testPermission('admin', $uid)) {
                Model_BSX_Account::delete($cid);
                $this->ajaxResult=array('err'=>'');
            } else {
                $this->ajaxResult=array('err'=>'Brak uprawnień');
            }
        }
    }


    private function showLogin($w)
    {
        // echo 'cookies';
        //     print_r($_COOKIE);
        //     exit;

        $this->view->title='Logowanie';
        $this->view->path[]=array('caption'=>'Logowanie','url'=>$this->link.'login');

        $page=Model_BSX_Core::create_view($this,'part_account_login1');
        $fields['countries']=array('Polska'=>'Polska');
        $fields['pcountry']='';
        $page->set('f', $fields);
        $save=(int)getPost('save');

        $username = getPost('username', '');
        $password = getPost('password', '');
        $ajax=getPost('ajax', '');
        $test=getPost('test', '');
        $mock=getPost('mock','');

        //sprawdzanie czy uzytownik zalogowany
        if($test==1 && $ajax==1) {
            if($mock==1) {
                $id=683;
                $row=sql_row('SELECT id, cms_status, pname, idprice, pemail, idparent, b2b_status, idbranch FROM bs_contractors WHERE id=:id',array(':id' => $id));
                $this->ajaxResult=array('result'=>true, 'row'=>$row );
            } else {
                $res=true;
                if (empty($_SESSION['login_user']['id']) || $_SESSION['login_user']['id']<=0)  {
                    $res=false;
                }
                $this->ajaxResult=array('result'=>$res, 'row'=> $_SESSION['login_user']);
            }
        }

        if ($save==0) {
            $ul = Cookie::get('tsxul');
            $up = Cookie::get('tsxup');
            $us = Cookie::get('tsxus');
            if ($ul != '' && $up != '' && $us != '') {
                $us = sha1($us) . 'bsx' . md5($us);
                $enc = new Encrypt($us, MCRYPT_MODE_ECB, MCRYPT_BLOWFISH);
                $username = $enc->decode($ul);
                $password = $enc->decode($up);
            }
        }


        if($username!='' && $password!='')
        {
            $res=Model_BSX_Account::login($username,$password,getPost('remember', '')==1);
            if($ajax==1) {
                $row=sql_row('SELECT id, cms_status, pname, idprice, idparent, pemail FROM bs_contractors WHERE (pemail = :user OR cms_email = :user OR pnip=:user) AND (cms_pass = :password)',array(':user' => $username,':password' => sha1($password)));
                $this->ajaxResult=array('result'=>$res, 'row'=>$row);
            }
            else {
                if ($res>0)
                {
                    Header('Location: /account');
                    exit;
                } else
                {
                    if ($res==-5) $page->set('msg1','<div class="alert alert-danger">Konto nie zostało jeszcze aktywowane!</div>');
                    else if ($res==-6) $page->set('msg1','<div class="alert alert-danger">Na to konto nie można się zalogować!</div>');
                    else $page->set('msg1','<div class="alert alert-danger">Podano nieprawidłowy e-mail i/lub hasło!</div>');
                    $page->set('username',$username);
                }
            }
            Cookie::delete('tsxul');
            Cookie::delete('tsxup');
            Cookie::delete('tsxus');
        }

        if ($save==2)
        {
            $nf = getPost('pname', '');
            $u = getPost('pemail', '');
            $n = getPost('pnip', '');
            $p1 = getPost('ppass1', '');
            $p2 = getPost('ppass2', '');
            $err='';
            if ($u=='') $err='Podaj adres e-mail!';
            else if ($n=='') $err='Podaj numer NIP!';
            else if ($nf=='' && isset($_POST['pname'])) $err='Podaj nazwę firmy!';
            else if (sql_row('SELECT id FROM bs_contractors WHERE pemail=:email',array(':email'=>$u))) $err='Istnieje już użytkownik o tym adresie e-mail!';
            else if (sql_row('SELECT id FROM bs_contractors WHERE cms_email=:email',array(':email'=>$u))) $err='Istnieje już użytkownik o tym adresie e-mail!';
            else if (sql_row('SELECT id FROM bs_contractors WHERE pnip=:nip',array(':nip'=>$n))) $err='Istnieje już użytkownik o tym numerze NIP!';
            else if ($p1=='') $err='Podaj hasło!';
            else if ($p1!=$p2) $err='Podano dwa różne hasła!';
            if ($err!='') {
                $page->set('msg2','<div class="alert alert-danger">'.$err.'</div>');
                $page->set('pemail',$u);
                $page->set('pnip',$n);
            } else {
                if (Model_BSX_Core::isOption('option_account_createAccountActivation')) {
                    $page->set('msg2', '<div class="alert alert-success"><strong>Konto zostało utworzone poprawnie.</strong> Zanim będziesz mógł zalogować się na swoje konto musi ono został aktywowane przez administratora.</div>');
                } else {
                    $page->set('msg2', '<div class="alert alert-success"><strong>Konto zostało utworzone poprawnie.</strong> Sprawdź pocztę e-mail i aktywuj swoje konto postępując zgodnie z instrukcjami.</div>');
                }

                $system=Model_BSX_System::init();

                $c=array();
                $c['add_id_user']=$system->user['id'];
                $c['add_time']=date('Y-m-d H:i:s');
                $c['modyf_id_user']=$c['add_id_user'];
                $c['modyf_time']=$c['add_time'];
                $c['idcompany']=$system->company['id'];
                $c['idbranch']=$system->branche['id'];
                $c['idowner']=$system->user['id'];
                $c['pnip']=$n;
                $c['pname']=$nf;
                $c['cms_status']=0;
                $c['cms_idsite']=Model_BSX_Core::$bsx_cfg['id'];
                $c['cms_datereg']=date('Y-m-d H:i:s');
                $c['cms_email']=$u;
                $c['pemail']=$u;
                $c['cms_pass']=sha1($p1);
                $c['cms_md5']=md5(time().$p1);
                if (!empty(Model_BSX_Core::$bsx_cfg['ptitle'])) $c['pfrom']=Model_BSX_Core::$bsx_cfg['ptitle'];

                $u=sql_insert('bs_contractors',$c);

                $mail=Model_BSX_Core::mail_view('rejestracja','Założenie konta');
                $mail->set('d',$c);
                $mail->set('shop',Model_BSX_Core::$bsx_cfg);
                $vmail=$mail->render();

                BinUtils::explodeMail(Model_BSX_Core::$bsx_cfg['pemail'],$em,$nz);

                if (!Model_BSX_Core::isOption('option_account_createAccountActivation')) {
                    $email = Email::factory()
                        ->subject($mail->title)
                        ->to($c['cms_email'])
                        ->from($em, $nz)
                        ->message($vmail)
                        ->send();
                }
            }

        }

        if($ajax !== 1) {
            $page->set('title','Logowanie');
            echo $page;
        }
    }
    


    //------------------- B2B - koniec zmian
    //------------------------------------------

     private function showRegister($w) 
    {
        $this->view->title='Rejestracja';
        $this->view->path[]=array('caption'=>'Zakładanie konta','url'=>$this->link.'/register');

        $page=Model_BSX_Core::create_view($this,'part_account_login1');
        $save=(int)getPost('save');
        $ajax=(int)getPost('ajax');

        $fields=array('pname'=>'','pemail'=>'','pnip'=>'','ppass1'=>'','ppass2'=>'','pcity'=>'','ppostcode'=>'','pregulations'=>'','pstreet'=>'','pcountry'=>'','account_type'=>'','idparent'=>'', 'b2b_status'=>'', 'pphone1'=>'', 'pstreet_n1'=>'', 'noemail'=>'', 'idbranch'=>'');

        $countries=array('Polska'=>'Polska');
        $fields['countries']=$countries;

        if ($save==1) {
            foreach ($fields as $name=>$value) if (isset($_POST[$name])) $fields[$name]=getPost($name);
            if(isset($fields['account_type']) && $fields['account_type']=='consumer') $fields['pnip']='';

            $err = '';
            $err_code = 0;
            $a_type=$fields['account_type'];
            if ($fields['pname'] == '') $err = 'Podaj '.(($fields['account_type']==='consumer')?' imię i nazwisko ':' nazwę firmy ').'!';
            else if ($fields['account_type'] == '') $err = 'Nie podano czy jestes osobą fizyczną czy firmą!';
            else if ($fields['pnip'] == '' && $fields['account_type']=='business') $err = 'Podaj numer NIP!';
            else if ($fields['pstreet'] == '') $err = 'Podaj adres!';
            else if ($fields['pcity'] == '') $err = 'Podaj miejscowość!';
            else if ($fields['ppostcode'] == '') $err = 'Podaj kod pocztowy!';
            else if ($fields['pcountry'] == '') $err = 'Podaj kraj!';
            else if ($fields['pemail'] == '' && $a_type!=='company') $err = 'Podaj adres e-mail!';
            else if ($a_type !=='company' && sql_row('SELECT id FROM bs_contractors WHERE pemail=:email', array(':email' => $fields['pemail'])))
             { $err = 'Istnieje już osoba/firma o tym adresie e-mail!';  }
            else if ($a_type !=='company' &&  sql_row('SELECT id FROM bs_contractors WHERE cms_email=:email', array(':email' => $fields['pemail']))) $err = 'Istnieje już osoba/firma o tym adresie e-mail!';
            else if ($a_type ==='company' && sql_row('SELECT id FROM bs_contractors WHERE pname=:name and idparentbr is not null', array(':name' => $fields['pname']))) 
                { $err = 'Istnieje już taki oddział. Musisz podać inną nazwę';  $err_code=1;}
            else if ($fields['account_type']=='business' && sql_row('SELECT id FROM bs_contractors WHERE pnip=:nip', array(':nip' => $fields['pnip']))) $err = 'Istnieje już firma o tym numerze NIP!';
            else if ($fields['ppass1'] == '' && $a_type !=='company') $err = 'Podaj hasło!';
            else if ($fields['ppass1'] != $fields['ppass2']) $err = 'Podano dwa różne hasła!';
            else if ($fields['pregulations'] == '') $err = 'Należy zaakceptować regulamin!';

            // if($err=='') $err="wymuszony blad";
            if ($err != '') {
                if($ajax==1) $this->ajaxResult=array('err'=>$err, 'err_code'=>$err_code);
                else $page->set('msg2', '<div class="alert alert-danger">' . $err . '</div>');
            } else {
                if($ajax==1) $this->ajaxResult=array('err'=>'');
                else {
                    if (Model_BSX_Core::isOption('option_account_createAccountActivation')) {
                        $page->set('msg2', '<div class="alert alert-success"><strong>Konto zostało utworzone poprawnie.</strong> Zanim będziesz mógł zalogować się na swoje konto musi ono zostać aktywowane przez administratora.</div>');
                    } else {
                        $page->set('msg2', '<div class="alert alert-success"><strong>Konto zostało utworzone poprawnie.</strong> Sprawdź pocztę e-mail i aktywuj swoje konto postępując zgodnie z instrukcjami.</div>');
                    }
                }

                $system = Model_BSX_System::init();

                $c = array();
                $c['add_id_user'] = $system->user['id'];
                $c['add_time'] = date('Y-m-d H:i:s');
                $c['modyf_id_user'] = $c['add_id_user'];
                $c['modyf_time'] = $c['add_time'];
                $c['idcompany'] = $system->company['id'];
                $c['idbranch'] = $system->branche['id'];
                $c['idowner'] = $system->user['id'];
                $c['cms_status'] = 0;
                $c['cms_idsite'] = Model_BSX_Core::$bsx_cfg['id'];
                $c['cms_datereg'] = date('Y-m-d H:i:s');
                $c['cms_email'] = $fields['pemail'];
                $c['cms_pass'] = sha1($fields['ppass1']);
                $c['cms_md5'] = md5(time() . $fields['pemail']);
                if (!empty(Model_BSX_Core::$bsx_cfg['ptitle'])) $c['pfrom'] = Model_BSX_Core::$bsx_cfg['ptitle'];

                if($a_type==='company') //tworzenie oddzialu
                {
                    $c['idparentbr']=$fields['idparent'];
                }


                foreach ($fields as $name=>$value) $c[$name]=$value;
                unset($c['pregulations']);
                unset($c['ppass1']);
                unset($c['ppass2']);
                unset($c['countries']);
                unset($c['account_type']);
                unset($c['idparent']);
                unset($c['noemail']);

                // print_r('{"err":"tutaj"}');
               // $c['err']='tutaj';
               //  print_r(json_encode($c));
               //  exit;

                $u = sql_insert('bs_contractors', $c);

                
                if($fields['noemail']!=1) {//musi byc != a nie !== bo inaczej wymagany jest dokladny typ a wiec 1 oraz '1' to nie to samo
                    $mail = Model_BSX_Core::mail_view('rejestracja', 'Założenie konta');
                    $mail->set('d', $c);
                    $mail->set('shop', Model_BSX_Core::$bsx_cfg);
                    $vmail = $mail->render();

                    BinUtils::explodeMail(Model_BSX_Core::$bsx_cfg['pemail'], $em, $nz);

                    if (!Model_BSX_Core::isOption('option_account_createAccountActivation')) {
                        $email = Email::factory()
                            ->subject($mail->title)
                            ->to($c['cms_email'])
                            ->from($em, $nz)
                            ->message($vmail)
                            ->send();
                    }
                }

                $page->set('success','success');
                //czyszczenie formularza
                $fields=array(
                   'countries'=>$countries,
                   'pcountry'=>'',
                );
            }
        }

       
        if($ajax!==1) {
            $page->set('title','Logowanie');
            $page->set('f',$fields);
            echo $page;
        }
    }


    private function showRemind($w)
    {
        $this->view->title='Resetowanie hasła';
        $this->view->path[]=array('caption'=>'Resetowanie hasła','url'=>$this->link.'/remind');

        $info='';
        $email=getGetPost('email');
        $code=getGetPost('code');
        $opc=0;
        if (!empty($email))
        {
            if (!empty($code)) {
                $u=sql_row('SELECT id,pemail,add_time FROM bs_contractors WHERE (pemail=:email OR cms_email=:email OR pnip=:email) AND add_time=:time',array(':email'=>$email,':time'=>date('Y-m-d H:i:s',$code)));
                if ($u) {

                    $p1=getPost('pass1');
                    $p2=getPost('pass2'); ;
                    if (isset($_POST['save'])) {
                        $err='';
                        if ($p1 == '') $err = 'Podaj nowe hasło do konta!';
                        else if ($p1 != $p2) $err = 'Podano dwa różne hasła!';
                        if ($err!='')  $info='<div class="alert alert-danger">'.$err.'</div>';
                        else {
                            sql_query('UPDATE bs_contractors SET cms_pass=:p WHERE id=:id',array(':id'=>$u['id'],':p'=>sha1($p1)));
                            $info='<div class="alert alert-success">Hasło zostało zmienione!</div>';
                            $opc=2;
                        }
                    }
                } else {
                    $info='<div class="alert alert-danger">Nieprawidłowy kod autoryzacyjny!</div>';
                    $code='';
                }
            } else
            {
                $u=sql_row('SELECT id,pemail,add_time,cms_email,pemail FROM bs_contractors WHERE pemail=:email OR cms_email=:email OR pnip=:email',array(':email'=>$email));
                if ($u && empty($u['pemail']) && empty($u['cms_email'])) {
                    $info='<div class="alert alert-danger">Użytkownik o tym numerze NIP nie ma zdefiniowanego adresu e-mail! Skontaktuj się z nami aby utworzyć sobie konto.</div>';
                } else
                    if ($u) {
                        $email=$u['cms_email'];
                        if ($u['pemail']=='') $u['pemail']=$u['cms_email'];
                        if (empty($email)) {
                            $email=$u['pemail'];
                            sql_query('UPDATE bs_contractors SET cms_email=:email WHERE id=:id',array(':id'=>$u['id'],':email'=>$email));
                        }
                        $mail=Model_BSX_Core::mail_view('resetowanie','Resetowanie hasła');
                        $mail->set('d',$u);
                        $mail->set('shop',Model_BSX_Core::$bsx_cfg);
                        $vmail=$mail->render();

                        BinUtils::explodeMail(Model_BSX_Core::$bsx_cfg['pemail'],$em,$nz);
                        Email::factory()->subject($mail->title)->to($email)->from($em,$nz)->message($vmail)->send();
                        $info='<div class="alert alert-success">Wysłano wiadomość z instrukcją rozpoczęcia resetowania hasła!</div>';
                        $email='';
                    } else {
                        $info='<div class="alert alert-danger">Brak użytkownika o tym adresie e-mail!</div>';
                    }
            }
        }

        $p=Model_BSX_Core::create_view($this,'part_account_remind');
        $p->set('info',$info);
        $p->set('subtitle','Resetowanie hasła');
        $p->set('email',$email);
        $p->set('code',$code);
        $p->set('opc',$opc);


        $page=Model_BSX_Core::create_view($this,'page_article');
        $page->set('submenu','profile');
        $page->set('body',$p);
        $page->set('subtitle','Profil');
        $page->set('title','Resetowanie hasła');
        $page->path[]=array('caption'=>'Resetowanie hasła','url'=>$this->link.'/remind');

        echo $page;
    }

    private function showLogout()
    {
        $_SESSION['login_user']=array('id'=>0);
        Cookie::delete('tsxul');
        Cookie::delete('tsxup');
        Cookie::delete('tsxus');
        Header('Location: /');
        exit;
    }


    private function showHome($w)
    {
        if (!Model_BSX_Account::test_login($this)) return;

        $this->view->title='Strona główna użytkownika';
        $this->view->path[]=array('caption'=>'Strona główna','url'=>$this->link.'/home');


        $u=sql_row('SELECT * FROM bs_contractors WHERE id=:id',array(':id'=>$_SESSION['login_user']['id']));
        $imie='';
        if ($u['cms_firstname']!='') $imie=' '.$u['cms_firstname'];

        $t = new Model_BSX_Table('bs_orders', 'tbl', $this);
        $t->formURL = 'account';
        $t->where = 'pidcontractor=' . $_SESSION['login_user']['id'];

        $t->tableView = Model_BSX_Core::create_view($this, 'part_table_std');
        $t->showView = Model_BSX_Core::create_view($this, 'part_table_show_std');
        $t->showShowBtn = true;
        $t->limit=2;
        $t->ajax=true;
        $t->linkShow = 'account/order/{id}';
        $t->onCell='fnc_OrderCell';
        $t->headerHTML='<h1>Witaj'.$imie.'!</h1><p>Twoje ostatnie zamówienia:</p>';
        $t->addField('nnodoc', 'Numer', 'center', 'varchar', '');
        $t->addField('ndate_issue', 'Data wystawienia', 'center', 'date', '');
        $t->addField('nstatus', 'Status', 'center', 'select', '150px');
        $t->addField('nstotal_n', 'Wartości netto', 'right', 'price', '');
        $t->addField('nstotal_b', 'Wartości brutto', 'right', 'price', '');



        $page=Model_BSX_Core::create_view($this,'part_account_home1');
        $page->set('submenu','home');
        $page->set('subtitle','Strona główna');
        $page->set('body',$t->render());
        echo $page;

    }

    private function showProfile($w)
    {
        if (!Model_BSX_Account::test_login($this)) return;

        $this->view->title='Profil';
        $this->view->path[]=array('caption'=>'Profil','url'=>$this->link.'/profile');

        $info='';
        $fields=array('cms_firstname');
        if (isset($_POST['save']))
        {
            $p1=getPost('pass1');
            $p2=getPost('pass2');
            $d=array();
            foreach ($fields as $field) $d[$field]=getPost($field);
            if ($p1==$p2 && $p1!='')
            {
                $d['ppass']=sha1($p1);
                $info='<div class="alert alert-success">Hasło zostało zmienione!</div>';
            } else $info='<div class="alert alert-success">Informacje zostały zaktualizowane!</div>';
            sql_update('bs_contractors',$d,$_SESSION['login_user']['id']);
        }

        $f=sql_row('SELECT * FROM bs_contractors WHERE id=:id',array(':id'=>$_SESSION['login_user']['id']));

        $p=Model_BSX_Core::create_view($this,'part_account_profile');
        $p->set('f',$f);
        $p->set('info',$info);
        $p->set('subtitle','Profil');

        $page=Model_BSX_Core::create_view($this,'part_account_home1');
        $page->set('submenu','profile');
        $page->set('body',$p);
        $page->set('subtitle','Profil');

        echo $page;
    }



    private function showCompany($w)
    {
        if (!Model_BSX_Account::test_login($this)) return;

        $this->view->title='Twoja firma';
        $this->view->path[]=array('caption'=>'Twoja firma','url'=>$this->link.'/company');

        $info='';
        $fields=array('pname','pnip','pstreet','pstreet_n1','ppostcode','pcity','pcountry','pemail');
        if (isset($_POST['save']))
        {
            $p1=getPost('pass1');
            $p2=getPost('pass2');
            $d=array();
            foreach ($fields as $field) $d[$field]=getPost($field);
            $info='<div class="alert alert-success">Informacje zostały zaktualizowane!</div>';
            sql_update('bs_contractors',$d,$_SESSION['login_user']['id']);
        }

        $f=sql_row('SELECT * FROM bs_contractors WHERE id=:id',array(':id'=>$_SESSION['login_user']['id']));

        $p=Model_BSX_Core::create_view($this,'part_account_company');
        $p->set('f',$f);
        $p->set('info',$info);
        $p->set('subtitle','Informacje o firmie');

        $page=Model_BSX_Core::create_view($this,'part_account_home1');
        $page->set('submenu','company');
        $page->set('body',$p);
        $page->set('subtitle','Informacje o firmie');
        echo $page;
    }

    private function showActivate($w)
    {
        $info='';

        $page=Model_BSX_Core::create_view($this,'part_account_login1');

        if ($w[1]!='')
        {
            $ww=sql_row('SELECT id FROM bs_contractors WHERE cms_md5=:md5 AND cms_status=0',array(':md5'=>$w[1]));
            if ($ww)
            {
                sql_query('UPDATE bs_contractors SET cms_status=2 WHERE id=:id',array(':id'=>$ww['id']));
                $info='<div class="alert alert-success">Konto zostało aktywowane poprawnie. Możesz już się na nie zalogować!</div>';
            } else {
                $info='<div class="alert alert-danger">Nie udało się aktywować konta!</div>';
            }

        }
        $page->set('info',$info);
        echo $page;
    }

    function fnc_OrderCell($table,$row,$name,$value)
    {
        if ($name=='nstatus')
        {
            $v=$value;
            $value=$this->statusOrdersList[$value];
            if ($v==1) {
                if ($row['nprice']=='' || $row['nprice']=='1970-01-01')
                {
                    $value.='<br /><span style="color:red;"> Czeka na płatność </span>';
                } else {
                    $value = '<span style="color:red;"> ' . $value . ' </span>';
                }
            } else if ($v==2) {
                $value.='<br /><span style="color:green;"> Czeka na realizację </span>';
            } else if ($v==3)
            {
                $value='<span class="label label-sm label-danger"> '.$value.' </span>';
            } else if ($v==4)
            {
                $value='<span class="label label-sm label-info"> '.$value.' </span>';
            } else if ($v==5)
            {
                $value='<span class="label label-sm label-success"> '.$value.' </span>';
            }
        }
        return $value;
    }

    private function showOrders($w)
    {
        if (!Model_BSX_Account::test_login($this)) return;

        $this->view->title='Twoje zamówienia';
        $this->view->path[]=array('caption'=>'Twoje zamówienia','url'=>$this->link.'/orders');

        $t = new Model_BSX_Table('bs_orders', 'tbl', $this);

        $t->formURL = 'account/orders';
        $t->where = 'pidcontractor=' . $_SESSION['login_user']['id'];

        $t->tableView = Model_BSX_Core::create_view($this, 'part_table_std');
        $t->showView = Model_BSX_Core::create_view($this, 'part_table_show_std');
        $t->showPagesExacly = true;
        $t->showSearchPanel = false;
        $t->showEditBtn = false;
        $t->showDelBtn = false;
        $t->showShowBtn = true;
        $t->ajax=true;
        $t->linkShow = 'account/order/{id}';
        $t->onCell='fnc_OrderCell';
        //$t->headerHTML='';
        //$t->footerHTML='</div></div>';
        $t->addField('nnodoc', 'Numer', 'center', 'varchar', '');
        $t->addField('ndate_issue', 'Data wystawienia', 'center', 'date', '');
        $t->addField('nstatus', 'Status', 'center', 'select', '150px');
        $t->addField('nstotal_n', 'Wartości netto', 'right', 'price', '');
        $t->addField('nstotal_b', 'Wartości brutto', 'right', 'price', '');


        $r='<h1>Lista zamówień</h1>'.$t->render().'';

        $page=Model_BSX_Core::create_view($this,'part_account_home1');
        $page->set('submenu','orders');
        $page->set('subtitle','Zamówienia');
        $page->set('body',$r);

        echo $page;
    }

    private function showOrder($w)
    {
        $sklep=Model_BSX_Shop::init();
        $order=$sklep->getOrderById($w[1]);

        if (!$order) die('Brak uprawnień!');

        $this->view->title='Zamówienie '.$order['nnodoc'];
        $this->view->path[]=array('caption'=>'Zamówienie','url'=>$this->link.'/orders');

        $pay=new Model_BSX_Payments();
        if (!empty(Model_BSX_Core::$bsx_cfg['model'])&&is_callable(array(Model_BSX_Core::$bsx_cfg['model'], 'getSuppliers'),true))
        {
            $pay->suppliers=forward_static_call(array(Model_BSX_Core::$bsx_cfg['model'], 'getSuppliers'));
        }

        $pay->price=$order['nstotal_b'];
        $pay->orderID=$order['id'];
        $pay->urlOK=Model_BSX_Core::$bsx_cfg['purl'].'/sklep/order/'.$order['md5'].'?return=true';
        $pay->urlFAIL=Model_BSX_Core::$bsx_cfg['purl'].'/sklep/order/'.$order['md5'].'?return=false';
        $pay->desc='Zamowienie '.$order['nnodoc'];
        $pay->buttonPriceTXT='Zapłać za zamówienie '.$order['nnodoc'].' - '.BinUtils::price($order['nstotal_b']).' PLN';
        $pay->buyer=array('name'=>$order['pname'],'street'=>$order['pstreet'].' '.$order['pstreet_n1'],'city'=>$order['pcity'],'postcode'=>$order['ppostcode'],'country'=>$order['pcountry'],'email'=>$order['pemail']);



        $t = new Model_BSX_Table('bs_orders_pr', 'tbl', $this);

        $t->formURL = 'account/order/'.$order['id'];
        $t->where = 'iddoc=' . $order['id'];

        $t->tableView = Model_BSX_Core::create_view($this, 'part_table_std');
        $t->showView = Model_BSX_Core::create_view($this, 'part_table_show_std');
        //$t->onCell='fnc_OrderCell';
        $t->addField('pname', 'Nazwa', 'left', 'varchar', '');
        $t->addField('pquantity', 'Ilość', 'right', 'price', '');
        $t->addField('pstotal_n', 'Wartości<br>netto', 'right', 'price', '');
        $t->addField('pstotal_v', 'Wartości<br>netto', 'right', 'price', '');
        $t->addField('pstotal_b', 'Wartości<br>brutto', 'right', 'price', '');

        $view = Model_BSX_Core::create_view($this, 'part_account_order');
        $view->set('order', $order);
        $view->set('products', $t->render());
        $view->set('id', $w[1]);
        $view->set('pay',$pay);


        $page=Model_BSX_Core::create_view($this,'part_account_home1');
        $page->set('submenu','orders');
        $page->set('subtitle','Zamówienie '.$order['nnodoc']);
        $page->set('body',$view);
        echo $page;
    }
}