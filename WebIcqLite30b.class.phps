<?php
/*
* WebIcqLite: ICQ messages sender. v3.0b
* (C) 2006 Sergey Akudovich, http://intrigue.ru/
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU Lesser General Public
* License as published by the Free Software Foundation; either
* version 2.1 of the License, or (at your option) any later version.
* See http://www.gnu.org/copyleft/lesser.html
*
*/

class WebIcqLite_TLV {
	var $type;
	var $size;
	var $error;
	
	var $types = array
	(
		'UIN' 				=>  1, // 0x01
		'DATA'				=>  2, // 0x02
		'CLIENT'			=>  3, // 0x03
		'ERROR_URL'			=>  4, // 0x04
		'RECONECT_HERE'		=>  5, // 0x05
		'COOKIE'			=>  6, // 0x06
		'SNAC_VERSION'		=>  7, // 0x07
		'ERROR_SUBCODE'		=>  8, // 0x08
		'DISCONECT_REASON'	=>  9, // 0x09
		'RECONECT_HOST'		=> 10, // 0x0A
		'URL'				=> 11, // 0x0B
		'DEBUG_DATA'		=> 12, // 0x0C
		'SERVICE'			=> 13, // 0x0D
		'CLIENT_COUNTRY'	=> 14, // 0x0E
		'CLIENT_LNG'		=> 15, // 0x0F
		'SCRIPT'			=> 16, // 0x10
		'USER_EMAIL'		=> 17, // 0x11
		'OLD_PASSWORD'		=> 18, // 0x12
		'REG_STATUS'		=> 19, // 0x13
		'DISTRIB_NUMBER'	=> 20, // 0x14
		'PERSONAL_TEXT'		=> 21, // 0x15
		'CLIENT_ID'			=> 22, // 0x16
		'CLI_MAJOR_VER' 	=> 23, // 0x17
		'CLI_MINOR_VER' 	=> 24, // 0x18
		'CLI_LESSER_VER' 	=> 25, // 0x19
		'CLI_BUILD_NUMBER'	=> 26, // 0x1A
//		'PASSWORD'			=> 37
	);
	
	function setTLV($type, $value, $length = false)
	{
		switch ($length) 
		{
			case 1:
				$format = 'c';
				break;
			case 2:
				$format = 'n';
				break;
			case 4:
				$format = 'N';
				break;
			default:
				$format = 'a*';
				break;
		}
		if ($length === false) 
		{
			$length = strlen($value);
		}
		return pack('nn'.$format, $this->types[$type], $length, $value);
	}
	
	function getTLV($data)
	{
		$arr = unpack('n2', substr($data, 0, 4));
		$this->type = $arr[1];
		$this->size = $arr[2];
		return substr($data, 4, $this->size);
	}

	function getTLVFragment($data)
	{
		$frg = unpack('cid/cversion/nsize', substr($data, 0, 4));
		$frg['data'] = substr($data, 4, $frg['size']);
		return $frg;
	}
}

class WebIcqLite_SNAC extends WebIcqLite_TLV {
	
	var $request_id = 0;
	var $uin;
	
	function setSNAC0102()
	{
		$this->request_id++;
		$out = pack('nnnN', 1, 2, 0, $this->request_id);
		$out .= pack('n*', 1, 3, 272, 650);
		$out .= pack('n*', 2, 1, 272, 650);
		$out .= pack('n*', 3, 1, 272, 650);
		$out .= pack('n*', 21, 1, 272, 650);
		$out .= pack('n*', 4, 1, 272, 650);
		$out .= pack('n*', 6, 1, 272, 650);
		$out .= pack('n*', 9, 1, 272, 650);
		$out .= pack('n*', 10, 1, 272, 650);
		
		return $out;
	}
	
	function setSNAC0406($uin, $message)
	{
		$this->request_id++;
		$cookie = microtime();
		$out = pack('nnnNdnca*', 4, 6, 0, $this->request_id, $cookie, 2, strlen($uin), $uin);
		
		$capabilities = pack('H*', '094613494C7F11D18222444553540000'); // utf-8 support
		// '97B12751243C4334AD22D6ABF73F1492' rtf support
		
		$data = pack('nd', 0, $cookie).$capabilities;
		$data .= pack('nnn', 10, 2, 1);
		$data .= pack('nn', 15, 0);
		$data .= pack('nnvvddnVn', 10001, strlen($message)+62, 27, 8, 0, 0, 0, 3, $this->request_id);
		$data .= pack('nndnn', 14, $this->request_id, 0, 0, 0); //45
		$data .= pack('ncvnva*', 1, 0, 0, 1, (strlen($message)+1), $message);
		$data .= pack('H*', '0000000000FFFFFF00');
		$out .= $this->setTLV('RECONECT_HERE', $data);
		$out .= $this->setTLV('CLIENT', '');
		return $out;
	}
	
