<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  
************************************************************************************/

class Controller_B2B_Core extends Controller {

   

    private $b2b_statuses_a=array(1,9,10,20);//mozliwe statusy do nadawania przez admina: admin, oddział, admin oddziału, zwykly user
    private $b2b_statuses_abr=array(10,20); //mozliwe statusy do nadawania przez admina oddziału: admin oddziału, zwykly user
    private $err=null;//komunikat bledu - liczy sie w przypadku, gdy err_code rozny od 0
    private $err_code=0;//0 - brak błedu, kazda inna wartosc to jakis kod bledu
    private $fields=null;
    private $row=null;
    private $rows=null;
    private $res=null;
    private $user=null;//zalogowany uzytkownik
    private $_user=null;//czy zalogowany uzytkownik ma uprawnienia uzytkownika
    private $auth=null;
    private $admin=false;//czy zalogowany uzytkownik ma uprawnienia admina
    private $admin_br=false;//czy zalogowany uzytkownik ma uprawnienia kierownika oddziału
    private $uid=0;

    public function before() {
        parent::before();
        Model_BSX_B2B::getLoggedUser(getGetPost('auth',''));
        $this->auth=getGetPost('auth','');
        $this->user=$_SESSION['user_b2b'];
        $this->is_logged=$_SESSION['b2b_logged'];
        if($this->is_logged) {
            $this->uid=$this->user->id;
            $this->admin=Model_BSX_B2B::testPermission('admin', $this->uid);
            $this->admin_br=Model_BSX_B2B::testPermission('admin_br', $this->uid);
            $this->_user=Model_BSX_B2B::testPermission('user', $this->uid);
        }
    }

    public function after() {
        $this->ajaxResult['err_code']=$this->err_code;
        if(isset($this->err)) $this->ajaxResult['err']=$this->err;
        if(isset($this->fields)) $this->ajaxResult['fields']=$this->fields;
        if(isset($this->row)) $this->ajaxResult['row']=$this->row;
        if(isset($this->rows)) $this->ajaxResult['rows']=$this->rows;
        if(isset($this->res)) $this->ajaxResult['res']=$this->res;
        echo json_encode($this->ajaxResult);

        parent::after();
    }


    public function action_detect() {
        $m = $this->request->param('modrewrite');
        $w=explode('/',$m);
        while (count($w)<=5) $w[]='';

        if ($w[0]=='login') return $this->login($w);//logowanie uzytkownika
        else if ($w[0]=='logout') return $this->logout($w);//wylogowywanie
        else if ($w[0]=='create_update_user') return $this->createUpdateUser($w);//tworzenie i modyfikacja uzytkownika
        else if ($w[0]=='create_update_branch') return $this->createUpdateBranch($w);//tworzenie i modyfikacja oddziału
        else if ($w[0]=='get_user') return $this->getUserById($w);//pobranie uzytkownika po id
        else if ($w[0]=='get_branch') return $this->getBranchById($w);//pobranie oddzialu po id
        else if ($w[0]=='get_users_by_branch') return $this->getUsersByBranch($w);//uzytkownicy przypisani do danego oddzialu
        else if ($w[0]=='get_branches') return $this->getBranches($w);//wszystkie oddzialy dla danego admina
        else if ($w[0]=='get_users') return $this->getUsers($w);//wszyscy uzytkownicy dla dango admina
        else if ($w[0]=='remove_user') return $this->removeUser($w);//usuwanie pracownika
        else if ($w[0]=='remove_branch') return $this->removeBranch($w);//usuwanie oddziału
        else if ($w[0]=='get_keys') return $this->getKeys($w);//wszystkie klucze dla danej grupy
        else if ($w[0]=='get_licenses') return $this->getLicenses($w);//wszystkie programy
        else if ($w[0]=='get_credit') return $this->getCredit($w);//pobieranie danych odnosnie kredytu kupieckiego, rabatu
        else if ($w[0]=='get_key') return $this->getKeyById($w);//klucz po id
        else if ($w[0]=='create_update_key') return $this->createUpdateKey($w);//tworzenie i modyfikacja kluczy
        else if ($w[0]=='remove_key') return $this->removeKey($w);//usuwanie klucza
        else if ($w[0]=='page') return $this->getArticle($w);//wyswietlanie artykułów
        else if ($w[0]=='templates') return $this->templates($w);//operacje na szablonach
        else if ($w[0]=='send_key') return $this->sendKey($w);//wysyłanie e-maila
        else if ($w[0]=='print_key') return $this->printKey($w);//drukowanie klucza
        else if ($w[0]=='test') return $this->test($w);
        else {
            $this->err="Podany adres nie istnieje";
        }
    }

