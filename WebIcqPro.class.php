<?php
include('rtf.class.php');
/**
 * http://wip.asminog.com/ - disscus this package here.
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * See http://www.gnu.org/copyleft/lesser.html
 *
 * @author Sergey Akudovich
 * @package WebIcqPro
 *
 */

class WebIcqPro_LV {
	const VERSION = '1.5b';
	/**
	 * Last error message
	 *
	 * @var string
	 */
	public $error;

	protected function packLV($value, $length = 'n')
	{
		return pack($length, strlen($value)).$value;
	}

	protected function unpackLV(&$data, $l = 'n')
	{
		$length = $l == 'c' ? 1 : 2;
		if (strlen($data)>=$length)
		{
			$result = unpack($l.'size', $data);
			$result['data'] = substr($data, $length , $result['size']);
			$data = substr($data, $result['size']+$length);
			return $result['data'];
		}
		$this->error = 'unpackLV brocken LV';
		return false;
	}
}

/**
 * Layer for TLV (Type-Length-Value) data format
 * @access private
 */
class WebIcqPro_TLV extends WebIcqPro_LV {

	/**
	 * Pack data to TLV
	 *
	 * Use this method to create TLVs. available formats 'c', 'n', 'N', 'a*', 'H*'
	 *
	 * @access private
	 * @param integer $type
	 * @param string $value
	 * @param string $format
	 * @return string binary
	 */
	protected function packTLV($type, $value='', $format = 'a*')
	{
		if (in_array($format, array('c', 'n', 'N', 'a*', 'H*')))
		{
			$len = strlen($value);
			switch ($format) {
				case 'c':
					$len = 1;
					break;
				case 'n':
					$len = 2;
					break;
				case 'N':
					$len = 4;
					break;
				case 'H*':
					$len = strlen($value)/2;
					break;
			}
			return pack('nn'.$format, $type, $len, $value);
		}
		$this->error = 'Warning: packTLV unknown data format: '.$format;
		return false;
	}

	/**
	 * Unpack data from TLV
	 *
	 * Use this method to extract TLV binary data
	 *
	 * @access private
	 * @param string $data
	 * @return string
	 */
	protected function unpackTLV(& $data)
	{
		if (strlen($data)>=4)
		{
			$result = unpack('ntype/nsize', $data);
			$result['data'] = substr($data, 4 , $result['size']);
			$data = substr($data, $result['size']+4);
			return $result;
		}
		$this->error = 'Error: unpackTLV brocken TLV';
		return false;
	}

	/**
	 * Unpuck data from set of TLVs
	 *
	 * Use this method to extract a set of TLVs binary data to an associated array where array indexes are TLV type and value TLV data
	 *
	 * @access private
	 * @param string $data
	 * @return array
	 */
	protected function splitTLVsToArray($data, $fixqip = false)
	{
		$tlv = array();
		while (strlen($data) > 0)
		{
			$tlv_data = $this->unpackTLV($data);
			if ($tlv_data)
			{
				$tlv[$tlv_data['type']] = $tlv_data['data'];
			}
			elseif($fixqip)
			{
				if(isset($tlv[0x0101]))
				{
					$tlv[0x0101] = substr($tlv[0x0101], 0 , -1);
				}
				return $tlv;
			}
			else
			{
				return false;
			}
		}
		return $tlv;
	}
}

/**
 * Layer for SNAC data format
 * @access private
 */
class WebIcqPro_SNAC extends WebIcqPro_TLV {

	/**
	 * Request counter
	 *
	 * This variable store request id for SNAC
	 *
	 * @access private
	 * @var integer
	 */
	private $request_id;

	/**
	 * Capability for message
	 *
	 * This variable store current method of capability for ibcm
	 *
	 * @access private
	 * @var string
	 */
	private $ibcm_capabilities;

	/**
	 * Message type
	 *
	 * This variable store current type of ibcm
	 *
	 * @access private
	 * @var string
	 */
	private $ibcm_type;

	/**
	 * Message Encoding
	 *
	 * This variable store current encoding of message
	 *
	 * @access private
	 * @var string
	 */
	private $ibcm_encoding;

	/**
	 * Client type.
	 *
	 * This variable used to store current client name.
	 *
	 * @access private
	 * @var string
	 */
	protected $agent;

	/**
	 * Array of known SNACs
	 *
	 * Full list of supported SNACs. All added handlers should be added here.
	 *
	 * @access private
	 * @var array
	 */
	protected $snac_names = array(
	0x01 => array( // Generic service controls
	0x02 => 'ClientReady',
	0x03 => 'ServerFamilies',
	0x06 => 'ClientRates',
	0x07 => 'ServerRates',
	0x08 => 'ClientRatesAck',
	0x0A => 'ServerRateLimit',
	0x0B => 'ServerPause',
	0x0C => 'ClientPause',
	0x0D => 'ServerResume',
	0x0E => 'ClientRequestSelfInfo',
	0x0F => 'ServerResponseSelfInfo',
	0x11 => 'ClientIdleTime',
	0x12 => 'ServerMigration',
	0x17 => 'ClientFamiliesVersions',
	0x18 => 'ServerFamiliesVersions',
	0x1E => 'ClientStatus',
	0x21 => 'ClientBart',
	'version' => 0x04
	),
	0x02 => array( // Location services
	0x02 => 'ClientLocationRights',
	0x03 => 'ServerLocationRights',
	0x04 => 'ClientLocationInfo',
	'version' => 0x01
	),
	0x03 => array( // Buddy List management service
	0x01 => 'OscarError',
	0x02 => 'ClientBuddylistRights',
	0x03 => 'ServerBuddylistRights',
	0x04 => 'ClientBuddylistAdd',
	0x05 => 'ClientBuddylistDelete',
	0x0B => 'ServerUserOnline',
	0x0C => 'ServerUserOffline',
	'version' => 0x01
	),
	0x04 => array( // ICBM (messages) service
	0x01 => 'OscarError',
	0x02 => 'ClientSetIBCMParams',
	0x04 => 'ClientIBCMRights',
	0x05 => 'ServerIBCMRights',
	0x06 => 'ClientIBCM',
	0x07 => 'ServerIBCM',
	0x0B => 'ClientIBCMAck',
	0x0C => 'ServerIBCMAck',
	'version' => 0x01
	),
	0x09 => array( // Privacy management service
	0x02 => 'ClientPrivicyRights',
	0x03 => 'ServerPrivicyRights',
	'version' => 0x01
	),
	0x13 => array( // Server Side Information (SSI) service
	0x01 => 'ServerSSIError',
	0x02 => 'ClientSSIRights',
	0x03 => 'ServerSSIRights',
	0x04 => 'ClientSSI',
	0x05 => 'ClientSSICheckout',
	0x06 => 'ServerSSI',
	0x07 => 'ClientSSIActivate',
	0x08 => 'ClientSSIAdd',
	0x0A => 'ClientSSIDelete',
	0x0E => 'ServerSSIAck',
	0x0F => 'ServerSSIModificationDate',
	0x11 => 'ClientSSIEditStart',
	0x12 => 'ClientSSIEditEnd',
	0x18 => 'ClientSSIAuthRequest',
	0x19 => 'ServerSSIAuthRequest',
	0x1a => 'ClientSSIAuthResponse',
	0x1b => 'ServerSSIAuthResponse',
	0x1c => 'ServerSSIYouAdded',
	'version' => 0x04
	),
	0x15 => array( // ICQ specific extensions service
	0x02 => 'ClientMetaData',
	0x03 => 'ServerMetaData',
	'version' => 0x01
	),
	0x17 => array( // Authorization/registration service
	0x02 => 'ClientMd5Login',
	0x03 => 'ServerMd5LoginReply',
	0x06 => 'ClientMd5Request',
	0x07 => 'ServerMd5Response',
	'version' => 0x01
	)
	);

	public $debug = false;

	protected $protocol_version = 11;
	protected $capability_flag = '03000000';

	private $rates;
	protected $rates_groups;

	private $login_errors = array(
	0x0001 => 'Invalid nick or password',
	0x0002 => 'Service temporarily unavailable',
	0x0003 => 'All other errors',
	0x0004 => 'Incorrect nick or password, re-enter',
	0x0005 => 'Mismatch nick or password, re-enter',
	0x0006 => 'Internal client error (bad input to authorizer)',
	0x0007 => 'Invalid account',
	0x0008 => 'Deleted account',
	0x0009 => 'Expired account',
	0x000A => 'No access to database',
	0x000B => 'No access to resolver',
	0x000C => 'Invalid database fields',
	0x000D => 'Bad database status',
	0x000E => 'Bad resolver status',
	0x000F => 'Internal error',
	0x0010 => 'Service temporarily offline',
	0x0011 => 'Suspended account',
	0x0012 => 'DB send error',
	0x0013 => 'DB link error',
	0x0014 => 'Reservation map error',
	0x0015 => 'Reservation link error',
	0x0016 => 'The users num connected from this IP has reached the maximum',
	0x0017 => 'The users num connected from this IP has reached the maximum (reservation)',
	0x0018 => 'Rate limit exceeded (reservation). Please try to reconnect in a few minutes',
	0x0019 => 'User too heavily warned',
	0x001A => 'Reservation timeout',
	0x001B => 'You are using an older version of ICQ. Upgrade required',
	0x001C => 'You are using an older version of ICQ. Upgrade recommended',
	0x001D => 'Rate limit exceeded. Please try to reconnect in a few minutes',
	0x001E => 'Can`t register on the ICQ network. Reconnect in a few minutes',
	0x0020 => 'Invalid SecurID',
	0x0022 => 'Account suspended because of your age (age < 13)'
	);