	function setSNAC0406offline($uin, $message)
	{
		$this->request_id++;
		$cookie = microtime();
		$out = pack('nnnNdnca*', 4, 6, 0, $this->request_id, $cookie, 1, strlen($uin), $uin);
		
		$data = pack('ccnc', 5, 1, 1, 1);
		$data .= pack('ccnnna*', 1, 1, strlen($message)+4, 3, 0, $message);
		$out .= $this->setTLV('DATA', $data);
		$out .= $this->setTLV('CLIENT', '');
		$out .= $this->setTLV('COOKIE', '');
		return $out;
	}
	
	function getSNAC0407($body)
	{
		if (strlen($body)) 
		{
			$msg = unpack('nfamily/nsubtype/nflags/Nrequestid/N2msgid/nchannel/cnamesize', $body);
			if ($msg['family'] == 4 && $msg['subtype'] == 7) 
			{
				$body = substr($body, 21);
				$from = substr($body, 0, $msg['namesize']);
				$channel = $msg['channel'];
				$body = substr($body, $msg['namesize']);
				$msg = unpack('nwarnlevel/nTLVnumber', $body);
				$body = substr($body, 4);
				for ($i = 0; $i <= $msg['TLVnumber']; $i++)
				{
					$part = $this->getTLV($body);
					$body = substr($body, 4 + $this->size);
					if ($channel == 1 && $this->type == 2) 
					{
						while (strlen($part)) 
						{
							$frg = $this->getTLVFragment($part);
							if ($frg['id'] == 1 && $frg['version'] == 1) 
							{
								return array('from' => $from, 'message' => substr($frg['data'], 4));
							}
							$part = substr($part, 4+$frg['size']);
						}
						return false;
					}
				}
			}
		}
		return false;
	}
	function dump($str)
	{
		$f = fopen('dump', 'a');
		fwrite($f, $str);
		fclose($f);
	}
	
}

class WebIcqLite_FLAP extends WebIcqLite_SNAC{
	
	var $socet;
	var $command = 0x2A;
	var $channel;
	var $sequence;
	var $body;
	var $info = array();

	function WebIcqLite_FLAP() {
		$this->sequence = rand(1, 30000);
	}
	
	function getFLAP()
	{
		if($this->socet)
		{
			$header = socket_read($this->socet, 6);
			if ($header) 
			{
				$header = unpack('c2channel/n2size', $header);
				$this->channel = $header['channel2'];
				$this->body = socket_read($this->socet, $header['size2']);
				return true;
			}
			else 
			{
				return false;
			}
		}
	}
	
	function parseCookieFLAP()
	{
		$this->getFLAP();
		$this->info = array();
		while($this->body != '')
		{
			$info = $this->getTLV($this->body);
			$key = array_search($this->type, $this->types);
			if($key)
			{
				$this->info[$key] = $info;
			}
			$this->body = substr($this->body, ($this->size+4));
		}
	}
	
	function parseAnswerFLAP()
	{
		$this->getFLAP();
		$array = unpack('n3int/Nint', $this->body);
		while ($array['int'] != $this->request_id) 
		{
			$this->getFLAP();
			$array = unpack('n3int/Nint', $this->body);
		}

		$this->error = 'Unknown serwer answer';
		if ($array['int1'] == 4) 
		{
			switch ($array['int2']) 
			{
				case 1:
						$this->error = 'Error to sent message';
						return false;
					break;
				case 0x0c:
						return true;
					break;
			}
		}

		$this->error = 'Unknown serwer answer';
		return false;
	}
	
	function prepare()
	{
		$this->sequence++;
		$out = pack('ccnn', $this->command, $this->channel, $this->sequence, strlen($this->body)).$this->body;
		return $out;
	}
	
