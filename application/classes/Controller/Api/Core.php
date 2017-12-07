<?php defined('SYSPATH') or die('No direct script access.');

/*
 * Podstawowa obsługa serwisu. Pomocnicze kontrolery do AJAX-a
 */

class Controller_Api_Core extends Controller {
    private $ajaxResult=array();

    public function before() {
        parent::before();
        set_time_limit(360);
    }

    public function after() {
           echo json_encode($this->ajaxResult);
           parent::after();
    }


    public function action_index()
    {
      echo json_encode($this->ajaxResult);
    }

    //--- usunięcie załącznika ---
    public function action_delattachment() {
        $id=(int)getGetPost('id');
        $formID=getGetPost('formID');
        $ob=getGetPost('clAtt');

        if ($id<=0 || $formID=='')
        {
            $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-danger">Przesłano niekompletne dane.</div>';
            return;
        }

        if ($_SESSION['admin_user']['id']<=0)
        {
            $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-danger">Brak uprawnień do wykonania tej czynności</div>';
            return;
        }


        if ($ob!='')
        {
            $g=Model_BSX_Attachments::getUnserialized($ob);
        } else die('Fatal Error!');
        if (!$g) die('Fatal Error!!');


        $res=$g->deleteFile($id);
        if ($res==-1)
        {
            $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-danger">Nie odnaleziono takiego załącznika</div>';
            return;
        }

        $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-success">Plik został usunięty.</div>';
        $g->loadItems();
        $this->ajaxResult[]['%attachments_items_'.$formID]=$g->itemsHTML->render();
    }

    //--- gwiazdki dla załącznika ---
    public function action_ratingattachment() {
              $id=(int)getGetPost('id');




              $a=(int)getGetPost('a');
              $table=getGetPost('table');
              $formID=getGetPost('formid');
              $url=getGetPost('url');
              if ($id<=0 || $a<=0 || $table=='')
              {
                   $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-danger">Przesłano niekompletne dane.</div>';
                   return;
              }
              if ($_SESSION['admin_user']['id']<=0)
              {
                   $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-danger">Brak uprawnień do wykonania tej czynności</div>';
                   return;
              }
              $r=sql_row('SELECT id,prating FROM bs_attachments WHERE id=:a AND ptable=:table AND pid=:id',array(':a'=>$a,':table'=>$table,':id'=>$id));
              if (!$r)
              {
                   $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-danger">Nie odnaleziono takiego załącznika</div>';
                   return;
              }
              if ($r['prating']<=0) $m=100; else $m=0;
              sql_query('UPDATE bs_attachments SET prating=:m WHERE id=:a',array(':m'=>$m,':a'=>$r['id']));

              $g=new Model_BSX_Attachments($table,$id,$formID,$this);
              if ($url!='') $g->url=$url;
              $g->loadItems();
              $this->ajaxResult[]['%attachments_items_'.$formID]=$g->itemsHTML;
    }


    //--- gwiazdki dla załącznika ---
    public function action_visattachment() {
              $id=(int)getGetPost('id');
              $a=(int)getGetPost('a');
              $table=getGetPost('table');
              $formID=getGetPost('formid');
              $url=getGetPost('url');
              if ($id<=0 || $a<=0 || $table=='')
              {
                   $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-danger">Przesłano niekompletne dane.</div>';
                   return;
              }
              if ($_SESSION['admin_user']['id']<=0)
              {
                   $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-danger">Brak uprawnień do wykonania tej czynności</div>';
                   return;
              }
              $r=sql_row('SELECT id,pinvisible FROM bs_attachments WHERE id=:a AND ptable=:table AND pid=:id',array(':a'=>$a,':table'=>$table,':id'=>$id));
              if (!$r)
              {
                   $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-danger">Nie odnaleziono takiego załącznika</div>';
                   return;
              }
              if ($r['pinvisible']<=0) $m=1; else $m=0;
              sql_query('UPDATE bs_attachments SET pinvisible=:m WHERE id=:a',array(':m'=>$m,':a'=>$r['id']));

              $g=new Model_BSX_Attachments($table,$id,$formID,$this);
              if ($url!='') $g->url=$url;
              $g->loadItems();
              $this->ajaxResult[]['%attachments_items_'.$formID]=$g->itemsHTML;
    }


    //--- upload załacznika ---
    public function action_upload()
    {
        $filename=getGetPost('filename');
        $formID=getGetPost('formID');
        $data=getGetPost('data');
        $ob=getGetPost('clAtt');

        if ($filename=='' || $data=='' || $formID=='')
        {
            $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-danger">Przesłano niekompletne dane.</div>';
            return;
        }

        if ($_SESSION['admin_user']['id']<=0)
        {
            $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-danger">Brak uprawnień do wykonania tej czynności</div>';
            return;
        }


        if ($ob!='')
        {
            $g=Model_BSX_Attachments::getUnserialized($ob);
        } else die('Fatal Error!');
        if (!$g) die('Fatal Error!!');


        if ($g->id<=0) {
            $this->ajaxResult[]['#uploadErrorStr_' . $formID] = '<div class="alert alert-danger">Brak ID rekordu.</div>';
            return;
        }

        //dane AJAX-em zakodowane są w base64
        $data=substr($data,strpos($data,'base64')+7);
        $data=base64_decode($data);
        $tmpfname = tempnam(DOCROOT.'tmp'.DIRECTORY_SEPARATOR, 'tmp');
        file_put_contents($tmpfname,$data);

        $filetype='';

        $file=array(
                  'name'=>$filename,
                  'error'=>UPLOAD_ERR_OK,
                  'type'=>$filetype,
                  'size'=>strlen($data),
                  'tmp_name'=>$tmpfname
        );
        $code=$g->upload_files($file);
        if ($code<=0)
        {
                        $msg='Nie udało się załadować pliku.';
                        if ($code==-7) $msg='Przekroczono limit pojemności konta.';
                        else if ($code==-2) $msg='Błędnie uploadowany plik.';
                        else if ($code==-3) $msg='Błędnie uploadowany plik.';
                        else if ($code==-4) $msg='Nieobsługiwany format graficzny. Dostępne formaty to: JPG, PNG.';
                        $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-danger">'.$msg.' Kod błędu:'.$code.'</div>';
                        return;

        } else
        {
                        $this->ajaxResult[]['#uploadErrorStr_'.$formID]='<div class="alert alert-success">Pliki zostały przesłane prawidłowo.</div>';

                        //$g=new Model_BSX_Attachments($table,$id,'attachments_sliders',$this);
                        //if ($url!='') $g->url=$url;
                        $g->loadItems();
                        $this->ajaxResult[]['%attachments_items_'.$formID]=$g->itemsHTML->render();


                        return;
        }

    }

    public function action_sendmsg()
    {
         session_cache_limiter('nocache');
         header('Expires: ' . gmdate('r', 0));
         header('Content-type: application/json');

        $arrResult = array ('response'=>'error');
        echo json_encode($arrResult);
        exit;

    }


}
