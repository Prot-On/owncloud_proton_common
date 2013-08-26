<?php
namespace OCA\Proton;

class BearerPest extends \Pest {

	protected $bearerHeader;
	
	public function setupAuth($user, $pass, $auth = 'basic'){
		if ($auth == 'bearer') {
			$this->bearerHeader = 'Authorization: Bearer ' . $user;
		} else {
			parent::setupAuth($user, $pass, $auth);
		}
	}
	
	protected function prepHeaders($headers) {
		$headers = parent::prepHeaders($headers);
		if (!empty($this->bearerHeader)) {
			$headers[] = $this->bearerHeader;
		}
		return $headers;
	}

}

?>
