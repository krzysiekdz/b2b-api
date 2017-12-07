<?php defined('SYSPATH') or die('No direct script access.');


class Model_BSX_Contractor {
    public $data=array();

    public function __construct() {
        $system=Model_BSX_System::init();

        $this->data['add_id_user']=z2n(0);
        $this->data['add_time']=date('Y-m-d H:i:s');
        $this->data['modyf_id_user']=$this->data['add_id_user'];
        $this->data['modyf_time']=$this->data['add_time'];
        $this->data['idcompany']=$system->company['id'];
        $this->data['idbranch']=$system->branche['id'];
        $this->data['idowner']=$system->user['id'];
        $this->data['psymbol']='';
        $this->data['pname']='';
        $this->data['pstreet']='';
        $this->data['pstreet_n1']='';
        $this->data['ppostcode']='';
        $this->data['ppost']='';
        $this->data['pcity']='';
        $this->data['pprovince']='';
        $this->data['pdistrict']='';
        $this->data['pcountry']='';
        $this->data['pemail']='';
        $this->data['pnip']='';
        $this->data['pphone1']='';
        $this->data['pphone2']='';
        $this->data['pfax']='';
        $this->data['pwww']='';
        $this->data['ppesel']='';
        $this->data['pregon']='';
        $this->data['pkrs']='';
        $this->data['nagree_mar']=0;
        $this->data['pgg']='';
        $this->data['pskype']='';
        $this->data['cms_idsite']=Model_BSX_Core::$bsx_cfg['id'];
        $this->data['cms_status']=0;
        $this->data['idtype']=z2n(0);
        $this->data['kname']='';
        $this->data['kstreet']='';
        $this->data['kstreet_n1']='';
        $this->data['kpostcode']='';
        $this->data['kpost']='';
        $this->data['kcity']='';
        $this->data['kprovince']='';
        $this->data['kdistrict']='';
        $this->data['kcountry']='';
    }

    public function importDataFromOrder($order) {
        $d=$order->getOrderArr();
        $this->data['psymbol']=$d['psymbol'];
        $this->data['pname']=$d['pname'];
        $this->data['pstreet']=$d['pstreet'];
        $this->data['pstreet_n1']=$d['pstreet_n1'];
        $this->data['ppostcode']=$d['ppostcode'];
        $this->data['ppost']=$d['ppost'];
        $this->data['pcity']=$d['pcity'];
        $this->data['pprovince']=$d['pprovince'];
        $this->data['pdistrict']=$d['pdistrict'];
        $this->data['pcountry']=$d['pcountry'];
        $this->data['pemail']=$d['pemail'];
        $this->data['pnip']=$d['pnip'];

        $this->data['kname']=$d['kname'];
        $this->data['kstreet']=$d['kstreet'];
        $this->data['kstreet_n1']=$d['kstreet_n1'];
        $this->data['kpostcode']=$d['kpostcode'];
        $this->data['kpost']=$d['kpost'];
        $this->data['kcity']=$d['kcity'];
        $this->data['kprovince']=$d['kprovince'];
        $this->data['kdistrict']=$d['kdistrict'];
        $this->data['kcountry']=$d['kcountry'];

        $this->data['cms_idsite']=$d['cms_idsite'];
    }

    public function execute() {
        $r=sql_insert('bs_contractors',$this->data);
        $this->data['id']=$r;
        return $this->data['id'];
    }
}
