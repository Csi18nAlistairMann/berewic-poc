# berewic-poc
Proof of Concept for the Berewic idea 

Alistair Mann	 al@berewic.com    2018-12-26

## /berewic-bitcoin/berewic-bitcoin.php
A PHP library containing classes useful for simulating berewic back end functionality. 
Also helpers :
/berewic-bitcoin/base58.php
/berewic-bitcoin/berewic-defines.php
/berewic-bitcoin/easybitcoin.php (MIT Licence)

## /berewic-transport-agent
A PHP file simulating a BTA

###/berewic-transport-agent/bond_server_v1.php
Simulate /bond.
GET /bond/N at Bob's BTA obtains the status (+ confirmation code) due for P2SH address matching N
POST /bond at Alice's BTA takes a proposal and commits funds to it

###/berewic-transport-agent/proposal_server_v1.php
Simulate /proposal
GET /proposal/<HOST>/<URI>?idv1=<A>&ratev1=<B>&hmacv1=<C> at Bob's BTA obtains proposals for that user A at host HOST etc
PUT /proposal/<HOST>/<URI>?idv1=<A>&ratev1=<B>&hmacv1=<C> at Bob's BTA accepts the uploaded proposal and asks for the P2SH address

## /berewic-user-agent/user-agent.sh
A Bash script simulating a BUA from initial connection to obtaining the confirmation code

## /covered-resource/endpoint-v1.php
A PHP file that can return different content based on the bonding status of incoming connections
