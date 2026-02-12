<?php
/**
 * RpiDNS Container Deployment Module
 * 
 * This module generates Docker container setup scripts for RpiDNS deployment.
 * It creates docker-compose.yml, bind configuration, and rsyslog configuration
 * based on user-specific settings from the database.
 * 
 * @package ioc2rpz.gui
 * @author Vadim Pavlov
 * @copyright 2019-2026
 * @license MIT
 * 
 * Requirements: 8.1
 */

/**
 * Generate docker-compose.yml content
 * 
 * @param array $config Configuration array with the following keys:
 *   - hostname: string - RpiDNS hostname
 *   - dns_type: string - "primary" or "secondary"
 *   - dns_ipnet: string - IP network for ACL (if primary)
 *   - logging: string - "local" or "forward"
 *   - logging_host: string - Remote syslog host (if forward mode)
 * @return string docker-compose.yml content
 * 
 * Requirements: 4.6, 5.1, 5.2, 5.3, 5.4, 5.5, 13.4
 */
function generate_docker_compose($config) {
    $hostname = $config['hostname'] ?? 'rpidns.ioc2rpz.rpidns';
    $dns_type = $config['dns_type'] ?? 'primary';
    $dns_ipnet = $config['dns_ipnet'] ?? '';
    $logging = $config['logging'] ?? 'local';
    $logging_host = $config['logging_host'] ?? '';
    
    // Expose syslog port only when in local logging mode (receiving logs)
    $syslog_port = ($logging !== 'forward') ? '      - "10514:10514"' : '';
    
    $compose = <<<YAML
# RpiDNS Docker Compose Configuration
# Generated for: {$hostname}
# DNS Type: {$dns_type}

services:
  bind:
    image: ghcr.io/homas/rpidns-bind:latest
    container_name: rpidns-bind
    hostname: {$hostname}
    restart: unless-stopped
    ports:
      - "53:53/tcp"
      - "53:53/udp"
    volumes:
      - ./config/bind:/etc/bind
      - ./bind-cache:/var/cache/bind
      - ./logs:/opt/rpidns/logs
    environment:
      - RPIDNS_HOSTNAME={$hostname}
      - RPIDNS_DNS_TYPE={$dns_type}
      - RPIDNS_DNS_IPNET={$dns_ipnet}
      - RPIDNS_LOGGING={$logging}
      - RPIDNS_LOGGING_HOST={$logging_host}
    networks:
      - rpidns-net
    healthcheck:
      test: ["CMD", "dig", "@127.0.0.1", "localhost", "+short", "+time=2", "+tries=1"]
      interval: 30s
      timeout: 10s
      retries: 3

  web:
    image: ghcr.io/homas/rpidns-web:latest
    container_name: rpidns-web
    hostname: {$hostname}
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
{$syslog_port}
    volumes:
      - ./config/nginx:/opt/rpidns/conf
#      - ./www:/opt/rpidns/www
      - ./www/rpisettings.php:/opt/rpidns/www/rpisettings.php
      - ./www/db:/opt/rpidns/www/db
      - ./logs:/opt/rpidns/logs
      - ./scripts:/opt/rpidns/scripts
      - ./config/bind:/etc/bind
      - /var/run/docker.sock:/var/run/docker.sock
    environment:
      - RPIDNS_HOSTNAME={$hostname}
      - RPIDNS_LOGGING={$logging}
      - RPIDNS_LOGGING_HOST={$logging_host}
    depends_on:
      bind:
        condition: service_healthy
    networks:
      - rpidns-net
    healthcheck:
      test: ["CMD", "wget", "-q", "--spider", "http://127.0.0.1/blocked.php"]
      interval: 30s
      timeout: 10s
      retries: 3

networks:
  rpidns-net:
    driver: bridge
YAML;

    return $compose;
}

/**
 * Generate Bind named.conf.options content
 * 
 * @param array $rpz_feeds Array of RPZ feed configurations, each containing:
 *   - rpz: string - RPZ zone name
 *   - action: string - Policy action (nxdomain, cname, passthru)
 *   - description: string - Feed description
 *   - type: string - Feed type (w=whitelist, d=domain, v=verified, m=mixed, i=ip)
 *   - ip: string - Server IP for zone transfer
 *   - tkey_name: string - TSIG key name
 *   - tkey_alg: string - TSIG key algorithm
 *   - tkey: string - TSIG key secret
 * @param array $config General configuration:
 *   - hostname: string - RpiDNS hostname
 *   - dns_type: string - "primary" or "secondary"
 *   - dns_ipnet: string - IP network for ACL
 *   - logging: string - "local" or "forward"
 *   - logging_host: string - Remote syslog host
 *   - redirect: string - Redirect mode ("default" or custom)
 *   - redirect_cname: string - Custom redirect CNAME
 * @return string named.conf.options content
 * 
 * Requirements: 3.1, 3.2, 3.6, 6.3, 6.4, 6.7
 */
