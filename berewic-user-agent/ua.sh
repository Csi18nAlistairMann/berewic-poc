#!/bin/bash
#
# This bash script is so written as to simulate a device wishing to
# access a berewic covered-resource, discovering that bonding is
# required, and instructing the user's own berewic transport agent
# to fulfill it.
#
# At this date this script is incomplete.

#
# Obtain the headers when accessing the covered resource without bonding first
RESPONSE1=$(wget -S http://berewic.pectw.net/covered-resource/endpoint-v1.php -O /dev/null 2>&1)

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
# Extract the btc/testnet proposal and reform as proper json
RESPONSE5={$(echo $RESPONSE4 | sed 's/^.*\("0":{\)/\1/' | sed 's/,"1":.*//')}

#
# Connect to our own BTA and instruct it to post bond
RESPONSE6=$(php ./post-bond.php "$RESPONSE5")
echo $RESPONSE6
