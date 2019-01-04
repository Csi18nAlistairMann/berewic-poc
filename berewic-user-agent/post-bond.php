<?php
//
// This code is used by ua.sh, which simulates a berewic user agent.
// In particular, it will accept a bond retrieved by the bash
// script and so handle it as to post a testnet bond as stipulated
// by that proposal

function main($proposal) {
  echo $proposal;
}

main($argv[1]);
?>
