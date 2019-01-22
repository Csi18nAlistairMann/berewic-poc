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
define('CONST_BOND_URI_ROOT', '/bond/');
define('CONST_LEN_CRC32', 8);
define('CONST_LEN_RIPEMD160', 40);
define('CONST_RATE_TXT_ZERO', 'zero');
define('CONST_RATE_TXT_LOW', 'low');
define('CONST_RATE_TXT_NORMAL', 'normal');
define('CONST_RATE_DEFAULT', CONST_RATE_TXT_NORMAL);
define('CONST_TXT_IDV1', 'idv1');
define('CONST_TXT_RATEV1', 'ratev1');
define('CONST_TXT_AUTHV1', 'authv1');
define('CONST_MAX_LEN_BOND_URI', 45);
define('CONST_MIN_LEN_BOND_URI', 35);
define('CONST_MAX_POST_UPLOAD_LEN', 352);
define('CONST_MAX_QUERY_STRING_LEN', 255);
define('CONST_MIN_BONDING_PERIOD', 1814400);  // 1,814,400 = 3 weeks
define('CONST_PROPOSALS_PATHANDFILE', '/home/httpd-writes/accepted-proposals');
define('CONST_HEADER_CONFIRMATION', 'berewic-bond-confirmation');

define('LOCAL_BTA_ID', "78f7");

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
define('ERR_QUERY_MISSING', 10014);
define('ERR_QUERY_FOUND', 10015);
define('ERR_BOND_URI_LEN_TOO_LONG', 10016);
define('ERR_BOND_URI_LEN_TOO_SHORT', 10017);
define('ERR_BOND_URI_UNEXPECTED', 10018);
define('ERR_BOND_URI_CORRUPTED', 10019);
define('ERR_BOND_INSUFFICIENT_FUNDS', 10020);

function main_get($query_string, $request_uri){
  // GET /bond - unimplemented
  // GET /bond/<P2SH address> - GET status of bond specified
  $shenanigan = array();
  $amount_received = false;
  if ($query_string != '') {
    // Should not be a query string for POST
    $shenanigan[] = ERR_QUERY_FOUND;

  } else {
    $request_uri = substr($request_uri, 0, CONST_MAX_LEN_BOND_URI);
    // GET /bond/<P2SH ADDRESS>
    if (strlen($request_uri) === CONST_MAX_LEN_BOND_URI) {
      $shenanigan[] = ERR_BOND_LEN_TOO_LONG;

    } elseif (strlen($request_uri) < CONST_MIN_LEN_BOND_URI) {
      $shenanigan[] = ERR_BOND_LEN_TOO_SHORT;

    } elseif (substr($request_uri, 0, strlen(CONST_BOND_URI_ROOT)) !== CONST_BOND_URI_ROOT) {
      $shenanigan[] = ERR_BOND_URI_UNEXPECTED;

    } else {
      $unsafe_uri = substr($request_uri, strlen(CONST_BOND_URI_ROOT));
      $ok = true;
      for ($a = 0; $a < strlen($unsafe_uri); $a++) {
	if (!((ord($unsafe_uri[$a]) >= 48 && ord($unsafe_uri[$a]) <= 57) ||
	      (ord($unsafe_uri[$a]) >= 65 && ord($unsafe_uri[$a]) <= 90) ||
	      (ord($unsafe_uri[$a]) >= 97 && ord($unsafe_uri[$a]) <= 122))) {
	  $ok = false;
	}
      }

      if ($ok === false) {
	$shenanigan[] = ERR_BOND_URI_CORRUPTED;

      } else {
	$uri = $unsafe_uri;

	// What's the balance of this transaction? While it's zero it
	// hasn't arrived.
	$bitcoin = new Bitcoin(UN, PW, HOST, PORT);
	$bitcoin->getreceivedbyaddress($uri);
	$amount_received = strval($bitcoin->response['result']);
      }
    }
  }

  if ($amount_received === false ||
      $amount_received === "0") {
    echo "+NOK not arrived yet\n";

  } else {
    // check if the script is good. Maybe we could have done this
    // a bit earlier. Better to make the user agent hold on to the
    // details and hash/check those, but for this experiment lets
    // just draw them in from the file. Start from the end of file
    // and work earlier.
    $proposals_whole = file_get_contents(CONST_PROPOSALS_PATHANDFILE);
    $proposals_split = explode("\n", $proposals_whole);
    $found = false;
    while (sizeof($proposals_split) > 0 && $found === false) {
      $candidate_json = array_pop($proposals_split);
      $candidate_arr = json_decode($candidate_json, true);
      if ($candidate_arr['0']['network']['p2sh-address'] === $uri) {
	$found = $candidate_arr;
	continue;
      }
    }
    $redeemscript_good = false;
    if ($found !== false) {
      // We already know that the bond is properly formed, as we checked
      // it out before recording it. What we don't know is if amount
      // sent to the address is sufficient.
      if (bccomp($found['0']['value']['value'], $amount_received) < 0) {
	$shenanigan[] = ERR_BOND_INSUFFICIENT_FUNDS;

      } else {
	$redeemscript_good = true;
      }
    }

    if ($redeemscript_good !== true) {
      var_dump($shenanigan);

    } else {
      list($usec, $sec) = explode(" ", microtime());
      $mtime = strval($sec) . substr(strval($usec), 2, -2);
      $plain = 'idv1=' . $found['0']['idv1'];
      $plain .= '&bta=' . LOCAL_BTA_ID;
      $plain .= '&amount=' . $amount_received;
      $plain .= '&locktime=' . $found['0']['minblocktime'];
      $plain .= '&mtime=' . $mtime;
      $hash = hash('ripemd160', $plain . BEREWIC_SECRET);
      $confirmation = '&hash=' . $hash;
      echo "+OK " . CONST_HEADER_CONFIRMATION . ': ' . $plain . $confirmation . "\n";
    }
  }
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

    $bitcoin = new Bitcoin(UN, PW, HOST, PORT);

    $htlb_signing = false;
    $funding_amount = $value['value'];
    $locktime = $min_timeout['minblocktime'];
    $locktime_redeemscript = bc_dechex($locktime);

    $seller_address = $network['seller-address'];
    $buyer_address = $network['buyer-address'];

    $htlb = new berewicBond($bitcoin);
    $htlb->constructBond($htlb_signing, $network['seller-address'],
			 $network['buyer-address'],
			 $locktime_redeemscript);
    try {
      $txid = $htlb->postBond($funding_amount);
    } catch (Exception $e) {
      var_dump("Caught exception!");
    }
    echo "+OK TXID: $txid Consider it POSTed\n";

  } else {
    var_dump($shenanigan);
  }
}

function main($method, $query_string, $post_upload, $request_uri) {
  if ($method === 'GET') {
    main_get($query_string, $request_uri);

  } elseif ($method === 'POST') {
    // A user agent is asking us to post bond
    main_post($query_string, $post_upload);
  }
  return;
}

main($_SERVER['REQUEST_METHOD'], $_SERVER['QUERY_STRING'],
     trim(file_get_contents("php://input")), $_SERVER['REQUEST_URI']);
?>
