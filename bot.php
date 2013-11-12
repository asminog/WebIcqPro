<?php
error_reporting (E_ALL);

define('BOTUIN', '214176492');
define('PASSWORD', '******');
define('ADMINUIN', '150727255');
define('STARTXSTATUS', 'studying');
define('STARTSTATUS', 'STATUS_FREE4CHAT');

$help = "Bot commands:\r
\t'!about' - print message about this bot.\r
\t'!addme your_custom_name' - Add your uin to bot contact list.\r
\t'!contact' - Shows bot contact list.\r
\t'!help'  - print help message.\r
\t'!info 3180142'  - show user info.\r
\t'!removeme' - Delete your uin ftom bot contact list.\r
\t'!status away status text' - set bot status. See !help status for more info.\r
\t'!stop'  - stop bot (administrative).\r
\t'!to 111111 message' - send message to UIN.\r
\t'!uptime'  - print bot uptime.\r
\t'!xstatus' - get bot xstatuses.\r
";

require_once('WebIcqPro.class.php');

$about = "PHP BOT v3.10
Based on WebIcqPro " . WebIcqPro::VERSION . "
(c) Sergey Akudovich
Contact author:
http://wip.asminog.com/forum/
" .
"Donate project:
http://webmoney.ru
R840601686033,
Z110610335096,
E494800171389
";

$ignore_list = array(
'219109666',
'285190848',
'242901633'
);

$message_response = array();

