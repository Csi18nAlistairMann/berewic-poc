#!/bin/bash
#
# This bash script is so written as to simulate a device wishing to
# access a berewic covered-resource, discovering that bonding is
# required, and instructing the user's own berewic transport agent
# to fulfill it.
#
# At this date this script is incomplete.
#
# Want to watch an address made earlier? Use this:
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
    # Supply completed proposal back to Bob's BTA, expect +OK <p2sh-address>
    # The BTA server will make a record of the proposal at this point, even
    # if Alice ultimately doesn't post bond
    RESPONSE7=$(wget --method=PUT --header=Content-Type:application/json --body-data=$RESPONSE6 -q -O - --no-check-certificate "$RESPONSE3")

    #
    # Extract P2SH Address
    P2SH_ADDRESS=$(echo $RESPONSE7 | sed 's/^.*+OK //' | tr -d "\n")
    echo "P2SH Address: "$P2SH_ADDRESS

    #
    # Add P2SH Address to our own copy of the contract
    RESPONSE9=$(echo $RESPONSE6 | sed "s/^\(.*\"p2sh-address\":\"\)\(\".*\)$/\1$P2SH_ADDRESS\2/")

    #
    # Connect to Alice's BTA and instruct it to post bond. This
    # part will end up credentialled up the wazoo. Expect +OK
    RESPONSE10=$(wget --method=POST --header=Content-Type:application/json --body-data=$RESPONSE9 -q -O - --no-check-certificate "https://berewic.mpsvr.com:8443/bond")
    echo $RESPONSE10
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
RESPONSE11='+NOK not arrived yet'
until [ "$RESPONSE11" != "+NOK not arrived yet" ]; do
    TIMESTAMP=$(date +%s)
    echo $TIMESTAMP": "$RESPONSE11
    RESPONSE11=$(wget --method=GET -q -O - --no-check-certificate "https://berewic.mpsvr.com:8443/bond/"$P2SH_ADDRESS)
    sleep 30
done
echo $TIMESTAMP": "$RESPONSE11