	private $oscar_errors = array(
	0x01 => 'Invalid SNAC header.',
	0x02 => 'Server rate limit exceeded',
	0x03 => 'Client rate limit exceeded',
	0x04 => 'Recipient is not logged in',
	0x05 => 'Requested service unavailable',
	0x06 => 'Requested service not defined',
	0x07 => 'You sent obsolete SNAC',
	0x08 => 'Not supported by server',
	0x09 => 'Not supported by client',
	0x0A => 'Refused by client',
	0x0B => 'Reply too big',
	0x0C => 'Responses lost',
	0x0D => 'Request denied',
	0x0E => 'Incorrect SNAC format',
	0x0F => 'Insufficient rights',
	0x10 => 'In local permit/deny (recipient blocked)',
	0x11 => 'Sender too evil',
	0x12 => 'Receiver too evil',
	0x13 => 'User temporarily unavailable',
	0x14 => 'No match',
	0x15 => 'List overflow',
	0x16 => 'Request ambiguous',
	0x17 => 'Server queue full',
	0x18 => 'Not while on AOL',
	);
	private $oscar_buddy_errors = array(
	0x0000 => 'No errors (success)',
	0x0002 => 'Item you want to modify not found in list',
	0x0003 => 'Item you want to add allready exists',
	0x000A => 'Error adding item (invalid id, allready in list, invalid data)',
	0x000C => 'Can\'t add item. Limit for this type of items exceeded',
	0x000D => 'Trying to add ICQ contact to an AIM list',
	0x000E => 'Can\'t add this contact because it requires authorization'
	);
	protected $substatuses = array(
	'STATUS_WEBAWARE'   => 0x0001,
	'STATUS_SHOWIP'     => 0x0002,
	'STATUS_BIRTHDAY'   => 0x0008,
	'STATUS_WEBFRONT'   => 0x0020,
	'STATUS_DCDISABLED' => 0x0100,
	'STATUS_DCAUTH'     => 0x1000,
	'STATUS_DCCONT'     => 0x2000
	);

	protected $statuses = array(
	'STATUS_ONLINE'     => 0x0000,
	'STATUS_AWAY'       => 0x0001,
	'STATUS_DND'        => 0x0002,
	'STATUS_DND2'       => 0x0013,
	'STATUS_NA'         => 0x0004,
	'STATUS_NA2'        => 0x0005,
	'STATUS_OCCUPIED'   => 0x0010,
	'STATUS_OCCUPIED2'  => 0x0011,
	'STATUS_FREE4CHAT'  => 0x0020,
	'STATUS_INVISIBLE'  => 0x0100,
 	'STATUS_EVIL'       => 0x3000,
 	'STATUS_DEPRESSION' => 0x4000,
 	'STATUS_ATHOME'     => 0x5000,
 	'STATUS_ATWORK'     => 0x6000,
 	'STATUS_LUNCH'		=> 0x2001,
 	'STATUS_OFFLINE'	=> 0xFFFF
	);

	protected $status_message = '';

	protected $xstatus_message = '';

	protected $capabilities = array(
	'0138ca7b769a491588f213fc00979ea8',
	'67361515612d4c078f3dbde6408ea041',
	'1a093c6cd7fd4ec59d51a6474e34f5a0',
	'b2ec8f167c6f451bbd79dc58497888b9',
	'094613494C7F11D18222444553540000',
	'0946134E4C7F11D18222444553540000',
	'094613434C7F11D18222444553540000',
	'563FC8090B6F41BD9F79422609DFA2F3'

//	'094600004C7F11D18222444553540000', //  Avatar
	//'0946134D4C7F11D18222444553540000', //  Setting this lets AIM users receive messages from ICQ users, and ICQ users receive messages from AIM users. It also lets ICQ users show up in buddy lists for AIM users, and AIM users show up in buddy lists for ICQ users. And ICQ privacy/invisibility acts like AIM privacy, in that if you add a user to your deny list, you will not be able to see them as online (previous you could still see them, but they couldn't see you.
	//'094613444C7F11D18222444553540000', //  Something called "route finder". Currently used only by ICQ2K clients.
	//'094613494C7F11D18222444553540000', //	Client supports channel 2 extended, TLV(0x2711) based messages. Currently used only by ICQ clients. ICQ clients and clones use this GUID as message format sign.
	//'1A093C6CD7FD4EC59D51A6474E34F5A0', //  Xtraz
//	'0946134E4C7F11D18222444553540000', //	Client supports UTF-8 messages. This capability currently used by AIM service and AIM clients.
//	'97B12751243C4334AD22D6ABF73F1492', //  Client supports RTF messages. This capability currently used by ICQ service and ICQ clients.
//	'563FC8090B6f41BD9F79422609DFA2F3', //	Typing Notifications
	);

	protected $user_agent_capability = array(
	'miranda'   => '4D6972616E64614D0004000200030700',
	'sim'       => '53494D20636C69656E74202000090402',
	'trillian'  => '97B12751243C4334AD22D6ABF73F1409',
	'licq'      => '4c69637120636c69656e742030303030',
	'kopete'    => '4b6f7065746520494351202030303030',
	'micq'      => '6d49435120A920522e4b2e2030303030',
	'andrq'     => '265251696e7369646530303030303030',
	'randq'     => '522651696e7369646530303030303030',
	'mchat'     => '6d436861742069637120303030303030',
	'jimm'      => '4a696d6d203030303030303030303030',
	'macicq'    => 'dd16f20284e611d490db00104b9b4b7d',
	'icqlite'   => '178C2D9BDAA545BB8DDBF3BDBD53A10A',
	'icq'		=> '178c2d9bdaa545bb8ddbf3bdbd53a10a',
	//		'qip'       => '563FC8090B6F41514950203230303561',
	//		'qippda'    => '563FC8090B6F41514950202020202021',
	//		'qipmobile' => '563FC8090B6F41514950202020202022',
	//		'anastasia' => '44E5BFCEB096E547BD65EFD6A37E3602',
	//		'icq2001'   => '2e7a6475fadf4dc8886fea3595fdb6df',
	//		'icq2002'   => '10cf40d14c7f11d18222444553540000',
	//		'IcqJs7     => '6963716A202020202020202020202020',
	//		'IcqJs7sec  => '6963716A2053656375726520494D2020',
	//		'TrilCrypt  => 'f2e7c7f4fead4dfbb23536798bdf0000',
	//		'SimOld     => '97b12751243c4334ad22d6abf73f1400',
	//		'Im2        => '74EDC33644DF485B8B1C671A1F86099F',
	//		'Is2001     => '2e7a6475fadf4dc8886fea3595fdb6df',
	//		'Is2002     => '10cf40d14c7f11d18222444553540000',
	//		'Comm20012  => 'a0e93f374c7f11d18222444553540000',
	//		'StrIcq     => 'a0e93f374fe9d311bcd20004ac96dd96',
	//		'AimIcon    => '094613464c7f11d18222444553540000',
	//		'AimDirect  => '094613454c7f11d18222444553540000',
	//		'AimChat    => '748F2420628711D18222444553540000',
	//		'Uim        => 'A7E40A96B3A0479AB845C9E467C56B1F',
	//		'Rambler    => '7E11B778A3534926A80244735208C42A',
	//		'Abv        => '00E7E0DFA9D04Fe19162C8909A132A1B',
	//		'Netvigator => '4C6B90A33D2D480E89D62E4B2C10D99F',
	//		'tZers      => 'b2ec8f167c6f451bbd79dc58497888b9',
	//		'HtmlMsgs   => '0138ca7b769a491588f213fc00979ea8',
	//		'SimpLite   => '53494D5053494D5053494D5053494D50',
	//		'SimpPro    => '53494D505F50524F53494D505F50524F',
	'webicqpro' => '57656249637150726f00010405000062'
	);

	private $message_capabilities = array(
	'TLV2711'     => '094613494c7f11d18222444553540000',
	'REVERSE_REQ' => '094613444c7f11d18222444553540000',
	'OSCAR_FT'    => '094613434c7f11d18222444553540000',
	'OSCAR_FT'    => '094613434c7f11d18222444553540000',
	'MESSAGE'     => '00000000000000000000000000000000',
	'PLUGIN'      => ARRAY(
	'MESSAGE'       => 'be6b73050fc2104fa6de4db1e3564b0e',
	'STATUSMSGEXT'  => '811a18bc0e6c1847a5916f18dcc76f1a',
	'FILE'          => 'f02d12d93091d3118dd700104b06462e',
	'WEBURL'        => '371c5872e987d411a4c100d0b759b1d9',
	'CONTACTS'      => '2a0e7d467676d411bce60004ac961ea6',
	'GREETING_CARD' => '01e53b482ae4d111b679006097e1e294',
	'CHAT'          => 'bff720b2378ed411bd280004ac96d905',
	'SMS_MESSAGE'   => '0e28f60011e7d311bcf30004ac969dc2',
	'XTRAZ_SCRIPT'  => '3b60b3efd82a6c45a4e09c5a5e67e865'
	)
	);


	/**
	 * Set of - X Statuses.
	 * @thanks �_� ���� (http://intrigue.ru/forum/index.php?action=profile;u=190)
	 *
	 * @var array
	 */
	protected $x_statuses = array(
	"journal"             => '0072d9084ad143dd91996f026966026f',
	"angry"               => '01d8d7eeac3b492aa58dd3d877e66b92',
	"ppc"                 => '101117c9a3b040f981ac49e159fbd5d4',
	"cinema"              => '107a9a1812324da4b6cd0879db780f09',
	"phone"               => '1292e5501b644f66b206b29af378e48d',
	"browsing"            => '12d07e3ef885489e8e97a72a6551e58d',
	"mobile"              => '160c60bbdd4443f39140050f00e6c009',
	"wc"                  => '16f5b76fa9d240358cc5c084703c98fa',
	"coffee"              => '1b78ae31fa0b4d3893d1997eeeafb218',
	"sick"                => '1f7a4071bf3b4e60bc324c5787b04cf1',
	"picnic"              => '2ce0e4e57c6443709c3a7a1ce878a7dc',
	"smoking"            => '3fb0bd36af3b4a609eefcf190f6a5a7e',
	"thinking"             => '3fb0bd36af3b4a609eefcf190f6a5a7f',
	"business"            => '488e14898aca4a0882aa77ce7a165208',
	"duck"                => '5a581ea1e580430ca06f612298b7e4c7',
	"studying"            => '609d52f8a29a49a6b2a02524c5e9d260',
	"?"                   => '631436ff3f8a40d0a5cb7b66e051b364',
	"typing"              => '634f6bd8add24aa1aab9115bc26d05a1',
	"shopping"            => '63627337a03f49ff80e5f709cde0a4ee',
	"music"               => '61bee0dd8bdd475d8dee5f4baacf19a7',
	"zzz"                 => '6443c6af22604517b58cd7df8e290352',
	"fun"                 => '6f4930984f7c4affa27634a03bceaea7',
	"sleeping"            => '785e8c4840d34c65886f04cf3f3f43df',
	"tv"                  => '80537de2a4674a76b3546dfd075f5ec6',
	"tired"               => '83c9b78e77e74378b2c5fb6cfcc35bec',
	"beer"                => '8c50dbae81ed4786acca16cc3213c7b7',
	"surfing"             => 'a6ed557e6bf744d4a5d4d2e7d95ce81f',
	"pro7"                => 'b70867f538254327a1ffcf4cc1939797',
	"working"             => 'ba74db3e9e24434b87b62f6b8dfee50f',
	"love2"               => 'cd5643a2c94c4724b52cdc0124a1d0cd',
	"gaming"              => 'd4a611d08f014ec09223c5b6bec6ccf0',
	"google"              => 'd4e2b0ba334e4fa598d0117dbf4d3cc8',
	"love"                => 'ddcf0ea971954048a9c6413206d6f280',
	"party"               => 'e601e41c33734bd1bc06811d6c323d81',
	"sex"                 => 'e601e41c33734bd1bc06811d6c323d82',
	"meeting"             => 'f18ab52edc57491d99dc6444502457af',
	"eating"              => 'f8e8d7b282c4414290f810c6ce0a89a6',
	);

