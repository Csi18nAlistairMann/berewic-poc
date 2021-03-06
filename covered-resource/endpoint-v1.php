<?php
/*
  This endpoint will initially live at http://berewic.com

  The output will reflect the presence or absence in the inbound
  connections headers of berewic information.

  This endpoint ultimately grows into /Bob's server/: he hosts content here
  which he wishes to have protected using berewic bonds.
*/
//
// globals
error_reporting(E_ALL);

// fakes! don't use
define('BEREWIC_BTA_1', 'https://z4r56iqh3gnhj67bn.onion:8443/proposal/35cb904aced147bd1230965501af4306ac5204db/64be35a9db5e5f09f40a058b9f27c5ed112ea49c');
define('BEREWIC_BTA_2', 'https://bobs-bta.mpsvr.com:8443/proposal/4025061200627151c0c2b7b80d7af47b3b5c8bd2/e8ebaa9cb957844658dd0bcea2aeae6ffb1e2349');
define('BEREWIC_SECRET', 'thisisasecret');

define('CONST_HEADER_BEREWIC_BONDED', 'berewic-bonded');
define('CONST_HEADER_CONFIRMATION', 'berewic-bond-confirmation');
define('CONST_HEADER_KEY_MARKER', 'berewic-');
define('CONST_HEADER_KEY_MARKER_SZ', strlen(CONST_HEADER_KEY_MARKER));
define('CONST_HEADER_KEY_MAX_SZ', 32);
define('CONST_HEADER_VALUE_MAX_SZ', 255);
define('CONST_HEADER_TXT_VERSION', CONST_HEADER_KEY_MARKER . 'version');
define('CONST_HEADER_TXT_ROLE', CONST_HEADER_KEY_MARKER . 'role');
define('CONST_REDIRECT_HEADER', CONST_HEADER_KEY_MARKER . 'transport-agent');
define('CONST_NO_VERSION', '0.0');
define('CONST_ROLE_TXT_CLIENT', 'client');
define('CONST_ROLE_TXT_SERVER', 'server');
define('CONST_HEADER_FORCED_KEY_MARKER', 'forced-berewic-');
define('CONST_HEADER_FORCED_KEY_MARKER_SZ', strlen(CONST_HEADER_FORCED_KEY_MARKER));
define('CONST_HEADER_TXT_TEMP_RATE', CONST_HEADER_FORCED_KEY_MARKER . 'use-this-rate');
define('CONST_RATE_TXT_ZERO', 'zero');
define('CONST_RATE_TXT_LOW', 'low');
define('CONST_RATE_TXT_NORMAL', 'normal');
define('CONST_RATE_DEFAULT', CONST_RATE_TXT_NORMAL);
define('CONST_TXT_IDV1', 'idv1');
define('CONST_TXT_RATEV1', 'ratev1');
define('CONST_TXT_HMACV1', 'hmacv1');

define('CONST_VERSION', '0.1');
define('CONST_ROLE', CONST_ROLE_TXT_SERVER);

define('LOCAL_BEREWIC_BTA_1_01', "1b61");
define('LOCAL_BEREWIC_BTA_1_02', "78f7");

define('ERR_HEADER_KEY_TOO_LARGE', 1000);
define('ERR_HEADER_KEY_TOO_LARGE_MSG',
	   'Headers have a key equal or longer than ' . CONST_HEADER_KEY_MAX_SZ .
	   ' characters');
define('ERR_HEADER_VALUE_TOO_LARGE', 1001);
define('ERR_HEADER_VALUE_TOO_LARGE_MSG',
	   'Headers have a value equal or longer than ' . CONST_HEADER_VALUE_MAX_SZ .
	   ' characters');
define('ERR_HEADER_ROLE_NOT_RECOGNISED', 1002);
define('ERR_HEADER_ROLE_NOT_RECOGNISED_MSG', 'Role not "' . CONST_ROLE_TXT_CLIENT .
	   '" or "' . CONST_ROLE_TXT_SERVER . '"');
define('ERR_HEADER_KEY_UNKNOWN', 1003);
define('ERR_HEADER_KEY_UNKNOWN_MSG', 'Headers have a key unknown to this system');
define('ERR_HEADER_TEMP_RATE_UNKNOWN', 1004);
define('ERR_HEADER_TEMP_RATE_UNKNOWN_MSG', 'Headers have a key unknown to this system');
define('ERR_HEADER_BOND_CONFIRMATION_NO_GOOD', 1005);
define('ERR_HEADER_BOND_CONFIRMATION_NO_GOOD_MSG', 'Your confirmation header does not bear verification');
define('ERR_HEADER_BOND_EXPIRED', 1006);
define('ERR_HEADER_BOND_EXPIRED_MSG', 'Your bond has expired, you need a new one');

