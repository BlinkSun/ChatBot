<?php

/*
__PocketMine Plugin__
name=ChatBot
description=Feel alone in your server:P
version=0.5
author=BlinkSun
class=ChatBot
apiversion=11,12
*/

class ChatBot implements Plugin {
	private $api;

	public function __construct(ServerAPI $api, $server = false) {
		$this->api = $api;
	}
	
	public function init() {
		$this->api->addHandler("player.chat", array($this, "eventHandle"), 50);
		$this->api->addHandler("player.join", array($this, "eventHandle"), 50);
		$this->config = new Config($this->api->plugin->configPath($this)."config.yml", CONFIG_YAML, array("chatbot"=>"on","bothost"=>"http://www.pandorabots.com/pandora/talk-xml","botid"=>"f5d922d97e345aa1","botname"=>"Alice"));
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
					$player->sendChat($message,$player);
					$messageURL = $this->curlpost(
						$this->config->get("bothost"),
						array(
							 "botid" => $this->config->get("botid"),
							 "input" => $message,
							 "custid" => $player->eid
						)
					);
					$response = "Sorry ".$player->username.", can you repeat your last message please ?";
					if($messageURL != false) $response = $this->XMLtoArray($messageURL);
					$player->sendChat($response["RESULT"]["THAT"],$this->config->get("botname"));
					return false;
				}
			break;
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
	
	public function XMLtoArray($XML) {
		$xml_parser = xml_parser_create();
		xml_parse_into_struct($xml_parser, $XML, $vals);
		xml_parser_free($xml_parser);
		$_tmp='';
		foreach ($vals as $xml_elem) {
			$x_tag=$xml_elem['tag'];
			$x_level=$xml_elem['level'];
			$x_type=$xml_elem['type'];
			if ($x_level!=1 && $x_type == 'close') {
				if (isset($multi_key[$x_tag][$x_level]))
					$multi_key[$x_tag][$x_level]=1;
				else
					$multi_key[$x_tag][$x_level]=0;
			}
			if ($x_level!=1 && $x_type == 'complete') {
				if ($_tmp==$x_tag)
					$multi_key[$x_tag][$x_level]=1;
				$_tmp=$x_tag;
			}
		}
		foreach ($vals as $xml_elem) {
			$x_tag=$xml_elem['tag'];
			$x_level=$xml_elem['level'];
			$x_type=$xml_elem['type'];
			if ($x_type == 'open')
				$level[$x_level] = $x_tag;
			$start_level = 1;
			$php_stmt = '$xml_array';
			if ($x_type=='close' && $x_level!=1)
				$multi_key[$x_tag][$x_level]++;
			while ($start_level < $x_level) {
				$php_stmt .= '[$level['.$start_level.']]';
				if (isset($multi_key[$level[$start_level]][$start_level]) && $multi_key[$level[$start_level]][$start_level])
					$php_stmt .= '['.($multi_key[$level[$start_level]][$start_level]-1).']';
				$start_level++;
			}
			$add='';
			if (isset($multi_key[$x_tag][$x_level]) && $multi_key[$x_tag][$x_level] && ($x_type=='open' || $x_type=='complete')) {
				if (!isset($multi_key2[$x_tag][$x_level]))
					$multi_key2[$x_tag][$x_level]=0;
				else
					$multi_key2[$x_tag][$x_level]++;
				$add='['.$multi_key2[$x_tag][$x_level].']';
			}
			if (isset($xml_elem['value']) && trim($xml_elem['value'])!='' && !array_key_exists('attributes', $xml_elem)) {
				if ($x_type == 'open')
					$php_stmt_main=$php_stmt.'[$x_type]'.$add.'[\'content\'] = $xml_elem[\'value\'];';
				else
					$php_stmt_main=$php_stmt.'[$x_tag]'.$add.' = $xml_elem[\'value\'];';
				eval($php_stmt_main);
			}
			if (array_key_exists('attributes', $xml_elem)) {
				if (isset($xml_elem['value'])) {
					$php_stmt_main=$php_stmt.'[$x_tag]'.$add.'[\'content\'] = $xml_elem[\'value\'];';
					eval($php_stmt_main);
				}
				foreach ($xml_elem['attributes'] as $key=>$value) {
					$php_stmt_att=$php_stmt.'[$x_tag]'.$add.'[$key] = $value;';
					eval($php_stmt_att);
				}
			}
		}
		return $xml_array;
	}
	
	public function __destruct() {}
}