	function login($uin, $password)
	{
		$this->getFLAP();
		$this->uin = $uin;
		$this->body .= $this->setTLV('UIN', 				"$uin");
		$this->body .= $this->setTLV('DATA', 				$this->xorpass($password));
		$this->body .= $this->setTLV('CLIENT', 				'ICQBasic');
		$this->body .= $this->setTLV('CLIENT_ID', 			266, 2);
		$this->body .= $this->setTLV('CLI_MAJOR_VER', 		20, 2);
		$this->body .= $this->setTLV('CLI_MINOR_VER', 		34, 2);
		$this->body .= $this->setTLV('CLI_LESSER_VER', 		0, 2);
		$this->body .= $this->setTLV('CLI_BUILD_NUMBER', 	2321, 2);
		$this->body .= $this->setTLV('DISTRIB_NUMBER', 		1085, 4);
		$this->body .= $this->setTLV('CLIENT_LNG', 			'en');
		$this->body .= $this->setTLV('CLIENT_COUNTRY', 		'us');
		
		
		$this->channel = 1;
		$pack = $this->prepare();
		socket_write($this->socet, $pack, strlen($pack));
		$this->parseCookieFLAP();
		
		$this->body = 0x0000;
		$pack = $this->prepare();
		socket_write($this->socet, $pack, strlen($pack));
		$this->close();
		
		if(isset($this->info['RECONECT_HERE']))
		{
			$url = explode(':', $this->info['RECONECT_HERE']);
			if(!$this->open($url))
			{
				$this->error = isset($this->info['DISCONECT_REASON']) ? $this->info['DISCONECT_REASON'] : 'Unable to reconnect';
				return false;
			}
		}
		else
		{
			$this->error = isset($this->info['DISCONECT_REASON']) ? $this->info['DISCONECT_REASON'] : 'UIN blocked, please try again 20 min later.';
			return false;
		}

		$this->getFLAP();
		$this->body .= $this->setTLV('COOKIE', $this->info['COOKIE']);
		$pack = $this->prepare();
		if (!socket_write($this->socet, $pack, strlen($pack)))
		{
			$this->error = 'Can`t send cookie, server close connection';
			return false;
		}
		$this->getFLAP();
		$this->body = $this->setSNAC0102();
		$pack = $this->prepare();
		if (!socket_write($this->socet, $pack, strlen($pack)))
		{
			$this->error = 'Can`t send ready signal, server close connection';
			return false;
		}
		return true;
	}
	
	function write_message($uin, $message)
	{
		$this->body = $this->setSNAC0406($uin, $message);
		$pack = $this->prepare();
		if (!socket_write($this->socet, $pack, strlen($pack)))
		{
			$this->error = 'Can`t send message, server close connection';
			return false;
		}
		if (! $this->parseAnswerFLAP()) {
			// try to send offline message
			
			$this->body = $this->setSNAC0406offline($uin, $message);
			$pack = $this->prepare();
			if (!socket_write($this->socet, $pack, strlen($pack)))
			{
				$this->error = 'Can`t send offline message, server close connection';
				return false;
			}
			if (! $this->parseAnswerFLAP()) 
			{
				return false;
			}
			else
			{
				$this->error = 'Client is offline. Message sent to server.';
				return false;
			}
		}
		
		return true;
	}
	
	function read_message()
	{
		while($this->getFLAP())
		{
			$message = $this->getSNAC0407($this->body);
			if($message){
				return $message;
			}
		}
		return false;
	}

	function xorpass($pass)
	{
		$roast = array(0xF3, 0x26, 0x81, 0xC4, 0x39, 0x86, 0xDB, 0x92, 0x71, 0xA3, 0xB9, 0xE6, 0x53, 0x7A, 0x95, 0x7c);
		$roasting_pass = '';
		for ($i=0; $i<strlen($pass); $i++) 
		{
			$roasting_pass .= chr($roast[$i] ^ ord($pass{$i}));
		}
		return($roasting_pass);
	}
	
	function open($url = array('login.icq.com', 5190))
	{
		$this->socet = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($this->socet < 0) 
		{
			$this->error = "socket_create() failed: reason: " . socket_strerror($this->socet);
			return false;
		}
		$result = socket_connect($this->socet, gethostbyname($url[0]), $url[1]);
		if ($result < 0) 
		{
			$this->error = "socket_connect() failed.\nReason: ($result) " . socket_strerror($result);
			return false;
		}
		return true;
	}

	function close()
	{
		return socket_close($this->socet);
	}
}

class WebIcqLite extends WebIcqLite_FLAP {

	function WebIcqLite ()
	{
		$this->WebIcqLite_FLAP();
	}
	
	function connect($uin, $pass)
	{
		if (!$this->open()) 
		{
			return false;
		}
		
		return $this->login($uin, $pass);
	}

	function disconnect()
	{
		return $this->close();
	}

	function get_message()
	{
		return $this->read_message();
	}
	
	function send_message($uin, $message)
	{
		return $this->write_message($uin, $message);
	}
	function send_sms($phpne, $message)
	{
		return $this->write_sms($phpne, $message);
	}
}
?>