//
// classes
class BerewicHeaders {
	private $isClient = false;
	private $isServer = false;
	private $version = CONST_NO_VERSION;
	private $shenanigans_arr = array();
	function getShenanigans() {
		if (sizeof($this->shenanigans_arr) === 0)
			return false;
		else
			return true;
	}

	function addShenanigan($error) {
		$this->shenanigans_arr[] = $error;
	}

	function printShenanigans() {
		echo "<pre>";
		foreach ($this->shenanigans_arr as $value) {
			echo "* ";
			switch ($value) {
			case ERR_HEADER_KEY_TOO_LARGE:
				echo ERR_HEADER_KEY_TOO_LARGE_MSG . "<br>\n";
				break;
			case ERR_HEADER_VALUE_TOO_LARGE:
				echo ERR_HEADER_VALUE_TOO_LARGE_MSG . "<br>\n";
				break;
			case ERR_HEADER_ROLE_NOT_RECOGNISED:
				echo ERR_HEADER_ROLE_NOT_RECOGNISED_MSG . "<br>\n";
				break;
			case ERR_HEADER_KEY_UNKNOWN:
				echo ERR_HEADER_KEY_UNKNOWN_MSG . "<br>\n";
				break;
			case ERR_HEADER_TEMP_RATE_UNKNOWN:
				echo ERR_HEADER_TEMP_RATE_UNKNOWN_MSG . "<br>\n";
				break;
			case ERR_HEADER_BOND_CONFIRMATION_NO_GOOD:
				echo ERR_HEADER_BOND_CONFIRMATION_NO_GOOD_MSG . "<br>\n";
				break;
			case ERR_HEADER_BOND_EXPIRED:
				echo ERR_HEADER_BOND_EXPIRED_MSG . "<br>\n";
				break;
			default:
				echo "Unrecognised error $value<br>\n";
			}
		}
		echo "</pre>";
	}

	function setRole($value) {
		$value = strtolower($value);
		if ($value === CONST_ROLE_TXT_SERVER) {
			$this->isClient = false;
			$this->isServer = true;
		} elseif ($value === CONST_ROLE_TXT_CLIENT) {
			$this->isClient = true;
			$this->isServer = false;
		} else {
			$this->isClient = false;
			$this->isServer = false;
			$this->addShenanigan(ERR_ROLE_NOT_RECOGNISED);
		}
	}

	function setVersion($value) {
		$this->version = $value;
	}

	function getVersion() {
		return $this->version;
	}

	function announceSelf() {
		// won't work as client
		header(CONST_HEADER_TXT_VERSION . ': ' . $this->version);
		if ($this->isClient === true && $this->isServer === false) {
			header(CONST_HEADER_TXT_ROLE . ': ' . CONST_ROLE_TXT_CLIENT);
		} elseif ($this->isClient === false && $this->isServer === true) {
			header(CONST_HEADER_TXT_ROLE . ': ' . CONST_ROLE_TXT_SERVER);
		} else {
			// something happened (something happened)
		}
	}
}

class InHeaders extends BerewicHeaders {
	private $headers_passed = array();
	private $headers_seen = 0;
	private $forced_rate = CONST_RATE_DEFAULT;
	private $confirmation_whole;
	private $confirmation_idv1;
	private $confirmation_bta;
	private $confirmation_amount;
	private $confirmation_locktime;
	private $confirmation_mtime;
	private $confirmation_hmac;
	private $confirmation_good = false;

	function __construct($headers) {
		$this->confirmation_good = false;
		// Takes in an array of k=>v pairs as generated by
		// apache_request_headers(), size checks them, and
		// store those that pass.
		foreach ($headers as $header => $value) {
			if (strtolower(substr($header, 0, CONST_HEADER_KEY_MARKER_SZ)) ===
				CONST_HEADER_KEY_MARKER) {
				// sanity check for sizes
				$key = strtolower(substr($header, 0, CONST_HEADER_KEY_MAX_SZ));
				if (strlen($key) === CONST_HEADER_KEY_MAX_SZ)
					$this->addShenanigan(ERR_HEADER_KEY_TOO_LARGE);
				$value = substr($value, 0, CONST_HEADER_VALUE_MAX_SZ);
				if (strlen($value) === CONST_HEADER_VALUE_MAX_SZ)
					$this->addShenanigan(ERR_HEADER_VALUE_TOO_LARGE);
				// make a copy
				$this->headers_passed[] = [$key => $value];
				$this->incHeadersSeen();
				//
				if ($key === CONST_HEADER_TXT_VERSION)
					$this->setVersion($value);

				elseif ($key === CONST_HEADER_TXT_ROLE)
					$this->setRole($value);

				elseif ($key === CONST_HEADER_CONFIRMATION)
					$this->setConfirmation($value);

				else
					$this->addShenanigan(ERR_HEADER_KEY_UNKNOWN);

			} elseif (strtolower(substr($header, 0,
										CONST_HEADER_FORCED_KEY_MARKER_SZ)) ===
					  CONST_HEADER_FORCED_KEY_MARKER) {
				$key = strtolower(substr($header, 0, CONST_HEADER_KEY_MAX_SZ));
				if ($key === CONST_HEADER_TXT_TEMP_RATE) {
					$this->setForcedRate($value);
				}
			}
		}
	}

