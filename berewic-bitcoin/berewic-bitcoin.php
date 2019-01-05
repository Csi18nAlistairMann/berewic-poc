<?php
//
// globals
declare(strict_types = 1);
bcscale(9); // A satoshi tracks the 8th decimal place. I say 9 for rounding
//
// imports
require_once('easybitcoin.php');
require_once('berewic-defines.php');
require_once('base58.php');
//
// defines
define('EXC_ADDRESS_FAILS_CHECKS', 'Address doesn\'t pass checks');
define('EXC_BAD_JSON', 'JSON doesn\'t appear to be any good');
define('EXC_BAD_LOCKTIME', 'Locktime doesn\'t appear to be in range');
define('EXC_BAD_SIGNING_VAL', 'Sign this? Value not true or false');
define('EXC_BAD_TXID', 'Transaction ID does not pass checks');
define('EXC_HEX_BAD_VERSIONNO', 'Raw transaction with bad version no');
define('EXC_NOT_BUYER_NOR_SELLER', 'Address doesn\'t belong to buyer or seller');
define('EXC_REACHED_DARK_TERRITORY', 'Execution reached code it shouldn\'t');
define('EXC_TRADE_WITH_SELF', 'Buyer and seller the same address? Seems unlikely');

class hexTransaction {
  //
  // hexTransaction class
  //
  // bitcoind does not yet support htlc or htlb via the rpc api
  // (at v0.17.1.0-gef70f9b52b851c7997a9f1a0834714e3eebc1fd8)
  // so instead I 'hand-craft' the changes required to the raw
  // transaction.
  //
  // This class allows decomposing, changing and recomposing of
  // the hex. The previous version was procedural and guessed
  // string positions. It's questionable if there's a next version
  // of this assuming bitcoind does adopt BIP-0199 etc.
  //
  // I'm aware that this code forces use of one byte varints, and
  // therefore constrains number of inputs and outputs to <= 253
  // and length of witness data to 253 bytes also. Later code
  // even limits inputs to one and outputs to <= 2. Very obvious
  // checks are also missing. You really don't want to be using
  // this code on mainnet.
  //
  private $current;

  function __construct($hex, $debug = false) {
    $this->decompose($hex, $debug);
  }

  function decompose($hex, $debug = false) {
    $new = array();

    if (substr($hex, 0, 8) === '01000000' ||
	substr($hex, 0, 8) === '02000000') {
      $new['versionno'] = substr($hex, 0, 8);
      $hex = substr($hex, 8);
      if ($debug) {
	echo "Version: " . $new['versionno'] . "\n";
      }

    } else {
      throw new Exception('EXC_HEX_BAD_VERSIONNO');
    }

    if (substr($hex, 0, 4) === '0001') {
      $new['flag'] = substr($hex, 0, 4);
      $hex = substr($hex, 4);
      $segwit = true;
      if ($debug) {
	echo "Flag: " . $new['flag'] . "\n";
      }

    } else {
      $new['flag'] = null;
      $segwit = false;
      if ($debug) {
	echo "Flag: none\n";
      }
    }

    // limited to <= 253 inputs for now
    $new['incounter'] = substr($hex, 0, 2);
    $hex = substr($hex, 2);
    if ($debug) {
      echo "Incounter: " . $new['incounter'] . "\n";
    }

    // handle the inputs
    for ($a = 0; $a < bc_hexdec($new['incounter']); $a++) {
      $txid = substr($hex, 0, 64);
      $vout = substr($hex, 64, 8);
      $scriptsig_sz = bc_hexdec(substr($hex, 72, 2)) * 2;
      $scriptsig = substr($hex, 72, 2 + $scriptsig_sz);
      $sequence = substr($hex, 72 + 2 + $scriptsig_sz, 8);
      $new['inputs'][] = array('inputtxid' => $txid,
			       'inputvout' => $vout,
			       'scriptsig' => $scriptsig,
			       'sequence' => $sequence);
      $hex = substr($hex, 72 + 2 + $scriptsig_sz + 8);
    }
    if ($debug) {
      echo "Inputs:\n";
      var_dump($new['inputs']);
    }

    // limited to <= 253 outputs for now
    $new['outcounter'] = substr($hex, 0, 2);
    $hex = substr($hex, 2);
    if ($debug) {
      echo "Outcounter: " . $new['outcounter'] . "\n";
    }

    // handle the outputs
    for ($a = 0; $a < bc_hexdec($new['outcounter']); $a++) {
      $satoshis = substr($hex, 0, 16);
      $scriptpubkey_sz = bc_hexdec(substr($hex, 16, 2)) * 2;
      $scriptpubkey = substr($hex, 16, 2 + $scriptpubkey_sz);
      $new['outputs'][] = array('satoshis' => $satoshis,
				'scriptpubkey' => $scriptpubkey);
      $hex = substr($hex, 16 + 2 + $scriptpubkey_sz);
    }
    if ($debug) {
      echo "Outputs:\n";
      var_dump($new['outputs']);
    }

    if ($segwit === true) {
      for ($a = 0; $a < bc_hexdec($new['incounter']); $a++) {
	// limited to <= 253 witnesses for now
	$num_witnesses = substr($hex, 0, 2);
	$new['witnesses'][] = $num_witnesses;
	$hex = substr($hex, 2);
	for ($b = 0; $b < bc_hexdec($num_witnesses); $b++) {
	  // limited to scripts <= 253 bytes for now
	  $sig_sz = bc_hexdec(substr($hex, 0, 2)) * 2;
	  $witness = substr($hex, 0, 2 + $sig_sz);
	  $new['witnesses'][] = $witness;

	  $hex = substr($hex, 2 + $sig_sz);
	}
      }
      if ($debug) {
	echo "Witnesses:\n";
	var_dump($new['witnesses']);
      }
    }

    $new['locktime'] = substr($hex, 0, 8);
    $hex = substr($hex, 8);
    if ($debug) {
      echo "Locktime: " . $new['locktime'] . "\n";
    }
    // final check - we shouldn't have anything left

    $this->current = $new;
  }