function generate_bind_config($rpz_feeds, $config) {
    $hostname = $config['hostname'] ?? 'rpidns';
    $dns_type = $config['dns_type'] ?? 'primary';
    $dns_ipnet = $config['dns_ipnet'] ?? '';
    $logging = $config['logging'] ?? 'local';
    $logging_host = $config['logging_host'] ?? '';
    $redirect = $config['redirect'] ?? 'default';
    $redirect_cname = $config['redirect_cname'] ?? '';
    
    $rpiname = $hostname . ".ioc2rpz.rpidns";
    $redirect_default = "cname " . ($redirect == 'default' ? $rpiname : $redirect_cname);
    
    // Build RPZ response-policy zones
    $feeds = "\n    zone \"allow.ioc2rpz.rpidns\" policy passthru log no; # local allow list\n";
    $feeds_files = "";
    $tkey_definitions = "";
    $zone_files = "";
    $ztype = "w";
    $processed_keys = [];
    
    if (!empty($rpz_feeds)) {
        foreach ($rpz_feeds as $rpz) {
            // Handle zone type transitions for local zones
            if ($ztype != $rpz['type']) {
                switch ($ztype . $rpz['type']) {
                    case "wd":
                    case "wt":
                        $feeds .= "    zone \"block.ioc2rpz.rpidns\" policy {$redirect_default}; # local blocklist\n";
                        break;
                    case "wv":
                    case "wm":
                        $feeds .= "    zone \"block.ioc2rpz.rpidns\" policy {$redirect_default}; # local blocklist\n";
                        $feeds .= "    zone \"allow-ip.ioc2rpz.rpidns\" policy passthru log no; # local allow ip-based\n";
                        break;
                    case "wi":
                        $feeds .= "    zone \"block.ioc2rpz.rpidns\" policy {$redirect_default}; # local blocklist\n";
                        $feeds .= "    zone \"allow-ip.ioc2rpz.rpidns\" policy passthru log no; # local allow ip-based\n";
                        $feeds .= "    zone \"block-ip.ioc2rpz.rpidns\" policy {$redirect_default}; # local blocklist ip-based\n";
                        break;
                    case "dv":
                    case "dm":
                    case "tv":
                    case "tm":
                        $feeds .= "    zone \"allow-ip.ioc2rpz.rpidns\" policy passthru log no; # local allow ip-based\n";
                        break;
                    case "di":
                    case "ti":
                        $feeds .= "    zone \"allow-ip.ioc2rpz.rpidns\" policy passthru log no; # local allow ip-based\n";
                        $feeds .= "    zone \"block-ip.ioc2rpz.rpidns\" policy {$redirect_default}; # local blocklist ip-based\n";
                        break;
                    case "vm":
                    case "vi":
                        $feeds .= "    zone \"block-ip.ioc2rpz.rpidns\" policy {$redirect_default}; # local blocklist ip-based\n";
                        break;
                }
                $ztype = $rpz['type'];
            }
            
            // Determine action for this feed
            $redirect_action = ($rpz['redirect'] ?? 'default') == 'default' ? $rpiname : ($rpz['redirect_cname'] ?? $rpiname);
            $action = ($rpz['action'] ?? 'nxdomain') == 'cname' ? 'cname ' . $redirect_action : ($rpz['action'] ?? 'nxdomain');
            
            // Add feed to response-policy
            $description = preg_replace('~[\r\n]+~', '', $rpz['description'] ?? '');
            $feeds .= "    zone \"{$rpz['rpz']}\" policy {$action}; # {$description}\n";
            
            // Add zone definition for secondary zone
            $tkey_name = $rpz['tkey_name'] ?? '';
            $server_ip = $rpz['ip'] ?? '';
            
            if (!empty($tkey_name) && !empty($server_ip)) {
                $feeds_files .= "zone \"{$rpz['rpz']}\" {\n";
                $feeds_files .= "    type secondary;\n";
                $feeds_files .= "    file \"/var/cache/bind/{$rpz['rpz']}\";\n";
                $feeds_files .= "    primaries { {$server_ip} key \"{$tkey_name}\"; };\n";
                $feeds_files .= "    ixfr-from-differences no;\n";
                $feeds_files .= "};\n\n";
                
                // Add TSIG key definition (only once per key)
                if (!in_array($tkey_name, $processed_keys)) {
                    $tkey_alg = $rpz['tkey_alg'] ?? 'sha256';
                    $tkey_secret = $rpz['tkey'] ?? '';
                    $tkey_definitions .= "key \"{$tkey_name}\" {\n";
                    $tkey_definitions .= "    algorithm hmac-{$tkey_alg};\n";
                    $tkey_definitions .= "    secret \"{$tkey_secret}\";\n";
                    $tkey_definitions .= "};\n\n";
                    $processed_keys[] = $tkey_name;
                }
                
                // Zone file initialization command
                $zone_files .= "[ ! -f /var/cache/bind/{$rpz['rpz']} ] && cp /etc/bind/db.empty.pi /var/cache/bind/{$rpz['rpz']}\n";
            }
        }
    }
    
    // Ensure all local zones are included in response-policy (fix for missing zones)
    if (strpos($feeds, 'block.ioc2rpz.rpidns') === false) {
        $feeds .= "    zone \"block.ioc2rpz.rpidns\" policy {$redirect_default}; # local blocklist\n";
    }
    if (strpos($feeds, 'allow-ip.ioc2rpz.rpidns') === false) {
        $feeds .= "    zone \"allow-ip.ioc2rpz.rpidns\" policy passthru log no; # local allow ip-based\n";
    }
    if (strpos($feeds, 'block-ip.ioc2rpz.rpidns') === false) {
        $feeds .= "    zone \"block-ip.ioc2rpz.rpidns\" policy {$redirect_default}; # local blocklist ip-based\n";
    }
    
    // Build logging configuration
    // When in forward mode, configure bind to send logs to external syslog host
    // Requirements: 10.4, 11.6
    $logging_config = "";
    $syslog_channel = "";
    if ($logging == 'forward' && !empty($logging_host)) {
        // Configure syslog channel to forward to external host
        // Bind uses local4 facility for DNS logs
        $syslog_channel = "    channel forward_syslog {\n        syslog local4;\n        severity info;\n        print-time iso8601;\n        print-category yes;\n        print-severity yes;\n    };\n";
        $logging_config = "    category rpz { forward_syslog; rpz_log; };\n    category queries { forward_syslog; bind_qlog; };";
    }
    
    // Build ACL configuration
    $acl_config = "";
    if ($dns_type == 'primary' && !empty($dns_ipnet)) {
        $acl_config = "acl \"allow_update\" {\n    localnets;\n    {$dns_ipnet};\n};\n\n";
    } else {
        $acl_config = "acl \"allow_update\" {\n    localnets;\n};\n\n";
    }
    
    // Build local zones based on DNS type
    $local_zones = "";
    if ($dns_type == 'primary') {
        $local_zones = <<<ZONES
// Local zones (primary mode)
zone "allow.ioc2rpz.rpidns" { type primary; notify yes; allow-update { allow_update; }; file "/var/cache/bind/allow.ioc2rpz.rpidns"; };
zone "allow-ip.ioc2rpz.rpidns" { type primary; notify yes; allow-update { allow_update; }; file "/var/cache/bind/allow-ip.ioc2rpz.rpidns"; };
zone "block.ioc2rpz.rpidns" { type primary; notify yes; allow-update { allow_update; }; file "/var/cache/bind/block.ioc2rpz.rpidns"; };
zone "block-ip.ioc2rpz.rpidns" { type primary; notify yes; allow-update { allow_update; }; file "/var/cache/bind/block-ip.ioc2rpz.rpidns"; };
zone "ioc2rpz.rpidns" { type primary; notify yes; allow-update { allow_update; }; file "/var/cache/bind/ioc2rpz.rpidns"; };
ZONES;
    } else {
        $local_zones = <<<ZONES
// Local zones (secondary mode)
zone "allow.ioc2rpz.rpidns" { type secondary; primaries { {$dns_ipnet}; }; file "/var/cache/bind/allow.ioc2rpz.rpidns"; };
zone "allow-ip.ioc2rpz.rpidns" { type secondary; primaries { {$dns_ipnet}; }; file "/var/cache/bind/allow-ip.ioc2rpz.rpidns"; };
zone "block.ioc2rpz.rpidns" { type secondary; primaries { {$dns_ipnet}; }; file "/var/cache/bind/block.ioc2rpz.rpidns"; };
zone "block-ip.ioc2rpz.rpidns" { type secondary; primaries { {$dns_ipnet}; }; file "/var/cache/bind/block-ip.ioc2rpz.rpidns"; };
zone "ioc2rpz.rpidns" { type secondary; primaries { {$dns_ipnet}; }; file "/var/cache/bind/ioc2rpz.rpidns"; };
ZONES;
    }

    $named_conf = <<<CONF
// RpiDNS Bind Configuration
// Generated for: {$hostname}
// DNS Type: {$dns_type}

// Include rndc key for remote control
include "/etc/bind/rndc.key";

// Controls for rndc
controls {
    inet 127.0.0.1 port 953 allow { 127.0.0.1; } keys { "rndc-key"; };
};

options {
    directory "/var/cache/bind";
    dnssec-validation auto;
    auth-nxdomain no;
    listen-on { any; };
    listen-on-v6 { any; };
    allow-query { any; };
    bindkeys-file "/etc/bind/bind.keys";
    empty-zones-enable yes;
    recursion yes;
    
    response-policy {
{$feeds}
    } max-policy-ttl 30 qname-wait-recurse no break-dnssec yes;
};

logging {
    channel bind_log {
        file "/opt/rpidns/logs/bind.log" versions 10 size 20m;
        severity info;
        print-time iso8601;
        print-severity yes;
        print-category yes;
    };
    channel bind_qlog {
        file "/opt/rpidns/logs/bind_queries.log" versions 10 size 20m;
        severity info;
        print-time iso8601;
        print-severity yes;
        print-category yes;
    };
    channel rpz_log {
        file "/opt/rpidns/logs/bind_rpz.log" versions 10 size 20m;
        print-time iso8601;
        print-category yes;
        print-severity yes;
        severity info;
    };
    channel default_syslog { print-category yes; syslog local4; severity info; };
{$syslog_channel}
    category default { default_syslog; };
    category default { bind_log; };
    category queries { bind_qlog; };
    category rpz { bind_qlog; };
    category rpz { rpz_log; };
    category resolver { bind_qlog; };
{$logging_config}
};

{$acl_config}
// TSIG Keys
{$tkey_definitions}
// RPZ Zone Definitions
{$feeds_files}
{$local_zones}

// Root hints
zone "." {
    type hint;
    file "/var/bind/root.cache";
};
CONF;

    return $named_conf;
}

