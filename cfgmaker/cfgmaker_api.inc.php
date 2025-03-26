<?php

// Initialize the component
function onboard_interfaces_component_init()
{
    // Information to pass to XI about our component
    $args = array(
        COMPONENT_NAME =>           "onboard_interfaces",
        COMPONENT_VERSION =>        "1.0",
        COMPONENT_AUTHOR =>         "Nagios Enterprises, LLC",
        COMPONENT_DESCRIPTION =>    "Demonstrate Nagios XI Custom API Endpoints",
        COMPONENT_TITLE =>          "Nagios XI Custom API Endpoints Example"
    );

    // Register with XI
    register_component("onboard_interfaces", $args);

    // Register our custom API handler
    register_custom_api_callback('onboard_interface', 'dev', 'onboard_interfaces_onboard_interface_dev');
}

define("NAGIOS_API_URL", "http://192.168.253.53/nagiosxi/api/v1/");
define("SERVICES_PATH", "/usr/local/nagios/etc/services/");
define("CFGMAKER_PATH", "/usr/bin/cfgmaker");
define("LOG_FILE", "/var/log/cfgmaker_api.log");

function log_message($message)
{
    $timestamp = date("Y-m-d H:i:s");
    $log_entry = "[$timestamp] $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

function get_device_name_from_ip($ip_address, $api_key)
{
    log_message("Fetching device name for IP: $ip_address");
    log_message("API KEY : $api_key");
    // Construct the API URL
    $url = NAGIOS_API_URL . "objects/host?apikey=$api_key&address=$ip_address";
    log_message("URL : $url");

    // Fetch the API response
    $response = file_get_contents($url);
    if ($response === FALSE) {
        log_message("ERROR: Failed to fetch Nagios API response");
        return null;
    }

    // Decode the JSON response
    $data = json_decode($response, true);

    // Log the API response
    log_message("API Response: " . json_encode($data, JSON_PRETTY_PRINT));

    if (isset($data['host']) && is_array($data['host'])) {
        foreach ($data['host'] as $host) {
            if (isset($host['address']) && $host['address'] === $ip_address) {
                log_message("Device name found: " . $host['host_name']);
                return $host['host_name'];
            }
        }
    } else {
        log_message("ERROR: Unexpected API response format");
    }

    log_message("ERROR: No device found for IP: $ip_address");
    return null;
}

/*
 https://nagiosxi-lab.getcrg.com/nagiosxi/api/v1/onboard_interface/dev?apikey=LRLklmMU3QgoitCKFlBJih5bg8dmtJVWIfcDHRkELhinSr7abafvgTPYSOIVRdSo&interfaces=1,2&target=192.168.49.52&snmp_version=3&username=snmpuser&auth_pass=CRG3mpow3rs&priv_pass=CRG3mpow3rs
 */
// Function to be called when reaching our custom endpoint via API
function onboard_interfaces_onboard_interface_dev($endpoint, $verb, $args = []) {

    log_message("=== API Request Received ===");
    log_message("Request Params: " . json_encode($_GET));
    // Get parameters from API request
    $url = "{$NAGIOS_API_URL}{$endpoint}?apikey={$api_key}";
    $NAGIOS_API_KEY =  $_GET['apikey'] ?? null;
    log_message($NAGIOS_API_KEY);
    $ip_address = $_GET['target'] ?? null;
    log_message($ip_address);
    $snmp_version = $_GET['snmp_version'] ?? "2c"; // Default to SNMPv2
    log_message($snmp_version);
    $community = $_GET['community'] ?? null;
    log_message($community);
    $username = $_GET['username'] ?? null;
    log_message($username);
    $auth_pass = $_GET['auth_pass'] ?? null;
    log_message($auth_pass);
    $priv_pass = $_GET['priv_pass'] ?? null;
    log_message($priv_pass);
    $interfaces = $_GET['interfaces'] ?? null;
    log_message($interfaces);


    log_message("Argunebtes  $interfaces");
    $device_name=get_device_name_from_ip($ip_address,$NAGIOS_API_KEY);
    log_message("Device Name:  $device_name");
    // Convert interfaces into an array if it's a comma-separated string
    $interfaces_array = $interfaces ? explode(',', htmlspecialchars($interfaces)) : [];
    

    // Ensure `$args` is an array
    $argslist = '';
    if (is_array($args)) {
        foreach ($args as $key => $arg) {
            $argslist .= "<arg><num>{$key}</num><data>" . htmlspecialchars($arg) . "</data></arg>";
        }
    }


    // Construct XML response
    $xml = "<xml>
                <endpoint>" . htmlspecialchars($endpoint) . "</endpoint>
                <verb>" . htmlspecialchars($verb) . "</verb>
                <api_key>" . htmlspecialchars($api_key) . "</api_key>
                <interfaces>" . implode(',', $interfaces_array) . "</interfaces>
                <argslist>{$argslist}</argslist>
            </xml>";

    // Convert XML to JSON
    $data = simplexml_load_string($xml);
    return json_encode($data, JSON_PRETTY_PRINT);
}

// Call the initialization function
onboard_interfaces_component_init();
?>
