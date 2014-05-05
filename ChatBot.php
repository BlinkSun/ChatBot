<?php

/*
__PocketMine Plugin__
name=ChatBot
description=Feel alone in your server:P
version=0.8
author=BlinkSun
class=ChatBot
apiversion=11,12
*/

class ChatBot implements Plugin {
	private $api;
	private $openChatBot;

	public function __construct(ServerAPI $api, $server = false) {
		$this->api = $api;
		$this->openChatBot = array();
	}
	
	public function init() {
		$this->api->addHandler("player.chat", array($this, "eventHandle"), 50);
		$this->api->addHandler("player.join", array($this, "eventHandle"), 50);
		$this->config = new Config($this->api->plugin->configPath($this)."config.yml", CONFIG_YAML, array("chatbot"=>"on","botid"=>"f5d922d97e345aa1","botname"=>"Alice"));
		$this->api->console->register('chatbot', "[on|off] Enable or disable PandoraBot.",array($this, 'commandHandle'));
		$this->api->console->alias("cb", "chatbot");
	}
	
	public function commandHandle($cmd, $params, $issuer, $alias){
		switch($cmd){
			case 'chatbot':
				if(isset($params[0]) && ($params[0] == "on" or $params[0] == "off")) {
					$this->config->set("chatbot",$params[0]);
					$this->config->save();
					$output = "[ChatBot] Set to " . $params[0] . ".\n";
				}else{
					$output = "Usage: /$cmd [on/off]\n";
				}
			break;
		}
		return $output;
	}
	
	public function eventHandle($data, $event) {
		switch ($event) {
			case "player.chat":
				if($this->config->get("chatbot") == "on") {
					$player = $data["player"];
					$message = $data["message"];
					if ((isset($this->openChatBot[$player->eid]) != false) or ((isset($this->openChatBot[$player->eid]) == false) and ($this->InStr(strtolower($message),strtolower($this->config->get("botname"))) != -1))) {
						$this->openChatBot[$player->eid] = true;
						$player->sendChat($message,$player);
						$messageURL = $this->curlpost(
							"http://www.pandorabots.com/pandora/talk-xml",
							array(
								 "botid" => $this->config->get("botid"),
								 "input" => $message,
								 "custid" => $player->username
							)
						);
						$response = "Sorry ".$player->username.", can you repeat your last message please ?";
						if($messageURL != false) {
							preg_match("/<that(.*)?>(.*)?<\/that>/", $messageURL, $match);
							$response = substr($match[2], 1, -1);
						}
						$player->sendChat($response,$this->config->get("botname"));
						if(($this->InStr(strtolower($message),"bye") != -1) and ($this->InStr(strtolower($message),strtolower($this->config->get("botname"))) != -1)) {
							unset($this->openChatBot[$player->eid]);
							//console($this->InStr(strtolower($message),"bye") . " " . $this->InStr(strtolower($message),strtolower($this->config->get("botname"))));
						}
						return false;
					}
				}
			break;
		}
	}
	
	public function InStr($haystack, $needle) { 
		$pos=strpos($haystack, $needle); 
		if ($pos !== false) 
		{ 
			return $pos; 
		} 
		else 
		{ 
			return -1; 
		} 
	} 
	
	public function curlpost($url, array $post = NULL, array $options = array()) {
		$defaults = array(
			CURLOPT_POST => 1,
			CURLOPT_HEADER => 0,
			CURLOPT_URL => $url,
			CURLOPT_FRESH_CONNECT => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FORBID_REUSE => 1,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_POSTFIELDS => http_build_query($post)
		);

		$ch = curl_init();
		curl_setopt_array($ch, ($options + $defaults));
		if (!$result = curl_exec($ch))
		{
			//trigger_error(curl_error($ch));
			return false;
		}
		curl_close($ch);
		return $result;
	}
		
	public function __destruct() {
	}
}
