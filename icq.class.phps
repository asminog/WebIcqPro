<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4: */
//
// PHP version 4
//
// Copyright (c) 2004 ASM
//
// Authors: Sergey Akudovich <asm@intrigue.ru>
//

class ICQ {
    var $error;
    var $info = array();
    var $sizes = array();
    var $socet, $o;
    var $F = array();
    var $mess = array();
    function ICQ($uin, $pass)
    {
        $this->error='';

        $this->info[1]=$uin.'';                                                 // uin
        $this->info[2]=$this->_xorpass($pass.'');                               // pass
        $this->info[3]='ICQ Inc. - Product of ICQ (TM).2003b.5.56.1.3916.85';   // client
        $this->info[14]='us';                                                   // client country
        $this->info[15]='en';                                                   // client language
        $this->info[20]=85;                                                     // distribution number
        $this->sizes[20]=4;                                                     // distribution number
        $this->info[22]=266;                                                    // client id
        $this->sizes[22]=2;                                                     // distribution number
        $this->info[23]=5;                                                      // client major version
        $this->sizes[23]=2;                                                     // distribution number
        $this->info[24]=37;                                                     // client minor version
        $this->sizes[24]=2;                                                     // distribution number
        $this->info[25]=1;                                                      // client lesser version
        $this->sizes[25]=2;                                                     // distribution number
        $this->info[26]=3728;                                                   // client build number
        $this->sizes[26]=2;                                                     // distribution number

        $this->F['outsid']=rand(0x0001, 0x8000);                                // define session out id

        $this->icq_login();
    }

    function icq_login()
    {
//        $this->o = fopen('out', 'wb');
        $this->socet = fsockopen("login.icq.com", 5190, $errno, $errstr);
        if (!$this->socet) {
            exit("ERROR: $errno - $errstr<br>\n");
        }
        $this->FLAP_read();
        $this->FLAP_write('CMD_LOGIN');
        $cook=$this->FLAP_read();
        if($this->F['type']==4){
            $this->_TLV_array($cook);
        };
        if(isset($this->info[5]) && isset($this->info[6])){
            $address = explode(':', $this->info[5]);
            $this->socet = fsockopen($address[0], $address[1], $errno, $errstr);
            if (!$this->socet) {
                exit("ERROR: $errno - $errstr<br>\n");
            }
            $this->FLAP_read();
            $this->FLAP_write('CMD_COOKIE');
            $this->FLAP_read();
            $this->FLAP_write('CMD_READY');
//            $this->info=array();
        }else{
            $this->error = 'Error to connect. Connect too fast. Try 10-20 minutes later.';
        }
    }

    function icq_send_message($uin, $message)
    {
        $this->F['touin']=$uin.'';
        $this->F['outdata']=$message;
        $this->FLAP_write('CMD_MESSAGE');
//        sleep(10);
        return 1;
//        fclose($this->o);
    }

    function icq_read_message()
    {
        $arr=$this->FLAP_read();
        if($arr!==0){
            if($this->F['type']==2){
                $this->_SNAC($arr);
            }
            if(isset($this->mess['channel'])){
                if($this->mess['channel']==1){
                    $this->mess['mess'] = $this->_MESS1($this->info[2]);
                }
                if($this->mess['channel']==2){
                    $this->mess['mess'] = $this->_MESS2($this->info[5]);
                }
                return true;
            }else{
                return false;
            }
//            unset($this->info);
        }else{
            return false;
        }
    }

    function icq_error()
    {
        icq_exit();
        exit();
    }

    function icq_exit()
    {
        fclose($this->socet);
    }

    function FLAP_read()
    {
        $Fh = @fread($this->socet, 6);
        if(strlen($Fh)==6){
    //        fwrite($this->o, "inhead:$Fh");
            if(ord($Fh{0})!==0x2A){
                exit('ICQ protocol sync error');
            }
            $this->F['type'] = ord($Fh{1});
            $Fsid = ord($Fh{3})+(ord($Fh{2})<<8);//(int)(ord($Fh{2}).ord($Fh{3}));
    //  If first time connect define insid
            if(!isset($this->F['insid'])){
                $this->F['insid']=$Fsid;
            }
    //  Check insid
            if($Fsid!=$this->F['insid']){
                exit('ICQ protocol sync error my sid $Fsid != '.$this->F['insid']);
            }else{
                $this->F['insid']++;
                if($this->F['insid']==0x8000){
                    $this->F['insid']=0x0000;
                }
            }
    //  Define data size
            $Fds  = ord($Fh{5})+(ord($Fh{4})<<8);//(int)(ord($Fh{4}).ord($Fh{5}));
    //  Read data
            $data = fread($this->socet, $Fds);
            if($this->F['type']==4){
                fclose($this->socet);
                unset($this->F['insid']);
            }
//            fwrite($this->o, "|-|".$data);
            return $data;
        }else{
            return 0;
        }
    }

