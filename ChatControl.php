<?php

/*
__PocketMine Plugin__
name=ChatControl
description=Extra chat options
version=1.0
author=wiezz
class=ChatControl
apiversion=9
*/

/*
--------ChangeLog--------
1.0 : Inital release
-------------------------
*/


class ChatControl implements Plugin{
	private $api;
	private $server;
	private $groups;
	private $players;
	private $config;
	private $filterlist;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->server = ServerAPI::request();
	}
	
	public function init(){
		$this->api->addHandler('player.chat', array($this, 'playerchat'), 100);
		$this->api->addHandler('player.spawn', array($this, 'playerspawn'), 100);
		$this->api->addHandler('player.quit', array($this, 'playerquit'), 100);
		$this->api->console->register("cc", "Chat control commands", array($this, "command"));
		$path = $this->api->plugin->configPath($this);
		$this->groups = new Config($path."groups.yml", CONFIG_YAML, array(
			'admin' => array(
				'Prefix' => '[ADMIN] ',
				'Members' => array(),
			),
			'op' => array(
				'Prefix' => '[OP] ',
				'Members' => array(),
			),
			'member' => array(
				'Prefix' => '[MEMBER] ',
				'Members' => array(),
			),
		));
		$this->groups = $this->api->plugin->readYAML($path ."groups.yml");
		$this->config = new Config($path."config.yml", CONFIG_YAML, array(
			'SpamProtection' => array(
				'Enabled' => true,
				'MsgInterval' => 10,
			),
			'ChatFilter' => array(
				'Enabled' => false,
				'KickPlayer' => false,
				'MutePlayer' => true,
			),
			'ChatMute' => array(
				'Enabled' => true,
				'MutesBroadcastMsg' => false,
			),
		));
		$this->config = $this->api->plugin->readYAML($path ."config.yml");
		if($this->config['ChatMute']['Enabled'] == true){
			$this->api->console->register("mute", "Dissable chat", array($this, "mute"));
			$this->api->console->register("unmute", "Enable chat", array($this, "mute"));
			$this->api->ban->cmdWhitelist("mute");
			$this->api->ban->cmdWhitelist("unmute");
		}
		if($this->config['ChatMute']['MutesBroadcastMsg'] == true){
			$this->api->addHandler('server.chat', array($this, 'serverchat'), 100);
		}
		if($this->config['ChatFilter']['Enabled'] == true){
			$this->filterlist = new Config($path."filterlist.txt", CONFIG_LIST);
			$this->filterlist = $this->api->plugin->readYAML($path ."filterlist.txt");
		}
		$this->players = array();
	}
	
	public function mute($cmd, $args, $issuer){
		$username = $issuer->username;
		switch($cmd){
			case 'mute':	$this->players[$username]['mute'] = 1;
							$this->api->chat->sendTo(false, "[ChatControl] You muted the chat", $username);
							break;
			case 'unmute':	$this->players[$username]['mute'] = 0;
							$this->api->chat->sendTo(false, "[ChatControl] You unmuted the chat", $username);
							break;
		}
	}
	
	public function command($cmd, $args, $issuer){
		$username = $issuer->username;
		switch($args[0]){
			case 'apg':
			case 'rpg':			if(!(isset($args[1]) and isset($args[2]))){
									$output = '[ChatControl] Usage: /cc <apg|rpg> <player> <group>';
									return $output;
								}
								$name = $args[1];
								$group = $args[2];
								if(!isset($this->groups[$group])){
									$output = "[ChatControl] Group doesn't exist";
									return $output;
								}
								if($args[0] === 'addplayer'){
									array_push($this->groups[$group]['Members'], $name);
									$output = '[ChatControl] '.$name.' is added to the group '.$group;
								}else{
									unset($this->groups[$group]['Members'][$name]);
									$output = '[ChatControl] '.$name.' is added to the group '.$group;
								}
								return $output;
								break;
			case 'ban':
			case 'unban':		if(!isset($args[1])){
									$output = '[ChatControl] Usage: /cc <ban|unban> <player>';
									return $output;
								}
								$name = $args[1];
								if(!isset($this->players[$name])){
									$output = "[ChatControl] Player doesn't exist";
									return $output;
								}
								if($args[0] == 'ban'){
									$this->players[$name]['banned'] = 1;
									$output = '[ChatControl] '.$name." can't chat anymore";
								}else{
									$this->players[$name]['banned'] = 0;
									$output = '[ChatControl] '.$name." can chat";
								}
								return $output;
								break;
			case 'reload':		$this->groups = $this->api->plugin->readYAML($path ."groups.yml");
								$this->config = $this->api->plugin->readYAML($path ."config.yml");
								$output = '[ChatControl] config file and group file reloaded';
								return $output;
								break;
			case 'help':
			case '?':			$issuer->sendChat('===[ChatControl Commands]===');
								$issuer->sendChat('/mute - Mutes the chat');
								$issuer->sendChat('/unmute - Unmutes the chat');
								$issuer->sendChat('/cc apg <player> <group> - Add player to a group');
								$issuer->sendChat('/cc rpg <player> <group> - Remove player from a group');
								$issuer->sendChat('/cc <ban|unban> <player> - Dissallow a player from chatting');
								$issuer->sendChat('/cc reload - Reload the config file');
			default:			$output = 'ChatControl v1.0 made by Wies';
								return $output;					
		}
	}
	
	public function serverchat($data){
		$message = $data['message'];
		$players = $this->api->player->getAll();
		foreach($players as $player){
			$username = $player->username;
			if($this->players[$username]['mute'] === 0){
				$this->api->chat->sendTo(false, $message, $username);
			}
		}
		return false;
	}
	
	public function playerchat($data){
		$username = $data['player']->username;
		if($username == 'console') return true;
		if($this->players[$username]['banned'] === 1){
			$this->api->chat->sendTo(false, 'You are not allowed to chat!', $username);
			return false;
		}
		$message = $data['message'];
		if(isset($this->filterlist) and !$this->api->ban->isOp($username)){
			foreach($this->filterlist as $key => $val){
				if(!strpos($message, $val)){
					$this->api->chat->sendTo(false, 'Your message contains forbidden words', $username);
					if($this->config['ChatFilter']['MutePlayer'] == true){
						$this->players[$username]['banned'] = 1;
					}
					if($this->config['ChatFilter']['KickPlayer'] == true){
						$this->api->console->run('kick '.$username." Don't use these words again", "console", false);
					}
					return false;
				}
			}
		}
		foreach($this->groups as $key => $val){
			if(in_array($username, $val['Members'])){
				$message = (string)('<'.$val['Prefix'].$username.'> '.$message);
			}
		}
		if(($this->config['SpamProtection']['Enabled'] and (time() - $this->players[$username]['lastmsg']) < $this->config['SpamProtection']['MsgInterval']) and !$this->api->ban->isOp($username)){
			$this->api->chat->sendTo(false, "Don't send more than one msg in ".$this->config['SpamProtection']['MsgInterval'].' seconds', $username);
			return false;
		}
		$this->players[$username]['lastmsg'] = time();
		$players = $this->api->player->getAll();
		console('[CHAT] '.$message);
		foreach($players as $player){
			$username = $player->username;
			if($this->players[$username]['mute'] === 0){
				$this->api->chat->sendTo(false, $message, $username);
			}
		}
		return false;
	}
	
	public function playerquit($data){
		$username = (string)$data->username;
		if(isset($this->players[$username])){
			unset($this->players[$username]);
		}
	}
	
	public function playerspawn($data){
		$username = (string)$data->username;
		$this->players[$username] = array(
			'lastmsg' => 0,
			'mute' => 0,
			'banned' => 0,
		);
	}
	
	public function __destruct(){
	
	}

}