<?php

namespace OCA\Proton;

class Util {
	
    const TOKEN_ENDPOINT         = '/external/oauth/token';
    
    private static function _storeSession($key, $value) {
        $_SESSION['proton'][$key] = $value;
    }

    private static function _getSession($key, $default = null) {
        return isset($_SESSION['proton'][$key])?$_SESSION['proton'][$key]:$default;
    }
    
	public static function storePassword($password) {
        return self::_storeSession('password', $password);
	}
	
	public static function getPassword() {
		return self::_getSession('password');
	}
    
    public static function storeUser($user) {
        return self::_storeSession('user', $user);
    }
    
    public static function getUser() {
        return self::_getSession('user');
    }
    
    public static function storeCompleteName($completeName) {
        return self::_storeSession('completeName', $completeName);
    }
    
    public static function getCompleteName() {
        return self::_getSession('completeName');
    }
    
    public static function markProtOnUser() {
        return self::_storeSession('proton_user', true);
    }
    
    public static function checkProtOnUser() {
        return (self::_getSession('proton_user') === true);
    }
	
	public static function log($message, $level = \OC_Log::DEBUG) {
		\OC_Log::write('Prot-On', $message, $level);
	}
	
	public static function getPest($auth = true) {
		$pest = new BearerPest(\OC_Config::getValue( "user_proton_api_url" ));	
		if ($auth) {
			if (self::getPassword() != null) {
				$pest->setupAuth(self::getUser(), self::getPassword());
			} else {
				$token = self::getToken();
				if (empty($token)) {
					throw new \Exception("No authentication found");
				}
				$pest->setupAuth($token, '', 'bearer');
			}
		}
		return $pest;
	}
    
    public static function isApiConfigured() {
        return !is_null(\OC_Config::getValue( "user_proton_api_url" ));
    }

    public static function isOAuthConfigured() {
        return !is_null(\OC_Config::getValue( "user_proton_oauth_secret" )) 
            && !is_null(\OC_Config::getValue( "user_proton_oauth_client_id" ))
            && !is_null(\OC_Config::getValue( "user_proton_url" ));
    }
		
    public static function parseOAuthTokenResponse($response) {
        if ($response['code'] == 200) {
            $token = $response['result'];
            $date = new \DateTime("now");
            $token['expiration'] = $date->add(new \DateInterval('PT'.$token['expires_in'].'S'));
            return $token;
        }
        return null;
    }
    
    public static function setToken($token) {
        $_SESSION['proton']['access_token'] = $token;
    }

    protected static function _getToken() {
        //TODO retrieve token from DB if needed
        return isset($_SESSION['proton']['access_token'])?$_SESSION['proton']['access_token']:null;
    }
    
    public static function getToken() {
        $token = self::_getToken();
        if ($token == null) {
            return null;
        }
        $currentDate = new \DateTime("now");
        self::log('Current: ' . $currentDate->format("Y-m-d\TH:i:s\Z"). ', Expiration: ' . $token['expiration']->format("Y-m-d\TH:i:s\Z"));
        if ($currentDate > $token['expiration']) {
            
            require_once('PHP-OAuth2/Client.php');
            require_once('PHP-OAuth2/GrantType/IGrantType.php');
            require_once('PHP-OAuth2/GrantType/AuthorizationCode.php');
            require_once('PHP-OAuth2/GrantType/RefreshToken.php');
            
            $client = new \OAuth2\Client(\OC_Config::getValue( "user_proton_oauth_client_id" ), \OC_Config::getValue( "user_proton_oauth_secret" ), \OAuth2\Client::AUTH_TYPE_AUTHORIZATION_BASIC);
            $params = array('refresh_token' => $token['refresh_token']);
            $response = $client->getAccessToken(\OC_Config::getValue( "user_proton_url" ).self::TOKEN_ENDPOINT, 'refresh_token', $params);
            $token = self::parseOAuthTokenResponse($response);
            self::setToken($token);
        }
        //TODO emit event to store token if needed
        return $token['access_token'];
    }
    
    public static function toTmpFileId($fileId) {
        $path = \OC\Files\Filesystem::getPath($fileId);
        return self::toTmpFilePath($path);
    }
    
    public static function toTmpFilePath($path) {
        $path = dirname($path) . '/' . basename($path);
                
        if (\OC\Files\Filesystem::isValidPath($path)) {
            $source = \OC\Files\Filesystem::fopen($path, 'r');
            if ($source) {
                $tmpFolder = \OC_Helper::tmpFolder();
                $tmpFile = $tmpFolder.basename($path);
                file_put_contents($tmpFile, $source);
                return $tmpFile;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    public static function getDocIds($fileId) {
        $query = \OC_DB::prepare( 'SELECT `docId`, `xDocId` FROM `*PREFIX*proton_docid` WHERE fileId = ?' );
        $result = $query->execute( array( $fileId ))->fetch();
        if ($result !== false) {
            return $result;
        }
        
        $temp = self::toTmpFileId($fileId);
        $pest = self::getPest();
        try {
            $thing = $pest->post('/documents/getInfo', array("file" => "@".$temp));
        } catch (\Exception $e) {
            self::log('Excepcion '.$e);
            return null;
        }
        $info = json_decode($thing, true);
        $result = array ('docId' => null, 'xDocId' => null);
        $params = array(null, null, $fileId);
        if (!is_null($info)) {
            $result = array ('docId' => $info['docid'], 'xDocId' => $info['xdocId']);
            $params = array($info['docid'], $info['xdocId'], $fileId);
        } 
        $query = \OC_DB::prepare( 'INSERT INTO `*PREFIX*proton_docid` (`docId`, `xDocId`, `fileId`) VALUES (?, ?, ?)' );
        $query->execute($params);
        return $result;
    }
    
    public static function unProtectDocIds($fileId) {
        $query = \OC_DB::prepare( 'UPDATE `*PREFIX*proton_docid` SET docId = ?, xDocId = ? WHERE fileId = ?' );
        $query->execute(array(null, null, $fileId));
    }
    
    public static function protectDocIds($fileId, $docId, $xDocId) {
         //If you can not get the docId and xDocId when protecting, then change this to delete the row and let the system refecth it when needed
        $query = \OC_DB::prepare( 'UPDATE `*PREFIX*proton_docid` SET docId = ?, xDocId = ? WHERE fileId = ?' );
        $query->execute(array($docId, $xDocId, $fileId));
    }
    
    
}

?>