    function FLAP_write($type, $data='')
    {
        $channel = 2;
        switch ($type) {
          case 'CMD_LOGIN':
                $data = $this->_int2bites(4, 1).
                        $this->_TLV(array(1, 2, 3, 22, 23, 24, 25, 26, 20, 15, 14));
                $channel = 1;
            break;
          case 'CMD_COOKIE':
                $data = $this->_int2bites(4, 1).
                        $this->_TLV(array(6));
                $this->info[6]='';
                $channel = 1;
            break;
          case 'CMD_READY':
                $data = $this->_int2bites(2, 1).$this->_int2bites(2, 2).$this->_int2bites(2, 0).$this->_int2bites(4, 2).
                        $this->_int2bites(2, 1).
                        $this->_int2bites(2, 3).
                        $this->_int2bites(2, 0x0110).
                        $this->_int2bites(2, 0x028a).
                        $this->_int2bites(2, 2).
                        $this->_int2bites(2, 1).
                        $this->_int2bites(2, 0x0101).
                        $this->_int2bites(2, 0x028a).
                        $this->_int2bites(2, 3).
                        $this->_int2bites(2, 1).
                        $this->_int2bites(2, 0x0110).
                        $this->_int2bites(2, 0x028a).
                        $this->_int2bites(2, 0x15).
                        $this->_int2bites(2, 1).
                        $this->_int2bites(2, 0x0110).
                        $this->_int2bites(2, 0x028a).
                        $this->_int2bites(2, 4).
                        $this->_int2bites(2, 1).
                        $this->_int2bites(2, 0x0110).
                        $this->_int2bites(2, 0x028a).
                        $this->_int2bites(2, 6).
                        $this->_int2bites(2, 1).
                        $this->_int2bites(2, 0x0110).
                        $this->_int2bites(2, 0x028a).
                        $this->_int2bites(2, 9).
                        $this->_int2bites(2, 1).
                        $this->_int2bites(2, 0x0110).
                        $this->_int2bites(2, 0x028a).
                        $this->_int2bites(2, 0x0a).
                        $this->_int2bites(2, 1).
                        $this->_int2bites(2, 0x0110).
                        $this->_int2bites(2, 0x028a);
            break;
          case 'CMD_MESSAGE':
                $this->info[2]=$this->_int2bites(4, 0x05010001).$this->_int2bites(3, 0x010101).$this->_int2bites(2, strlen($this->F['outdata'])+4).$this->_int2bites(4, 0).$this->F['outdata'];
                $data = $this->_int2bites(2, 4).$this->_int2bites(2, 6).$this->_int2bites(2, 0).$this->_int2bites(4, 6).
                        $this->_int2bites(8, time()).
                        $this->_int2bites(2, 1).
                        $this->_int2bites(1, strlen($this->F['touin'])).
                        $this->F['touin'].
                        $this->_TLV(array(2,6));
            break;
        }
        $cmd = $this->_int2bites(1, 0x2A).$this->_int2bites(1, $channel).$this->_int2bites(2, $this->F['outsid']).$this->_int2bites(2, strlen($data)).$data;
//        fwrite($this->o, "|||".$cmd);
        fwrite($this->socet, $cmd);
        $this->F['outsid']++;
    }

    function _xorpass($pass)
    {
        $roast = array(0xF3, 0x26, 0x81, 0xC4, 0x39, 0x86, 0xDB, 0x92, 0x71, 0xA3, 0xB9, 0xE6, 0x53, 0x7A, 0x95, 0x7c);
        $roasting_pass = '';
        for ($i=0; $i<strlen($pass); $i++) {
            $roasting_pass .= chr($roast[$i] ^ ord($pass{$i}));
        }
        return($roasting_pass);
    }

    function _int2bites($bites, $val=0)
    {
        $ret  = '';
        for ($i=0; $i<$bites; $i++) {
            $ret = chr(($val >> ($i*8) & 0xFF)).$ret;
        }
        return($ret);
    }
    function _bites2int($hex=0)
    {
        $dec = 0;
        $bitval = 1;
        for($pos = 1; $pos <= strlen($hex); $pos++) {
            $dec += hexdec(substr($hex, -$pos, 1)) * $bitval;
            $bitval *= 16;
        }
        return($dec);
    }
    function _TLV($arr)
    {
        $out='';
        foreach($arr as $i){
            $out.=$this->_int2bites(2, $i);
            if (isset($this->sizes[$i])) {
               $size = $this->sizes[$i];
            }else{
                $size = strlen($this->info[$i]);
            }
            $out.=$this->_int2bites(2, $size);
            if(is_int($this->info[$i])){
                $out.=$this->_int2bites($size, $this->info[$i]);
            }else{
                $out.=$this->info[$i];
            }
        }
        return($out);
    }
    function _TLV_array($str='')
    {
        $out = array();
        while ($str!='') {
            $i = ord($str{1})+(ord($str{0})<<8);
            $out[] = $i;
            $size = ord($str{3})+(ord($str{2})<<8);
            $sizes[] = $size;
            $this->info[$i] = substr($str, 4, $size);

            $str = substr($str, (4+$size));
        }
        return($out);
    }
    function _SNAC($str)
    {
        $out = array();

        $family = ord($str{1})+(ord($str{0})<<8);
        $subfamily = ord($str{3})+(ord($str{2})<<8);

        if($family==4){
            switch ($subfamily) {
              case 7:
                    $this->mess['mid'] = substr($str, 10, 8);
                    $this->mess['channel'] = ord(substr($str, 19, 1));
                    $uin_size = ord(substr($str, 20, 1));
                    $this->mess['uin'] = substr($str, 21, $uin_size);
                    $str = substr($str, 25+$uin_size);
                    $this->_TLV_array($str);
                break;
              default:
                return 0;
            }
        }else{
            return 0;
        }
        return 1;
    }
    function _MESS1($str)
    {
        $s1 = ord($str{3})+(ord($str{2})<<8);
        $s = substr($str, 6+$s1, 2);
        $s2 = ord($s{1})+(ord($s{0})<<8);
        $text = substr($str, 12+$s1, $s2+4);
        return $text;
    }
    function _MESS2($str)
    {
//        $s1 = substr($str, 2, 2);
//        $s2 = substr($str, 6+$s1, 2);
//        $text = substr($str, 8+$s1, $s2);
        return '*** Робот не понял ваш тип сообщения.';
//            fwrite($this->o, "|mess2|".$arr);
    }
}

?>