	protected $contact_list = array();
	protected $contact_list_groups = array('all_childs_ids' => array(0));

	protected $encodings = array(
		'ASCII' => 0x00,
		'UNICODE' => 0x02,
		'LATIN_1' => 0x03
	);

	private $rate_levels = array(
		0x01 => 'CHANGE',
		0x02 => 'WARNING',
		0x03 => 'LIMIT',
		0x04 => 'CLEAR'
	);

	/**
	 * Set default values
	 *
	 * @return WebIcqPro_SNAC
	 */
	protected function __construct()
	{
		$this->request_id = 0;
		$this->setMessageType();
		$this->setMessageCapabilities();
		$this->setUserAgent();
	}


	private function beautifyBinaryLog($data)
	{
		$return = chunk_split(chunk_split(strtoupper(bin2hex($data)), 2, ' '), 24, ' ');
		$return = str_split($return, 50);
		$search = array("\r", "\n", "\t");
		$replace = array(".", ".", ".");
		foreach ($return as $key => $line)
		{
			$return[$key] = str_pad($line, 50) . str_replace($search, $replace, substr($data, 0+16*$key, 16));
		}
		return  implode("\r\n", $return);
	}

	private function dump($str, $file = 'dump')
	{
		if ($this->debug && $file) {
			$f = fopen($file, 'a');
			fwrite($f, $str);
			fclose($f);
		}
		else if (!$file)
		{
			echo $str;
		}
	}

	public function log($msg, $data='', $tolog = false)
	{
		$msg .= "\n".trim($this->beautifyBinaryLog($data))."\n\n";
		if ($tolog)
		{
			$this->dump($msg, false);
		}
		else
		{
			$this->dump($msg);
		}
	}

	private function parseSnac($snac)
	{
		if (strlen($snac) > 10)
		{
			$return = unpack('ntype/nsubtype/nflag/Nrequest_id', $snac);
			$return['data'] = substr($snac, 10);
			return $return;
		}
		$this->error = 'Error: Broken SNAC can`t parse';
		return false;
	}

	protected function analizeSnac($snac)
	{
		$snac = $this->parseSnac($snac);
		if ($snac)
		{
			if (isset($this->snac_names[$snac['type']][$snac['subtype']]))
			{
				if (method_exists($this, $this->snac_names[$snac['type']][$snac['subtype']]))
				{
					$snac['callback'] = $this->snac_names[$snac['type']][$snac['subtype']];
				}
			}
		}
		return $snac;
	}

	/**
	 * Return SNAC header
	 *
	 * @param ineger $type
	 * @param integer $subtype
	 * @param integer $flag
	 * @return string binary
	 */
	private function __header($type, $subtype, $flag = 0)
	{
		return pack('nnnN', $type, $subtype, $flag, ++$this->request_id);
	}

	/**
	 * Pack ready CNAC
	 *
	 * @return string binary
	 */
	protected function ClientReady($args)
	{
		return $this->__header(0x01, 0x02).pack('n*',
		0x0022, 0x0001, 0x0110, 0x164F,
		0x0001, 0x0004, 0x0110, 0x164F,
		0x0013, 0x0004, 0x0110, 0x164F,
		0x0002, 0x0001, 0x0110, 0x164F,
		0x0003, 0x0001, 0x0110, 0x164F,
		0x0015, 0x0001, 0x0110, 0x164F,
		0x0004, 0x0001, 0x0110, 0x164F,
		0x0006, 0x0001, 0x0110, 0x164F,
		0x0009, 0x0001, 0x0110, 0x164F,
		0x000A, 0x0001, 0x0110, 0x164F,
		0x000B, 0x0001, 0x0110, 0x164F);
	}

	protected function ServerFamilies($data)
	{
		$families = unpack('n*', $data);
		foreach ($this->snac_names as $family => $value)
		{
			if (!in_array($family, $families))
			{
				unset($this->snac_names[$family]);
			}
		}
		return true;
	}

	protected function ClientRates($args)
	{
		return $this->__header(0x01, 0x06);
	}

	protected function ServerRates($data)
	{

		$classes = unpack('n', $data);
		$data = substr($data, 2);
		$i = 0;
		while ($i++ < $classes[1])
		{
			if (strlen($data)>=35)
			{
				$rate = unpack('nrate/Nwindow/Nclear/Nalert/Nlimit/Ndisconect/Ncurrent/Nmax/Ntime/cstate', $data);
				$this->rates[array_shift($rate)] = $rate;
				$data = substr($data, 35);
			}
			else
			{
				$this->error = 'Notice: Can`t get rates from server';
				return false;
			}
		}
		while (strlen($data) >= 4)
		{
			$group = unpack('nclass/nsize', $data);
			$data = substr($data, 4);
			if (strlen($data) >= 4*$group['size'])
			{
				$this->rates_groups[$group['class']] = unpack(substr(str_repeat('N/', $group['size']), 0, -1), $data);
				$data = substr($data, 4*$group['size']);
			}
			else
			{
				$this->error = 'Notice: Can`t get rates groups from server';
				return false;
			}
		}
		if (strlen($data))
		{
			$this->error = 'Notice: Can`t get rates/groups from server';
			return false;
		}
		$this->writeFlap('ClientRatesAck');
		return true;
	}

	protected function ClientRatesAck($args)
	{
		if (is_array($this->rates_groups) && count($this->rates_groups))
		{
			$snac = $this->__header(0x01, 0x08);
			foreach ($this->rates_groups as $group => $falilies)
			{
				$snac .= pack('n', $group);
			}
			return $snac;
		}
		$this->error = 'Error: Can`t create SNAC rates groups empty';
		return false;
	}

	protected function ServerRateLimit($data)
	{
		$level = unpack('n', $data);
		$data = substr($data, 2);
		if (strlen($data)>=37)
		{
			$rate = unpack('nlevel/nrate/Nwindow/Nclear/Nalert/Nlimit/Ndisconect/Ncurrent/Nmax/Ntime/cstate', $data);
			$this->rates[$rate['rate']] = $rate;
		}
		if (isset($this->rate_levels[$rate['level']]))
		{
			$rate['level'] = $this->rate_levels[$rate['level']];
		}
		return $this->createResponse('rate', $rate);
	}

	protected function ServerPause($data)
	{
		$this->writeFlap('ClientPause');
		return true;
	}

	protected function ClientPause($args)
	{
		return $this->__header(0x01, 0x0C);
	}

	protected function ServerResume($data)
	{
		return true;
	}

	protected function ClientRequestSelfInfo($args)
	{
		return $this->__header(0x01, 0x0E);
	}

	/**
	 * Requested online info response
	 *
	 * @todo implement this functionality
	 * @param string $data
	 * @return boolean
	 */
	protected function ServerResponseSelfInfo($data)
	{
		return true;
	}

	protected function ClientIdleTime($args)
	{
		return $this->__header(0x01, 0x11).pack('N', 1);
	}

	protected function ServerMigration($data)
	{
		$tlv = $this->splitTLVsToArray($data);
		if (isset($tlv[0x05]) && isset($tlv[0x06]))
		{
			return $this->reconect(array($tlv[0x05], $tlv[0x06]));
		}
		$this->error = 'Error: can`t parse server answer';
		if (isset($tlv[0x08]))
		{
			$error_no = unpack('n',$tlv[0x08]);
			if ($error_no && isset($this->login_errors[$error_no[1]])) {
				$this->error = $this->login_errors[$error_no[1]];
			}
		}
		return false;
	}

	protected function ClientFamiliesVersions($args)
	{
		$snac = $this->__header(0x01, 0x17);
		foreach ($this->snac_names as $fname => $family)
		{
			$snac .= pack('n2', $fname, $family['version']);
		}
		return $snac;
	}

	protected function ServerFamiliesVersions($data)
	{
		$families = unpack('n*', $data);
		while (count($families)>1)
		{
			$falily = array_shift($families);
			$version = array_shift($families);
			$versions[$falily] = $version;
		}

		foreach ($this->snac_names as $fname => $family)
		{
			if (!isset($versions[$fname])) // todo: need to do something with families lower versions // || $versions[$fname] > $family['version']
			{
				unset($this->snac_names[$fname]);
			}
		}
		return true;
	}

	protected function ClientStatus($args)
	{
		extract($args);
		$snac = $this->__header(0x01, 0x1E);
		if (isset($status) && isset($substatus)) {
			$snac .= $this->packTLV(0x06, ($this->substatuses[$substatus]<<16) + $this->statuses[$status], 'N');
			$snac .= $this->packTLV(0x0C, pack('NNcnNNNNNNn', 0x00, 0x00, 0x06, $this->protocol_version, 0x00, 0x00, 0x03, 0x00, 0x00, 0x00, 0x00));
		}
//		$message = $this->packLV($this->status_message).pack('n', 0x00);
//		$snac .= $this->packTLV(0x1D, pack('ncc', 0x02, 0x04, strlen($message)).$message);
		return $snac;
	}