  function recompose() {
    $hex = '';
    $hex .= $this->current['versionno'];

    if ($this->current['flag'] !== null) {
      $segwit = true;
      $hex .= $this->current['flag'];

    } else {
      $segwit = false;
    }

    $hex .= $this->current['incounter'];
    $inputs = $this->current['inputs'];
    for ($a = 0; $a < bc_hexdec($this->current['incounter']); $a++) {
      $input = array_shift($inputs);
      $hex .= $input['inputtxid'];
      $hex .= $input['inputvout'];
      $hex .= $input['scriptsig'];
      $hex .= $input['sequence'];
    }

    $hex .= $this->current['outcounter'];
    $outputs = $this->current['outputs'];
    for ($a = 0; $a < bc_hexdec($this->current['outcounter']); $a++) {
      $output = array_shift($outputs);
      $hex .= $output['satoshis'];
      $hex .= $output['scriptpubkey'];
    }

    if ($segwit === true) {
      $witnesses = $this->current['witnesses'];
      for ($a = 0; $a < bc_hexdec($this->current['incounter']); $a++) {
	$num_witnesses = array_shift($witnesses);
	$hex .= $num_witnesses;
	for ($b = 0; $b < bc_hexdec($num_witnesses); $b++) {
	  $hex .= array_shift($witnesses);
	}
      }
    }

    $hex .= $this->current['locktime'];
    return $hex;
  }

  function changeSequence($newval) {
    // this limits us to one input right now
    $this->current['inputs'][0]['sequence'] = $newval;
  }

  function changeLocktime($newval) {
    $this->current['locktime'] = $newval;
  }

  function pushWitness($witness) {
    // this limits us to one input and 9 outputs right now
    $num_witnesses = bc_hexdec(array_shift($this->current['witnesses']));
    array_unshift($this->current['witnesses'], $witness);
    $num_witnesses++;
    $num_witnesses = bc_hexdec($num_witnesses);
    if (strlen($num_witnesses) < 2) {
      $num_witnesses = '0' . $num_witnesses;
    }
    array_unshift($this->current['witnesses'], $num_witnesses);
  }
}

class berewicCrypto {
  //
  // berewicCrypto
  //
  // Only purposes of class right now is to simulate OP_HASH160 and
  // change the endianness of supplied hex
  //
  function op_hash160($preimage) {
    $preimage1 = hexStringToByteString($preimage);
    $hash1 = hash('sha256', $preimage1);
    $preimage2 = hexStringToByteString($hash1);
    $hash2 = hash('ripemd160', $preimage2);
    return $hash2;
  }

  function changeEndianNess($before, $add_count) {
    $during = $before;
    if (strlen($during) % 2 !== 0) {
      // hex should be even chars long
      $during = '0' . $during;
    }
    if (strlen($during) > 2) {
      // change the endianess: 0x1f2b = 2b1f
      $from = $during;
      $to = '';
      $count = 0;
      while (strlen($from) > 0) {
	$move = substr($from, -2);
	$from = substr($from, 0, -2);
	$to .= $move;
	$count++;
      }
      if ($add_count === true) {
	$after = '0' . $count . $to;
      } else {
	$after = $to;
      }

    } else {
      if ($add_count) {
	$count = '01';
      } else {
	$count = '';
      }
      $after = '01' . $during;
    }
    return $after;
  }
}

