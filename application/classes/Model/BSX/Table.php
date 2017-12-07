<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_Table {
    private static $cntTable=0;  //licznik tabel w serwisie

	public $db=null;             //baza danych
    public $tableName='';        //nazwa tabeli
    public $formMethod='get';   //typ formularza: GET/POST
    public $formURL='';          //URL do formularza

    public $controller=null;     //kontroller
    public $tableView=null;      //widok tabeli
    public $showView=null;       //widok podglądu rekordu
    public $form=null;           //powiązany formularz (rekord) wewnątrz którego jest ta lista

    public $start=0;             //od którego wiersza wyświetlić rekordy
    public $limit=25;            //po ile rekordów wyświetlać
    public $orderBy='';          //po której kolumnie sortować
    public $orderByInline='id';  //po której kolumnie ma sortować (nadane z kodu, jak nie przekazano orderBy - nie kontroluje czy kolumna jest na liście)
    public $desc='1';            //sortowanie ASC czy DESC

    public $query='';            //ręcznie wprowadzone zapytanie
    public $where='';            //ręcznie dodany warunek
    public $leftJoin=array();    //dodanie leftjoin-ów

    public $tableID='id';        //kolumna będąca kluczem
    public $formID='';           //identyfikator formularza

    public $labelNoResults='Tabela jest pusta.'; //komunikat jak nic nie znaleziono

    public $selectFields='';

    public $showPagesExacly=false; //czy ma wyliczać ile jest dokładnie stron


    public $fields=array();     //lista kolumn

    public $count=0;            //ilość rekordów

    public $ajax=false;

    public $linkEdit='';
    public $linkShow='';
    public $linkDel='';

    public $sortHeaders=true;
    public $showCheckboxes=false;
    public $showSearchPanel=false;
    public $showEditBtn=false;
    public $showDelBtn=false;
    public $showShowBtn=false;
    private $sendForm=0; //czy przesłano formularz (odświeżono); 0 - nie, 1 - tak, 2 - tak po AJAX-ie

    public $options=array();   //różne opcje

    public $cmd=''; //ostatnio wykonana komenda (pobrane z parametru - zależne od tabelki)
    public $p1='';
    public $p2='';
    public $showMessageValue=''; //komunikat do wyświetlenia
    public $showMessageClassValue=''; //klasa komunikatu
    public $row=null; //wczytany rekord podglądu

    public $headerHTML='';
    public $footerHTML=''; //za cały

    public $onDel=null;         //przy usuwaniu
    public $onCellOptions=null;     //komórka z opcjami
    public $onCell=null;        //ogólnie - każda komórka
    public $onCellStyle=null;   //styl dla komórki


    //konstruktor
    // $tableName   - nazwa tabeli
    // $formID - identyfikator formularza
    // $tableID - kolumna bądąca kluczem podstawowym
    // $db - identyfikator dostępu do bazy danych
    public function __construct($tableName,$formID='',$tableID='id',$db=null) {
         if ($formID=='') $formID='form_tbl'.Model_BSX_Table::$cntTable;
         Model_BSX_Table::$cntTable++;

         $this->options=array(
             'rootClass'=>'box green',      //style całej tabeli: light bordered
             'tableClass'=>'table-bordered table-striped table-hover ',// table-striped table-bordered table-hover table-checkable order-column
             'toolsVisible'=>true,          //czy widać "tools" (zwiń/odśwież)
             'toolbarVisible'=>false,
             'actionsVisible'=>false,       //czy widać opcje "Akcje"
             'searchItem'=>true,
             'paginationVisible'=>true,
             'toolButtons'=>array(),         //dodatkowe przyciski w nagłówku
         );

         $this->init($tableName,$formID,$tableID,$db);
    }

    public function init($tableName='',$formID='',$controller=null,$tableID='id',$db=null) {
        if ($tableName!='') $this->tableName=$tableName;
        if ($tableID!='') $this->tableID=$tableID;
        if ($formID!='') $this->formID=$formID;
        if ($controller!=null) $this->controller=$controller;
        if ($db!==null) $this->db=$db;

        $start=getGetPost($this->formID.'_start',$this->start);
        if ($start==='last') $this->start=99999; else $this->start=(int)$start;
        if ($this->start<0) $this->start=0;
        if (!isset($this->options['showViewData'])) $this->options['showViewData']=array();

        if (getGetPost($this->formID.'_send')==1)
        {
            $this->sendForm=1;
            if (getGetPost('ajax')<>'') $this->sendForm=2;
        }

        $this->cmd=getGetPost($this->formID.'_cmd');
        $this->p1=getGetPost($this->formID.'_p1');
        $this->p2=getGetPost($this->formID.'_p2');

        if ($this->cmd=='del') {
            if ($this->onDel)
            {
                if (function_exists($this->onDel)) $this->onDel($this->p1);
            } else {
                $this->showMessage('Rekord został usunięty!');
                sql_query('DELETE FROM '.$this->tableName.' WHERE id=:id', array(':id'=>(int)$this->p1));
            }
        } else if ($this->cmd=='show') {
            $this->row=sql_row('SELECT * FROM '.$this->tableName.' WHERE id=:id',array(':id'=>(int)$this->p1));
            if (!$this->row) {
                $this->showMessage('Nie odnaleziono rekordu o indeksie '.((int)$this->p1).'!','alert-danger');
                $this->cmd='table';
            }
            else {
              if (count($this->options['showViewData'])<=0)
                  $r=array();
                  foreach ($this->row as $name=>$value)
                  {
                      $r[]=array(array(
                          'name'=>$name,
                          'caption'=>$name,
                      ));
                  }
                  $this->options['showViewData']=array('groups'=>array(array('rows'=>$r)));

            }
        }


        if (!empty($_SERVER['QUERY_STRING'])) $this->href='/index.php?'.$_SERVER['QUERY_STRING'];
    }

    public function __destruct() {

    }

    public function showMessage($msg, $class='alert-info')
    {
        $this->showMessageValue=$msg;
        $this->showMessageClassValue=$class;
    }

    public function addField($name, $caption='',$align='',$type='',$width='',$data=null,$tpl=null,$fnc=null,$style=null) {
         if ($caption=='') $caption=$name;
         if ($type=='') $type='varchar';
         $field=array();
         $field['name']=$name;       //nazwa pola w bazie lub funkcja@nazwa
         $field['caption']=$caption; //tytuł, jak pusty to użyta będzie nazwa
         $field['align']=$align;     //wyrównanie
         $field['width']=$width;
         $field['type']=$type;       //typ lub typ@mtype
         $field['data']=$data;       //tablica z danymi do podstawienia
         $field['tpl']=$tpl;         //szablon
         $field['fnc']=$fnc;         //funkcja callback
         $field['style']=$style;
         $this->fields[]=$field;
    }

    public function render($fromAjax=false)
    {
        if ($this->cmd=='' || $this->cmd=='table' || $this->cmd=='del' || $this->cmd=='refresh' || $this->cmd=='orderby') {
            return $this->renderTableView($fromAjax);
        }
        else if ($this->cmd=='show') {
            return $this->renderShowView($fromAjax);
        }
        else {
            die('Nieznana komenda: '.$this->cmd);
        }
    }

    //funkcja generuje tabelę z wynikami
    private function renderTableView($fromAjax=false)
    {
        if (!$this->tableView) die('Nie zdefiniowano widoku tabeli!');

        $this->orderBy=getGetPostSQL($this->formID.'_orderby',$this->orderBy);
        $this->desc=getGetPostInt($this->formID.'_desc',$this->desc);

        //sprawdzamy czy orderBy jest dozwolony
        if ($this->orderBy!='')
        {
             $ok=false;
             foreach ($this->fields as $field) if ($field['name']==$this->orderBy || substr($field['name'],strpos($field['name'],'@')+1)==$this->orderBy) {$ok=true;break;}
             if (!$ok) $this->orderBy=$this->tableID;
        }
        if ($this->orderBy=='' && $this->orderByInline!='') $this->orderBy=$this->orderByInline;


        $data=array();

        $where='';
        $globalWhere='';

        //wyszukiwanie
        $where1=$this->where;
        $where2='';
        $d=Arr::merge($_POST,$_GET);
        foreach ($d as $name=>$value)
        {
            if (substr($name,0,7)!='search_' || $value=='') continue;
            $name=substr($name,7);
            $ok=false;
            foreach ($this->fields as $field) if ($field['name']==$name) {$ok=$field;break;}
            if (!$ok) continue;
            if ($field['type']=='int')
            {
                $where2.=$field['name'].' = :'.$field['name'].' AND ';
                $data[':'.$field['name']]=$value;
            }
            else {
                $where2.=$field['name'].' LIKE :'.$field['name'].' AND ';
                $data[':'.$field['name']]='%'.$value.'%';
            }

        }
        if ($where2!='')  $where2='('.substr($where2,0,-5).')';

        $globalWhere='';
        if ($where1!='') $globalWhere.='('.$where1.') AND ';
        if ($where2!='') $globalWhere.=$where2.' AND ';
        if ($globalWhere!='')
        {
            $globalWhere=' WHERE '.substr($globalWhere,0,-5);
        }


        //określenie liczby rekordów
        $w='';
        if ($this->query!='') $w=substr($this->query,strpos(strtoupper($this->query),' FROM '));
        if ($this->showPagesExacly)
        {
              if ($this->query!='')
              {
                  $this->count=sql_count('SELECT count(*) '.$w.$globalWhere);
              } else
              {
                  if (count($this->leftJoin) > 0)
                  {
                     $lj = '';
                     foreach($this->leftJoin as $v) {
                          $t=explode('#',$v);
                          $lj .= 'LEFT JOIN '.$t[0].' ON '.$t[1].' ';
                     }
                     $this->count=sql_count('SELECT count(*) FROM '.$this->tableName.' '.$lj.' '.$globalWhere,$data);
                  } else
                  {
                     $this->count=sql_count('SELECT count(*) FROM '.$this->tableName.$globalWhere,$data);
                  }
              }
        } else
        {
            $this->count=-1;
        }


        if($this->selectFields != '') $selectFields = $this->selectFields; else $selectFields = $this->tableName.'.*';

        if ($w!='') $query=$this->query.$globalWhere;
        else
        {
            if (count($this->leftJoin) > 0)
            {
                $lj = $ljf = '';
                foreach($this->leftJoin as $v) {
                          $t=explode('#',$v);
                          $lj .= 'LEFT JOIN '.$t[0].' ON '.$t[1].' ';

                          //if (!isset($t[2])) $t[2]='';
                          if (!empty($t[2]))
                          {
                              if ($t[2][0]!=',')  $ljf .= ','.$t[2]; else $ljf .= $t[2];
                          }
                }
                $query='SELECT '.$selectFields.$ljf.' FROM '.$this->tableName.' '.$lj.' '.$globalWhere;
            } else
            {
                $query='SELECT '.$selectFields.' FROM '.$this->tableName.$globalWhere;
            }
        }

        //dodajemy klauzule orderby
        if ($this->orderBy!='' && stripos($query,' order by')===false)
        {
            if (strpos($this->orderBy,'.')===false) $query.=' ORDER BY '.$this->tableName.'.'.$this->orderBy;
            else $query.=' ORDER BY '.$this->orderBy;
            if ($this->desc=='1') $query.=' DESC'; else $query.=' ASC';
        }

        if ($this->start==99999)
        {
          if ($this->count>0)
          {
             $stron=floor($this->count/$this->limit);
             $this->start=$stron*$this->limit;
          } else
          {
              $this->start=0;
          }
        }

        //dodajemy klauzulę LIMIT
        if ($this->limit>0)
        {
             $query.=' LIMIT '.$this->start.','.($this->limit+1);
        }

        //wykonujemy zapytanie
        //echo $this->count;
        $rows=sql_rows($query,$data);

        //echo $query;print_r($data);exit;
        $lp=0;
        foreach ($rows as $ilp=>&$row)
        { //pętla po wierszach
                    if (++$lp>$this->limit)
                    {
                        unset($rows[$ilp]);
                        break;
                    }

                    $row['%lp']=$lp;
                    $row['%orgRow']=$row;

                    //były podane kolumny
                    foreach ($this->fields as &$r)
                    {
                          $name=$r['name'];
                          $align=$r['align'];
                          $type=$r['type'];
                          $mtype='';
                          $tpl=$r['tpl'];
                          $modyfikator=array();
                          $data=$r['data'];

                          $k=strpos($type,'|');
                          if ($k!==false)
                          {
                             $modyfikator=explode(';',substr($type,$k+1));
                             $type=substr($type,0,$k);
                          }

                          $k=strpos($type,'@');
                          if ($k!==false)
                          {
                             $mtype=substr($type,$k+1);
                             $type=substr($type,0,$k);
                          }

                          $k=strpos($name,'@');
                          if ($k!==false) $name=substr($name,0,$k);

                          if ($name!='file' && function_exists($name)) //jak zamiast nazwy kolumny podano funkcję - to ją wywołujemy
                          {
                                  $name($row);
                          } else //if (isset($row[$name]))
                          {  //a jak kolumnę - to standardowo
                            $k=strpos($name,'.');
                            if ($k>0) $name=substr($name,$k+1);

                            if (isset($row[$name])) $value=$row[$name]; else $value='';
                            if ($this->onCell!=null)
                            {
                                if (strpos($this->onCell,'::')!==FALSE) $value=call_user_func($this->onCell,$this, $row, $name, $value);
                                else $value=call_user_func(array($this->controller,$this->onCell),$this, $row, $name, $value);
                            }


                            if ($type=='datetime')
                            {
                                if ($value=='' || $value=='0' || $value=='1970-01-01') $value='---';
                                else {
                                    if (!is_numeric($value)) $value = strtotime($value);
                                    if ($value > 0) $value = date('d.m.Y H:i:s', $value); else $value = '---';
                                }
                            }
                            else if ($type=='date')
                            {
                                if ($value=='' || $value=='0' || $value=='1970-01-01') $value='---';
                                else {
                                    if (!is_numeric($value)) $value = strtotime($value);
                                    $value = date('d.m.Y', $value);
                                }
                            }
                            else if ($type=='currency' || $type=='price') {$value=BinUtils::price($value);}
                            else if ($type=='filesize') $value=BinUtils::sizetostr($value);
                            else if ($type=='href' || $type=='url') $value='<a href="'.$value.'">'.$value.'</a>';


                            if ($align!='') $align=' align="'.$align.'"';

                            if ($data!=null)
                            {
                                if (isset($data[$value])) $value=$data[$value]; else $value='---';
                            }

                            $value = str_replace('{id}', $row[$this->tableID] , $value);



                            if (in_array('striptags',$modyfikator)) $value=strip_tags($value);
                            if ($mtype=='linkedit') $value='<a href="'.$linked.'">'.$value.'</a>';

                            if ($tpl!='')
                            {
                                $value=str_replace('{value}',$value,$tpl);
                                $x1=strpos($value,'{');
                                $x2=strpos($value,'}',$x1+1);
                                while ($x1!==false && $x2!==false && $x2>$x1)
                                {
                                    $a=substr($value,0,$x1);
                                    $b=substr($value,$x1+1,$x2-$x1-1);
                                    $m='';
                                    $x1=strpos($b,'|');
                                    if ($x1!==false)
                                    {
                                        $m=substr($b,$x1+1);
                                        $b=substr($b,0,$x1);
                                    }
                                    $c=substr($value,$x2+1);

                                    if (isset($row[$b])) $b=$row[$b]; else $b='---';
                                    if ($m=='currency' || $m=='price') {$b=number_format((double)$b, 2, '.', ',');}

                                    $value=$a.$b.$c;
                                    $x1=strpos($value,'{');
                                    $x2=strpos($value,'}',$x1+1);
                                }

                                 foreach ($row as $a=>$b)
                                     if (!is_array($b)) $value=str_replace('{'.$a.'}',$b,$value);
                            }

                            $value=$this->CC(str_replace('|','<br>',$value));


                            $row[$name]=$value;

                          }

                    }
                    unset($r);

            } //pętla po wierszach
            unset($row);

            $this->genNextPrev($lp);
            $this->tableView->set('rows',$rows);
            $this->tableView->set('table',$this);
            $this->tableView->set('controller',$this->controller);
            $r=$this->tableView->render();
            if ($this->sendForm==2)
            {
                $t=array();
                $t[]['%content_'.$this->formID]=$r;
                echo json_encode($t);
                exit;
            } else return $r;
    }



    private function renderShowView($fromAjax=false)
    {
        if (!$this->showView) die('Nie zdefiniowano widoku tabeli!');

        $this->showView->set('rowData',$this->row);
        $this->showView->set('table',$this);
        $this->showView->set('controller',$this->controller);
        $r=$this->showView->render();
        if ($this->sendForm==2)
        {
            $t=array();
            $t[]['%content_'.$this->formID]=$r;
            echo json_encode($t);
            exit;
        } else return $r;
    }

    public function CC($value)
    {
        //kolorowanie komórki
        if (substr($value,0,2)=='{#')
        {
            $span_class='';
            $span_style='';
            $value=substr($value,2);
            $k=strpos($value,'#}');
            if ($k!==false)
            {
                $rules=explode(';',substr($value,0,$k));
                $value=substr($value,$k+2);
                foreach ($rules as $rule)
                {
                    $r=explode('=',$rule);
                    if ($r[0]=='class') $span_class.=$r[1].' ';
                    else if ($r[0]=='background') $span_style.='background-color:'.$r[1].';';
                }
            }
            if ($span_class!='' || $span_style!='')
            {
                $value='<span class="label label-sm '.$span_class.'" style="'.$span_style.'">'.$value.'</span>';
            }
        }
        return $value;
    }

    public function CV($value)
    {
        $x1=strpos($value,'{');
        if ($x1!==false) $x2=strpos($value,'}',$x1+1);
        while  ($x1!==false && $x2!==false)
        {
            $a=substr($value,0,$x1);
            $b=substr($value,$x1+1,$x2-$x1-1);
            $c=substr($value,$x2+1);

            $x1=strpos($b,'|');
            if ($x1!==false)
            {
                $m=substr($b,$x1+1);
                $b=substr($b,0,$x1);
            } else $m='';
            if (isset($this->row[$b])) $b=$this->row[$b];
            else $b='';

            $b=trim($b);

            if ($m=='email') $b='<a href="mailto:'.$b.'">'.$b.'</a>';

            $value=$a.$b.$c;
            $x1=strpos($value,'{');
            if ($x1!==false) $x2=strpos($value,'}',$x1+1);
        }
        $v=trim(str_replace('|','<br>',$value));
        while (substr($v,0,4)=='<br>') $v=trim(substr($v,4));
        return $v;
    }

    private function genNextPrev($lp)
    {
     $top='';

     $wstecz=$this->start-$this->limit;
     if ($wstecz<0) $wstecz=0;

     $pagination=array();

     $link='javascript:bsxTable('.($this->ajax?'1':'0').',\''.$this->formID.'\',\'refresh\',{start})';

     if ($this->start>0)
     {
           $pagination['first']=str_replace('{start}',0,$link);
           $pagination['first_caption']='Start';

           $pagination['previous']=str_replace('{start}',$wstecz,$link);
           $pagination['previous_caption']='Wstecz';
     }

     if ($this->count>0 || $lp>$this->limit)
     {
        $akt=(int)(($this->start)/$this->limit)+1;
        $od=$akt-5;
        $do=$akt+5;
        if ($od<1) $od=1;

        if ($do*$this->limit>$this->count)
        {
            $do=floor($this->count/$this->limit)+1;
        }
        if ($do<=0) $do=$akt;

        for ($t=$od;$t<=$do;$t++)
        {
           $p=(($t-1)*$this->limit);
           $page=array('caption'=>$t,'url'=>str_replace('{start}',$p,$link));
           if ($t==$akt)
           {
                $page['active']=true;
           }
           $pagination['pages'][]=$page;
        }
     }
     if ($lp>$this->limit)
     {
       $p=$this->start+$this->limit;

       $pagination['end']=str_replace('{start}','last',$link);
       $pagination['end_caption']='Ostatnia';

       $pagination['next']=str_replace('{start}',$p,$link);;
       $pagination['next_caption']='Dalej';
     }

     if (count($pagination)>0) $this->tableView->set('pagination',$pagination);
    }

    //=============================

    public static function ajaxAnalise($sql=false) {
        global $_SQL;
        if ($sql===false) $sql=$_SQL;
        $formname=BinUtils::getGetPost('FormName');

        $obj=BinUtils::getGetPost($formname.'obj');
        $objKey=BinUtils::getGetPost($formname.'objKey');
        $correctObjKey=sha1($obj.'binboy&ks+');
        if ($objKey==$correctObjKey) {
          $obj=unserialize(gzuncompress(base64_decode($obj)));
          $obj->init();
          $obj->db=$sql;
          $obj->showTable(true);
        }
    }

}





