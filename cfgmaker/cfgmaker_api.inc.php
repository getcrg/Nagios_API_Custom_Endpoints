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

function read_config_file($device_name){
    $absolute_path="/usr/local/nagios/etc/services/$device_name.cfg";
    log_message("Testing path " . file_exists($absolute_path) );

    if(file_exists($absolute_path)){
        log_message("Reading existing config file: $absolute_path");
        $file_contents = file_get_contents($absolute_path);
        preg_match_all('/check_xi_service_ifoperstatus!(\d+)/', $file_contents, $matches);
        
        if (!empty($matches[1])) {
            $existing_interfaces = array_unique($matches[1]);
            log_message("Existing onboarded interfaces: " . implode(", ", $existing_interfaces));
            return $existing_interfaces;
        } else {
            log_message("No onboarded interfaces found.");
            
        }
    }else {
        log_message("No existing config file found, starting fresh.");
        
    }
    return null;
}

function create_filter($interface_list){
    // Log interfaces as a string
    log_message("Interfaces : " . implode(", ", $interface_list));
    
    $filter = '';
    foreach ($interface_list as $index) {
        $filter .= "\$if_index == $index || ";
    }

    // Trim the trailing " || "
    $filter = rtrim($filter, " || ");

    log_message("Filter : $filter");
    return $filter;
}
//------------- Onboar interfaces ----------------
/* $result=add_service($endpoint,$api_key,$api_key,$device_name,$ip_address,$interfaces_array); */
function post_payload($url,$payload){
    // cURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    // Log results
    if ($error) {
        log_message("cURL Error: " . $error);
        return "Error: {$error}";
    } else {
        log_message("API Response: " . $response);
        return $reponse;
    }

    curl_close($ch);
}

function create_payload($device_name,$command,$interface,$description){
    $payload = http_build_query([
        "host_name" => $device_name,  // Ensure it's not empty
        "service_description" => $description, 
        "check_command" => $command,
        "check_interval" => 5,
        "retry_interval" => 3,
        "max_check_attempts" => 2,
        "check_period" => "24x7",
        "contacts" => "nagiosadmin",  // REQUIRED: Either contacts OR contact_groups
        "notification_interval" => 5,
        "notification_period" => "24x7",
        "applyconfig" => 1
    ]);
    return $payload;
}

function add_service($endpoint, $api_key, $device_name, $ip_address, $interfaces_array, $snmp_username, $auth_pass,$priv_pass) {
    $url = NAGIOS_API_URL . "config/service?apikey={$api_key}";
    log_message("Creating Payload .....");


    // Ensure $interfaces_array is an array
    if (!is_array($interfaces_array)) {
        log_message("ERROR: interfaces_array is not an array! Type: " . gettype($interfaces_array));
        return;
    }

    foreach ($interfaces_array as $interface) {    
        log_message("Processing Interface: $interface");
        log_message("device Name: $device_name");
        // Construct payload in application/x-www-form-urlencoded format
        $bandwith_command="check_xi_service_mrtgtraf!{$ip_address}_{$interface}.rrd!500.0,500.0!800.0,800.0!M";
        $portStaus_command="check_xi_service_ifoperstatus!''!{$interface}!-v 3 -L authPriv -U {$snmp_username} -a SHA1 -A {$auth_pass} -P DES -X {$priv_pass}";

        $bandwith_payload = create_payload($device_name,$bandwith_command,$interface,"Port {$interface} Bandwith");
        log_message("PortBanwith payload: {$bandwith_payload}");
        $bandwith_result=post_payload($url,$bandwith_payload);

        $portStatus_payload=create_payload($device_name,$portStaus_command,$interface,"Port {$interface} Status");
        log_message("PortStatus payload: {$portStatus_payload}");
        $portStatus_result=post_payload($url,$portStatus_payload);
        log_message("PortStatus result: {$portStatus_result}");

        
    }
}


function run_cfgmaker($ip_address, $username, $auth_pass, $priv_pass, $filter) {
    // Define the output configuration file path
    $output_file = "/etc/mrtg/conf.d/{$ip_address}.cfg";

    // Construct the command
    $command = "/usr/bin/cfgmaker --no-down --noreversedns " .
               "--output {$output_file} " .
               "--if-template=/usr/local/nagiosxi/html/includes/configwizards/switch/template " .
               "--username={$username} " .
               "--authprotocol=SHA " .
               "--authpassword={$auth_pass} " .
               "--privprotocol=des " .
               "--privpassword={$priv_pass} " .
               "--if-filter='{$filter}' " .
               "--contextengineid=NULL " .
               "--snmp-options=':::::3' " .
               "{$ip_address}:161";

    // Log the command for debugging
   // log_message("Executing command: $command");

    // Execute the command
    $output = shell_exec($command . " 2>&1"); // Capture errors
    //log_message("Command output: " . $output);

    return $output;
}

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
    log_message("Interface list $interfaces_array");

    $current_interfaces=read_config_file($device_name);
    log_message("Interface found $current_interfaces");

    if(!$current_interfaces){
        $filter=create_filter($interfaces_array);
    }
    log_message("Filter : $filter");
    $output=run_cfgmaker($ip_address, $username, $auth_pass, $priv_pass, $filter) ;
    //log_message("cfgmaker exec result $output");
    // Ensure `$args` is an array
    $result=add_service($endpoint,$NAGIOS_API_KEY,$device_name,$ip_address,$interfaces_array,$username, $auth_pass,$priv_pass);
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