	protected function ClientBart($args = array())
	{
		extract($args);
		$snac = $this->__header(0x01, 0x21);
		$message = $this->packLV($this->xstatus_message).pack('n', 0x00);
		$snac .= $this->packTLV(0x1D, pack('ncc', 0x02, 0x04, strlen($message)).$message);
		return $snac;
	}


	protected function ClientLocationRights($args)
	{
		return $this->__header(0x02, 0x02);
	}

	/**
	 * @todo
	 */
	protected function ServerLocationRights($data)
	{
		return true;
	}

	protected function ClientLocationInfo($args)
	{
		return $this->__header(0x02, 0x04).
		$this->packTLV(0x05, implode($this->capabilities).$this->user_agent_capability[$this->agent], 'H*');
	}

	protected function OscarError($data)
	{
		$error = unpack('ncode', $data);
		if (isset($this->oscar_errors[$error['code']])) {
			$error['error'] = $this->oscar_errors[$error['code']];
		}
		return $this->createResponse("error", $error);
	}

	protected function ClientBuddylistRights($args)
	{
		return $this->__header(0x03, 0x02);
	}

	/**
	 * @todo
	 */
	protected function ServerBuddylistRights($data)
	{
		return true;
	}

	/**
	 * Add contact to list
	 *
	 * @deprecated
	 * @param array $args
	 * @return string binary
	 */
	protected function ClientBuddyListAdd($args)
	{
		$uins = array();
		extract($args);
		$data = '';
		if(is_array($uins)) {
			foreach ($uins as $id) {
				$data .= pack('c', strlen($id)).$id;
				$this->contact_list[$id] = array();
			}
		}
		else {
			$data .= pack('c', strlen($uins)).$uins;
			$this->contact_list[$uins] = array();
		}
		return $this->__header(0x03, 0x04).$data;
	}

	/**
	 * Delete contact from list
	 *
	 * @deprecated
	 * @param array $args
	 * @return string binary
	 */
	protected function ClientBuddyListDelete($args)
	{
		$uins = array();
		extract($args);
		$data = '';
		if(is_array($uins)) {
			foreach ($uins as $uin) {
				$data .= pack('c', strlen($uin)).$uin;
				unset($this->contact_list[$uin]);
			}
		}
		else {
			$data .= pack('c', strlen($uins)).$uins;
			unset($this->contact_list[$uins]);
		}
		return $this->__header(0x03, 0x05).$data;
	}

	protected function ServerUserOnline($data)
	{
		$response = $this->createResponse('useronline');
		$info = unpack('clength', $data);
		$uin = substr($data, 1, $info['length']);
		$response['uin'] = $uin;
		$data = substr($data, ($info['length']+1));
		if (!isset($this->contact_list[$uin]))
		{
			return false;
			// We dont need it!!!
			//$this->contact_list[$uin] = array();
		}
		else
		{
			if(isset($this->contact_list[$uin]['status']))
			{
				$response['old_status'] = $this->contact_list[$uin]['status'];
			}
			unset($this->contact_list[$uin]['ip']);
			unset($this->contact_list[$uin]['online_time']);
			unset($this->contact_list[$uin]['signon_time']);
			unset($this->contact_list[$uin]['member_since']);
			unset($this->contact_list[$uin]['substatus']);
			unset($this->contact_list[$uin]['status']);
		}
		$info = unpack('nwarning_level/nsize', $data);
		$data = substr($data, 4);
		for ($i = 0; $i < $info['size']; $i++)
		{
			$tlv = $this->unpackTLV($data);
			switch ($tlv['type']) {
				case 0x000A:
					$this->contact_list[$uin]['ip'] = long2ip($tlv['data']);
					break;
				case 0x000F:
					$this->contact_list[$uin]['online_time'] = $tlv['data'];
					break;
				case 0x0003:
					$this->contact_list[$uin]['signon_time'] = $tlv['data'];
					break;
				case 0x0005:
					$this->contact_list[$uin]['member_since'] = $tlv['data'];
					break;
				case 0x0006:
					$status = unpack('nsubstatus/nstatus', $tlv['data']);
					$this->contact_list[$uin]['substatus'] = array_search($status['substatus'], $this->substatuses);
					$this->contact_list[$uin]['status'] = array_search($status['status'], $this->statuses);
					$response['status'] = $this->contact_list[$uin]['status'];
					break;
				case 0x0001: // todo: user class
				case 0x000C: // todo: dc info
				case 0x000D: // todo: user capabilities
					$this->contact_list[$uin]['caps'] = array();
					$this->contact_list[$uin]['xstatus'] = false;
					$caps = str_split($tlv['data'], 0x10);
					foreach($caps as $cap) {
						$cap = unpack('H*', $cap);
						$cap = $cap[1];
						$this->contact_list[$uin]['caps'][] = $cap;
						if (!$this->contact_list[$uin]['xstatus']) {
							$this->contact_list[$uin]['xstatus'] = array_search($cap, $this->x_statuses);
						}
					}
					if (!$this->contact_list[$uin]['xstatus']) {
						unset($this->contact_list[$uin]['xstatus']);
					}
				case 0x0011: // todo: time updated
				case 0x0019: // todo: new style capabilities
				case 0x001D: // todo: user icon id & hash
				break;
			}
		}
		return $response;
	}

	protected function ServerUserOffline($data)
	{
		return $this->ServerUserOnline($data);
	}

	protected function ClientIBCMRights()
	{
		return $this->__header(0x04, 0x04);
	}

	/**
	 * @todo quick
	 */
	protected function ServerIBCMRights($data)
	{
		$this->writeFlap('ClientSetIBCMParams', array());
		return true;
	}

	protected function ClientSetIBCMParams($args)
	{
	  extract($args);
	  if(!isset($channel)) $channel = 0x00;
	  return $this->__header(0x04, 0x02).pack('nNnnnN', $channel, 0x03, 0x1f40, 0x03e7, 0X03e7, 0x00);
  }

	/**
	 * Pack message to SNAC
	 *
	 * @param string $uin
	 * @param string $message
	 * @return string binary
	 */
	protected function ClientIBCM($args)
	{
		$uin = $message = '';
		extract($args);
		$uin_size     = strlen($uin);
		$message_size = strlen($message);

		$cookie = microtime(true);
		$snack = $this->__header(0x04, 0x06).pack('dnca*', $cookie, $this->ibcm_type, $uin_size, $uin);

		switch ($this->ibcm_type)
		{
			case 0x01:
				$tlv_data = pack('c2nc3n3a*', 0x05, 0x01, 0x01, 0x01, 0x01, 0x01, ($message_size+4), $this->ibcm_encoding, 0x00, $message);
				$snack .= $this->packTLV(0x02, $tlv_data).$this->packTLV(0x03).$this->packTLV(0x06);
				break;
			case 0x02:
				$tlv_data = pack('ndH*n5n2v2d2nVn3dn3cvnva*H*', 0x00, $cookie, $this->ibcm_capabilities, 0x0A, 0x02, 0x01, 0x0F, 0x00, 0x2711, ($message_size+62), 0x1B, $this->protocol_version, 0x00, 0x00, 0x00, 0x03, $this->request_id, 0x0E, $this->request_id, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x01, ($message_size+1), $message, '0000000000FFFFFF00');
				$snack .= $this->packTLV(0x05, $tlv_data).$this->packTLV(0x03);
				break;
			default:
				$this->error = 'Warning: Snac ClientIBCM unknown ibcm_type';
				$snack = false;
				break;
		}

		return array('return' => $cookie, 'data' => $snack);
	}

	/**
	 * Unpack message from server
	 *
	 * @param string $data
	 * @return array
	 */
	protected function ServerIBCM($data)
	{
		if (strlen($data))
		{
			$msg = unpack('dcookie/nchannel/cnamesize', $data);
			$data = substr($data, 11);

			$return['from'] = substr($data, 0, $msg['namesize']);
			$return['channel'] = $msg['channel'];
			$return['cookie'] = $msg['cookie'];

			$data = substr($data, $msg['namesize']);
			$msg = unpack('nwarnlevel/nTLVnumber', $data);

			$data = substr($data, 4);
			$tlvs = $this->splitTLVsToArray($data);
			foreach ($tlvs as $type => $data)
			{
				switch ($type)
				{
					case 2:
						if ($return['channel'] == 1)
						{
							$subtlvs = $this->splitTLVsToArray($data, true);
							if (isset($subtlvs[0x0101]))
							{
								$return['encoding'] = unpack('nnumset/nsubset', substr($subtlvs[0x0101], 0, 4));
								$return['encoding']['numset'] = in_array($return['encoding']['numset'], $this->encodings) ? array_search($return['encoding']['numset'], $this->encodings): $return['encoding']['numset'];
								$return['message'] = substr($subtlvs[0x0101], 4);
								$return = $this->createResponse('message', $return);
							}
						}
						break;
					case 5:
						if ($return['channel'] == 2)
						{
							if (strlen($data) < 26 )
							{
								// empty message
								return false;
							}
							$msg = unpack('ntype/dcookie', $data);
							$return['type']   = $msg['type'];
							$return['cookie'] = $msg['cookie'];
							$return['capability'] = bin2hex(substr($data, 10, 16));
							$data = substr($data, 26);

							if ($return['capability'] != $this->message_capabilities['TLV2711'])
							{
								// todo: handel other
								echo "Not supported message capability:".$return['capability']."\r\n";
								$this->log('>>', $data, true);
								return $return;
							}

							$subtlvs = $this->splitTLVsToArray($data);

							foreach ($subtlvs as $type => $data)
							{
								switch ($type)
								{
									case 0x00004:
										$return['external_ip'] = long2ip($data);
									case 0x00005:
										$return['listening_port'] = $data;
										break;
									case 0x2711:
										if (strlen($data) < 33)
										{
											// empty message
											echo "Empty?\r\n";
											$this->log('>>', $data, true);
											return $return;
										}
										$meta = unpack('vuId/vversion', substr($data, 0, 4));
										$return['version'] = $meta['version'];
										$return['capability2'] = bin2hex(substr($data, 4, 16));
										$meta = unpack('vuId/vcookie2', substr($data, 29, 4));
										$return['cookie2'] = $meta['cookie2'];
										$data = substr($data, 33);

										switch ($return['capability2']) {
											// MESSAGE
											case $this->message_capabilities['MESSAGE']:
												if (strlen($data) < 20)
												{
													echo "Empty message?\r\n";
													$this->log('>>', $data, true);
													return $return;
												}
												//empty zerous
												$meta = unpack('Ctype/Cflag/vstatus/vpriority/vsize', substr($data, 12, 8));
												$data = substr($data, 20);
												switch ($meta['type'])
												{
													// Chat request message
													case 0x02:
														$return['debugmsg'] = 'deprecated?';
														$return = $this->createResponse('chatrequest', $return);
														break;
														// File request / file ok message
													case 0x03:
														if ($return['type'] == 0 || $return['type'] = 1)
														{
															$return = $this->createResponse('filerequest', $return);
														}
														else if ($return['type'] == 2)
														{
															$return = $this->createResponse('fileresponse', $return);
														}
														else
														{
															//strange file message
															$return['debugmsg'] = 'strange file message';
															return $return;
														}
														break;
														// Plugin message described by text string
													case 0x1A:
														return $this->parseServerGreeting($return, $meta, $data);
														break;
													default:
														return $this->parseMessageTypes($return, $meta, $data);
														break;
												}
												break;

										}
										break;
								}
							}
						}
						break;
					case 6:
						$status = unpack('nstatus/nsubstatus', $data);
						$return['status']     = $status['status'];
						$return['substatus']  = $status['substatus'];
						break;
				}
			}
			$this->writeFlap('ClientIBCMAck', $return);
			return $return;
		}
		return false;
	}

