<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_Attachments {
    private static $cntGallery=0;    //licznik formularzy w serwisie

    public $db=null;                 //baza danych
    public $tableName='';            //nazwa tabeli
    public $id=0;                    //id rekordu
    public $where='';                //jeśli sami chcemy podać URL

    public $view=null;               //widok nadrzędny
    public $attachmentsView=null;    //widok galerii

    public $tableID='id';            //kolumna będąca kluczem
    public $formID='';               //identyfikator formularza

    public $items=array();
    public $itemsHTML='';

    public $type=0;

    public $url='';
    public $assetsURL='';

    public $attachmentsItemView='part_attachments_std_item';
    public $attachmentsItemViewFolder='padmin';
    public $assetFolder='uploads';
    public $addDataArray=array();



    public function __construct($tableName,$id,$formID='',$view=null,$attachmentsView=null,$attachmentsItemView='part_attachments_std_item',$attachmentsItemViewFolder='padmin',$tableID='id',$db=null) {
         if ($formID=='') $formID='form_attachments'.Model_BSX_Attachments::$cntGallery;
         $this->attachmentsItemView=$attachmentsItemView;
         $this->attachmentsItemViewFolder=$attachmentsItemViewFolder;
         Model_BSX_Attachments::$cntGallery++;
         $this->init($tableName,$id,$formID,$view,$attachmentsView,$tableID,$db);
    }

    public function init($tableName=null,$id=null,$formID=null,$view=null,$attachmentsView=null,$tableID=null,$db=null) {
        if ($tableName!==null) $this->tableName=$tableName;
        if ($id!==null) $this->id=$id;
        if ($tableID!==null) $this->tableID=$tableID;
        if ($formID!==null) $this->formID=$formID;
        if ($view!==null) $this->view=$view;
        if ($attachmentsView!==null) $this->attachmentsView=$attachmentsView;
        if ($db!==null) $this->db=$db;
        if ($this->view!==null) $this->url=$this->view->request->url();
    }

    public function __destruct() {

    }

    public function getSerialized() {
        $enc = new Encrypt('#BsX2016*;-)',MCRYPT_MODE_ECB,MCRYPT_BLOWFISH);
        $data=$enc->encode(serialize($this));
        return base64_encode($data);
    }

    public static function getUnserialized($data) {
        $data=base64_decode($data);
        $enc = new Encrypt('#BsX2016*;-)',MCRYPT_MODE_ECB,MCRYPT_BLOWFISH);
        return unserialize($data=$enc->decode($data));
    }

    public function loadItems() {
           $this->itemsHTML='';
           if ($this->where!='') $where=$this->where; else $where='ptable=:table AND pid=:id';

           $this->items=sql_rows_id('SELECT * FROM bs_attachments WHERE '.$where.' ORDER BY add_time DESC',array(':table'=>$this->tableName,':id'=>$this->id));
           foreach ($this->items as &$item)
           {
               $item['ext'] = strtolower(pathinfo($item['pname'], PATHINFO_EXTENSION));
               if (in_array($item['ext'], array('jpg','png')))
               {
                  $item['icon_url']=Model_BSX_Core::cache_img($item['ptable'],$item['pid'],$item['pname'],135,130,100,$this->assetFolder);

               } else if ($item['ext']=='pdf')
               {
                   $item['icon_url']=$this->assetsURL.'pdf-ico.png';
               } else {
                   $item['icon_url']=$this->assetsURL.'file-ico.png';
               }
               $item['icon']='<img src="'.$item['icon_url'].'" />';

               $item['file']='assets'.DIRECTORY_SEPARATOR.$this->assetFolder.DIRECTORY_SEPARATOR.$this->tableName.DIRECTORY_SEPARATOR.$this->id.DIRECTORY_SEPARATOR.$item['pname'];

           }

        $view = Model_BSX_Core::create_view($this, $this->attachmentsItemView, $this->attachmentsItemViewFolder);
        $view->set('items', $this->items);
        $view->set('attachment',$this);
        $this->itemsHTML=$view;

    }


    public function render($fromAjax=false)
    {
           if (!$this->attachmentsView) die('Nie zdefiniowano widoku formularza!');
           $this->loadItems();

           $this->attachmentsView->set('items',$this->items);
           $this->attachmentsView->set('attachments',$this);

           $form=$this->attachmentsView->render();
           return $form;
    }

    public function upload_files($file)
    {
        if (!isset($file['error']) OR !isset($file['name']) OR !isset($file['type']) OR !isset($file['tmp_name']) OR !isset($file['size'])) return -1;
        if ($file['error'] !== UPLOAD_ERR_OK) reutn -2;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        //if (!in_array($ext, array('jpg','png'))) {$result=-4;continue;}

        $fld=DOCROOT.'assets'.DIRECTORY_SEPARATOR.$this->assetFolder;
        if (!is_dir($fld))
        {
            mkdir($fld, 0755, TRUE);
            chmod($fld, 0755);
        }
        $fld.=DIRECTORY_SEPARATOR.$this->tableName;
        if (!is_dir($fld))
        {
            mkdir($fld, 0755, TRUE);
            chmod($fld, 0755);
        }
        $fld.=DIRECTORY_SEPARATOR.$this->id;
        if (!is_dir($fld))
        {
            mkdir($fld, 0755, TRUE);
            chmod($fld, 0755);
        }
        $fld.=DIRECTORY_SEPARATOR;

        if (rename($file['tmp_name'], $fld.$file['name']))
        {
            $d=array();
            $d['add_time']=date('Y-m-d H:i:s');
            $d['modyf_time']=$d['add_time'];
            $d['add_id_user']=$_SESSION['admin_user']['id'];
            $d['modyf_id_user']=$d['add_id_user'];

            $d['ptitle']=$file['name'];
            $d['pfilename']=$file['name'];
            $d['pname']=$file['name'];
            $d['plocation']=$this->tableName.DIRECTORY_SEPARATOR.$this->id.DIRECTORY_SEPARATOR.$file['name'];
            $d['psize']=$file['size'];
            $d['pstatus']=2;
            $d['ptype']=$this->type;
            $d['ptable']=$this->tableName;
            $d['pfld']='';
            $d['pid']=$this->id;
            $d=Arr::merge($d,$this->addDataArray);
            $result=sql_insert('bs_attachments',$d);
        } else
        {
            return -10;
        }
        return $result;

    }
    public function deleteFile($id)
    {
        $r=sql_row('SELECT id,pname FROM bs_attachments WHERE id=:a AND ptable=:table AND pid=:id',array(':a'=>$id,':table'=>$this->tableName,':id'=>$this->id));
        if (!$r)
        {
            return -1;
        }
        $fld=DOCROOT.'assets'.DIRECTORY_SEPARATOR.$this->assetFolder.DIRECTORY_SEPARATOR.$this->tableName.DIRECTORY_SEPARATOR.$this->id.DIRECTORY_SEPARATOR.$r['pname'];
        @unlink($fld);
        sql_query('DELETE FROM bs_attachments WHERE id=:a',array(':a'=>$r['id']));
    }

}