while(1) {
	$icq = new WebIcqPro();
	$icq->debug = true;
//	$icq->setOption('UserAgent', 'miranda');
//	$icq->setOption('MaxMessageSize', 0x0FFF);


//	if($icq->connect(UIN1, PASSWORD1))
//	{
//		$icq->sendMessage(ADMINUIN, "Service PHP BOT started...");
//		$uptime = $status_time = $xstatus_time = time();
//		$icq->setStatus(STARTSTATUS, 'STATUS_WEBAWARE', 'Talk to me... I\'m WebIcqBot :)');
//		$icq->setXStatus(STARTXSTATUS);
//		$xstatus = STARTXSTATUS;
//		$status = STARTSTATUS;
//	}else{
//		echo "connect filed! Next try in 20 minutes!1\n";
//		if ($icq->error != '')
//		{
//			echo $icq->error."\r\n";
//			$icq->error = '';
//		}
//		sleep(1200);
//	}
//
//	$icq->disconnect();
//
//	sleep(10);

	if($icq->connect(BOTUIN, PASSWORD))
	{
		$icq->sendMessage(ADMINUIN, "Service PHP BOT started...");
		$uptime = $status_time = $xstatus_time = time();
		$icq->setStatus(STARTSTATUS, 'STATUS_DCAUTH', 'Talk to me... I\'m WebIcqBot :)');
		$icq->setXStatus(STARTXSTATUS, 'Talk to me... I\'m WebIcqBot :)');
		$xstatus = STARTXSTATUS;
		$status = STARTSTATUS;
	}else{
		echo "connect filed! Next try in 20 minutes!\n";
		if ($icq->error != '')
		{
			echo $icq->error."\r\n";
			$icq->error = '';
		}
		sleep(1200);
		continue;
	}

	$msg_old = array();

//	$icq->activateOfflineMessages(UIN);

	while($icq->isConnected()) {
		if ($icq->error != '')
		{
			echo $icq->error."\r\n";
			$icq->error = '';
		}
		$msg = $icq->readMessage();
		if (is_array($msg) && $msg !== $msg_old) {
			if (isset($msg['encoding']) && is_array($msg['encoding']))
			{
				if ($msg['encoding']['numset'] === 'UNICODE')
				{
					$msg['realmessage'] = $msg['message'];
					$msg['message'] = mb_convert_encoding($msg['message'], 'cp1251', 'UTF-16');
				}
				if ($msg['encoding']['numset'] === 'UTF-8')
				{
					$msg['realmessage'] = $msg['message'];
					$msg['message'] = mb_convert_encoding($msg['message'], 'cp1251', 'UTF-8');
				}
			}

			$msg_old = $msg;
			if (isset($msg['type']) && $msg['type'] == 'message' && isset($msg['from']) && isset($msg['message']) && $msg['message'] != '' && !in_array($msg['from'], $ignore_list))// && preg_match('~^[a-z0-9\-!�-� \t]+$~im', $msg['from']))
			{
				$icq->sendMessage(ADMINUIN, $msg['from'].'>'.trim($msg['message']));
				switch (strtolower(trim($msg['message'])))
				{
					case '!about':
						$icq->sendMessage($msg['from'], $about);
						break;
					case '!help':
						$icq->sendMessage($msg['from'], $help);
						break;
					case '!xstatus':
						$icq->sendMessage($msg['from'], "Use !xstatus xstatus, where xstatus is in:\r\n\t".implode("\r\n\t", $icq->getXStatuses()));
						break;
					case '!stop':
					case '!exit':
						if($msg['from'] == ADMINUIN)
						{
							$icq->sendMessage(ADMINUIN, "Service PHP BOT stopt...");
							$icq->disconnect();
							exit();
						}
						else
						{
							$icq->sendMessage($msg['from'], "The system is going down for reboot NOW! :)");
						}
						break;
					case '!clear':
						$list = $icq->getContactList();
						if($msg['from'] == ADMINUIN)
						{
							$icq->deleteContact(array_keys($list));
							$icq->sendMessage(ADMINUIN, "Contact list cleared...");
						}
						else
						{
							$icq->sendMessage($msg['from'], "What do you want to clear?");
						}
						break;
					case '!contact':
						$c = getContactList($icq->getContactList());
						foreach ($c as $m) {
							$m = str_replace("\x00", '', $m);
							$icq->sendMessage($msg['from'], $m);
						}
						break;
					case '!groups':
						var_dump($icq->getContactListGroups());
						break;
					case '!removeme':
						$list = $icq->getContactList();
						if(isset($list[$msg['from']]))
						{
							$icq->deleteContact($msg['from']);
						}
						$icq->sendMessage($msg['from'], $msg['from'].' deleted from bot contact list');
						break;
					case '!uptime':
						$seconds = time() - $uptime;
						$time = '';
						$days = (int)floor($seconds/86400);
						if($days > 1)
						{
							$time .= $days.' days, ';
						}
						elseif($days == 1)
						{
							$time .= $days.' day, ';
						}
						$hours = (int)floor(($seconds-$days*86400)/3600);
						$time .= ($hours > 1 ? $hours.' hours, ' : ($hours == 1 ? '1 hour, ' : ''));
						$minutes = (int)floor(($seconds-$days*86400 - $hours*3600)/60);
						$time .= ($minutes > 1 ? $minutes.' minutes, ' : ($minutes == 1 ? '1 minute, ' : ''));
						$seconds = (int)fmod($seconds, 60);
						$time .= ($seconds > 1 ? $seconds.' seconds' : ($seconds == 1 ? '1 second' : ''));
						$time =
						$icq->sendMessage($msg['from'], $time.' online. Last login : '.date('d.m.Y H:i:s', $uptime));
						break;
					default:
						$command = explode(' ', $msg['message']);
						if (count($command) > 1)
						{
							switch ($command[0])
							{
								case '!help':
									switch (strtolower($command[1]))
									{
										case 'status':
											$icq->sendMessage($msg['from'], "Usage: !status *\r\n\r\nWhere * is in the list:\r\n online, away, dnd, na, occupied, free4chat, invisible");
											break;
										default:
											$icq->sendMessage($msg['from'], "No additional information. Type '!help' command for basic help.");
											break;
									}
									break;
								case '!status':
									$status = 'STATUS_'.trim(strtoupper($command[1]));
									$status2 = 'STATUS_DCCONT';
									if (count($command) > 2) {
										$status2 = 'STATUS_'.trim(strtoupper($command[2]));
									}
									unset($command[0]);
									unset($command[1]);
									if (!$icq->setStatus($status, $status2, trim(implode(' ', $command))))
									{
										$status = STARTSTATUS;
										$icq->sendMessage($msg['from'], $icq->error);
									}
									else
									{
										$status_time = time();
									}
									break;
								case '!xstatus':
									$xstatus = $command[1];
									unset($command[0]);
									unset($command[1]);
									$statusMessage = count($command) > 0 ? trim(implode($command)) : '';
									if (!$icq->setXStatus(trim($xstatus), $statusMessage))
									{
										$icq->sendMessage($msg['from'], $icq->error);
									}
									else
									{
										$xstatus_time = time();
									}
									break;
								case '!to':
									$to = $command[1];
									if ($to != BOTUIN)
									{
										unset($command[0]);
										unset($command[1]);
										$command = implode(' ', $command);
										$id = $icq->sendMessage($to, ($msg['from']==ADMINUIN?'':"Message from: ".$msg['from']."\r\n").$command);
										if ($id !== false)
										{
											$message_response[$id] = array('from' => $msg['from'], 'to' => $to);
											$icq->sendMessage($msg['from'], "Accepted for delivery. Message id: ".$id);
										}
										else
										{
											$icq->sendMessage($msg['from'], $icq->error);
										}
									}
									else
									{
										$icq->sendMessage($msg['from'], 'Unable to send message to this UIN.');
									}
									break;
								case '!remove':
									$uin = $command[1];
									if ($msg['from']==ADMINUIN)
									{
										$icq->deleteContact($uin);
										$icq->sendMessage($msg['from'], 'Deleted '.$uin);
									}
									else
									{
										$icq->sendMessage($msg['from'], 'Use !removeme to remove your contact from contact list');
									}
									break;
								case '!addme':
									$list = $icq->getContactList();
									if(!isset($list[$msg['from']]))
									{
										unset($command[0]);
										$name = trim(implode(' ', $command));
										$group = 'auto';
										if (!in_array($group, $icq->getContactListGroups()))
										{
											$icq->addContactGroup($group);
										}
										$icq->addContact($group, array('uin' => $msg['from'], 'name' => $name));
										$icq->getAuthorization($msg['from'], 'I want to see your status!');
									}
									$icq->sendMessage($msg['from'], $msg['from'].' added to bot contact list');
									break;
								case '!addgroup':
									$name = $command[1];
									$parent = '';
									if (count($command) > 2) {
										$parent = $command[2];
									}
									$icq->addContactGroup($name, $parent);
									break;
								case '!ignore':
									if($msg['from'] == ADMINUIN || $msg['from'] == trim($command[1])) {
										$ignore_list[] = trim($command[1]);
										$icq->sendMessage(ADMINUIN, 'Ignored UINS: "'.implode('", "', $ignore_list).'"');
										$icq->sendMessage($command[1], 'You temporary added to bots ignore list.');
									}
									else
									{
										$icq->sendMessage(ADMINUIN, 'UIN "'.$command[1].'" temporary added to ignore list.');
									}
									break;
								case '!unignore':
									if($msg['from'] == ADMINUIN) {
										$ignore_list[] = array();
										$icq->sendMessage(ADMINUIN, 'Ignore list cleared.');
									}
									break;
								case '!info':
									$id = $icq->getShortInfo($command[1]);
									if($id) {
										$message_response[$id] = $msg['from'];
									}
									else {
										$icq->sendMessage($msg['from'], 'Error to get info for '.$command[1]);
									}
									break;
								default:
									var_dump($msg);
									$icq->sendMessage($msg['from'], "Type '!help' for assistance.");
									break;
							}
						}
						else
						{
							var_dump($msg);
							$icq->sendMessage($msg['from'], "Type '!help' for assistance.");
						}
						break;
				}
			}
			elseif (isset($msg['id']) && isset($message_response[$msg['id']]))
			{
				if(isset($msg['type']))
				{
					switch ($msg['type']) {
						case 'shortinfo':
							$message = 'Nick: '.$msg['nick']."\r\n";
							$message .= 'First Name: '.$msg['firstname']."\r\n";
							$message .= 'Last Name: '.$msg['lastname']."\r\n";
							$message .= 'Email: '.$msg['email']."\r\n";
//							$message .= 'Authorization: '.($msg['authorization'] > 0 ? 'true' : 'false')."\r\n";
//							$message .= 'Gender: '.($msg['gender'] == 0 ? 'W' : 'M');

							$icq->sendMessage($message_response[$msg['id']], $message);
							break;
						case 'accepted':
							$contact = $message_response[$msg['id']];
							$message = 'Message to '.$contact['to'].' accepted. Id: '.$msg['id'];
							$icq->sendMessage($contact['from'], $message);
							break;

						default:
							break;
					}
				}
				unset($message_response[$msg['id']]);
			}
			elseif (isset($msg['type']))
			{
				switch ($msg['type']) {
					case 'contactlist':
						$icq->sendMessage(ADMINUIN, 'contact');
//						$icq->sendMessage(ADMINUIN, getContactList($icq->getContactList()));
						break;
					case 'error':
						$icq->sendMessage(ADMINUIN, 'Error: '.$msg['code']." ".(isset($msg['error'])?$msg['error']:''));
						break;
					case 'authrequest':
						$icq->setAuthorization($msg['from'], true, 'Just for fun!');
						break;
					case 'authresponse':
						var_dump($msg);
						$icq->sendMessage(ADMINUIN, 'Authorization response: '.$msg['from'].' - '.$msg['granted'].' - '.trim($msg['message']));
						break;
					case 'accepted':
							if (!$msg['uin'] == ADMINUIN)
							{
								var_dump($msg);
							}
						break;
					case 'useronline':
					case 'autoaway':
						break;
					case 'rate':
						echo 'Rate: '.$msg['level']. "\r\n";
#						$icq->sendMessage(ADMINUIN, 'Rate: '.$msg['level']);
						break;
					default:
						var_dump($msg);
						break;
				}
			}
			elseif (isset($msg['errors'])) {
				$answer = "";
				foreach ($msg['errors'] as $error) {
					if (!isset($error['error']))
					{
						$error['error'] = '';
					}
					$answer .= 'Error: '.$error['code']." ".$error['error']."\r\n";
				}
				$icq->sendMessage(ADMINUIN, $answer);
			}
			else
			{
				var_dump($msg);
			}
		}
		else
		{
			if (is_array($msg))
			{
				var_dump($msg);
			}
		}
		flush();
#		sleep(1);

		if(($status_time + 60) < time() && $status != STARTSTATUS)
		{
			$icq->setStatus(STARTSTATUS, 'STATUS_WEBAWARE', 'Talk to me... I\'m WebIcqBot :)');
			$status = STARTSTATUS;
		}
		if(($xstatus_time + 60) < time() && $xstatus != STARTXSTATUS)
		{
			$icq->setXStatus(STARTXSTATUS);
			$xstatus = STARTXSTATUS;
		}
	}
	echo "Will restart in 30 seconds...\n";
	sleep(30);
}

function getContactList($list)
{
	$n = 0;
	$message[$n] = "Contact list:\r\n";
	$i = 0;
	foreach ($list as $uin => $data) {
		$i++;
		if ($i > 60) { $i = 0; $n++; $message[$n] = '';}
		$message[$n] .= (isset($data['name']) ? mb_convert_encoding(trim($data['name']), 'cp1251', 'UTF-8')
		." ($uin)" : $uin)
		.' : '.(isset($data['status']) ? $data['status'] : 'STATUS_OFFLINE')
		.' : '.(isset($data['xstatus']) ? $data['xstatus'] : '')."\r\n";
	}
	return $message;
}