class berewicRawTransaction {
  //
  // berewicRawTransaction
  //
  // This class to obtain the utxo txid and vout for what we're
  // about to redeem.
  //
  private $conn = null;
  private $decoded = null;
  private $nonchange_output_script = null;
  private $nonchange_vout = null;
  private $txinfo = null;
  // next two unimplemented
  private $change_output_script = null;
  private $change_vout = null;

  function __construct($conn) {
    $this->conn = $conn;
  }

  function loadFromTxid($txid) {
    $this->decoded = $this->conn->getrawtransaction($txid, true);
  }

  function storeUtxoMatchingAmount($funding_amount) {
    // More code limiting outputs
    if (bccomp(strval($this->decoded['vout'][0]['value']),
	       $funding_amount) === 0) {
      $voutn = $this->decoded['vout'][0];

    } elseif (bccomp(strval($this->decoded['vout'][1]['value']),
		     $funding_amount) === 0) {
      $voutn = $this->decoded['vout'][1];

    } else {
      throw new Exception('EXC_REACHED_DARK_TERRITORY');
    }
    $this->setUTXOVout(strval($voutn['n']));
    $this->setUTXOOutputScript(strval($voutn['scriptPubKey']['hex']));
  }

  function getUTXOVout() {
    return $this->nonchange_vout;
  }

  function setUTXOVout($nonchange_vout){
    if (!strlen($nonchange_vout) > 0) {
      throw new Exception('EXC_BAD_TXID');
    }
    $this->nonchange_vout = $nonchange_vout;
  }

  function getUTXOOutputScript() {
    return $this->nonchange_output_script;
  }

  function setUTXOOutputScript($nonchange_output_script){
    if (!strlen($nonchange_output_script) > 0) {
      throw new Exception('EXC_BAD_NONCHANGE_OUTPUT_SCRIPT');
    }
    $this->nonchange_output_script = $nonchange_output_script;
  }
}

class berewicRedeemScript {
  //
  // berewicRedeemScript
  //
  // Class to handle questions about the redeemScript
  //
  private $address_buyer = null;
  private $address_seller = null;
  private $conn = null;
  private $do_signing = null;
  private $locktime = '0';

  function __construct($conn) {
    $this->conn = $conn;
  }

  function getP2SHAddress(): string {
    $rv = $this->conn->decodescript($this->getRedeemScript());
    return ($this->conn->response['result']['segwit']['p2sh-segwit']);
  }

  function getRedeemScript(): string {
    if ($this->locktime < CONST_NLOCKTIME_BORDER) {
      $locktime2 = berewicCrypto::changeEndianNess($this->locktime, true);

    } else {
      $locktime2 = $this->locktime;
    }

    $rv = $this->conn->getaddressinfo($this->getAddress(CONST_SELLER));
    $seller_pubkey = $this->conn->response['result']['pubkey'];
    $seller_pubkey_hash = berewicCrypto::op_hash160($seller_pubkey);
    $seller_pubkey_hash2 = bc_dechex(strlen($seller_pubkey_hash) / 2) .
      $seller_pubkey_hash;

    $rv = $this->conn->getaddressinfo($this->getAddress(CONST_BUYER));
    $buyer_pubkey = $this->conn->response['result']['pubkey'];
    $buyer_pubkey_hash = berewicCrypto::op_hash160($buyer_pubkey);
    $buyer_pubkey_hash2 = bc_dechex(strlen($buyer_pubkey_hash) / 2) .
      $buyer_pubkey_hash;

    // OP_IF
    //   OP_DUP OP_HASH160 <seller pkh>
    // OP_ELSE
    //   <num> OP_CTLVP OP_DUP OP_HASH160 <buyer pkh>
    // OP_ENDIF OP_EQUALVERIFY
    $redeemscript = CONST_HTLB01 .
      CONST_HTLB03 . $seller_pubkey_hash2 .
      CONST_HTLB05 .
      $locktime2 . CONST_HTLB07 . $buyer_pubkey_hash2 .
      CONST_HTLB09;

    if ($this->getDoSigning()) {
      // OP_CHECKSIG
      $redeemscript .= CONST_HTLB0A;

    } else {
      // OP_DROP the signature data, we aint using it
      // OP_TRUE to finish
      $redeemscript .= CONST_OP_DROP;
      $redeemscript .= CONST_OP_TRUE;
    }
    return $redeemscript;
  }

  function getAddress($which) {
    if ($which !== CONST_SELLER && $which !== CONST_BUYER) {
      throw new Exception('EXC_NOT_BUYER_NOR_SELLER');
    }
    if ($which === CONST_SELLER) {
      return $this->address_seller;

    } elseif ($which === CONST_BUYER) {
      return $this->address_buyer;

    } else {
      throw new Exception('EXC_REACHED_DARK_TERRITORY');
    }
  }

