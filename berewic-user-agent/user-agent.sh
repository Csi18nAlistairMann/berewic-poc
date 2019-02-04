#!/bin/bash
#
# This bash script is so written as to simulate a device wishing to
# access a berewic covered resource, discovering that bonding is
# required, and instructing the user's own berewic transport agent
# to fulfill it.
#
# At this date this script is incomplete.
#
# Want to watch an address made earlier? Use this and comment out sleep 30 below
# P2SH_ADDRESS="2N1zWYSLBNqgpxAh5XHvkdbpbA1qPCzFL3e"
P2SH_ADDRESS=""
#
# Set up somewhere to save confirmations
CONFIRMATIONS_DIR="$HOME/berewic-confirmations"
if [ ! -d "$CONFIRMATIONS_DIR" ]; then
    mkdir -p $CONFIRMATIONS_DIR
fi

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
    echo -n "Accessing URL to obtain a BTA. "
    RESPONSE1=$(wget --method=GET -S http://berewic.com/covered-resource -O /dev/null 2>&1)
    echo "[Done]"

    #
    # Extract just the one header
    RESPONSE2=$(echo $RESPONSE1 | sed 's/^.*\(berewic-transport-agent: 10,\)/\1/' | sed 's/ berewic-transport-agent: 20,.*//')

    #
    # Extract just the value (could have been folded into the above but I'm after clarity
    BOBS_BTA=$(echo $RESPONSE2 | sed 's/berewic-transport-agent: 10,//')
    echo "BTA chosen: "$BOBS_BTA

    #
    # Connect to Bob's BTA and obtain his proposals for a bond
    echo -n "Get proposals from BTA. "
    PROPOSALS=$(wget -q -O - --no-check-certificate "$BOBS_BTA")
    echo "[Done]"

    #
    # Extract the btc/testnet proposal and reform as proper json (the surrounding { })
    echo -n "Add buyer address "$BUYER_ADDRESS" to proposal. "
    PROPOSAL1OF3={$(echo $PROPOSALS | sed 's/^.*\("0":{\)/\1/' | sed 's/,"1":.*//')}

    #
    # Add Alice's address
    PROPOSAL2OF3=$(echo $PROPOSAL1OF3 | sed "s/^\(.*\"testnet\",\"buyer-address\":\"\)\(\",\"p2sh-address\":.*\)$/\1$BUYER_ADDRESS\2/")
    echo "[Done]"

    #
    # Supply completed proposal back to Bob's BTA, expect "201"
    # The BTA server will make a record of the proposal at this point, even
    # if Alice ultimately doesn't post bond
    echo -n "Supply above modification back to to BTA for the P2SH address. "
    RESPONSE7A=$(wget -S --method=PUT --header=Content-Type:application/json --body-data=$PROPOSAL2OF3 -q -O - --no-check-certificate "$BOBS_BTA" 2>&1)
    RESPONSE7B=$(echo $RESPONSE7A | sed "s/^HTTP\/1.1 //" | sed "s/^\(...\).*/\1/")
    if [ "$RESPONSE7B" != "201" ]; then
	echo "Didn't get 201 response when PUTting the proposal back\n"
	echo $RESPONSE7A
	echo $RESPONSE7B
	exit
    fi
    RESPONSE7C=$(echo $RESPONSE7A | sed "s/^.*Location: //" | sed "s/^\(\S*\)\s.*/\1/")
    echo "[Done] "

    #
    # Extract P2SH Address
    P2SH_ADDRESS=$(echo $RESPONSE7C | sed "s/^.*\/\(.*\)/\1/")
    echo "P2SH Address: "$P2SH_ADDRESS

    #
    # Add P2SH Address to our own copy of the contract
    PROPOSAL3OF3=$(echo $PROPOSAL2OF3 | sed "s/^\(.*\"p2sh-address\":\"\)\(\".*\)$/\1$P2SH_ADDRESS\2/")

    #
    # Connect to Alice's BTA and instruct it to post bond. This
    # part will end up credentialled up the wazoo. Expect "201"
    echo -n "Fund the P2SH address and so commit to the bond. "
    RESPONSE10A=$(wget -S --method=POST --header=Content-Type:application/json --body-data=$PROPOSAL3OF3 -q -O - --no-check-certificate "https://alices-bta.mpsvr.com:8443/bond" 2>&1)
    RESPONSE10B=$(echo $RESPONSE10A | sed "s/^HTTP\/1.1 //" | sed "s/^\(...\).*/\1/")
    if [ "$RESPONSE10B" != "201" ]; then
	echo "Didn't get 201 response when POSTting the bond\n"
	echo $RESPONSE10A
	echo $RESPONSE10B
	exit
    fi
    # Resource = /bond/txid
    RESPONSE10C=$(echo $RESPONSE10A | sed "s/^.*\(Location: \S*\)\s.*/\1/")
    echo "[Done]"
fi
#
# Alice's BTA has posted bond to the blockchain, we now want to
# see Bob's BTA confirm it. That might take a while during which
# time Bob may give some or complete access on the assumption
# the bond will arrive. The BUA should periodically poll Bob's
# BTA for final confirmation,
#
# HTTP2 allows us to be told when it's arrived but for now we'll
# just poll for it
#
echo "Poll BTA for '200' & confirmation code once it sees bond on the blockchain, 30 second sleep if not. "
RESPONSE11B='202'
until [ "$RESPONSE11B" != "202" ]; do
    TIMESTAMP=$(date +%s)
    RESPONSE11A=$(wget -S --method=GET -q -O - --no-check-certificate "https://bobs-bta.mpsvr.com:8443/bond/"$P2SH_ADDRESS 2>&1)
    RESPONSE11B=$(echo $RESPONSE11A | sed "s/^HTTP\/1.1 //" | sed "s/^\(...\).*/\1/")
    if [ "$RESPONSE11B" == "202" ]; then
	echo $TIMESTAMP": "$RESPONSE11B
	sleep 30
    fi
done
echo -n $TIMESTAMP": "$RESPONSE11B" "
echo "[Done]"

#
# Strip out the header we need
# BOND_CONFIRMATION_CODE=$(echo $RESPONSE11 | sed 's/^.*+OK //' | tr -d "\n")
BOND_CONFIRMATION_CODE=$(echo $RESPONSE11A | sed "s/^.*\(berewic-bond-confirmation: \S*\).*/\1/")
echo "Header which confirms bond acceptable will be:"
echo $BOND_CONFIRMATION_CODE

#
# Save the header provided back
echo $BOND_CONFIRMATION_CODE >>$CONFIRMATIONS_DIR/confirmations

#
# We can now use the header to prove the bonding status to
# the satisfaction of the covered resource
# RESPONSE13=$(wget --method=GET -q -O - --header="$BOND_CONFIRMATION_CODE" http://berewic.com/covered-resource)
# echo "Server response to the above header:"
# echo $RESPONSE13
