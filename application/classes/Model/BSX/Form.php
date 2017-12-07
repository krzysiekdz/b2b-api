<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_Form {
    private static $cntForm=0;  //licznik formularzy w serwisie

	public $db=null;             //baza danych
    public $tableName='';        //nazwa tabeli
    public $id=0;                //id rekordu
    public $formURL='';          //URL do formularza
    public $backURL='';          //adres URL powrotu

    public $view=null;           //widok nadrzędny
    public $formView=null;       //widok tabeli

    public $tableID='id';        //kolumna będąca kluczem
    public $formID='';           //identyfikator formularza

    public $title='';            //szablon tytułu

    public $fields=array();     //lista kolumn
    public $sections=array();   //lista sekcji
    public $section=null;       //sekcja główna

    public $statusResult=0;     //status po zapisaniu, aktualizacji
    public $statusStr='';

    public $row=array();        //wczytany rekord

    public $tblFields=array();  //pola fizycznie w bazie danych

    public $comUpdate='Rekord został zaktualizowany!';
    public $comInsert='Rekord został dodany!';

    public $onBeforeSave=null;


    public function __construct($tableName,$id,$formID='',$view=null,$formView=null,$tableID='id',$db=null) {
         if ($formID=='') $formID='form_std'.Model_BSX_Form::$cntForm;
         Model_BSX_Form::$cntForm++;
         $this->init($tableName,$id,$formID,$view,$formView,$tableID,$db);

         $this->tblFields=array();
         $tbl=sql_rows('DESCRIBE '.$this->tableName);
         foreach ($tbl as $t) $this->tblFields[$t['Field']]=$t;


         $this->section=$this->addSection('');
    }

    public function init($tableName=null,$id=null,$formID=null,$view=null,$formView=null,$tableID=null,$db=null) {
        if ($tableName!==null) $this->tableName=$tableName;
        if ($id!==null) $this->id=$id;
        if ($tableID!==null) $this->tableID=$tableID;
        if ($formID!==null) $this->formID=$formID;
        if ($view!==null) $this->view=$view;
        if ($formView!==null) $this->formView=$formView;
        if ($db!==null) $this->db=$db;
    }

    public function __destruct() {

    }

    public function addSection($title) {
         $sekcja=new FormSekcja($this,$title);
         $this->sections[]=$sekcja;
         return $sekcja;
    }

    public function addField($name,$caption=null,$mtype='',$type='',$data=null,$default=null) {
         return $this->section->addField($name,$caption,$mtype,$type,$data,$default);
    }

    public function findField($name) {
         foreach ($this->fields as &$field)
         {
              if ($field->name==$name) return $field;
         }
         return false;
    }

    public function setLayout($name,$divClass=null,$row=null) {
         $field=$this->findField($name);
         if (!$field) return false;
         if ($divClass!==null) $field->divClass=$divClass;
         if ($row!==null) $field->row=$row;
    }


    public function analyse() {
         $save=getPost($this->formID.'_save');
         $this->backURL=getGet('return',$this->backURL);
         if ($save=='1')
         {
              $this->backURL=getPost($this->formID.'_back',$this->backURL);
              $lid=(int)getPost($this->formID.'_record');
              if ($lid>0) $this->id=$lid;
              $d=array();
              foreach ($this->fields as $field)
              {
                   $n=$this->formID.'_'.$field->name;
                   if (isset($_POST[$n]))
                   {
                        $w=getPost($n);
                        if ($field->mtype=='sha1')
                        {
                          if (strlen($w)<40) $w=sha1($w);
                        }
                        if ($field->type=='int') $w=(int)$w;
                        if ($field->type=='double') $w=(double)$w;
                        $d[$field->name]=$w;
                   }
              }

              if ($this->onBeforeSave!==null)
              {
                   call_user_func_array(array($this->view, $this->onBeforeSave),array($this,&$d));
              }
              if ($this->id>0)
              {
                   if (isset($this->tblFields['modyf_time'])) $d['modyf_time']=date('Y-m-d H:i:s');
                   if (isset($this->tblFields['modyf_id_user'])) $d['modyf_id_user']=$_SESSION['admin_user']['id'];
                   sql_update($this->tableName,$d,$this->id);
                   $this->statusResult=2;
                   $this->statusStr='<div class="alert alert-success">'.$this->comUpdate.'</div>';
              } else
              {
                   if (isset($this->tblFields['add_time'])) $d['add_time']=date('Y-m-d H:i:s');
                   if (isset($this->tblFields['add_id_user'])) $d['add_id_user']=$_SESSION['admin_user']['id'];
                   if (isset($this->tblFields['modyf_time'])) $d['modyf_time']=date('Y-m-d H:i:s');
                   if (isset($this->tblFields['modyf_id_user'])) $d['modyf_id_user']=$_SESSION['admin_user']['id'];
                   $this->id=sql_insert($this->tableName,$d);
                   $this->statusResult=1;
                   $this->statusStr='<div class="alert alert-success">'.$this->comInsert.'</div>';
              }
         }
         if ($this->id>0)
         {
              $this->row=sql_row('SELECT * FROM '.$this->tableName.' WHERE '.$this->tableID.'=:id',array(':id'=>$this->id));
         }

         foreach ($this->fields as &$field)
          if (isset($this->row[$field->name])) $field->value=$this->row[$field->name];
        // echo '<pre>';print_r($this->row);echo '</pre>';
    }

    function prepareFormView()
    {

           if (!$this->formView) die('Nie zdefiniowano widoku formularza!');

           $ntitle=$this->title;
           $k=strpos($ntitle,'|');
           if ($k>0) {
                if ($this->id<=0) $ntitle=substr($ntitle,0,$k);
                else $ntitle=substr($ntitle,$k+1);
           }

           if (count($this->row)>0 && strpos($ntitle,'{')!==false)
           {
                foreach ($this->row as $n=>$v) $ntitle=str_replace('{'.$n.'}',$v,$ntitle);
           }

           $this->formView->set('form',$this);
           $this->formView->set('title',$ntitle);
           $this->formView->set('statusResult',$this->statusResult);
           $this->formView->set('statusStr',$this->statusStr);

    }

    function renderFormView($runAnalyse=true)
    {
           if ($runAnalyse) $this->analyse();
           $this->prepareFormView();
           $form=$this->formView->render();
           return $form;
    }

}

class FormSekcja {
    public $form=null;
    public $fields=array();

    public function __construct($form,$title) {
         $this->form=$form;
         $this->title=$title;
    }

    public function addField($name,$caption=null,$mtype='',$type='',$data=null,$default=null) {
         if ($caption===null) $caption=$name;
         if ($mtype=='textarea') $mtype='memo';
         if ($mtype=='memo' && $type=='') $type='text';
         if (($mtype=='field' || $mtype=='edit') && $type=='') $type='varchar';

         $field=new FormField();
         $field->name=$name;
         $field->caption=$caption;
         $field->type=$type;
         $field->mtype=$mtype;
         $field->data=$data;
         $field->section=$this;
         $field->value=$default;
         $this->form->fields[$name]=$field;
         $this->fields[$name]=$field;
         return $field;
    }


}


class FormField {
    public $name;
    public $caption;
    public $type;
    public $mtype;
    public $data=array();
    public $section;
    public $help;
    public $placeholder;
    public $value;
    public $divClass='col-md-12'; //klasa DIV-a
    public $row=true; //czy odrębny wiersz

    public function __construct() {
    }

}