/**
 * Generate RSyslog configuration
 * 
 * @param string $logging_mode "local" or "forward"
 * @param string $logging_host Remote syslog host (if forward mode)
 * @return string rsyslog.conf content
 * 
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6
 */
function generate_rsyslog_config($logging_mode, $logging_host) {
    if ($logging_mode == 'forward' && !empty($logging_host)) {
        // Forward mode: send logs to remote syslog host (Requirement 11.6)
        $rsyslog_conf = <<<CONF
# RpiDNS RSyslog Configuration - Forward Mode
# Forwards bind logs to: {$logging_host}

module(load="imuxsock")

# Forward local4 (bind) logs to remote syslog host
local4.* @@{$logging_host}:10514

# Default local logging
*.info;mail.none;authpriv.none;cron.none;local4.none /var/log/messages
authpriv.* /var/log/secure
mail.* -/var/log/maillog
cron.* /var/log/cron
*.emerg :omusrmsg:*
CONF;
    } else {
        // Local mode: receive logs from remote RpiDNS instances (Requirements: 11.1, 11.2, 11.5)
        $rsyslog_conf = <<<CONF
# RpiDNS RSyslog Configuration - Local Mode
# Receives syslog messages from remote RpiDNS instances

#################
#### MODULES ####
#################

# Provides support for local system logging
module(load="imuxsock")

# Provides TCP syslog reception on port 10514 (Requirement 11.1, 11.2)
module(load="imtcp")
input(type="imtcp" port="10514")

###########################
#### GLOBAL DIRECTIVES ####
###########################

# Use RFC3339 timestamp format
\$ActionFileDefaultTemplate RSYSLOG_FileFormat

# Set default permissions for log files
\$FileOwner root
\$FileGroup adm
\$FileCreateMode 0640
\$DirCreateMode 0755
\$Umask 0022

# Work directory for rsyslog
\$WorkDirectory /var/lib/rsyslog

###############
#### RULES ####
###############

# Template for RpiDNS bind query logs with source IP in filename (Requirement 11.3, 11.4)
# RFC3339 timestamp format: 2024-01-15T10:30:45.123456+00:00
template(name="RpiDNSBindLog" type="string"
    string="/opt/rpidns/logs/bind_%fromhost-ip%_queries.log")

template(name="RFC3339Format" type="string"
    string="%timegenerated:::date-rfc3339% %fromhost-ip% %syslogtag%%msg%\n")

# Route bind/named logs to per-source-IP files
if \$programname == 'named' or \$programname == 'bind' or \$syslogfacility-text == 'local4' then {
    action(type="omfile" dynaFile="RpiDNSBindLog" template="RFC3339Format")
    stop
}

# Default rules for local logging
*.info;mail.none;authpriv.none;cron.none    /var/log/messages
authpriv.*                                   /var/log/secure
mail.*                                       -/var/log/maillog
cron.*                                       /var/log/cron
*.emerg                                      :omusrmsg:*
local7.*                                     /var/log/boot.log
CONF;
    }
    
    return $rsyslog_conf;
}