	private function parseMessageTypes($return, $meta, $data)
	{
		if ($return['type'] == 2)
		{
			//todo: hendle message ack
			return $return;
		}

		//todo: process 0xFE formatted

		switch ($meta['type']) {
			// Plain text (simple) message
			case 0x01:
				//todo: DC special check
				$meta['rtf'] = false;
				if (strlen($data) > $meta['size']+ 36)
				{
					//rtf message
					if (strpos($data, '{97B12751-243C-4334-AD22-D6ABF73F1492}') !== false)
					{
						$meta['rtf'] = true;
					}
					//utf-8 message
					if (strpos($data, '{0946134E-4C7F-11D1-8222-444553540000}') !== false)
					{
						$return['encoding'] = array('numset' => 'UTF-8', 'subset' => 0);
					}
				}
				$return['message'] = substr($data, 0, $meta['size']);

				if ($meta['rtf'])
				{
					$return['rtf'] = $return['message'];
					$return['message'] = RTF::Text($return['message']);
				}
				$return = $this->createResponse('message', $return);
				break;
				// URL message (0xFE formatted)
			case 0x04:
				#$this->log('URL', $data, true);
				$return['message'] = substr($data, 0, $meta['size']);
				$return = $this->createResponse('urlmessage', $return);
				break;
				// Authorization request message (0xFE formatted)
			case 0x06:
				// Authorization denied message (0xFE formatted)
			case 0x07:
				// Authorization given message (empty)
			case 0x08:
				// Message from OSCAR server (0xFE formatted)
			case 0x09:
				// Web pager message (0xFE formatted)
			case 0x0D:
				// Email express message (0xFE formatted)
			case 0x0E:
				// Contact list message (0xFE formatted)
			case 0x13:
				$this->log($meta['type'].'>>', $data, true);
				//$return['message'] = substr($data, 0, $meta['size']);
				$return = array_merge($return, $meta);
				break;
				// "You-were-added" message (0xFE formatted)
			case 0x0C:
				$return = $this->createResponse('youadded', $return);
				break;
				// Auto away message
			case 0xE8:
				$return['message'] = substr($data, 0, $meta['size']);
				$return = $this->createResponse('autoaway', $return);
				break;
				// Auto occupied message
			case 0xE9:
				$return['message'] = substr($data, 0, $meta['size']);
				$return = $this->createResponse('autooccupied', $return);
				break;
				// Auto not available message
			case 0xEA:
				$return['message'] = substr($data, 0, $meta['size']);
				$return = $this->createResponse('autona', $return);
				break;
				// Auto do not disturb message
			case 0xEB:
				$return['message'] = substr($data, 0, $meta['size']);
				$return = $this->createResponse('autodnd', $return);
				break;
				// Auto free for chat message
			case 0xEC:
				$return['message'] = substr($data, 0, $meta['size']);
				$return = $this->createResponse('autofreeforchat', $return);
				break;
		}
		return $return;
	}

	private function parseServerGreeting($return, $meta, $data)
	{
		if (strlen($data) < 24)
		{
			$return['debugmsg'] = 'to short to indificate plugin type';
			$this->log('SG', $data, true);
			return $return;
		}

		//		$plugin = unpack()
		//
		//
		//		$return['capability'] = bin2hex(substr($data, 10, 16));
		return false;
	}

	protected function ClientIBCMAck($args)
	{
		if (is_array($args))
		{
			$from = '';
			extract($args);
			$uin_size = strlen($from);
			$channel  = isset($channel) ? $channel : 0x00;
			$cookie   = isset($cookie) ? $cookie : 0x00;
			$reason   = isset($reason) ? $reason : (in_array($channel, array(0x01, 0x02)) ? 0x03 : 0x01);
			$message  = (isset($message) &&  $message != '') ? '' : $this->status_message;

			$snack = $this->__header(0x04, 0x0B).pack('dnca*n', $cookie, $channel, $uin_size, $from, $reason);

			switch ($channel)
			{
				case 0x02:
					$data = pack('vH*nH*cn', $this->protocol_version, '00000000000000000000000000000000', 0x00, $this->capability_flag, 0x00, 0x07);
					$message = pack('nH*va*H*', 0x07, '000000000000000000000000E80300000000', strlen($message)+1, $message, '0000000000FFFFFF00');
					$data .= pack('v', strlen($message)).$message;
					$snack .= pack('v', 0x1B).$data;
					break;
				default:
					$message_size = strlen($message);
					$snack .= pack('c2nc3n3a*', 0x05, 0x01, 0x01, 0x01, 0x01, 0x01, ($message_size+4), 0x03, 0x00, $message);
					break;
			}

			return $snack;
		}
		else
		{
			// todo: get answer from server.
		}
	}

	protected function ServerIBCMAck($data)
	{
		if (strlen($data) > 11) {
			$msg = unpack('did/nchannel/cuin', $data);
			$msg['uin'] = substr($data, 11, $msg['uin']);
			return $this->createResponse('accepted', $msg);
		}
		$this->error = 'ServerIBCMAck: too short';
		return false;
	}


	protected function ClientPrivicyRights()
	{
		return $this->__header(0x09, 0x02);
	}

	/**
	 * @todo
	 */
	protected function ServerPrivicyRights($data)
	{
		return true;
	}

	protected function ServerSSIError($data)
	{
		$response = unpack('ncode', substr($data, 0, 2));
		if (isset($this->oscar_errors[$response['code']])) {
			$response['error'] = $this->oscar_errors[$response['code']];
		}
		return $this->createResponse('error', $response);
	}

	protected function ClientSSIRights($args)
	{
		return $this->__header(0x13, 0x02);
	}

	/**
	 * @todo
	 */
	protected function ServerSSIRights($data)
	{
		return true;
	}

	protected function ClientSSI($args)
	{
		return $this->__header(0x13, 0x04);
	}

	protected function ServerSSI($data, $flag = 0)
	{
		$coockie = substr($data, 0, 8);
		$data = substr($data, 8);
		$header = unpack('cversion/nlength', $data);
		$data = substr($data, 3);
		for ($i=0; $i < $header['length']; $i++) {
			if(strlen($data) >= 10) {
				$itemname = $this->unpackLV($data);
				$props = unpack('ngroupID/nitemID/ntype/nsize', $data);
				$data = substr($data, 8);
				if(strlen($data) >= $props['size']) {
					$props['tlvs'] = array();
					if($props['size'] > 0) {
						$props['tlvs'] = $this->splitTLVsToArray(substr($data, 0, $props['size']));
						$data = substr($data, $props['size']);
					}
					switch ($props['type']) {
						case 0x0000: //  	  Buddy record (name: uin for ICQ and screenname for AIM)
						$this->contact_list[$itemname] = $this->convertContact($props);
						break;
						case 0x0001: //  	  Group record
						$this->contact_list_groups[$itemname] = $this->convertGroup($props);
						$this->contact_list_groups['all_childs_ids'] = array_merge($this->contact_list_groups['all_childs_ids'], $this->contact_list_groups[$itemname]['childs']);
						case 0x0002: // 	  Permit record ("Allow" list in AIM, and "Visible" list in ICQ)
						case 0x0003: // 	  Deny record ("Block" list in AIM, and "Invisible" list in ICQ)
						case 0x0004: // 	  Permit/deny settings or/and bitmask of the AIM classes
						case 0x0005: // 	  Presence info (if others can see your idle status, etc)
						case 0x0009: // 	  Unknown. ICQ2k shortcut bar items ?
						case 0x000E: // 	  Ignore list record.
						case 0x000F: // 	  Last update date (name: "LastUpdateDate").
						case 0x0010: // 	  Non-ICQ contact (to send SMS). Name: 1#EXT, 2#EXT, etc
						case 0x0013: // 	  Item that contain roster import time (name: "Import time")
						case 0x0014: // 	  Own icon (avatar) info. Name is an avatar id number as text
						break;
					}
				}
				else {
					$this->error .= 'SSI extra data parsing error item #'.($i+1).' Name: '.$itemname."\r\n";
				}
			}
			else {
				$this->error .= 'SSI parsing error item #'.($i+1)."\r\n";
				return false;
			}
		}
		if ($flag == 0 || $flag == 32768) {
			$this->writeFlap('ClientSSIActivate');
			return $this->createResponse("contactlist", array('groups'=> $this->contact_list_groups, 'users'=>$this->contact_list));
		}
		return true;
	}

	private function convertContact($item)
	{
		$contact = array();
		if(is_array($item['tlvs']))
		{
			foreach ($item['tlvs'] as $key => $value) {
				switch ($key) {
					case 0x0066:
						$contact['authorization'] = $value;
						break;
					case 0x0131:
						$contact['name'] = $value;
						break;
					case 0x0137:
						$contact['mail'] = $value;
						break;
					case 0x013A:
						$contact['sms'] = $value;
						break;
					case 0x013C:
						$contact['comment'] = $value;
						break;
					case 0x0145:
						$contact['time'] = $value;
						break;
					default:
						$contact[$key] = $value;
						break;
				}
			}
		}
		$contact['id'] = $item['itemID'];
		$contact['group'] = $item['groupID'];
		return $contact;
	}