    // /login - jedyna metoda dostepna bez autentykacji
    // przyklad logowania
    // username:jasio, password:1234 => (err_code, row, auth)
    // przyklad testowania czy uzytkownik zalogowany
    // auth=..., test=1  => (err_code, row)
    private function login($w) {
        $username=getPost('username','');
        $password=getPost('password','');
        $test=getPostInt('test',0,1);
        $remember=getPostInt('remember',0,1);
        
        if(!$test) {
            $res=Model_BSX_B2B::login($username,$password,$remember);
            if($res['id'] > 0) {
                unset($res['row']['idparent']);//tych pol nie trzeba wysylac
                $this->row=$res['row'];
                $this->ajaxResult['auth']=$res['auth'];
            }
            else if ($res['id']==-2) {$this->err_code=-5; $this->err="Konto nie aktywne. Wejdź na swój e-mail  aby je aktywować.";}
            else if ($res['id']==-3) {$this->err_code=-3; $this->err="Konto zablokowane. Skontaktuj sie z administracją.";}
            else if ($res['id']==-4) {$this->err_code=-4; $this->err="Wystąpił bląd. Skontaktuj się z administracją.";}
            else { $this->err_code=-1; $this->err="Błędny login lub hasło.";}
        } else if ($test && $this->is_logged)  {
            $this->row=$this->user;
        } else if($test) {
            $this->err_code=-2;
        }
       
    }

    // /logout
    private function logout($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Uzytkownik niezalogwany'; return;}
        $res=Model_BSX_B2B::deleteSession($this->auth);
        if(!$res) $this->err_code=-1;
    }


    //--------------- creates &&  updates

    
    // /create_update_user - tworzenie i modyfikacja uzytkownika
    //przyklad tworzenia uzytkownika: 
    // auth:.., create:1,  pname: jasio, pemail:jasio@praca.pl, b2b_status:20, b2b_branch:665, ppass1:1234, ppass2:1234 => (err_code, err)
    //przyklad modyfikacji uzytkownika - bez zmiany hasla
    // auth:.., update:1, id:718, pname:jasio2, pemail:jasio@praca.pl, b2b_status:20, b2b_branch:665 => (err_code, err)
    //przyklad modyfikacji uzytkownika - ze zmiana hasla
    // auth:.., update:1, setpass:1, id:718, pname: jasio, pemail:jasio@praca.pl, b2b_status:20, b2b_branch:665, ppass1:1234, ppass2:1234 => (err_code, err)
    private function createUpdateUser($w) 
    {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $id=(int)getPost('id');
        $create=(int)getPost('create');
        $update=(int)getPost('update');
        $admin=$this->admin;
        $admin_br=$this->admin_br;
        $_user=$this->_user;
        $uid=(int)$this->user->id;
        
        $fields=array('id'=>'','pname'=>'','b2b_status'=>'','pemail'=>'','b2b_branch'=>'', 'ppass1'=>'', 'ppass2'=>'', 'setpass'=>'', 'pcity'=>'','ppostcode'=>'','pnip'=>'','pstreet'=>'','pstreet_n1'=>'','pphone1'=>'','pcountry'=>'');
        foreach ($fields as $name=>$value) if (isset($_POST[$name])) $fields[$name]=getPost($name);
        $err = '';
        $err_code = 3;
        $set_pass=(int)$fields['setpass'];
        $fields['b2b_status']=(int)$fields['b2b_status'];
        $fields['b2b_branch']=(int)$fields['b2b_branch'];
        $fields['id']=(int)$fields['id'];

        if ($fields['pname'] == '') $err = 'Musisz podać Imie i Nazwisko użytkownika';
        else if ($fields['pemail'] == '') $err = 'Pole e-mail nie może być puste';
        else if ($admin && !in_array($fields['b2b_status'], $this->b2b_statuses_a)) $err = 'Nieprawidlowy status - admin';
        else if ($admin_br && !in_array($fields['b2b_status'], $this->b2b_statuses_abr)) $err = 'Nieprawidlowy status - admin oddziału';
        else if ($set_pass && $fields['ppass1'] == '') $err = 'Podaj hasło';
         else if ($create && $fields['ppass1'] == '') $err = 'Podaj hasło';
        else if ($set_pass && $fields['ppass1'] !== $fields['ppass2'] ) $err = 'Musisz podać 2 razy to samo hasło';
        else if ($create && $fields['ppass1'] !== $fields['ppass2'] ) $err = 'Musisz podać 2 razy to samo hasło';
        else if ($update && !sql_row('SELECT id FROM bs_contractors WHERE id=:i and idparentbr is null', array(':i' => $id))) 
            { $err = 'Nie  istnieje taki użytkownik';  $err_code=2;}
        else if ($update && sql_row('SELECT id FROM bs_contractors WHERE (pemail=:email or cms_email=:email) and (idparentbr is null) and (id!=:i)', array(':email' => $fields['pemail'], ':i'=>$id))) 
            { $err = 'Istnieje już użytkownik o tym adresie e-mail';  $err_code=1;}
         else if ($create && sql_row('SELECT id FROM bs_contractors WHERE (pemail=:email or cms_email=:email) and (idparentbr is null)', array(':email' => $fields['pemail'])))
            { $err = 'Istnieje już użytkownik o tym adresie e-mail!';  $err_code=1;}

        $this->err_code=$err_code;
        if($err=='') $this->err_code=0;
        else $this->err=$err;

        if($create && $err=='') {
            $res=Model_BSX_B2B::createUser($fields);
            if(!$res) {$this->err_code=-4; $this->err="Brak uprawnień";}
        } else if ($update && $err=='') {
            $res=Model_BSX_B2B::updateUser($id, $fields, $set_pass);
            if(!$res) {$this->err_code=-4; $this->err="Brak uprawnień";}
            else if($uid==$id) { //jesli uzytkownik zmodyfikowal sam siebie,to nalezy ukatulanic dane w sesji
                Model_BSX_B2B::writeSession($this->auth, array('user'=>Model_BSX_B2B::getUser($id)));
            }
        }
        
        
    }

    

