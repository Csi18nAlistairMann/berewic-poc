#!/bin/bash
#
# This bash script is so written as to simulate a device wishing to
# access a berewic covered-resource, discovering that bonding is
# required, and instructing the user's own berewic transport agent
# to fulfill it.
#
# At this date this script is incomplete.
#
# Want to watch an address made earlier? Use this and comment out sleep 30 below
# P2SH_ADDRESS="2N1zWYSLBNqgpxAh5XHvkdbpbA1qPCzFL3e"
P2SH_ADDRESS=""
if [ "$P2SH_ADDRESS" == "" ]; then
    # BUYER_ADDRESS aka Alice's redemption address
    # This is the address to which some or all of your bond is returned.
    # The default, 2N89uTZbJ1K3jWRviJkfZ3uGVC9ATxmh4eS, is a testnet
    # address at the berewic server: if you don't change it, your
    # testnet funds may be returned to a faucet.
    #
    # If you have testnet available on your machine you can obtain
    # your own address with something like:
    # bitcoin-cli -testnet getnewaddress
    BUYER_ADDRESS='2N89uTZbJ1K3jWRviJkfZ3uGVC9ATxmh4eS'

    #
    # Obtain the headers when accessing the covered resource without bonding first
    RESPONSE1=$(wget --method=GET -S http://berewic.pectw.net/covered-resource/endpoint-v1.php -O /dev/null 2>&1)

    #
    # Extract just the one header
    RESPONSE2=$(echo $RESPONSE1 | sed 's/^.*\(berewic-bond-transport-agent: 10,\)/\1/' | sed 's/ berewic-bond-transport-agent: 20,.*//')

    #
    # Extract just the value (could have been folded into the above but I'm after clarity
    RESPONSE3=$(echo $RESPONSE2 | sed 's/berewic-bond-transport-agent: 10,//')

    #
    # Connect to Bob's BTA and obtain his proposal for the bond
    RESPONSE4=$(wget -q -O - --no-check-certificate "$RESPONSE3")

    #
    # Extract the btc/testnet proposal and reform as proper json (the surrounding { })
    RESPONSE5={$(echo $RESPONSE4 | sed 's/^.*\("0":{\)/\1/' | sed 's/,"1":.*//')}

    #
    # Add Alice's address
    RESPONSE6=$(echo $RESPONSE5 | sed "s/^\(.*\"testnet\",\"buyer-address\":\"\)\(\",\"p2sh-address\":.*\)$/\1$BUYER_ADDRESS\2/")

    #
    # Supply completed proposal back to Bob's BTA, expect "201"
    # The BTA server will make a record of the proposal at this point, even
    # if Alice ultimately doesn't post bond
    RESPONSE7A=$(wget -S --method=PUT --header=Content-Type:application/json --body-data=$RESPONSE6 -q -O - --no-check-certificate "$RESPONSE3" 2>&1)
    RESPONSE7B=$(echo $RESPONSE7A | sed "s/^HTTP\/1.1 //" | sed "s/^\(...\).*/\1/")
    if [ "$RESPONSE7B" != "201" ]; then
	echo "Didn't get 201 response when PUTting the proposal back\n"
	echo $RESPONSE7A
	echo $RESPONSE7B
	exit
    fi
    RESPONSE7C=$(echo $RESPONSE7A | sed "s/^.*Location: //" | sed "s/^\(\S*\)\s.*/\1/")

    #
    # Extract P2SH Address
    P2SH_ADDRESS=$(echo $RESPONSE7C | sed "s/^.*\/\(.*\)/\1/")
    echo "P2SH Address: "$P2SH_ADDRESS

    #
    # Add P2SH Address to our own copy of the contract
    RESPONSE9=$(echo $RESPONSE6 | sed "s/^\(.*\"p2sh-address\":\"\)\(\".*\)$/\1$P2SH_ADDRESS\2/")

    #
    # Connect to Alice's BTA and instruct it to post bond. This
    # part will end up credentialled up the wazoo. Expect "201"
    RESPONSE10A=$(wget -S --method=POST --header=Content-Type:application/json --body-data=$RESPONSE9 -q -O - --no-check-certificate "https://berewic.mpsvr.com:8443/bond" 2>&1)
    RESPONSE10B=$(echo $RESPONSE10A | sed "s/^HTTP\/1.1 //" | sed "s/^\(...\).*/\1/")
    if [ "$RESPONSE7B" != "201" ]; then
	echo "Didn't get 201 response when POSTting the bond\n"
	echo $RESPONSE10A
	echo $RESPONSE10B
	exit
    fi
    # Resource = /bond/txid
    RESPONSE10C=$(echo $RESPONSE10A | sed "s/^.*\(Location: \S*\)\s.*/\1/")
fi
#
# Alice's BTA has posted bond to the blockchain, we now want to
# see Bob's BTA confirm it. That might take a while during which
# time Bob may give some or complete access on the assumption
# the bond will arrive. The BUA should periodically poll Bob's
# BTA for final confirmation
#
# HTTP2 allows us to be told when its arrived but for now we'll
# just poll for it
#
RESPONSE11B='202'
until [ "$RESPONSE11B" != "202" ]; do
    TIMESTAMP=$(date +%s)
    RESPONSE11A=$(wget -S --method=GET -q -O - --no-check-certificate "https://berewic.mpsvr.com:8443/bond/"$P2SH_ADDRESS 2>&1)
    RESPONSE11B=$(echo $RESPONSE11A | sed "s/^HTTP\/1.1 //" | sed "s/^\(...\).*/\1/")
    if [ "$RESPONSE11B" == "202" ]; then
	echo $TIMESTAMP": "$RESPONSE11B
	sleep 30
    fi
done
echo $TIMESTAMP": "$RESPONSE11B

#
# Strip out the header we need
# RESPONSE12=$(echo $RESPONSE11 | sed 's/^.*+OK //' | tr -d "\n")
RESPONSE12=$(echo $RESPONSE11A | sed "s/^.*\(berewic-bond-confirmation: \S*\).*/\1/")
echo "Header which confirms bond acceptable will be:"
echo $RESPONSE12

#
# We can now use the header to prove the bonding status to
# the satisfaction of the covered resource
RESPONSE13=$(wget --method=GET -q -O - --header="$RESPONSE12" http://berewic.pectw.net/covered-resource/endpoint-v1.php)
echo "Server response to the above header:"
echo $RESPONSE13
