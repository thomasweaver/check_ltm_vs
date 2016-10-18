Nagios Plugin for F5 LTM Virtual Server
=======================================

A plugin for nagios to check the state of virtual servers on an LTM as well as creating Performance Data output on the amount of connections to each Virtual Server.  The plugin is written in PHP and uses SNMP to gather the statuses of each Virtual Server. The plugin currently only supports SNMPv2 and has been tested on Big-IP 10.2 only but should work with other version of Big-IP.


Help Output
-----------

This performs an SNMP lookup against an LTM and then check all Virtual Servers are OK and outputs current client connections as nagios PerfData

Required Values:         

	-H Host address
	-C SNMP Community string

Optional Values:
	-t Timeout. Number of microseconds until first timeout
	-r Number of retries
	-e Exceptions. Value is a comma sperated string of exceptions not to check.
    -d Disabled Exceptions. Don't check whether the provided virtual servers are disabled. Comma Seperated

./check_ltm_vs.php -H IPADDRESS -c COMMUNITYSTRING -e vs_virtualserver1,vs_virtualserver2 -d vs_virtualserver1,vs_virtualserver2
