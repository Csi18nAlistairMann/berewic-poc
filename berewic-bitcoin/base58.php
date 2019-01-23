<?php
//
// This file developed from
// https://stackoverflow.com/questions/19233053/hashing-from-a-public-key-to-a-bitcoin-address-in-php
//

function hexStringToByteString($hexString): string {
  $len = strlen($hexString);
  $byteString = "";
  for ($i = 0; $i < $len; $i = $i + 2) {
    $charnum = hexdec(substr($hexString, $i, 2));
    $byteString .= chr($charnum);
  }
  return $byteString;
}

// BCmath version for huge numbers
function bc_arb_encode($num, $basestr) {
  if (!function_exists('bcadd')) {
    Throw new Exception('You need the BCmath extension.');
  }

  $base = strlen($basestr);
  $rep = '';

  while (true){
    if (strlen($num) < 2) {
      if (intval($num) <= 0) {
	break;
      }
    }
    $rem = bcmod($num, $base, 0);
    $rep = $basestr[intval($rem)] . $rep;
    $num = bcdiv(bcsub($num, $rem, 0), $base, 0);
  }
  return $rep;
}

function bc_arb_decode($num, $basestr) {
  if (!function_exists('bcadd')) {
    Throw new Exception('You need the BCmath extension.');
  }

  $base = strlen($basestr);
  $dec = '0';

  $num_arr = str_split((string)$num);
  $cnt = strlen($num);
  for ($i = 0; $i < $cnt; $i++) {
    $pos = strpos($basestr, $num_arr[$i]);
    if ($pos === false) {
      Throw new Exception(sprintf('Unknown character %s at offset %d', $num_arr[$i], $i));
    }
    $dec = bcadd(bcmul($dec, $base, 0), $pos, 0);
  }
  return $dec;
}

// base 58 alias
function bc_base58_encode($num) {
  return bc_arb_encode($num, '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
}

function bc_base58_decode($num) {
  return bc_arb_decode($num, '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
}

//hexdec with BCmath
function bc_hexdec($num) {
  return bc_arb_decode(strtolower($num), '0123456789abcdef');
}

function bc_dechex($num) {
  return bc_arb_encode($num, '0123456789abcdef');
}

function convert_to_base58($preimage, $version, $debug) {
  // step 1
  if ($debug) {
    echo "step1 " . $preimage . "\n";
  }
  $step1 = hexStringToByteString($preimage);
  if ($debug) {
    echo "step1a " . $step1 . "\n";
  }

  // step 2
  $step2 = hash("sha256", $step1);
  if ($debug) {
    echo "step2 " . $step2 . "\n";
  }

  // step 3
  $step3 = hash('ripemd160', hexStringToByteString($step2));
  if ($debug) {
    echo "step3 " . $step3 . "\n";
  }

  // step 4
  if (strlen($version) !== 2) {
    echo "version has bad length (should be 2) >$version<\n";
  }
  $step4 = $version . $step3;
  if ($debug) {
    echo "step4 " . $step4 . "\n";
  }

  // step 5
  $step5 = hash("sha256", hexStringToByteString($step4));
  if ($debug) {
    echo "step5 " . $step5 . "\n";
  }

  // step 6
  $step6 = hash("sha256", hexStringToByteString($step5));
  if ($debug) {
    echo "step6 " . $step6 . "\n";
  }

  // step 7
  $checksum = substr($step6, 0, 8);
  if ($debug) {
    echo "step7 " . $checksum . "\n";
  }

  // step 8
  $step8 = $step4 . $checksum;
  if ($debug) {
    echo "step8 " . $step8 . "\n";
  }

  // step 9
  // base conversion is from hex to base58 via decimal.
  // Leading hex zero converts to 1 in base58 but it is dropped
  // in the intermediate decimal stage. Simply added back manually.
  //
  // AM: I suspect this only applies to 00 version bytes
  $step8a = bc_hexdec($step8);
  if ($debug) {
    echo "step8a " . $step8a . "\n";
  }

  $step9 = bc_base58_encode($step8a);
  if ($debug) {
    echo "step9 " . $step9 . "\n";
  }

  if ($version === "00") {
    $step9a = "1" . $step9;
  } else {
    $step9a = $step9;
  }
  if ($debug) {
    echo "step9a " . $step9a . "\n\n";
  }

  return $step9a;
}

function test_base58() {
  require_once('berewic-defines.php');
  // step 1
  $preimage = '0450863AD64A87AE8A2FE83C1AF1A8403CB53F53E486D8511DAD8A04887E5B23522CD470243453A299FA9E77237716103ABC11A1DF38855ED6F2EE187E9C582BA6';
  $version = "05"; //CONST_LTC_VERSION_BYTE;

  $base58 = convert_to_base58($preimage, $version, true);
  echo "Base58: " . $base58 . "\n";

  /* for ($a = 0; $a < 256; $a++) { */
  /*   $version = bc_dechex($a); */
  /*   if (strlen($version) === 0) { */
  /*     $version = "00"; */
  /*   } */
  /*   if (strlen($version) === 1) { */
  /*     $version = "0" . $version; */
  /*   } */
  /*   $p2sh_address2 = convert_to_base58($preimage, $version, false); */
  /*   echo "$version " . $p2sh_address2; */
  /*   //    var_dump($p2sh_address2); */
  /*   if ($base58 !== $p2sh_address2) { */
  /*     echo ("Could not replicate address :-(\n"); */
  /*   } else { */
  /*     echo "Got it! >$a<\n"; */
  /*     exit; */
  /*   } */
  /* } */
  /* echo "Not got it :-("; */
  /* exit; */

  // step 1
  $preimage = '524104a882d414e478039cd5b52a92ffb13dd5e6bd4515497439dffd691a0f12af9575fa349b5694ed3155b136f09e63975a1700c9f4d4df849323dac06cf3bd6458cd41046ce31db9bdd543e72fe3039a1f1c047dab87037c36a669ff90e28da1848f640de68c2fe913d363a51154a0c62d7adea1b822d05035077418267b1a1379790187410411ffd36c70776538d079fbae117dc38effafb33304af83ce4894589747aee1ef992f63280567f52f5ba870678b4ab4ff6c8ea600bd217870a8b4f1f09f3a8e8353ae';
  $version = CONST_LTC_VERSION_BYTE;
  $base58 = convert_to_base58($preimage, $version, false);
  echo "Base58: " . $base58 . "\n";
}

// test_base58();
?>