  function setAddress($which, $address) {
    if ($which !== CONST_SELLER && $which !== CONST_BUYER) {
      throw new Exception('EXC_NOT_BUYER_NOR_SELLER');
    }
    if (!strlen($address) > 1) {
      throw new Exception('EXC_ADDRESS_FAILS_CHECKS');
    }
    if ($which === CONST_SELLER) {
      if ($this->address_buyer === $address) {
	throw new Exception('EXC_TRADE_WITH_SELF');
      }
      $this->address_seller = $address;

    } elseif ($which === CONST_BUYER) {
      if ($this->address_seller === $address) {
	throw new Exception('EXC_TRADE_WITH_SELF');
      }
      $this->address_buyer = $address;

    } else {
      throw new Exception('EXC_REACHED_DARK_TERRITORY');
    }
    $this->modified = true;
  }

  function getDoSigning() {
    return $this->do_signing;
  }

  function setDoSigning($val) {
    if ($val !== true && $val !== false) {
      throw new Exception('EXC_BAD_SIGNING_VAL');
    }
    $this->do_signing = $val;
  }

  function setLocktime($locktime) {
    if (!$locktime >= 1) {
      throw new Exception('EXC_BAD_LOCKTIME');
    }
    $this->locktime = $locktime;
  }
}

class berewicBond {
  //
  // berewicBond
  //
  // class for handling hashed time-locked bonds
  //
  private $conn = null;
  public $fundtx = null;
  public $redeemscript = null;
  private $txid = null;

  function __construct($conn) {
    $this->conn = $conn;
  }

  function constructBond($htlb_signing, $seller_address,
			 $buyer_address, $locktime) {
    $this->redeemscript = new berewicRedeemScript($this->conn);
    $this->redeemscript->setDoSigning($htlb_signing);
    $this->redeemscript->setAddress(CONST_SELLER, $seller_address);
    $this->redeemscript->setAddress(CONST_BUYER, $buyer_address);
    $this->redeemscript->setLocktime($locktime);
  }

  function postBond($funding_amount) {
    $p2sh_address = $this->redeemscript->getP2SHAddress();
    //
    // From p2sh-multisig: consider calling SetTxFee()
    $rv = $this->conn->sendtoaddress($p2sh_address,
				     $funding_amount);
    $txid = strval($this->conn->response['result']);
    $this->setBondTxid($txid);

    //
    // Save vout and output_script
    $this->fundtx = new berewicRawTransaction($this->conn);
    $this->fundtx->loadFromTxid($txid);
    $this->fundtx->storeUtxoMatchingAmount($funding_amount);
    return $txid;
  }

  function createAndSignRedemption($redemption_address, $redeeming_amount, $funding_amount, $redeemer) {
    // create a raw transaction
    $pt1 = array(array('txid' => $this->getBondTxid(),
		       'vout' => intval($this->fundtx->getUTXOVout())));
    $pt2 = array($redemption_address => $redeeming_amount);
    $rv = $this->conn->createrawtransaction($pt1, $pt2);
    $unsigned_rawtx = strval($this->conn->response['result']);

    // get the private keys
    if ($redeemer === CONST_SELLER) {
      $rv = $this->conn->dumpprivkey($this->redeemscript->getAddress(CONST_SELLER));

    } elseif ($redeemer === CONST_BUYER) {
      $rv = $this->conn->dumpprivkey($this->redeemscript->getAddress(CONST_BUYER));
    }
    $user1_privkey = $this->conn->response['result'];

    // sign off with the keys
    // not sure relationship between this sign off, and htlb sign off down
    // below
    $pt1 = array(array('txid' => $this->getBondTxid(),
		       'vout' => intval($this->fundtx->getUTXOVout()),
		       'scriptPubKey' => $this->fundtx->getUTXOOutputScript(),
		       'redeemScript' => $this->getRedeemScript(),
		       'amount' => $funding_amount));
    $pt2 = array($user1_privkey);
    $rv = $this->conn->signrawtransactionwithkey($unsigned_rawtx, $pt2, $pt1);
    $signed_rawtx = $this->conn->response['result']['hex'];
    $script_sz = substr($signed_rawtx, 86, 2);
    if ($script_sz !== '23') {
      echo "Unexpected script size! Exiting\n";
      exit;
    }
    return $signed_rawtx;
  }

  function getRedeemScript() {
    return $this->redeemscript->getRedeemScript();
  }

  function getP2SHAddress() {
    return $this->redeemscript->getP2SHAddress();
  }

  function getBondTxid() {
    return $this->txid;
  }

  function setBondTxid($txid) {
    if (!strlen($txid) > 0) {
      throw new Exception('EXC_BAD_TXID');
    }
    $this->txid = $txid;
  }
}
?>