	private function convertGroup($item)
	{
		$group = array('childs' => array());
		if(is_array($item['tlvs']))
		{
			foreach ($item['tlvs'] as $key => $value) {
				switch ($key) {
					case 0x00C8:
						while (strlen($value) > 1)
						{
							$id = unpack('n', substr($value, 0, 2));
							$group['childs'][] = $id[1];
							$value = substr($value, 2);
						}
						break;
				}
			}
		}
		$group['id'] = $item['itemID'];
		$group['group'] = $item['groupID'];
		return $group;
	}

	/**
	 * Check SSI state (time / number of items)
	 *
	 * @todo implement this functionality
	 * @param string $data
	 * @return boolean
	 */
	protected function ClientSSICheckout($args)
	{
		extract($args);
		if(!isset($time))
		{
			$time = '47877097';
		}
		return $this->__header(0x13, 0x05).pack('Nn', $time, 0x00);
	}

	protected function ClientSSIActivate($args)
	{
		return $this->__header(0x13, 0x07);
	}

	protected function ClientSSIAdd($args)
	{
		if (is_array($args))
		{
			$group = '';
			extract($args);
			$uins = isset($uins) ? $uins : array();
			$data = '';
			foreach ($uins as $uin => $name) {
				if(!isset($this->contact_list[$uin]))
				{
					$data .= $this->packContact($uin, $name, array('id'=>$this->reservItemId($group), 'group' => $this->getGroupId($group)), true);
				}
			}
			if (isset($group) && isset($parent))
			{
				$data .= $this->packGroup($group, $parent);
			}
			if (strlen($data) > 0) {
				return $this->__header(0x13, 0x08).$data;
			}
			$this->log('add', $data, true);
			$this->error = "SSIAdd error: Nothing to add.";
			return false;
		}
		// todo: parse server SSI add
		return true;
	}

	private function reservItemId($group)
	{
		if (isset($this->contact_list_groups[$group]))
		{
			$id = 0;
			while ($id++ < 65535)
			{
				if (!in_array($id, $this->contact_list_groups['all_childs_ids']))
				{
					$this->contact_list_groups['all_childs_ids'][] = $id;
					return $id;
				}
			}
			return $id;
		}
	}

	private function getGroupId($name)
	{
		if (isset($this->contact_list_groups[$name]))
		{
			return $this->contact_list_groups[$name]['group'];
		}
		$this->error = 'getGroupId: No such group '.$name;
		return false;
	}

	protected function ClientSSIDelete($args)
	{
		if (is_array($args))
		{
			extract($args);
			$uins = isset($uins) ? $uins : array();
			$data = '';
			foreach ($uins as $uin) {
				if(isset($this->contact_list[$uin]))
				{
					$data .= $this->packContact($uin, false, $this->contact_list[$uin]);
				}
			}
			if (isset($group) && isset($parent))
			{
				$data .= $this->packGroup($group, $parent);
			}
			if (strlen($data) > 0) {
				return $this->__header(0x13, 0x0A).$data;
			}
			$this->log('delete', $data, true);
			$this->error = "SSIDelete error: Nothing to delete.";
			return false;
		}
		return false;
		// todo: parse server SSI delete
	}

	private function packContact($uin, $name = false, $contact = array(), $authorize = false)
	{
		//todo: groups
		$tlv = $name ? $this->packTLV(0x0131, $name) : '';
		if ($authorize) {
			$tlv .= $this->packTLV(0x66);
		}
		$group = isset($contact['group']) ? $contact['group'] : 0;
		$id    = isset($contact['id']) ? $contact['id'] : 0;
		return $this->packLV($uin).pack('nnn', $group, $id, 0x00).$this->packLV($tlv);
	}

	private function packGroup($name, $parent = "")
	{
		$group = $this->getGroupId($parent);
		$id    = $this->getGroupId($name) ? $this->getGroupId($name) : $this->reservItemId($parent);
		return $this->packLV($name).pack('nnnn', $id, $group, 0x01, 0x00);
	}

	/**
	 * @todo
	 */
	protected function ServerSSIModificationDate($data)
	{
		return true;
	}

	/**
	 * @todo errors to is
	 */
	protected function ServerSSIAck($data)
	{
		$errors = array('errors' => array());
		while (strlen($data) > 1) {
			$error = unpack('ncode', $data);
			if (isset($this->oscar_buddy_errors[$error['code']])) {
				$error['error'] = $this->oscar_buddy_errors[$error['code']];
			}
			$data = substr($data, 2);
			$errors['errors'][] = $error;
		}
		return $errors;
	}

	protected function ClientSSIEditStart()
	{
		return $this->__header(0x13, 0x11);
	}

	protected function ClientSSIEditEnd()
	{
		$this->contact_list = array();
		$this->contact_list_groups = array('all_childs_ids' => array(0));
		$this->writeFlap('ClientSSICheckout');
		return $this->__header(0x13, 0x12);
	}

	protected function ClientSSIAuthRequest($args)
	{
		$uin = '';
		extract($args);
		$reason = isset($reason) ? $reason : "";
		return $this->__header(0x13, 0x18).$this->packLV($uin, 'c').$this->packLV($reason).pack('n', 0x0);
	}

	protected function ServerSSIAuthRequest($data)
	{
		$response = $this->createResponse('authrequest');
		$data = substr($data, 8);
		$response['from'] = $this->unpackLV($data, 'c');
		$response['reason'] = $this->unpackLV($data);
		return $response;
	}

	protected function ClientSSIAuthResponse($args)
	{
		$uin = '';
		extract($args);
		$reason = isset($reason) ? $reason : "";
		$allow  = isset($allow) ? $allow : false;
		return $this->__header(0x13, 0x1a).$this->packLV($uin, 'c').pack('c', $allow).$this->packLV($reason);
	}

	protected function ServerSSIAuthResponse($data)
	{
		$response = $this->createResponse('authresponse');
		$response['from'] = $this->unpackLV($data, 'c');
		$granted = unpack('c', substr($data, 0, 1));
		$response['granted'] = $granted[1];
		$data = substr($data, 1);
		$response['message'] = $this->unpackLV($data);
		return $response;
	}

	protected function ServerSSIYouAdded($data)
	{
		$response = $this->createResponse('youadded');
		$this->unpackTLV($data);
		$data = substr($data, 1);
		$response['from'] = $this->unpackLV($data, 'c');
		return $response;
	}

	private function createResponse($type = "message", $response = array())
	{
		$response['type'] = $type;
		return $response;
	}

	protected function ClientMetaData($args)
	{
		static $sequence = 1;
		$type = $uin = $uinsearch = '';
		extract($args);
		$ret = $this->__header(0x15, 0x02);
		switch ($type)
		{
			case 'offline': // 003C
			$ret .= $this->packTLV(0x01, pack('vVvv', 0x08, $uin, 0x3C, $sequence));
			break;
			case 'delete_offline': // 003E
			$ret .= $this->packTLV(0x01, pack('vVvv', 0x08, $uin, 0x3E, $sequence));
			break;
			default: // todo: 07D0
			$pack = pack('VvvvV', $uin, 0x07D0, $sequence, 0x04BA, $uinsearch); //CLI_SHORTINFO_REQUEST
			$ret .= $this->packTLV(0x01, pack('v', strlen($pack)).$pack);
			break;
		}
		return array('return' => $sequence++, 'data' => $ret);
	}

	protected function ServerMetaData($data)
	{
		if (strlen($data))
		{
			$data = substr($data, 4);

			if (strlen($data) > 0)
			{
				$msg = unpack('vsize/Vmyuin/vtype', $data);
				$msg['data'] = substr($data, 10);
				switch ($msg['type'])
				{
					case 0x41: // Offline message
					$msg = unpack('Vfrom/vyear/Cmonth/Cday/Chour/Cminute/Cmsgtype/Cflag/nlength/a*message', $msg['data']);
					$this->createResponse("offlinemessage", $msg);
					break;
					case 0x42: // End of offline messages
					$this->writeFlap('ClientMetaData', array('uin' => $msg['myuin'], 'type' => 'delete_offline'));
					$msg = true;
					break;
					case 0x07DA: // SRV_META_INFO_REPLY
					$msg = array_merge($msg, unpack('vid', substr($data, 8, 10)));
					$msg1 = unpack('vsubtype', $msg['data']);
					$msg['data'] = substr($msg['data'], 2);
					switch ($msg1['subtype']) {
						case 0x0104: //Short info
						$msg1 = unpack('csuccess', $msg['data']);
						$msg['data'] = substr($msg['data'], 1);
						$msg['type'] = 'shortinfo';
						if($msg1['success'] == 0x0A)
						{
							$size = unpack('v', $msg['data']);
							$msg['nick'] = substr($msg['data'], 2, $size[1]-1);
							$msg['data'] = substr($msg['data'], $size[1]+2);
							$size = unpack('v', $msg['data']);
							$msg['firstname'] = substr($msg['data'], 2, $size[1]-1);
							$msg['data'] = substr($msg['data'], $size[1]+2);
							$size = unpack('v', $msg['data']);
							$msg['lastname'] = substr($msg['data'], 2, $size[1]-1);
							$msg['data'] = substr($msg['data'], $size[1]+2);
							$size = unpack('v', $msg['data']);
							$msg['email'] = substr($msg['data'], 2, $size[1]-1);
							$msg['data'] = substr($msg['data'], $size[1]+2);
							#$this->log("Short info data:", $msg['data']);
							$msg1 = unpack('cauthorization/cunknown/cgender', $msg['data']);
							unset($msg['data']);
							$msg = array_merge($msg, $msg1);
							$this->createResponse("shortinfo", $msg);
						}
						break;
						default:
							return false;
							break;
					}
					break;
					default:
						return false;
						break;
				}
			}
			return $msg;
		}
		return false;
	}

	protected function ClientMd5Request($args)
	{
		$uin = '';
		extract($args);
		return $this->__header(0x17, 0x06).$this->packTLV(0x01, $uin);//.$this->packTLV(0x4B).$this->packTLV(0x5A);

	}

