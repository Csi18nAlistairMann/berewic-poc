# berewic-poc
Proof of Concept for the Berewic idea 

Alistair Mann	 al@berewic.com    2018-12-26

## /berewic-bitcoin/berewic-bitcoin.php
A PHP library containing classes useful for simulating berewic back end functionality.  
Also helpers:  
/berewic-bitcoin/base58.php  
/berewic-bitcoin/berewic-defines.php  
/berewic-bitcoin/easybitcoin.php (MIT Licence)  

## /berewic-transport-agent
PHP files simulating a BTA.

### /berewic-transport-agent/bond_server_v1.php
Simulate /bond.  
GET /bond/N at Bob's BTA obtains the status (+ confirmation code) due for P2SH address matching N.  
POST /bond at Alice's BTA takes a proposal and commits funds to it.  

### /berewic-transport-agent/proposal_server_v1.php
Simulate /proposal  
GET /proposal/[HOST]/[URI]?idv1=[A]&ratev1=[B]&hmacv1=[C] at Bob's BTA obtains proposals for that user A at host HOST etc.  
PUT /proposal/[HOST]/[URI]?idv1=[A]&ratev1=[B]&hmacv1=[C] at Bob's BTA accepts the uploaded proposal and asks for the P2SH address.  

## /berewic-user-agent/user-agent.sh
A Bash script simulating a BUA from initial connection to obtaining the confirmation code.  
Typical run:  
<pre>$ ./user-agent.sh
Accessing URL to obtain a BTA. [Done]
BTA chosen: https://bobs-bta.mpsvr.com:8443/proposal/4025061200627151c0c2b7b80d7af47b3b5c8bd2/e8ebaa9cb957844658dd0bcea2aeae6ffb1e2349?idv1=52676381&ratev1=normal&hmacv1=80569a4a03a9f4c5df677165e2d94de360cb6da8
Get proposals from BTA. [Done]
Add buyer address 2N89uTZbJ1K3jWRviJkfZ3uGVC9ATxmh4eS to proposal. [Done]
Supply above modification back to to BTA for the P2SH address. [Done] 
P2SH Address: 2N4C6TcbHxEdHiin5CzPbQyA1zP7cnZVbQq
Fund the P2SH address and so commit to the bond. [Done]
Poll BTA for '200' & confirmation code once it sees bond on the blockchain, 30 second sleep if not. 
1549292539: 202
1549292569: 202
1549292599: 202
1549292629: 202
1549292659: 202
1549292689: 202
1549292719: 200 [Done]
Header which confirms bond acceptable will be:
berewic-bond-confirmation: idv1=52676381&bta=78f7&amount=0.0004&locktime=1549297938&mtime=1549292719636004&hmacv1=17c7c1f3793c4178b270a59c3dfd47ef0e68bf63
$
</pre>

## /covered-resource/endpoint-v1.php
A PHP file that can return different content based on the bonding status of incoming connections.  