/**
 * Generate container setup script for a RpiDNS instance
 * 
 * This is the main function that generates a complete bash script for deploying
 * RpiDNS using Docker containers. The script creates directories, configuration
 * files, and docker-compose.yml with user-specific settings.
 * 
 * @param SQLite3 $db Database connection handle
 * @param string $uuid RpiDNS device UUID
 * @return string Generated bash script content
 * 
 * Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 7.1, 7.4, 9.4
 */
function generate_install_script_docker($db, $uuid) {
    // Sanitize UUID to prevent SQL injection
    $safe_uuid = DB_escape($db, $uuid);

    // Query database for RPZ feeds and configuration
    // Uses SQLite JSON functions matching the schema in io2install_rpidns.php
    $sql = "SELECT rpidns_uuid, rpi_name as name, comment, model, dns, updconf, feed as rpz, action, 
            '' as description, pub_ip as ip, tkey_name, alg as tkey_alg, tkey, 
            logging, logging_host, redirect, redirect_cname, dns_type, dns_ipnet,
            CASE 
                WHEN action IN ('disabled','passthrunolog','passthru') AND ioc_type IN ('fqdn','mixed') THEN 'w' 
                WHEN action IN ('disabled','passthrunolog','passthru') AND ioc_type='ip' THEN 'v' 
                WHEN action IN ('cname','nxdomain','nodata','drop') AND ioc_type='fqdn' THEN 'd' 
                WHEN action IN ('cname','nxdomain','nodata','drop') AND ioc_type='mixed' THEN 'm' 
                WHEN action IN ('cname','nxdomain','nodata','drop') AND ioc_type='ip' THEN 'i' 
            END as type
            FROM (
                SELECT *, tk.name as tkey_name, tk.rowid as tkeys_rowid, s.rowid as srv_rowid 
                FROM (
                    SELECT name as rpi_name, commentary as comment, rpidns_uuid, 
                           json_extract(configuration,'$.model') as model, 
                           json_extract(configuration,'$.dns') as dns, 
                           json_extract(configuration,'$.updconf') as updconf,
                           json_extract(configuration,'$.redirect') as redirect, 
                           json_extract(configuration,'$.redirect_cname') as redirect_cname, 
                           json_extract(configuration,'$.logging') as logging, 
                           json_extract(configuration,'$.logging_host') as logging_host, 
                           json_extract(configuration,'$.dns_type') as dns_type, 
                           json_extract(configuration,'$.dns_ipnet') as dns_ipnet,
                           json_extract(value,'$.feed') as feed,
                           json_extract(value,'$.action') as action 
                    FROM rpidns, json_each(json_extract(rpidns.configuration,'$.rpz')) 
                    WHERE rpidns_uuid='$safe_uuid'
                ) as f 
                LEFT JOIN rpzs r ON f.feed=r.name 
                LEFT JOIN rpzs_servers rs ON r.rowid=rs.rpz_id 
                LEFT JOIN servers s ON s.rowid=rs.server_id 
                LEFT JOIN rpzs_tkeys rtk ON r.rowid=rtk.rpz_id 
                LEFT JOIN tkeys tk ON tk.rowid=rtk.tkey_id 
                WHERE s.disabled=0 AND tk.mgmt=0
            ) 
            GROUP BY rpidns_uuid, rpz
            ORDER BY CASE type 
                WHEN 'w' THEN 1 
                WHEN 'd' THEN 2 
                WHEN 'v' THEN 2 
                WHEN 'm' THEN 3 
                WHEN 'i' THEN 4 
                ELSE 5 
            END";
    
    $rpz_feeds = DB_selectArray($db, $sql);
    
    if (empty($rpz_feeds)) {
        return "#!/bin/bash\necho 'Error: No configuration found for this RpiDNS instance'\nexit 1\n";
    }
    
    // Extract configuration from first feed row
    $hostname = $rpz_feeds[0]['name'];
    $dns_type = $rpz_feeds[0]['dns_type'] ?? 'primary';
    $dns_ipnet = $rpz_feeds[0]['dns_ipnet'] ?? '';
    $logging = $rpz_feeds[0]['logging'] ?? 'local';
    $logging_host = $rpz_feeds[0]['logging_host'] ?? '';
    $redirect = $rpz_feeds[0]['redirect'] ?? 'default';
    $redirect_cname = $rpz_feeds[0]['redirect_cname'] ?? '';
    
    // Build configuration array
    $config = [
        'hostname' => $hostname,
        'dns_type' => $dns_type,
        'dns_ipnet' => $dns_ipnet,
        'logging' => $logging,
        'logging_host' => $logging_host,
        'redirect' => $redirect,
        'redirect_cname' => $redirect_cname
    ];
    
    // Generate docker-compose.yml content
    $docker_compose = generate_docker_compose($config);
    
    // Generate bind configuration
    $bind_config = generate_bind_config($rpz_feeds, $config);
    
    // Generate rsyslog configuration
    $rsyslog_config = generate_rsyslog_config($logging, $logging_host);
    
    // Build zone file initialization commands
    $zone_init_commands = "";
    foreach ($rpz_feeds as $rpz) {
        if (!empty($rpz['rpz'])) {
            $zone_init_commands .= "[ ! -f \"\${BIND_CACHE_DIR}/{$rpz['rpz']}\" ] && cp \"\${BIND_CONFIG_DIR}/db.empty.pi\" \"\${BIND_CACHE_DIR}/{$rpz['rpz']}\"\n";
        }
    }
    
    // Extract TSIG key info for display
    $tkey_name = $rpz_feeds[0]['tkey_name'] ?? '';
    $tkey_alg = $rpz_feeds[0]['tkey_alg'] ?? 'hmac-sha256';
    
    // Build the setup script
    $script = <<<'SCRIPT'
#!/bin/bash
##############################################################################
# RpiDNS Container Setup Script
# Generated by ioc2rpz.net community portal
#
# This script sets up RpiDNS using Docker containers.
# Pre-built images are pulled from the container registry.
##############################################################################

set -e

# Check if running as root or with docker permissions
if [[ $EUID -ne 0 ]]; then
    if ! groups | grep -q docker; then
        echo "Error: This script requires root privileges or docker group membership."
        echo "Run with: sudo bash $0"
        exit 1
    fi
fi

# Check for Docker
if ! command -v docker &> /dev/null; then
    echo "Error: Docker is not installed."
    echo "Please install Docker first: https://docs.docker.com/get-docker/"
    exit 1
fi

# Check for docker-compose or docker compose
DOCKER_COMPOSE=""
if command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
elif docker compose version &> /dev/null 2>&1; then
    DOCKER_COMPOSE="docker compose"
else
    echo "Error: docker-compose is not installed."
    echo "Please install docker-compose: https://docs.docker.com/compose/install/"
    exit 1
fi

# Check for port conflicts
check_port() {
    if netstat -tuln 2>/dev/null | grep -q ":$1 " || ss -tuln 2>/dev/null | grep -q ":$1 "; then
        echo "Warning: Port $1 is already in use."
        return 1
    fi
    return 0
}

echo "Checking for port conflicts..."
PORTS_OK=true
check_port 53 || PORTS_OK=false
check_port 80 || PORTS_OK=false
check_port 443 || PORTS_OK=false

if [ "$PORTS_OK" = false ]; then
    echo ""
    echo "Some ports are already in use. Continue anyway? (y/N)"
    read -r CONTINUE
    if [ "$CONTINUE" != "y" ] && [ "$CONTINUE" != "Y" ]; then
        echo "Aborted."
        exit 1
    fi
fi

echo ""
echo "=========================================="
echo "RpiDNS Container Setup"
echo "=========================================="

SCRIPT;

    // Add configuration variables
    $script .= <<<SCRIPT

# Configuration
RPIDNS_UUID="{$rpidns_uuid}"
RPIDNS_HOSTNAME="{$hostname}"
RPIDNS_DNS_TYPE="{$dns_type}"
RPIDNS_DNS_IPNET="{$dns_ipnet}"
RPIDNS_LOGGING="{$logging}"
RPIDNS_LOGGING_HOST="{$logging_host}"
export RPIDNS_INSTALL_TYPE="container"
TSIG_KEY_NAME="{$tkey_name}"
TSIG_KEY_ALG="{$tkey_alg}"

echo "Hostname: \${RPIDNS_HOSTNAME}"
echo "DNS Type: \${RPIDNS_DNS_TYPE}"
echo "Logging Mode: \${RPIDNS_LOGGING}"
echo ""

SCRIPT;

    // Add directory creation
    $script .= <<<'SCRIPT'

# Create directory structure
echo "Creating directory structure..."
INSTALL_DIR="/opt/rpidns"
BIND_CONFIG_DIR="${INSTALL_DIR}/config/bind"
BIND_CACHE_DIR="${INSTALL_DIR}/bind-cache"
NGINX_CONFIG_DIR="${INSTALL_DIR}/config/nginx"
LOGS_DIR="${INSTALL_DIR}/logs"
WWW_DIR="${INSTALL_DIR}/www"
SCRIPTS_DIR="${INSTALL_DIR}/scripts"

mkdir -p "${BIND_CONFIG_DIR}"
mkdir -p "${BIND_CACHE_DIR}"
mkdir -p "${NGINX_CONFIG_DIR}"
mkdir -p "${NGINX_CONFIG_DIR}/ssl"
mkdir -p "${NGINX_CONFIG_DIR}/ssl_sign"
mkdir -p "${NGINX_CONFIG_DIR}/ssl_cache"
mkdir -p "${LOGS_DIR}"
mkdir -p "${WWW_DIR}"
mkdir -p "${WWW_DIR}/db"
mkdir -p "${SCRIPTS_DIR}"

# Set permissions
echo "Setting permissions..."
chmod 755 "${INSTALL_DIR}"
chmod 755 "${BIND_CONFIG_DIR}"
chmod 755 "${BIND_CACHE_DIR}"
chmod 755 "${NGINX_CONFIG_DIR}"
chmod 700 "${NGINX_CONFIG_DIR}/ssl"
chmod 755 "${LOGS_DIR}"
chmod 755 "${WWW_DIR}"
chmod 755 "${SCRIPTS_DIR}"

SCRIPT;

    // Add docker-compose.yml generation
    $docker_compose_escaped = str_replace("'", "'\\''", $docker_compose);
    $script .= <<<SCRIPT

# Generate docker-compose.yml
echo "Generating docker-compose.yml..."
cat > "\${INSTALL_DIR}/docker-compose.yml" << 'DOCKER_COMPOSE_EOF'
{$docker_compose}
DOCKER_COMPOSE_EOF

SCRIPT;

    // Add bind configuration generation
    $bind_config_escaped = str_replace("'", "'\\''", $bind_config);
    $script .= <<<SCRIPT

# Generate bind configuration
echo "Generating bind configuration..."
cat > "\${BIND_CONFIG_DIR}/named.conf" << 'BIND_CONFIG_EOF'
{$bind_config}
BIND_CONFIG_EOF

SCRIPT;

    // Add empty zone template
    $script .= <<<'SCRIPT'

# Create empty zone template
echo "Creating zone templates..."
cat > "${BIND_CONFIG_DIR}/db.empty.pi" << 'ZONE_EOF'
$TTL    3600
@       IN      SOA     localhost. root.localhost. (
                              1         ; Serial
                         3600         ; Refresh
                         3600         ; Retry
                       2419200         ; Expire
                          300 )       ; Negative Cache TTL
;
@       IN      NS      localhost.
ZONE_EOF

SCRIPT;

    // Add zone file initialization
    $script .= <<<SCRIPT

# Initialize zone files
echo "Initializing zone files..."
{$zone_init_commands}
# Initialize local zones
[ ! -f "\${BIND_CACHE_DIR}/allow.ioc2rpz.rpidns" ] && cp "\${BIND_CONFIG_DIR}/db.empty.pi" "\${BIND_CACHE_DIR}/allow.ioc2rpz.rpidns"
[ ! -f "\${BIND_CACHE_DIR}/allow-ip.ioc2rpz.rpidns" ] && cp "\${BIND_CONFIG_DIR}/db.empty.pi" "\${BIND_CACHE_DIR}/allow-ip.ioc2rpz.rpidns"
[ ! -f "\${BIND_CACHE_DIR}/block.ioc2rpz.rpidns" ] && cp "\${BIND_CONFIG_DIR}/db.empty.pi" "\${BIND_CACHE_DIR}/block.ioc2rpz.rpidns"
[ ! -f "\${BIND_CACHE_DIR}/block-ip.ioc2rpz.rpidns" ] && cp "\${BIND_CONFIG_DIR}/db.empty.pi" "\${BIND_CACHE_DIR}/block-ip.ioc2rpz.rpidns"
[ ! -f "\${BIND_CACHE_DIR}/ioc2rpz.rpidns" ] && cp "\${BIND_CONFIG_DIR}/db.empty.pi" "\${BIND_CACHE_DIR}/ioc2rpz.rpidns"

# Add A records for hostname to ioc2rpz.rpidns zone
echo "Adding A records for hostname to ioc2rpz.rpidns zone..."

# Get host IP addresses - supports both Linux (ip) and macOS (ifconfig)
get_host_ips() {
    if command -v ip &> /dev/null; then
        # Linux: use ip command
        ip -4 addr show 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | grep -v '^127\.' | grep -v '^172\.17\.' | head -5
    elif command -v ifconfig &> /dev/null; then
        # macOS/BSD: use ifconfig
        ifconfig 2>/dev/null | grep 'inet ' | awk '{print \$2}' | grep -v '^127\.' | grep -v '^172\.17\.' | head -5
    fi
}

get_host_ipv6() {
    if command -v ip &> /dev/null; then
        # Linux: use ip command
        ip -6 addr show 2>/dev/null | grep -oP '(?<=inet6\s)[0-9a-f:]+' | grep -v '^::1' | grep -v '^fe80' | head -5
    elif command -v ifconfig &> /dev/null; then
        # macOS/BSD: use ifconfig
        ifconfig 2>/dev/null | grep 'inet6 ' | awk '{print \$2}' | grep -v '^::1' | grep -v '^fe80' | head -5
    fi
}

HOST_IPS=\$(get_host_ips)
HOST_IPV6=\$(get_host_ipv6)

# Create ioc2rpz.rpidns zone with A records
SERIAL=\$(date +%Y%m%d%H)
cat > "\${BIND_CACHE_DIR}/ioc2rpz.rpidns" << ZONE_RPIDNS_EOF
\\\$TTL    3600
@       IN      SOA     localhost. root.localhost. (
                        \${SERIAL}  ; Serial
                         3600         ; Refresh
                         3600         ; Retry
                       2419200         ; Expire
                          300 )       ; Negative Cache TTL
;
@       IN      NS      localhost.
ZONE_RPIDNS_EOF

# Add A records for each IPv4 address
for IP in \${HOST_IPS}; do
    echo "{$rpz_feeds[0]['name']}    IN      A       \${IP}" >> "\${BIND_CACHE_DIR}/ioc2rpz.rpidns"
    echo "  Added A record: {$rpz_feeds[0]['name']} -> \${IP}"
done

# Add AAAA records for each IPv6 address
for IP6 in \${HOST_IPV6}; do
    echo "{$rpz_feeds[0]['name']}    IN      AAAA    \${IP6}" >> "\${BIND_CACHE_DIR}/ioc2rpz.rpidns"
    echo "  Added AAAA record: {$rpz_feeds[0]['name']} -> \${IP6}"
done

# If no IPs found, add localhost as fallback
if [ -z "\${HOST_IPS}" ] && [ -z "\${HOST_IPV6}" ]; then
    echo "{$rpz_feeds[0]['name']}    IN      A       127.0.0.1" >> "\${BIND_CACHE_DIR}/ioc2rpz.rpidns"
    echo "  Warning: No host IPs found, using localhost fallback"
fi

echo "✓ ioc2rpz.rpidns zone configured with hostname A records"

SCRIPT;

    // Add rsyslog configuration
    $rsyslog_config_escaped = str_replace("'", "'\\''", $rsyslog_config);
    $script .= <<<SCRIPT

# Generate rsyslog configuration
echo "Generating rsyslog configuration..."
cat > "\${NGINX_CONFIG_DIR}/rsyslog.conf" << 'RSYSLOG_EOF'
{$rsyslog_config}
RSYSLOG_EOF

SCRIPT;

    // Add RpiDNS application setup (Requirements: 13.1, 13.2, 13.3, 13.4)
    $script .= <<<'SCRIPT'

# Clone RpiDNS repository and copy files
# Requirements: 13.1, 13.2
echo ""
echo "=========================================="
echo "Setting up RpiDNS Application"
echo "=========================================="

RPIDNS_REPO="https://github.com/Homas/RpiDNS.git"
TEMP_DIR=$(mktemp -d)
CLONE_SUCCESS=false

echo "Cloning RpiDNS repository..."
cd "${TEMP_DIR}"

if git clone --depth 1 --single-branch "${RPIDNS_REPO}" 2>&1; then
    CLONE_SUCCESS=true
    echo "✓ Repository cloned successfully"
    
    # Copy web files to /opt/rpidns/www (Requirement 13.1)
    echo "Copying web files to ${WWW_DIR}..."
    if [ -d "RpiDNS/www" ]; then
        cp -R RpiDNS/www/* "${WWW_DIR}/" 2>/dev/null || true
        echo "✓ Web files copied to ${WWW_DIR}"
    else
        echo "Warning: www directory not found in repository"
    fi
    
    # Copy scripts to /opt/rpidns/scripts (Requirement 13.2)
    echo "Copying scripts to ${SCRIPTS_DIR}..."
    if [ -d "RpiDNS/scripts" ]; then
        cp -R RpiDNS/scripts/* "${SCRIPTS_DIR}/" 2>/dev/null || true
        chmod +x "${SCRIPTS_DIR}"/*.sh 2>/dev/null || true
        echo "✓ Scripts copied to ${SCRIPTS_DIR}"
    else
        echo "Warning: scripts directory not found in repository"
    fi
    
    # Execute rpidns_install.sh (Requirement 13.3)
    echo ""
    echo "Running RpiDNS installation script..."
    if [ -f "${SCRIPTS_DIR}/rpidns_install.sh" ]; then
        chmod +x "${SCRIPTS_DIR}/rpidns_install.sh"
        # Run install script with INSTALL_DIR environment variable
        RPIDNS_INSTALL_DIR="${INSTALL_DIR}" bash "${SCRIPTS_DIR}/rpidns_install.sh" 2>&1 || {
            echo "Warning: Installation script completed with warnings"
        }
        echo "✓ Installation script executed"
    else
        echo "Note: rpidns_install.sh not found, skipping"
    fi
else
    echo "✗ Could not clone RpiDNS repository"
    echo "  Network error or repository unavailable"
    echo "  Web UI may not be fully functional"
fi

cd - > /dev/null
rm -rf "${TEMP_DIR}"

# Ensure SQLite database directory exists and has correct permissions (Requirement 13.4, 4.6)
echo ""
echo "Configuring SQLite database directory..."
mkdir -p "${WWW_DIR}/db"
chmod 755 "${WWW_DIR}/db"
# Set ownership for www-data (UID 82 in Alpine)
chown -R 82:82 "${WWW_DIR}/db" 2>/dev/null || true
echo "✓ Database directory configured at ${WWW_DIR}/db"

# Set final permissions on www directory
echo "Setting web directory permissions..."
chown -R 82:82 "${WWW_DIR}" 2>/dev/null || true
chmod -R 755 "${WWW_DIR}"
echo "✓ Web directory permissions set"

SCRIPT;

    // Add container startup and verification
    $script .= <<<'SCRIPT'

# Set final permissions
echo "Setting final permissions..."
chown -R 100:101 "${BIND_CACHE_DIR}" 2>/dev/null || true  # named:named in Alpine
chmod -R 755 "${BIND_CACHE_DIR}"

# Pull container images
echo ""
echo "Pulling container images..."
cd "${INSTALL_DIR}"
${DOCKER_COMPOSE} pull

# Start containers
echo ""
echo "Starting containers..."
${DOCKER_COMPOSE} up -d

# Wait for containers to be healthy
echo ""
echo "Waiting for containers to start..."
sleep 10

# Verification
echo ""
echo "=========================================="
echo "Verification"
echo "=========================================="

# Check container status
echo ""
echo "Container Status:"
${DOCKER_COMPOSE} ps

# Test DNS resolution
echo ""
echo "Testing DNS resolution..."
if command -v dig &> /dev/null; then
    if dig @127.0.0.1 localhost +short +time=5 +tries=1 > /dev/null 2>&1; then
        echo "✓ DNS service is responding"
    else
        echo "✗ DNS service is not responding (may still be starting)"
    fi
else
    echo "Note: 'dig' not installed, skipping DNS test"
fi

# Test web service
echo ""
echo "Testing web service..."
if command -v curl &> /dev/null; then
    if curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1/blocked.php 2>/dev/null | grep -q "200"; then
        echo "✓ Web service is responding"
    else
        echo "✗ Web service is not responding (may still be starting)"
    fi
elif command -v wget &> /dev/null; then
    if wget -q --spider http://127.0.0.1/blocked.php 2>/dev/null; then
        echo "✓ Web service is responding"
    else
        echo "✗ Web service is not responding (may still be starting)"
    fi
else
    echo "Note: Neither 'curl' nor 'wget' installed, skipping web test"
fi

echo ""
echo "=========================================="
echo "RpiDNS Container Setup Complete!"
echo "=========================================="
echo ""
echo "Installation Directory: ${INSTALL_DIR}"
echo "Hostname: ${RPIDNS_HOSTNAME}"
echo "DNS Type: ${RPIDNS_DNS_TYPE}"
echo ""
echo "Services:"
echo "  - DNS: Port 53 (TCP/UDP)"
echo "  - Web: Port 80 (HTTP), Port 443 (HTTPS)"

SCRIPT;

    // Add syslog port info if in local mode
    if ($logging !== 'forward') {
        $script .= <<<'SCRIPT'
echo "  - Syslog: Port 10514 (TCP) - for remote RpiDNS log collection"

SCRIPT;
    }

    $script .= <<<'SCRIPT'
echo ""
echo "Management Commands:"
echo "  cd ${INSTALL_DIR}"
echo "  ${DOCKER_COMPOSE} logs -f        # View logs"
echo "  ${DOCKER_COMPOSE} restart        # Restart services"
echo "  ${DOCKER_COMPOSE} down           # Stop services"
echo "  ${DOCKER_COMPOSE} up -d          # Start services"
echo ""
echo "Configure your devices to use this server as their DNS server."
echo ""
echo ""
echo "  Execute the following command to get the admin password"
echo "  cd ${INSTALL_DIR}; sudo ${DOCKER_COMPOSE} logs | grep Password"
echo ""
echo ""

SCRIPT;

    return $script;
}

?>