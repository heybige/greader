#!/usr/local/bin/php -q
<?
	$reader = new GReader('[gmail username]','[gmail password]');

	// If you want to send to another email address
	// $reader->set_notify("[alternate email address]");

	// Default is not to delete starred items; this enables deletion
	$reader->set_delete(TRUE);

	echo $reader->listStarred();

	class GReader {

		private $_username;
		private $_password;
		private $_auth;
		private $_sid;

		private $_notify;
		private $_delete;

		private $_token;
		private $_cookie;

		public function __construct($username, $password) {

			$this->_username = $username;
			$this->_password = $password;

			if ( $this->_valid_email($username) )
				$this->_notify = $username;

			$this->_delete = FALSE;

			$this->_connect();
  		}

		private function _connect() {

			$this->_getAuth();
			$this->_getToken();
			return $this->_token != null;
		}

		private function _getToken() {

			$url = "http://www.google.com/reader/api/0/token";

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_COOKIE, $this->_cookie);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: GoogleLogin auth={$this->_auth}"));

			ob_start();
			curl_exec($ch);
			curl_close($ch);
			$this->_token = ob_get_contents();
			ob_end_clean();
		}

		private function _getAuth() {

			$requestUrl = "https://www.google.com/accounts/ClientLogin?service=reader&Email=" . urlencode($this->_username) . '&Passwd=' . urlencode($this->_password);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $requestUrl);
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );

			ob_start();

			curl_exec($ch);
			curl_close($ch);
			$data = ob_get_contents();
			ob_end_clean();

			preg_match("/Auth=(.+?)[\n$]/",$data,$matches);

			if ( empty($matches[1]) )
				return NULL;

			$this->_auth = $matches[1];
		}

		private function _httpGet($requestUrl, $getArgs) {

			$url = sprintf('%1$s?%2$s', $requestUrl, $getArgs);
			$https = strpos($requestUrl, "https://");

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: GoogleLogin auth={$this->_auth}"));
			if($https === true) curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );

			ob_start();

			try {
				curl_exec($ch);
				curl_close($ch);
				$data = ob_get_contents();
				ob_end_clean();
			} catch(Exception $err) {
				$data = null;
			}

			return $data;
		}

		private function _httpPost($requestUrl, $getArgs) {

			$url = sprintf('%1$s?%2$s', $requestUrl, $getArgs);
			$https = strpos($requestUrl, "https://");

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: GoogleLogin auth={$this->_auth}"));
			if($https === true) curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );

			ob_start();

			try {
				curl_exec($ch);
				curl_close($ch);
				$data = ob_get_contents();
				ob_end_clean();
			} catch(Exception $err) {
				$data = null;
			}

			return $data;
		}

		private function _valid_email($email) {

			// Need to improve this obviously

			if ( strstr($email,"@") )
				return TRUE;

			return FALSE;
		}

		public function listStarred() {

			$gUrl = "http://www.google.com/reader/atom/user/-/state/com.google/starred";

			$args = sprintf('ck=%1$s', time());

			$data = simplexml_load_string( $this->_httpGet($gUrl, $args) );

			$BODY = "";

			$ITEMS = array();

			foreach($data->entry as $item => $array) {
				$feedIdx = strpos($array->source->id,"feed/http");
				$Feed = substr($array->source->id,$feedIdx);
				$ITEMS[ (string)$array->id ] = $Feed;
				$BODY .= $array->link['href'] . "\n";
			}

			if ( count($ITEMS) && !empty($this->_notify) )
				mail($this->_notify,"Daily Starred Items",$BODY);
			else
				print "$BODY\n";

			if ( !empty($this->_delete) ) {

				foreach($ITEMS as $ID => $FEED) {

					$this->_getToken();

					$STRING="r=user/-/state/com.google/starred&async=true&s={$FEED}&i={$ID}&T={$this->_token}";

					$ch = curl_init();
					curl_setopt($ch,CURLOPT_URL, 'https://www.google.com/reader/api/0/edit-tag?client={$this->_username}');
					curl_setopt($ch,CURLOPT_HTTPHEADER, array("Authorization: GoogleLogin auth={$this->_auth}"));
					curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
					curl_setopt($ch,CURLOPT_POST,true);
					curl_setopt($ch,CURLOPT_POSTFIELDS,$STRING);
					curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, true);

					ob_start();

					try {
						curl_exec($ch);
						curl_close($ch);
						$data = ob_get_contents();
						ob_end_clean();
					} catch(Exception $err) {
						$data = null;
					}
				}
			}
		}

		public function set_delete($state) {

			if ( empty($state) )
				$this->_delete = FALSE;
			else
				$this->_delete = TRUE;
		}

		public function set_notify($email) {

			if ( $this->_valid_email($email) )
				$this->_notify = $email;
		}
	}
?>