    // /create_update_branch - tworzenie i modyfikacja oddziału
    //przyklad tworzenia : 
   // auth:.., create:1,  pname, pcity, ppostcode, pstreet, pstreet_n1, pphone1 => (err_code, err)
    //przyklad modyfikacji oddziału
    // auth:.., update:1, id:718, pname, pcity, ppostcode, pstreet, pstreet_n1, pphone1 => (err_code, err)
    private function createUpdateBranch($w) 
    {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $id=(int)getPost('id');
        $create=(int)getPost('create');
        $update=(int)getPost('update');
        $admin=$this->admin;
        
        if($this->admin) {
            $fields=array('id'=>'','pname'=>'','pcity'=>'','ppostcode'=>'','pstreet'=>'', 'pstreet_n1'=>'', 'pphone1'=>'');
            foreach ($fields as $name=>$value) if (isset($_POST[$name])) $fields[$name]=getPost($name);
            $err = '';
            $err_code = 3;
            $fields['id']=(int)$fields['id'];

            if ($fields['pname'] == '') $err = 'Musisz podać nazwe oddziału';
            else if ($fields['pcity'] == '') $err = 'Pole \"miasto\" nie może być puste';
            else if ($fields['ppostcode'] == '') $err = 'Pole \"kod pocztowy\" nie może być puste';
            else if ($fields['pstreet'] == '') $err = 'Pole  \"adres\" nie może być puste';
            else if ($fields['pstreet_n1'] == '') $err = 'Pole  \"numer domu\" nie może być puste';
            else if ($fields['pphone1'] == '') $err = 'Pole \"numer telefonu\" nie może być puste';
            else if ($update && !sql_row('SELECT id FROM bs_contractors WHERE (id=:i) and (idparentbr is not null)', array(':i' => $id))) 
                { $err = 'Nie  istnieje taki oddział';  $err_code=2;}
            else if ($update && sql_row('SELECT id FROM bs_contractors WHERE (pname=:name) and (idparentbr is not null) and (id!=:i)', array(':name' => $fields['pname'], ':i'=>$id))) 
                { $err = 'Istnieje już oddział o tej nazwie';  $err_code=1;}
             else if ($create && sql_row('SELECT id FROM bs_contractors WHERE pname=:name and idparentbr is not null', array(':name' => $fields['pname'])))
                { $err = 'Istnieje już oddział o takiej nazwie!';  $err_code=1;}

            $this->err_code=$err_code;
            if($err=='') $this->err_code=0;
            else $this->err=$err;
            
            if($create && $err=='') {
                Model_BSX_B2B::createBranch($fields);
            } else if ($update && $err=='') {
                $res=Model_BSX_B2B::updateBranch($id, $fields);
                if(!$res) {$this->err_code=-4; $this->err="Brak uprawnień";}
            }
        } else {
            $this->err='Brak uprawnień';
            $this->err_code=-1;
        }
        
    }

