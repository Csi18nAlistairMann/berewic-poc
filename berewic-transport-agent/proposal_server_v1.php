<?php
//
// globals
declare(strict_types = 1);
error_reporting(E_ALL);
//
require_once('./berewic-bitcoin.php');

define('BEREWIC_SECRET', 'thisisasecret');
define('BEREWIC_DIGEST_SECRET', 'thisisapreimage');

define('CONST_ABS_NUM_PARAMS', 3);
define('CONST_SELLER_ADDRESS', '2Mv7JjZLNrueGqyWcxisBL3jHWKxSpkYbsu');
define('CONST_LEN_CRC32', 8);
define('CONST_LEN_RIPEMD160', 40);
define('CONST_RATE_TXT_ZERO', 'zero');
define('CONST_RATE_TXT_LOW', 'low');
define('CONST_RATE_TXT_NORMAL', 'normal');
define('CONST_RATE_DEFAULT', CONST_RATE_TXT_NORMAL);
define('CONST_TXT_IDV1', 'idv1');
define('CONST_TXT_RATEV1', 'ratev1');
define('CONST_TXT_AUTHV1', 'authv1');
define('CONST_MAX_PUT_UPLOAD_LEN', 320);
define('CONST_MAX_QUERY_STRING_LEN', 255);
define('CONST_MIN_BONDING_PERIOD', 1 * 60 * 60);  // 1,814,400 = 3 weeks
define('CONST_PROPOSALS_PATHANDFILE', '/home/httpd-writes/accepted-proposals');

