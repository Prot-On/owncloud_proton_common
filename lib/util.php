<?php

namespace OCA\Proton;

class Util {
	
	public static function storePassword($password) {
		$_SESSION['proton']['password'] = $password;
	}
	
	public static function getPassword() {
		return isset($_SESSION['proton']['password'])?$_SESSION['proton']['password']:null;
	}
	
	public static function storeCompleteName($name) {
		$_SESSION['proton']['complete_name'] = $name;
	}
	
	public static function getCompleteName() {
		return isset($_SESSION['proton']['complete_name'])?$_SESSION['proton']['complete_name']:null;
	}
	
	public static function log($message, $level = \OC_Log::DEBUG) {
		\OC_Log::write('Prot-On', $message, $level);
	}
	
	public static function getPest($auth = true) {
		$pest = new BearerPest(\OC_Config::getValue( "user_proton_url" ));	
		if ($auth) {
			if (self::getPassword() != null) {
				$pest->setupAuth(\OC_User::getUser(), self::getPassword());
			} else {
				$token = OAuth::getToken();
				if (empty($token)) {
					throw new \Exception("No authentication found");
				}
				$pest->setupAuth($token, '', 'bearer');
			}
		}
		return $pest;
	}
		
}

?>