    // /create_update_key
    private function createUpdateKey($w) 
    {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $create=(int)getPost('create');
        $update=(int)getPost('update');
        $renew=(int)getPost('renew');
        
        $f=array('id'=>'','symbol'=>'','fitems'=>'','fdays'=>'','pname'=>'','pstreet'=>'','pstreet_n1'=>'','ppostcode'=>'','pcity'=>'','pemail'=>'','pcountry'=>'','pnip'=>'','pphone1'=>'','ntrial'=>'');
        foreach ($f as $name=>$value) if (isset($_POST[$name])) $f[$name]=getPost($name);
        
        if($create) {
            $f['fitems']=getPostInt('fitems',1, 999999);
            $f['fdays']=getPostInt('fdays',1);
            $f['ntrial']=getPostInt('ntrial',0, 1);   
            $res=Model_BSX_B2B::createKey($f);
            if(!$res) {$this->err_code=-4; $this->err="Nie powidło sie!";}     
        } 
        else if ($update) {
            $f['id']=getPostInt('id',0);
            $res=Model_BSX_B2B::updateKey($f);
            if(!$res) {$this->err_code=-4; $this->err="Nie powiodło sie!";}
        }
        else if ($renew) {
            $f['id']=getPostInt('id',0);
            $f['fdays']=getPostInt('fdays',1);
            $res=Model_BSX_B2B::renewKey($f);
            if($res!=0) {$this->err_code=$res; $this->err="Nie powiodło sie!";}
        }
    }


    
    //-------------------- getters 

    // /get_user
    private function getUserById($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $id=(int)getPost('id');
        
        $row=Model_BSX_B2B::getUser($id, $this->uid);
        if($row)$this->row=$row;
        else {
            $this->err='Zasob nie istenieje';
            $this->err_code=-4;
        } 
    }

    // /get_branch
    private function getBranchById($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $id=(int)getPost('id');
        
        if($this->admin) {
            $row=Model_BSX_B2B::getBranch($id, $this->uid);
            if($row)$this->row=$row;
            else {
                $this->err='Zasob nie istenieje';
                $this->err_code=-4;
            }
        } else {
            $this->err='Brak uprawnień';
            $this->err_code=-1;
        }
    }

    // /get_users_by_branch
    private function getUsersByBranch($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $id=(int)getPost('id');
        $start=(int)getPost('start');
        $count=(int)getPost('count');
        $search=getPost('search');
        $orderby=getPost('orderby');
        $orderbydesc=getPostInt('orderbydesc');
        
        if($this->admin || $this->admin_br) {
            $res=Model_BSX_B2B::getUsersByBranch($id, $start, $count, $search, $orderby, $orderbydesc);
            if($res) {
                $this->rows=$res['rows'];
                $this->res=$res['pagination'];
            }
            else {
                $this->err='Zasob nie istenieje';
                $this->err_code=-4;
            }
        } else {
            $this->err='Brak uprawnień';
            $this->err_code=-1;
        }
    }

    // /get_branches
    private function getBranches($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $start=(int)getPost('start');
        $count=(int)getPost('count');
        $search=getPost('search');
        $orderby=getPost('orderby');
        $orderbydesc=getPostInt('orderbydesc');
        
        if($this->admin || $this->admin_br) {
            $res=Model_BSX_B2B::getBranches($start, $count, $search, $orderby, $orderbydesc);
            if($res) {
                $this->rows=$res['rows'];
                $this->res=$res['pagination'];
            }
            else {
                $this->err='Zasob nie istenieje';
                $this->err_code=-4;
            }
        } else {
            $this->err='Brak uprawnień';
            $this->err_code=-1;
        }
    }

    // /get_users   wszyscy uzytkownicy dla danego admina
    private function getUsers($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $start=(int)getPost('start');
        $count=(int)getPost('count');
        $search=getPost('search');
        $orderby=getPost('orderby');
        $orderbydesc=getPostInt('orderbydesc');
        
        if($this->admin) {
            $res=Model_BSX_B2B::getUsersByParent($start, $count, $search, $orderby, $orderbydesc);
            if($res) {
                $this->rows=$res['rows'];
                $this->res=$res['pagination'];
            }
            else {
                $this->err='Zasob nie istenieje';
                $this->err_code=-4;
            }
        } else {
            $this->err='Brak uprawnień';
            $this->err_code=-1;
        }
    }


