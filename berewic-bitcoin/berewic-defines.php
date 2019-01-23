<?php
//
// defines
define('HOST', '127.0.0.1');

define('ADDR_TYPE_SEGWIT', 'p2sh-segwit');
define('CONST_BC_SCALE', 9);
define('CONST_BODGE', '00'); // For off-by-one stack issue
define('CONST_BODGE_SZ', strlen(CONST_BODGE));
define('CONST_HTLC01', '63a9'); // OP_IF OP_SHA256
define('CONST_HTLC01_SZ', strlen(CONST_HTLC01));
define('CONST_HTLC03', '8876a9'); // OP_EQUALVERIFY OP_DUP OP_HASH160
define('CONST_HTLC03_SZ', strlen(CONST_HTLC03));
define('CONST_HTLC05', '67'); // OP_ELSE
define('CONST_HTLC05_SZ', strlen(CONST_HTLC05));
define('CONST_HTLC07', 'b17576a9'); // OP_CHECKTIMELOCKVERIFY OP_DROP OP_DUP OP_HASH160
define('CONST_HTLC07_SZ', strlen(CONST_HTLC07));
define('CONST_HTLC09', '6888'); // OP_ENDIF OP_EQUALVERIFY
define('CONST_HTLC09_SZ', strlen(CONST_HTLC09));
define('CONST_HTLC0A', 'ac'); // OP_CHECKSIG
define('CONST_HTLC0A_SZ', strlen(CONST_HTLC0A));
define('CONST_HTLB01', '63'); // OP_IF
define('CONST_HTLB01_SZ', strlen(CONST_HTLB01));
define('CONST_HTLB03', '76a9'); // OP_DUP OP_HASH160
define('CONST_HTLB03_SZ', strlen(CONST_HTLB03));
define('CONST_HTLB05', '67'); // OP_ELSE
define('CONST_HTLB05_SZ', strlen(CONST_HTLB05));
define('CONST_HTLB07', 'b17576a9'); // OP_CHECKTIMELOCKVERIFY OP_DROP OP_DUP OP_HASH160
define('CONST_HTLB07_SZ', strlen(CONST_HTLB07));
define('CONST_HTLB09', '6888'); // OP_ENDIF OP_EQUALVERIFY
define('CONST_HTLB09_SZ', strlen(CONST_HTLB09));
define('CONST_HTLB0A', 'ac'); // OP_CHECKSIG
define('CONST_HTLB0A_SZ', strlen(CONST_HTLB0A));
define('CONST_IFTRUE_DATA', '0101');
define('CONST_IFTRUE_DATA_SZ', strlen(CONST_IFTRUE_DATA));
define('CONST_IFFALSE_DATA', '00');
define('CONST_IFFALSE_DATA_SZ', strlen(CONST_IFFALSE_DATA));
// 0x3a? https://bitcoin.stackexchange.com/questions/62781/bitcoin-constants-and-prefixes
define('CONST_BTC_VERSION_BYTE', '00');
define('CONST_LTC_VERSION_BYTE', '3a');
define('CONST_OP_DROP', '75');
define('CONST_OP_TRUE', '51');
define('CONST_PW_CLEAR', 'thisisasecret');
define('CONST_PW_DIGEST', '835ca4a6a1c2baaaf63bf33c777aff2fc681b017');
define('CONST_PW_HEX', '74686973697361736563726574');
define('CONST_PW_HEX_SZ', '0d');
define('CONST_NLOCKTIME_BORDER', 500000000);
define('SATSPERCOIN', 100000000);

define('CONST_BTC_MAINNET_PORT', 8332);
define('CONST_BTC_TESTNET_PORT', 18332);
define('CONST_BTC_REGTEST_PORT', 18443);
define('CONST_LTC_TESTNET_PORT', 19332);
define('CONST_LTC_REGTEST_PORT', 19443);

define('CONST_BUYER', 1);
define('CONST_SELLER', 2);

define('CONST_BITCOIN_BLOCK_PERIOD', 1 * 60);  // should be 10 on mainnet

if (file_exists('./credentials.php')) {
  // this file, if it exists, contains installation specific
  // credentials along the same lines as in the else statement
  // below
  require_once('./credentials.php');

} else {
  if (1) {
    // testnet
    define('PORT', CONST_BTC_TESTNET_PORT);
    define('UN', 'ARandmUsername');
    define('PW', 'ArAndomPW');

  } else {
    // regtest
    define('PORT', CONST_BTC_REGTEST_PORT);
    define('UN', 'ARandmUsername');
    define('PW', 'ArAndomPW');
  }
}
?>