	protected function ServerMd5Response($data)
	{
		return substr($data, 2);
	}

	protected function ClientMd5Login($args)
	{
		$authkey = $uin = '';
		extract($args);
		$password = pack('H*', md5($password));
		$password = pack('H*', md5($authkey.$password.'AOL Instant Messenger (SM)'));
		return $this->__header(0x17, 0x02).
				$this->packTLV(0x01, $uin).
				$this->packTLV(0x25, $password).
				$this->packTLV(0x4C, '').
				$this->packTLV(0x03, 'ICQ Client').
				$this->packTLV(0x17, 0x0006, 'n').
				$this->packTLV(0x18, 0x00, 'n').
				$this->packTLV(0x19, 0x00, 'n').
				$this->packTLV(0x1A, 0x1B67, 'n').
				$this->packTLV(0x14, 0x00007535, 'N').
				$this->packTLV(0x0F, 'ru').
				$this->packTLV(0x0E, 'ru');//.
				//$this->packTLV(0x94, 0x00, 'c');
	}

	protected function ServerMd5LoginReply($data)
	{
		$tlv = $this->splitTLVsToArray($data);
		if (isset($tlv[0x05]) && isset($tlv[0x06]))
		{
			return array($tlv[0x05], $tlv[0x06]);
		}
		$this->error = 'Error: can`t parse server answer';
		if (isset($tlv[0x08]))
		{
			$error_no = unpack('n',$tlv[0x08]);
			if ($error_no && isset($this->login_errors[$error_no[1]])) {
				$this->error = $this->login_errors[$error_no[1]];
			}
		}
		return false;
	}

	/**
	 * Set message capability
	 *
	 * @param string $value
	 * @return boolean
	 */
	protected function setMessageCapabilities($value = 'utf-8')
	{
		switch (strtolower($value))
		{
			case 'rtf':
				$this->ibcm_capabilities = '97B12751243C4334AD22D6ABF73F1492';
				break;
			case 'utf-8':
				$this->ibcm_capabilities = '094613494C7F11D18222444553540000';
				break;
			default:
				$this->error = 'Warning: MessageCapabilities: "'.$value.'" unknown';
				return false;
		}
		return true;
	}

	protected function setUserAgent($value = 'webicqpro')
	{
		$value = strtolower($value);
		if (isset($this->user_agent_capability[$value]))
		{
			$this->agent = $value;
			return true;
		}
		$this->error = 'Warning: UserAgent: "'.$value.'" is not valid user agent or has unknown capability';
		return false;
	}

	/**
	 * Set message type
	 *
	 * @param string $value
	 * @return boolean
	 */
	protected function setMessageType($value = 'plain_text')
	{
		switch (strtolower($value))
		{
			case 'plain_text':
				$this->ibcm_type = 0x01;
				break;
			case 'rtf':
				$this->ibcm_type = 0x02;
				break;
			case 'old_style':
				$this->ibcm_type = 0x04;
				break;
			default:
				$this->error = 'Warning: MessageType: "'.$value.'" unknown';
				return false;
		}
		return true;
	}

	protected function setEncoding($value = 'LATIN_1')
	{
		if(isset($this->encodings[$value])) {
			$this->ibcm_encoding = $this->encodings[$value];
			return true;
		}
		$this->error = 'Warning: Encoding not supported';
		return false;
	}
}

/**
 * Layer for FLAP data format
 * @access private
 */
class WebIcqPro_FLAP extends WebIcqPro_SNAC{

	protected $channel;
	private $sequence;
	private $body;
	private $info = array();

	protected function __construct()
	{
		parent::__construct();
		$this->sequence = rand(0x0000, 0x8000);
	}

	private function getSequence()
	{
		if (++$this->sequence > 0x8000)
		{
			$this->sequence = 0x00;
		}
		return $this->sequence;
	}

	protected function packFlap($body)
	{
		return pack('ccnn', 0x2A, $this->channel, $this->getSequence(), strlen($body)).$body;
	}

	protected function helloFlap($flap = false, $extra = '')
	{
		if ($flap)
		{
			if (isset($flap['data']) && strlen($flap['data']) == 4)
			{
				return unpack('N', $flap['data']);
			}
		}
		else
		{
			return $this->packFlap(pack('N', 0x01).$extra);
		}
		return false;
	}
}

/**
 * Class for simple work with socets
 * @access private
 */
class WebIcqPro_Socet extends WebIcqPro_FLAP
{
	protected $socet = false;
	private $server_url;
	private $server_port;
	private $timeout_second;
	private $timeout_msecond;

	protected function __construct()
	{
		parent::__construct();
		$this->setServerUrl();
		$this->setServerPort();
		$this->setTimeout(6,0);
	}

	protected function socetOpen()
	{
		$this->socet = fsockopen($this->server_url, $this->server_port, $erorno, $errormsg, ($this->timeout_second+$this->timeout_msecond/1000));
		if ($this->socet)
		{
			return true;
		}
		$this->error = 'Error: Cant establish connection to: '.$this->server_url.':'.$this->server_port."\n".$errormsg;
		return false;
	}

	protected function socetClose()
	{
		@fclose($this->socet);
		$this->socet = false;
	}

	protected function socetWrite($data)
	{
		if ($this->socet)
		{
			stream_set_timeout($this->socet, $this->timeout_second, $this->timeout_msecond);
			if (!fwrite($this->socet, $data)) {
				$this->socet = false;
				$this->error = 'Error: Server close connection';
				return false;
			}
			return true;
		}
		$this->error = 'Error: Not connected';
		return false;
	}

	protected function writeFlap($name, $args = array())
	{
		if (method_exists($this, $name))
		{
			$response = $this->$name($args);
			if (is_array($response) && isset($response['return']) && isset($response['data']))
			{
				$flap = $this->packFlap($response['data']);
				$this->log(">> ".$name, $flap);
				if ($this->socetWrite($flap))
				{
					return $response['return'];
				}
				return false;
			}
			elseif ($response)
			{
				$flap = $this->packFlap($response);
				$this->log(">> ".$name, $flap);
				return $this->socetWrite($flap);
			}
		}
		return false;
	}

	private function socetRead($size)
	{
		if ($this->socet)
		{
			stream_set_timeout($this->socet, $this->timeout_second, $this->timeout_msecond);
			$data = @fread($this->socet, $size);
			$socet_status = stream_get_meta_data($this->socet);
			if ($data && !$socet_status['timed_out'])
			{
				return $data;
			}
			if ($socet_status['eof'])
			{
				$this->socet = false;
				$this->error = 'Error: Server close connection';
			}
		}
		return false;
	}

	protected function readFlap($name = false)
	{
		$data = $this->socetRead(6);
		if ($data)
		{
			$flap = unpack('ccommand/cchanel/nsequence/nsize', $data);
			if ($flap['chanel'] == 4)
			{
				$this->error = 'Notice: Server close connection';
				$this->socetClose();
				return false;
			}
			$flap['data'] = $this->socetRead($flap['size']);
			if ($flap['data'])
			{
				if ($name)
				{
					$snac = $this->analizeSnac($flap['data']);
					if (isset($snac['callback']) && $snac['callback'] == $name)
					{
						$this->log("<< ".$snac['callback'].'('.dechex($snac['type']).', '.dechex($snac['subtype']).')', $data.$flap['data']);
						return $this->$name($snac['data'], $snac['flag']);
					}
					elseif(isset($snac['callback']))
					{
						$this->log("<< ".$snac['callback'].'('.dechex($snac['type']).', '.dechex($snac['subtype']).')', $data.$flap['data']);
						$this->$snac['callback']($snac['data'], $snac['flag']);
						$this->error = 'Warning: Wrong server response "'.$name.'" expected but SNAC('.dechex($snac['type']).', '.dechex($snac['subtype']).') received';
					}
					return false;
				}
				return $flap;
			}
		}
		return false;
	}

	protected function readSocket()
	{
		$data = $this->socetRead(6);
		if ($data)
		{
			$flap = unpack('ccommand/cchanel/nsequence/nsize', $data);
			if ($flap['chanel'] == 4)
			{
				$this->error = 'Notice: Server close connection';
				$this->socetClose();
				return false;
			}
			$flap['data'] = $this->socetRead($flap['size']);
			if ($flap['data'])
			{
				$snac = $this->analizeSnac($flap['data']);
				if (isset($snac['callback']))
				{
					$this->log("<< ".$snac['callback'].' ('.dechex($snac['type']).'x'.dechex($snac['subtype']).')', $data.$flap['data']);
					return $this->$snac['callback']($snac['data'], $snac['flag']);
				}
				else
				{
					$this->log('<< Unknown SNAC: '.dechex($snac['type']).'x'.dechex($snac['subtype']), $data.$flap['data']);
				}
			}
		}
		return false;
	}

	protected function setTimeout($second = 1, $msecond = 0)
	{
		$this->timeout_second = $second;
		$this->timeout_msecond = $msecond;
	}

	protected function setServerUrl($value = 'login.icq.com')
	{
		$this->server_url = $value;
		return true;
	}

	protected function setServerPort($value = 5190)
	{
		$this->server_port = $value;
		return true;
	}

	protected function setServer($value = 'login.icq.com:5190')
	{
		$server = explode(':', $value);
		if (count($server) == 2 && is_numeric($server[1]))
		{
			$this->server_url  = $server[0];
			$this->server_port = $server[1];
			return true;
		}
		$this->error = 'Error: Wrong server address format';
		return false;
	}
}

/**
 * Set of tools for simple and clear work with ICQ(OSCAR) protocol.
 *
 */
class WebIcqPro extends WebIcqPro_Socet {

	private $botuin;