	function setConfirmation($value) {
		if (strlen($value) > CONST_HEADER_VALUE_MAX_SZ) {
			$this->addShenanigan(ERR_HEADER_VALUE_TOO_LARGE);

		} else {
			// we break them down as we might later want to use them. For the moment
			// though we'll only be recombining them to check the hash is good.
			// If we see an error we don't say what step they failed on; in a
			// production system that'd be mad
			$value_arr = explode('&', $value);
			$okay = true;
			$secret = 'this is a fake secret that doesnt get used';
			foreach($value_arr as $pair) {
				$pair_arr = explode('=', $pair);
				switch ($pair_arr[0]) {
				case 'idv1':
					if ($pair_arr[1] !== hash('crc32', $_SERVER['REMOTE_ADDR'])) {
						// End points will no doubt confine particular bonds to
						// particular users. Fake it for the moment by matching
						// the IP address the BTA saw with the IP address claiming
						// to have the bond
						$this->addShenanigan(ERR_HEADER_BOND_CONFIRMATION_NO_GOOD);
					} else {
						$this->confirmation_idv1 = $pair_arr[1];
					}
					break;

				case 'bta':
					// used to distinguish secrets on federated machines
					if ($pair_arr[1] !== LOCAL_BEREWIC_BTA_1_01 &&
						$pair_arr[1] !== LOCAL_BEREWIC_BTA_1_02) {
						$this->addShenanigan(ERR_HEADER_BOND_CONFIRMATION_NO_GOOD);
					} else {
						// Bob might have a federation of BTAs any one of which
						// could have handled the bond, and each of which has its
						// own secret.
						$this->confirmation_bta = $pair_arr[1];
						if ($this->confirmation_bta === LOCAL_BEREWIC_BTA_1_02) {
							$secret = BEREWIC_SECRET;
						}
					}
					break;

				case 'amount':
					if ($pair_arr[1] !== '0.0004') {
						$this->addShenanigan(ERR_HEADER_BOND_CONFIRMATION_NO_GOOD);
					} else {
						$this->confirmation_amount = $pair_arr[1];
					}
					break;

				case 'locktime':
					// this the redeem_locktime, that is, the timestamp at which Alice
					// can first redeem her funds back. This will normally be some time
					// after the timestamp at which the covered_resource will stop
					// accepting the bond. The difference allows for abuse in the last
					// few seconds of service to nevertheless still allow time for
					// investigation.
					if (strval(time()) > $pair_arr[1]) {
						$this->addShenanigan(ERR_HEADER_BOND_EXPIRED);
					} else {
						$this->confirmation_locktime = $pair_arr[1];
					}
					break;

				case 'mtime':
					$this->confirmation_mtime = $pair_arr[1];
					break;

				case 'hmacv1':
					$this->confirmation_hmac = $pair_arr[1];
					break;

				default:
					$okay = false;
					$this->addShenanigan(ERR_HEADER_BOND_CONFIRMATION_NO_GOOD);
					break;
				}
			}
			if ($okay === true) {
				$preimage = 'idv1=' . $this->confirmation_idv1 .
					'&bta=' . $this->confirmation_bta .
					'&amount=' . $this->confirmation_amount .
					'&locktime=' . $this->confirmation_locktime .
					'&mtime=' . $this->confirmation_mtime;
				$hmac = hash_hmac('ripemd160', $preimage, $secret);
				if ($hmac !== $this->confirmation_hmac) {
					$this->addShenanigan(ERR_HEADER_BOND_CONFIRMATION_NO_GOOD);

				} else {
					$this->confirmation_good = true;
				}
			}
		}
	}

	function getConfirmationGood() {
		return $this->confirmation_good;
	}

	function incHeadersSeen() {
		$this->headers_seen++;
	}

