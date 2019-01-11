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
define('CONST_LEN_CRC32', 8);
define('CONST_LEN_RIPEMD160', 40);
define('CONST_RATE_TXT_ZERO', 'zero');
define('CONST_RATE_TXT_LOW', 'low');
define('CONST_RATE_TXT_NORMAL', 'normal');
define('CONST_RATE_DEFAULT', CONST_RATE_TXT_NORMAL);
define('CONST_TXT_IDV1', 'idv1');
define('CONST_TXT_RATEV1', 'ratev1');
define('CONST_TXT_AUTHV1', 'authv1');
define('CONST_MAX_POST_UPLOAD_LEN', 352);
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
define('ERR_DATA_TOO_LONG', 10009);
define('ERR_DATA_NOT_JSON', 10010);
define('ERR_DATA_MISSING_ZERO_STRUCTURE', 10011);
define('ERR_DATA_INVALID_VALUE', 10012);

function main_get($query_string){
  // GET /bond - unimplemented
  // GET /bond/<P2SH address> - GET status of bond specified
}

function main_post($query_string, $post_upload) {
  $shenanigan = array();
  $found = false;
  if ($query_string != '') {
    // Should not be a query string for POST

  } else {
    // POST /bond
    if (strlen($post_upload) > CONST_MAX_POST_UPLOAD_LEN) {
      $shenanigan[] = ERR_DATA_TOO_LONG;
      // 400 bad request

    } else {
      $data = json_decode($post_upload, true);
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
		    strlen($network['p2sh-address']) == 0 ||
		    strlen($network['seller-address']) < 30 ||
		    strlen($network['seller-address']) > 40) {
	    // plenty of checking that could and should be done
	    // but this is an experiment
	    $shenanigan[] = ERR_DATA_INVALID_VALUE;

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
    // If the bond was posted okay (so, not run out of funds, no errors etc)
    // the reference for it will be the P2SH address concerned.
    //echo fakeAResponse($idv1, $ratev1);

  } else {
    var_dump($shenanigan);
  }
}

function main($method, $query_string, $post_upload) {
  if ($method === 'GET') {
    main_get($query_string);

  } elseif ($method === 'POST') {
    // A user agent is asking us to post bond
    main_post($query_string, $post_upload);
    echo "+OK Consider it POSTed\n";
  }
  return;
}

main($_SERVER['REQUEST_METHOD'], $_SERVER['QUERY_STRING'],
     trim(file_get_contents("php://input")));
?>