define('ERR_QUERY_TOO_LONG', 10000);
define('ERR_QUERY_WRONG_NUMBER_KEYS', 10001);
define('ERR_QUERY_NOT_KEYVALUE_PAIR', 10002);
define('ERR_QUERY_BAD_CRC32', 10003);
define('ERR_QUERY_BAD_KEY', 10004);
define('ERR_QUERY_BAD_RIPEMD160', 10005);
define('ERR_QUERY_BAD_RATE', 10006);
define('ERR_QUERY_INCOMPLETE', 10007);
define('ERR_QUERY_AUTH_FAILURE', 10008);
define('ERR_DATA_TOO_LONG', 10009);
define('ERR_DATA_NOT_JSON', 10010);
define('ERR_DATA_MISSING_ZERO_STRUCTURE', 10011);
define('ERR_DATA_INVALID_VALUE', 10012);
define('ERR_QUERY_TOO_SHORT', 10013);

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
				    'buyer-address' => '',
				    'p2sh-address' => '',
				    'seller-address' => CONST_SELLER_ADDRESS),
		 'min-timeout' => array('minblocktime' =>
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

function main_get($query_string){
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
}

// to PUT: /proposal/NNN at the moment is to do no more than
// have the code return the p2sh-address.
function main_put($query_string, $put_upload) {
  $found = false;
  $shenanigan = array();
  if ($query_string == '') {
    // Should not be an empty query string for PUT
    $shenanigan[] = ERR_DATA_TOO_SHORT;

  } else {
    // Put /proposal
    if (strlen($put_upload) > CONST_MAX_PUT_UPLOAD_LEN) {
      $shenanigan[] = ERR_DATA_TOO_LONG;
      // 400 bad request

    } else {
      $data = json_decode($put_upload, true);
      if ($data === NULL) {
	$shenanigan[] = ERR_DATA_NOT_JSON;

      } elseif (!isset($data['0'])) {
	$shenanigan[] = ERR_DATA_MISSING_ZERO_STRUCTURE;

      } else {
	// not breaking errors down more for an experiment
	$zero_arr = $data['0'];
	if (!isset($zero_arr['version']) ||
	    !isset($zero_arr['type']) ||
	    !isset($zero_arr['value']) ||
	    !isset($zero_arr['network']) ||
	    !isset($zero_arr['min-timeout'])) {
	  $shenanigan[] = ERR_DATA_MISSING_DATA;

	} else {
	  $value = $zero_arr['value'];
	  $network = $zero_arr['network'];
	  $min_timeout = $zero_arr['min-timeout'];
	  if (!isset($value['currency']) ||
	      !isset($value['value']) ||
	      !isset($network['networkname']) ||
	      !isset($network['buyer-address']) ||
	      !isset($network['p2sh-address']) ||
	      !isset($network['seller-address']) ||
	      !isset($min_timeout['minblocktime'])) {
	    $shenanigan[] = ERR_DATA_MISSING_DATA;

	  } elseif ($zero_arr['version'] !== '0.1' ||
		    $zero_arr['type'] !== 'bond' ||
		    $value['currency'] !== 'btc' ||
		    $value['value'] !== '0.0004' ||
		    $network['networkname'] !== 'testnet' ||
		    !is_int($min_timeout['minblocktime'])) {
	    $shenanigan[] = ERR_DATA_INVALID_VALUE;

	  } elseif ($min_timeout['minblocktime'] < CONST_NLOCKTIME_BORDER ||
		    $min_timeout['minblocktime'] > time() + 50 * 24 * 60 * 60) {
	    $shenanigan[] = ERR_DATA_INVALID_VALUE;

	  } elseif (strlen($network['buyer-address']) < 30 ||
		    strlen($network['buyer-address']) > 40 ||
		    strlen($network['p2sh-address']) !== 0 ||
		    strlen($network['seller-address']) < 30 ||
		    strlen($network['seller-address']) > 40) {
	    // plenty of checking that could and should be done
	    // but this is an experiment. Example: we don't
	    // even check if the addresses belong to us
	    $shenanigan[] = ERR_DATA_INVALID_VALUE + 1;

	  } else {
	    $found = true;
	  }
	}
      }
    }
  }
  //
  //
  if ($found === true) {
    // If the bond was putted okay (no errors etc) establish and
    // return the p2sh address
    $conn = new Bitcoin(UN, PW, HOST, PORT);
    $htlb_signing = false;  // we don't have signing logic yet
    $htlb = new berewicBond($conn);
    $htlb->constructBond($htlb_signing, $network['seller-address'],
			 $network['buyer-address'],
			 bc_dechex($min_timeout['minblocktime']));
    $p2sh_address = $htlb->getP2SHAddress();
    //
    // Import this into the local wallet as a watch-only address
    $rv = $conn->importaddress($p2sh_address, 'proposals', false);

    //
    // make a copy of the accepted parts of the proposal and
    // p2sh address so we can later redeem the funds
    $network['p2sh-address'] = $p2sh_address;
    $prop = array('0' => array('version' => $zero_arr['version'],
			       'type' => $zero_arr['type'],
			       'value' => array('currency' => $value['currency'],
						'value' => $value['value']),
			       'network' => array('networkname' => $network['networkname'],
						  'buyer-address' => $network['buyer-address'],
						  'p2sh-address' => $network['p2sh-address'],
						  'seller-address' => $network['seller-address']),
			       'minblocktime' => $min_timeout['minblocktime'])
		  );
    $rv = file_put_contents(CONST_PROPOSALS_PATHANDFILE,
			    json_encode($prop, JSON_FORCE_OBJECT) . "\n",
			    FILE_APPEND | LOCK_EX);
    //
    // Only return okay once we've recorded the proposal
    if ($rv === false) {
      echo "+NOK failed to store the proposal at this end\n";
    } else {
      echo "+OK $p2sh_address\n";
    }

  } else {
    var_dump($shenanigan);
  }
}

function main($method, $query_string, $put_upload) {
  if ($method === 'GET') {
    // A user agent is asking: what do I need to access a resource?
    main_get($query_string);

  } elseif ($method === 'PUT') {
    // A user agent is agreeing to a contract: We capture their response
    // as if valid will now include all data needed to create the
    // p2sh-address concerned.
    main_put($query_string, $put_upload);
  }
  return;
}

main($_SERVER['REQUEST_METHOD'], $_SERVER['QUERY_STRING'],
     trim(file_get_contents("php://input")));
?>