    // /get_keys   pobiera wszystkie klucze dla danego superadmin - kazdy moze pobrac te klucze - jesli nalezy do oddzialu  tego superadmina
    private function getKeys($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $start=(int)getPost('start');
        $count=(int)getPost('count');
        $search=getPost('search');
        $orderby=getPost('orderby');
        $orderbydesc=getPostInt('orderbydesc');
        $filters=getPost('filters');

        if(!empty($filters)) {
            $filters=explode(',', $filters);
            foreach($filters as &$f){
                $f=explode(':', $f);
                if(count($f)==2) {
                    $f[0]=trim($f[0]);
                    $f[1]=trim($f[1]);
                    $f=array($f[0]=>$f[1]);
                } else $f=array();
            }
        } else $filters=array();

        // $this->row=array('a'=>$orderby);
        // return;
        
        $res=Model_BSX_B2B::getKeys($start, $count, $search, $orderby, $orderbydesc, $filters);
        if($res) {
            $this->rows=$res['rows'];
            $this->res=$res['pagination'];
        }
        else {
            $this->err='Zasob nie istenieje';
            $this->err_code=-4;
        }
        
    }

    // /get_key
    private function getKeyById($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $id=getPostInt('id');
        
        $res=Model_BSX_B2B::getKeyById($id);
        if($res)$this->row=$res['row'];
        else {
            $this->err='Zasob nie istenieje';
            $this->err_code=-4;
        } 
    }

    private function getLicenses($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        
        $res=Model_BSX_B2B::getLicenses();
        if($res) {
            $this->rows=$res['rows'];
        }
    }

    private function getCredit($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        
        $res=Model_BSX_B2B::getCredit();
        if($res) {
            $this->row=$res['row'];
        }
    }

    private function getArticle($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        
        $res=Model_BSX_B2B::getArticle($w);
        if($res) {
            $this->row=$res;
        } else {$this->err_code='404';}
    }

     private function templates($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}

        $type=getPost('type');
        $id=getPostInt('id');
        
        if($type=='get_all') {
            $res=Model_BSX_B2B::getTemplates();
            if($res) $this->rows=$res;
            else $this->err_code=-1;
        } else if ($type=='get_byid') {
            $res=Model_BSX_B2B::getTemplateById($id);
            if($res) $this->row=$res;
            else $this->err_code=-1;
        } else if ($type=='update') {
            $f=array('pbody'=>'','ptitle'=>'');
            getPostArray($f);
            $res=Model_BSX_B2B::updateTemplate($id, $f);
            if(!$res) $this->err_code=-1;
        } else {
            $this->err_code=-10;
            $this->err='Nie ma takiej operacji';
        }
        
    }

     private function sendKey($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}

        $id=getPostInt('id');
        $res=Model_BSX_B2B::sendKey($id);
        if($res!=0) {$this->err_code=$res;}
    } 

    private function printKey($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}

        $id=(int)getGetPost('id');
        $format=getGetPost('format');
        $res=Model_BSX_B2B::printKey($id,$format);
        if($res) $this->rows=$res;
        else {$this->err_code=-3;}
    }



    //------------------ deletes

    private function removeUser($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $id=(int)getPost('id');
        
        if($this->admin || $this->admin_br) {
            $res=Model_BSX_B2B::removeUser($id);
            if(!$res) {
                $this->err='Zasob nie istenieje';
                $this->err_code=-4;
            }
        } else {
            $this->err='Brak uprawnień';
            $this->err_code=-1;
        }
    }

    private function removeBranch($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $id=(int)getPost('id');
        
        if($this->admin) {
            $res=Model_BSX_B2B::removeBranch($id);
            if(!$res) {
                $this->err='Zasob nie istenieje';
                $this->err_code=-4;
            }
        } else {
            $this->err='Brak uprawnień';
            $this->err_code=-1;
        }
    }

    private function removeKey($w) {
        if(!$this->is_logged) {$this->err_code=-2; $this->err='Brak uprawnień'; return;}
        $id=getPostInt('id');
        
        $res=Model_BSX_B2B::removeKey($id);
        if(!$res) {
            $this->err='Zasob nie istenieje';
            $this->err_code=-4;
        }
        
    }


    //===================

    private function test($w) {
        // $a=strtotime('2017-11-18');
        // $b=strtotime('2017-11-17');
        // $dzien=3600*24;
        
        $this->row=array('test'=>Model_BSX_B2B::generateKey());
    }



    //===================

   
    

}