	function getHeadersSeen() {
		return $this->headers_seen;
	}

	function getHeadersPassed() {
		return $this->headers_passed;
	}

	function setForcedRate($value) {
		$value = strtolower($value);
		switch ($value) {
		case CONST_RATE_TXT_ZERO:
		case CONST_RATE_TXT_LOW:
		case CONST_RATE_TXT_NORMAL:
			$this->forced_rate = $value;
			break;
		default:
			$this->addShenanigan(ERR_HEADER_TEMP_RATE_UNKNOWN);
			$this->forced_rate = CONST_RATE_TXT_NORMAL;
		}
	}

	function getForcedRate() {
		return $this->forced_rate;
	}

	function getLocktime() {
		return $this->confirmation_locktime;
	}
}

class OutHeaders extends BerewicHeaders {
	private $cryptoTransportAgents = array();

	function __construct($version, $role, $ip_address, $ratev1) {
		$this->setVersion($version);
		$this->setRole($role);
		$idv1 = hash('crc32', $ip_address);
		$hmacv1 = hash_hmac('ripemd160', $idv1 . $ratev1, BEREWIC_SECRET);

		$params = '?' . CONST_TXT_IDV1 . '=' . $idv1 .
			'&' . CONST_TXT_RATEV1 . '=' . $ratev1 .
			'&' . CONST_TXT_HMACV1 . '=' . $hmacv1;
		$this->addCryptTransportAgent('20', BEREWIC_BTA_1 . $params);
		$this->addCryptTransportAgent('10', BEREWIC_BTA_2 . $params);
	}

	function addCryptTransportAgent($preference, $location) {
		if (!isset($this->cryptoTransportAgents[$preference]))
			$this->cryptoTransportAgents[$preference] = array();
		$this->cryptoTransportAgents[$preference][] = $preference . ',' . $location;
	}

	function pleaseBond() {
		$rv = array();
		sort($this->cryptoTransportAgents);
		foreach ($this->cryptoTransportAgents as $agents) {
			foreach ($agents as $location) {
				header(CONST_REDIRECT_HEADER . ': ' . $location, false);
				$rv[] = $location;
			}
		}
		return $rv;
	}
}

//
// Subroutines

function main($headers) {
	echo '<html><head>';
	echo '<title>Bob\'s website</title>';
	echo '<link rel="icon" href="data:;base64,iVBORw0KGgo=">';
	echo '</head><body>';
	echo '<font size=+2>Berewic demo page</font><br>';

	$inHeaders = new InHeaders($headers);
	if ($inHeaders->getShenanigans() === true) {
		header(CONST_HEADER_BEREWIC_BONDED . ": false");
		echo "Bonding headers seen but shenanigans detected:<br>\n";
		$inHeaders->printShenanigans();
		echo "<img src='/covered-resource/unbonded-shenanigan.jpg'><br>";
		echo '"Shenanigans"';

	} elseif ($inHeaders->getHeadersSeen() === 0) {
		header(CONST_HEADER_BEREWIC_BONDED . ": false");
		$id = '';
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
			$id .= $_SERVER['HTTP_X_FORWARDED_FOR'];
		if (isset($_SERVER['REMOTE_ADDR']))
			$id .= $_SERVER['REMOTE_ADDR'];

		$outHeaders = new OutHeaders(CONST_VERSION, CONST_ROLE, $id,
									 $inHeaders->getForcedRate());
		$outHeaders->announceSelf();
		$whatsaid = $outHeaders->pleaseBond();

		echo "No bonding headers seen<br>\n";
		echo "<img src='/covered-resource/unbonded-anticipation.png'><br>";
		echo '"Anticipation"<br><br>';
		echo '<a href="https://github.com/Csi18nAlistairMann/berewic-poc/blob/master/berewic-user-agent/user-agent.sh">user agent shell script</a>';

	} else {
		$timenow = time();
		if ($timenow >= $inHeaders->getLocktime() - 1 * 60 * 30 &&
			$timenow < $inHeaders->getLocktime()) {
			header(CONST_HEADER_BEREWIC_BONDED . ": false");
			echo "Service has ended, bond is still subject to review<br>\n";
			echo "<img src='/covered-resource/unbonded-im-not-dead-yet.jpg'><br>";
			echo '"Grace period"';

		} else {
			header(CONST_HEADER_BEREWIC_BONDED . ": true");
			echo "Congratulations! This connection is bonded<br>\n";
			echo "<img src='/covered-resource/bonded-hula.png'><br>";
			echo '"Success"';
		}
	}

	echo '</body></html>';
}

//
// entry point

main(apache_request_headers());
?>
