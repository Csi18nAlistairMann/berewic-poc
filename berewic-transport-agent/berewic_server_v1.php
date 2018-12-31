<?php
//
// globals
error_reporting(E_ALL);

define('BEREWIC_SECRET', 'thisisasecret');
define('BEREWIC_DIGEST_SECRET', 'thisisapreimage');

define('CONST_ABS_NUM_PARAMS', 3);
define('CONST_LEN_CRC32', 8);
define('CONST_LEN_RIPEMD160', 40);
define('CONST_RATE_TXT_ZERO', 'zero');
define('CONST_RATE_TXT_LOW', 'low');
define('CONST_RATE_TXT_NORMAL', 'normal');
define('CONST_RATE_DEFAULT', CONST_RATE_TXT_NORMAL);
define('CONST_TXT_IDV1', 'idv1');
define('CONST_TXT_RATEV1', 'ratev1');
define('CONST_TXT_AUTHV1', 'authv1');
define('CONST_MAX_QUERY_STRING_LEN', 255);
define('CONST_MIN_BONDING_PERIOD', 1814400);  // 1,814,400 = 3 weeks

define('ERR_QUERY_TOO_LONG', 10000);
define('ERR_QUERY_WRONG_NUMBER_KEYS', 10001);
define('ERR_QUERY_NOT_KEYVALUE_PAIR', 10002);
define('ERR_QUERY_BAD_CRC32', 10003);
define('ERR_QUERY_BAD_KEY', 10004);
define('ERR_QUERY_BAD_RIPEMD160', 10005);
define('ERR_QUERY_BAD_RATE', 10006);
define('ERR_QUERY_INCOMPLETE', 10007);
define('ERR_QUERY_AUTH_FAILURE', 10008);

function fakeAResponse($idv1, $ratev1) {
	if ($ratev1 === CONST_RATE_TXT_ZERO) {
		// This connection doesn't need a bond
		// Handle as if bond seen on the blockchain okay

	} else {
		switch ($ratev1) {
		case CONST_RATE_TXT_LOW:
			$value = '0.0002';
			break;
		case CONST_RATE_TXT_NORMAL:
			$value = '0.0004';
			break;
		default:
			$value = '0.0006';
			break;
		}
	}
	$sugg1 = array('version' => '0.1',
				   'type' => 'bond',
				   'value' => array('currency' => 'btc',
									'value' => $value),
				   'network' => array('networkname' => 'testnet',
									  'seller-address' => '2MvHsfFpR6FBxs8vNNTKBe46vnhuYtDLpRR'),
				   'min-timeout' => array('minblockheight' =>
										  time() + CONST_MIN_BONDING_PERIOD));
	$sugg2 = array('version' => '0.1',
				   'type' => 'payment',
				   'value' => array('currency' => 'Send me a postcard',
									'value' => '1.0'),
				   'network' => array('networkname' => 'snailmail',
									  'address' => 'Santa Claus, North Pole, H0H 0H0, Canada'),
				   'timing' => array('to-arrive-by' => '201901010000'));
	$response = array('version' => '0.1',
					  'timestamp' => time(),
					  $sugg1,
					  $sugg2
					  );
	$json = json_encode($response);
	$hash = hash('ripemd160', $json . BEREWIC_SECRET);
	$response['hash'] = $hash;
	return json_encode($response);
}

function main($query_string) {
	$shenanigan = array();

	if (strlen($query_string) > CONST_MAX_QUERY_STRING_LEN) {
		$shenanigan[] = ERR_QUERY_TOO_LONG;
		// 400 bad request
	} else {
		$parameters = explode('&', $query_string);
		if (sizeof($parameters) !== CONST_ABS_NUM_PARAMS) {
			$shenanigan[] = ERR_QUERY_WRONG_NUMBER_KEYS ;
			// 400 bad request
		} else {
			$idv1 = false;
			$ratev1 = false;
			$authv1 = false;
			foreach($parameters as $pair) {
				$parameter = explode('=', $pair);
				if (sizeof($parameter) != 2) {
					$shenanigan[] = ERR_QUERY_NOT_KEYVALUE_PAIR ;
					// 400 bad request
				} else {
					$key = strtolower($parameter[0]);
					$value = $parameter[1];
					switch ($key) {
					case CONST_TXT_IDV1:
						if (strlen($value) !== CONST_LEN_CRC32) {
							$shenanigan[] = ERR_QUERY_BAD_CRC32;
							// 400 bad request
						} else {
							$idv1 = $value;
						}
						break;
					case CONST_TXT_RATEV1:
						switch ($value) {
						case CONST_RATE_TXT_ZERO:
						case CONST_RATE_TXT_LOW:
						case CONST_RATE_TXT_NORMAL:
							$ratev1 = $value;
							break;
						default:
							$shenanigan[] = ERR_QUERY_BAD_RATE;
							// 400 bad request
							break;
						}
						break;
					case CONST_TXT_AUTHV1:
						if (strlen($value) !== CONST_LEN_RIPEMD160) {
							$shenanigan[] = ERR_QUERY_BAD_RIPEMD160;
							// 400 bad request
						} else {
							$authv1 = $value;
						}
						break;
					default:
						$shenanigan[] = ERR_QUERY_BAD_KEY;
						// 400 bad request
						break;
					}
				}
			}
		}
	}
	$found = false;
	if ($idv1 === false || $ratev1 === false || $authv1 === false) {
		$shenanigan[] = ERR_QUERY_INCOMPLETE;
		// 400 bad request
	} else {
		if ($authv1 !== hash('ripemd160', $idv1 . $ratev1 . BEREWIC_SECRET)) {
			$shenanigan[] = ERR_QUERY_AUTH_FAILURE;
			// 400 bad request
		} else {
			$found = true;
		}
	}
	//
	//
	if ($found === true) {
		echo fakeAResponse($idv1, $ratev1);
	} else {
		var_dump($shenanigan);
	}
	return;
}

main($_SERVER['QUERY_STRING']);
?>