	/**
	 * Constructor for WebIcqPro class
	 * @access internal
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Establish connection to ICQ server
	 *
	 * Method initiate login sequence with MD5 based authorization.
	 *
	 * @todo full client request/responses support
	 *
	 * @param string $uin
	 * @param string $pass
	 * @return boolean
	 */
	public function connect($uin, $pass)
	{
		$this->botuin = str_replace('-', '', $uin);
		if ($this->socet)
		{
			$this->error = 'Error: Connection already opened';
			return false;
		}
		if ($this->socetOpen())
		{
			$flap = $this->readFlap();
			$this->channel = 0x01;
			if ($this->helloFlap($flap))
			{
				$this->socetWrite($this->helloFlap());
				$this->channel = 0x02;
				$this->writeFlap('ClientMd5Request', array('uin' => $this->botuin));
				$authkey = $this->readFlap('ServerMd5Response');
				if ($authkey)
				{
					$this->writeFlap('ClientMd5Login', array('uin' => $this->botuin, 'password' => $pass, 'authkey' => $authkey));
					$reconect = $this->readFlap('ServerMd5LoginReply');
					return $this->reconect($reconect);
				}
				else
				{
					$this->readFlap('ServerMd5LoginReply');
				}
			}
		}
		return false;
	}

	protected function reconect($reconect)
	{
		$this->disconnect();
		if ($reconect)
		{
			$this->setServer(array_shift($reconect));
			$cookie = array_shift($reconect);
			if ($this->socetOpen())
			{
				$flap = $this->readFlap();
				$this->channel = 0x01;
				if ($this->helloFlap($flap))
				{
					$this->socetWrite($this->helloFlap(false, $this->packTLV(0x06, $cookie)));
					$this->channel = 2;
					$this->readFlap('ServerFamilies');
					$this->writeFlap('ClientFamiliesVersions');
					$this->readFlap('ServerFamiliesVersions');
					$this->writeFlap('ClientRates');
					$this->writeFlap('ClientSSIRights');
					$this->writeFlap('ClientSSICheckout');
					$this->writeFlap('ClientLocationRights');
					$this->writeFlap('ClientBuddylistRights');
					$this->writeFlap('ClientIBCMRights');
					$this->writeFlap('ClientPrivicyRights');

					$this->writeFlap('ClientLocationInfo');
					$this->writeFlap('ClientReady');
					return true;
				}
			}
		}
		return false;
	}
	/**
	 * Method activate Status Notifications
	 *
	 * This method make possible to read Status Notifications for contacts in your contact list.
	 *
	 * @deprecated Generate a traffic, but handler is not implemented yet!
	 * @todo make it useful. Let's class reads status notifications!
	 *
	 * @return boolean
	 */
	public function activateStatusNotifications()
	{
		return $this->writeFlap('ClientSSI');
	}

	/**
	 * Method set status for client
	 *
	 * Parameters are case insensitive. Try to set different substatuses for privicy and etc. purposes.
	 * You can set any icq status from the list:
	 * - STATUS_ONLINE
	 * - STATUS_AWAY
	 * - STATUS_DND
	 * - STATUS_NA
	 * - STATUS_OCCUPIED
	 * - STATUS_FREE4CHAT
	 * - STATUS_INVISIBLE
	 *
	 * Also possible to set substutuses:
	 * - STATUS_WEBAWARE
	 * - STATUS_SHOWIP
	 * - STATUS_BIRTHDAY
	 * - STATUS_WEBFRONT
	 * - STATUS_DCDISABLED
	 * - STATUS_DCAUTH
	 * - STATUS_DCCONT
	 *
	 * @param string $status
	 * @param string $substatus
	 * @return boolean
	 */
	public function setStatus($status = 'STATUS_ONLINE', $substatus = 'STATUS_DCCONT', $message = '')
	{
		$this->status_message = $message;
		if (isset($this->statuses[$status]))
		{
			if (isset($this->substatuses[$substatus]))
			{
				return $this->writeFlap('ClientStatus', array('status' => $status, 'substatus' => $substatus,)) && $this->writeFlap('ClientIdleTime');
			}
			$this->error = 'setStatus: unknown substatus '.$substatus;
			return false;
		}
		$this->error = 'setStatus: unknown status '.$status;
		return false;
	}

	/**
	 * Method set X status for client
	 *
	 *
	 * @param string $status
	 * @return boolean
	 */
	public function setXStatus($status = '', $message = '')
	{
		$this->xstatus_message = $message;
		if (isset($this->x_statuses[$status]))
		{
			$this->capabilities['xSatus'] = $this->x_statuses[$status];
		} elseif ($status == '') {
			unset($this->capabilities['xSatus']);
		} else {
			$this->error = 'setXStatus: unknown status '.$status;
			return false;
		}
		return $this->writeFlap('ClientLocationInfo') && $this->writeFlap('ClientBart') && $this->writeFlap('ClientIdleTime');
	}

	/**
	 * Method return all available X Statuses
	 *
	 * @return array
	 */
	public function getXStatuses()
	{
		return array_keys($this->x_statuses);
	}

	/**
	 * Method indicate the connection status.
	 *
	 *
	 *
	 * @return boolean
	 */
	public function isConnected()
	{
		return $this->socet;
	}

	/**
	 * Close connection
	 *
	 */
	public function disconnect()
	{
		$this->channel = 0x04;
		$this->socetWrite($this->packFlap(''));
		print_r($this->readSocket());
		print_r($this->readSocket());
		print_r($this->readSocket());
		$this->socetClose();
	}

	/**
	 * Activate the posibility to read offline messages.
	 *
	 * After activation offline messages will be accessable like simple messages.
	 *
	 * @see readMessage
	 * @param string $uin
	 * @return boolean
	 */
	public function activateOfflineMessages()
	{
		return $this->writeFlap('ClientMetaData', array('uin' => $this->botuin, 'type' => 'offline'));
	}

	/**
	 * Get contact short info.
	 *
	 *
	 * @see readMessage
	 * @param string $uin
	 * @return boolean
	 */
	public function getShortInfo($uin)
	{
		$uin = str_replace('-', '', $uin);
		return $this->writeFlap('ClientMetaData', array('uin' => $this->botuin, 'uinsearch' => $uin, 'type' => 'shortinfo'));
	}

	/**
	 * Read message from the server.
	 *
	 * Return an associated array with different set of values of false if nathisn to read.
	 * Available sets of data:
	 * - from - sender UIN
	 * - message - the message
	 * - ... - can be ather information like status and etc.
	 *
	 * @return array
	 */
	public function readMessage()
	{
		return $this->readSocket();
	}

	/**
	 * Send message to the server.
	 *
	 * Try to send message to the $uin.
	 *
	 * @todo check the delivery
	 * @param string $uin
	 * @param string $message
	 * @return boolean
	 */
	public function sendMessage($uin, $message)
	{
		$uin = str_replace('-', '', $uin);
		$this->writeFlap('ClientIdleTime');
		return $this->writeFlap('ClientIBCM', array('uin' => $uin, 'message' => $message));
	}

	/**
	 * Sets additional options
	 *
	 * Do not change this options if you dont know what it mean.
	 *
	 * Usage: $icq->setOption('UserAgent', 'miranda');
	 *
	 * Options available:
	 * MessageType - rtf, utf, old style(offline message)
	 * MessageCapabilities - utf, rtf
	 * UserAgent - miranda, sim, trillian, licq, kopete, micq, andrq, randq, mchat, jimm, macicq, icqlite
	 * Server - login.icq.com:5190
	 * ServerPort - 5190, 80, 8080
	 * Timeout - seconds before stop reading from socets.
	 *
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return boolean
	 */
	public function setOption($name, $value)
	{
		$method = 'set'.$name;
		if (method_exists($this, $method))
		{
			return $this->$method($value);
		}
		$this->error = 'Warning: setOption name: "'.$name.'" unknown';
		return false;
	}

	/**
	 * Return list of contacts
	 *
	 * @deprecated 1.5 - 08.02.2010
	 * @return array
	 */
	public function getContactList()
	{
		return $this->contact_list;
	}

	/**
	 * Return list of contacts groups
	 *
	 * @deprecated 1.5 - 08.02.2010
	 * @return array
	 */
	public function getContactListGroups()
	{
		return $this->contact_list_groups;
	}

	/**
	 * Add uins to list of contacts. First argument is group name. Ather uins to add.
	 * Also posible to add with custom name:
	 *
	 * addContact("Buddies", array('uin' => UIN_TO_ADD, 'name' => "Custom name"), ...)
	 *
	 * @todo errors handling
	 *
	 * @param mixed list of uins
	 * @return boolean
	 */
	public function addContact()
	{
		$uin = array();
		$args = func_get_args();
		$group = array_shift($args);
		foreach ($args as $id) {
			if(is_array($id))
			{
				$uin[str_replace('-', '', $id['uin'])] = $id['name'];
			}
			else
			{
				$uin[str_replace('-', '', $id)] = false;
			}
		}
		if(count($uin) > 0) {
			$this->writeFlap('ClientSSIEditStart');
			$this->writeFlap('ClientSSIAdd', array('uins' => $uin, 'group' => $group));
			$this->writeFlap('ClientSSIEditEnd');
			return true;
		}
		return false;
	}

	/**
	 * Delete uins from list of contacts.
	 * deleteContact(UIN_TO_DELETE, ...)
	 *
	 * @todo errors handling
	 * @param mixed list of uins
	 * @return boolean
	 */
	public function deleteContact($uin)
	{
		$uin = is_array($uin) ? $uin : array($uin);
		$uins = array();
		foreach ($uin as $id) {
			$uins[] = str_replace('-', '', $id);
		}
		if(count($uin) > 0) {
			$this->writeFlap('ClientSSIEditStart');
			$this->writeFlap('ClientSSIDelete', array('uins' => $uins));
			$this->writeFlap('ClientSSIEditEnd');
			return true;
		}
		return true;
	}

	public function addContactGroup($name, $parent = "")
	{
		$this->writeFlap('ClientSSIEditStart');
		$this->writeFlap('ClientSSIAdd', array('group' => $name, 'parent' => $parent));
		$this->writeFlap('ClientSSIEditEnd');
		return true;
	}

	public function deleteContactGroup($name, $parent)
	{
		$this->writeFlap('ClientSSIEditStart');
		$this->writeFlap('ClientSSIDelete', array('group' => $name, 'parent' => $parent));
		$this->writeFlap('ClientSSIEditEnd');
		return true;
	}

	public function getAuthorization($uin, $reason='')
	{
		return $this->writeFlap('ClientSSIAuthRequest', array('uin' => $uin, 'reason' => $reason));
	}

	public function setAuthorization($uin, $granted=true, $reason='')
	{
		return $this->writeFlap('ClientSSIAuthResponse', array('uin' => $uin, 'reason' => $reason, 'allow' => $granted));
	}
}
?>