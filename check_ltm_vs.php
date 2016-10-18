#!/usr/bin/php
<?php

/*Nagios Exit codes
	0 = OK
	1 = WARNING
	2 = CRITICAL
	3 = UNKNOWN

perfdata output rta=35.657001ms;1000.000000;3000.000000;0.000000 pl=0%;80;100;0

*/

$arguements = getopt("H:C:r:t:e:d:");

$timeout = isset($arguements['t']) ? $arguements['t'] : "1000000";
$retry = isset($arguements['r']) ? $arguements['r'] : "5";
$exceptions = isset($arguements['e']) ? explode(",", $arguements['e']) : NULL;
$disabledExceptions = isset($arguements['d']) ? explode(",", $arguements['d']) : NULL;

$oidVirtualServerStatTableName = ".1.3.6.1.4.1.3375.2.2.10.2.3.1.1";
$oidVirtualServerStatTableCurrCon = ".1.3.6.1.4.1.3375.2.2.10.2.3.1.12";
$oidVirtualServerStatus = ".1.3.6.1.4.1.3375.2.2.10.13.2.1.2";
$oidVirtualServerEnableStat = ".1.3.6.1.4.1.3375.2.2.10.13.2.1.3";

if(!isset($arguements['C']) || !isset($arguements['H'])) {
	usage();
	exit(3);
}

try{
	$virtualServers = @snmp2_real_walk($arguements['H'], $arguements['C'], $oidVirtualServerStatTableName, $timeout, $retry);
	!$virtualServers ? nagiosOutput("UNKNOWN", "No response check IP Address and Comunity string") : NULL;
	$virtualServerStates = @snmp2_real_walk($arguements['H'], $arguements['C'], $oidVirtualServerStatus, $timeout, $retry);
	!$virtualServerStates ? nagiosOutput("UNKNOWN", "No response check IP Address and Comunity string") : NULL;
	$virtualServerConns = @snmp2_real_walk($arguements['H'], $arguements['C'], $oidVirtualServerStatTableCurrCon, $timeout, $retry);
	!$virtualServerConns ? nagiosOutput("UNKNOWN", "No response check IP Address and Comunity string") : NULL;
	$virtualServerEnStat = @snmp2_real_walk($arguements['H'], $arguements['C'], $oidVirtualServerEnableStat, $timeout, $retry);
	!$virtualServerEnStat ? nagiosOutput("UNKNOWN", "No response check IP Address and Comunity string") : NULL;
}
catch(Exception $e) {
	nagiosOutput("UNKNOWN", $e->getMessage());
}

try {
	//print_r($virtualServerEnStat);

	foreach($virtualServers as $oid=>$value) {
		$instanceOID = str_replace("SNMPv2-SMI::enterprises.3375.2.2.10.2.3.1.1", "", $oid);
		$value = str_replace(array("STRING: ","\""), "", $value);
		$virtualServerNames[$instanceOID] = $value;		
	}
	foreach($virtualServerNames as $oid=>$value) {
		$vsConnections = str_replace("Counter64: ", "", $virtualServerConns["SNMPv2-SMI::enterprises.3375.2.2.10.2.3.1.12".$oid]);
		$vsState = str_replace("INTEGER: ", "", $virtualServerStates["SNMPv2-SMI::enterprises.3375.2.2.10.13.2.1.2".$oid]);
		$vsEnabledState = str_replace("INTEGER: ", "", $virtualServerEnStat["SNMPv2-SMI::enterprises.3375.2.2.10.13.2.1.3".$oid]);

		if($vsEnabledState != 1) {
			$skip =0;
			if(isset($disabledExceptions) && in_array($value, $disabledExceptions)) {
				$skip = 1;
			}

			if($skip !=1) {
				$vsState = 4;
			}
		}
		$virtualServerConnections[$value]=array("Connections"=>$vsConnections, "Status"=>$vsState);
	}
}catch(Exception $e) {
	nagiosOutput("UNKNOWN", $e->getMessage());
}

$results = parseOutput($virtualServerConnections, $exceptions);

nagiosOutput($results[0], $results[1]);

function parseOutput($virtualServers, $exceptions = NULL) {
	$output = "";
	$perfData = "";
	$overallState = "OK";
	$okCount = 0;

	foreach($virtualServers as $vs=>$stateConn) {
		$skip = false;	

		if($exceptions != NULL && in_array($vs, $exceptions)) {
			$skip = true;
		}

		if(!$skip) {	
			//echo $vs." ".$stateConn['Status'];
			switch(trim($stateConn['Status'])) {
				case 1:
					$state = "OK";
					break;				

				case 2:
					$state = "CRITICAL";
					$overallState = "CRITICAL";
					break;

				case 3:
					$state = "CRITICAL";
					$overallState = "CRITICAL";
					break;

				case 4:
					$state = "WARNING";
					break;			

				default:
					$state = "UNKNOWN";
			}

			if($overallState != "CRITICAL" && $state == "WARNING") {
				$overallState = $state;
			}

			if($overallState != "CRITICAL" && $overallState !="WARNING" && $state == "UNKNOWN") {
				$overallState = $state;
			}

			if($state != "OK") {
				$output .= $vs." ".$state.", ";
			}
			else {
				$okCount++;
			}

			$perfData .= $vs."=".$stateConn['Connections'].";;; ";
		}
	}	

	$output = $output." ".$okCount." Virtual Servers OK | ".$perfData;

	$returnArr = array($overallState,$output);

	return $returnArr;
}

function usage() {
	echo "This performs an SNMP lookup against an LTM and then check all Virtual Servers are OK 
	      and outputs current client connections as nagios PerfData

		Required Values:
			-H Host address
			-C SNMP Community string
		
		Optional Values:
			-t Timeout. Number of microseconds until first timeout
			-r Number of retries
			-e Exceptions. Value is a comma sperated string of exceptions not to check.
			-d Disabled Exceptions. Don't check whether the provided virtual servers are disabled. Comma Seperated

		./check_ltm_vs.php -H IPADDRESS -c COMMUNITYSTRING -e vs_virtualserver1,vs_virtualserver2 -d vs_virtualserver1,vs_virtualserver2 \n\r";
}

function nagiosOutput($result, $message) {

	switch($result) {
		case "OK" :
			echo "OK : ".$message;
			exit(0);
			break;

		case "WARNING" :
			echo "WARNING : ".$message;
			exit(1);
			break;
	
		case "CRITICAL" :
			echo "CRITICAL : ".$message;
			exit(2);
			break;

		default :
			echo "UNKNOWN : ".$message;
			exit(3);
	}
}


?>
