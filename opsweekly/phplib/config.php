<?php

include_once('./providers/authentication/github.php');
// Login details for the MySQL database, where all the data is stored.
// The empty database schema is stored in opsweekly.sql
$mysql_host = getenv('DB_HOST', true) ?: getenv('DB_HOST');
$mysql_user = getenv('DB_USER', true) ?: getenv('DB_USER');
$mysql_pass = getenv('DB_PASSWORD', true) ?: getenv('DB_PASSWORD');

// Provider environment variables
$pagerduty_api_key = getenv('PAGERDUTY_API_KEY', true) ?: getenv('PAGERDUTY_API_KEY');
$pagerduty_base_url = getenv('PAGERDUTY_BASE_URL', true) ?: getenv('PAGERDUTY_BASE_URL');
$pagerduty_team_ids = getenv('PAGERDUTY_TEAM_IDS', true) ?: getenv('PAGERDUTY_TEAM_IDS');
$pagerduty_include_options = getenv('PAGERDUTY_INCLUDE_OPTIONS', true) ?: getenv('PAGERDUTY_INCLUDE_OPTIONS');

// The domain name your company uses to send email from, used for a reply-to address
// for weekly reports
$email_from_domain = getenv('EMAIL_DOMAIN_ROOT', true) ?: getenv('EMAIL_DOMAIN_ROOT');
$email_report_to = getenv('REPORT_TO_EMAIL', true) ?: getenv('REPORT_TO_EMAIL');

// General settings
$hostname = getenv('HOSTNAME', true) ?: getenv('HOSTNAME');

//// START LOGIN
$oauth_client_id = getenv('OAUTH2_CLIENT_ID', true) ?: getenv('OAUTH2_CLIENT_ID');
$oauth_client_secret = getenv('OAUTH2_CLIENT_SECRET', true) ?: getenv('OAUTH2_CLIENT_SECRET');

// Github Authentication
$authorizeURL = 'https://github.com/login/oauth/authorize';
$tokenURL = 'https://github.com/login/oauth/access_token';
$apiURLBase = 'https://api.github.com/';

// Determine inbound protocol
$protocol = getenv('PROTOCOL', true) ?: getenv('PROTOCOL');
$protocol = $protocol . '://';

if ($pagerduty_api_key == '' || $pagerduty_base_url == '' ||
    $pagerduty_team_ids == '' || $pagerduty_include_options == '' ||
    $hostname == '' || $oauth_client_id == '' ||
    $oauth_client_secret == '' || $mysql_host == '' || $mysql_user == '' ||
    $mysql_pass == ''){
      print "You are missing an environment variable. <br/>";
      exit;
    }
session_start();

$authenticator->checkAction();
$authenticator->checkLogin();

/**
 * Authentication configuration
 * Nagdash must know who is requesting pages, as every update entry etc is unique
 * to a single person.
 * Therefore, you must define a function somewhere called getUsername()
 * that will return a plain text username string to Nagdash, e.g. "ldenness" or "bsmith"
 *
 **/
 function getUsername() {
   global $authenticator;
   // Use the PHP_AUTH_USER header which contains the username when Basic auth is used.
   if($authenticator->checkLogin()) {
     $user = $authenticator->apiRequest($GLOBALS['apiURLBase'] . 'user');
     return $user->name;
   }
 }

/**
 * Team configuration
 * Arrays of teams, the key being the Virtual Host FQDN, e.g. someteam.somedomain.com
 *
 * Options:
 * display_name: Used for display purposes, your nice team name.
 * email_report_to: The email address the weekly reports users write should be emailed to
 * database: The name of the MySQL database the data for this team is stored in
 * event_versioning: Set to 'on' to store each event with a unique id each time the on-call report is saved.
 *                   Set to 'off' to only update existing events and insert new ones each time the on-call report is saved. Makes for a cleaner database.
 *                   If undefined, defaults to 'on', for backwards compatibility.
 * oncall: false or an array. If false, hides the oncall sections of the interface. If true, please complete the other information.
 *   - provider: The plugin you wish to use to retrieve on call information for the user to complete
 *   - provider_options: An array of options that you wish to pass to the provider for this team's on call searching
 *       - There are variables for the options that are subsituted within the provider. See their docs for more info
 *   - timezone: The PHP timezone string that your on-call rotation starts in
 *   - start: Inputted into strtotime, this is when your oncall rotation starts.
 *            e.g. Match this to Pagerduty if you use that for scheduling.
 *   - end: Inputted into strtotime, this is when your oncall rotation ends.
 *          e.g. Match this to Pagerduty if you use that for scheduling.
 **/
$teams = array(
    $hostname => array(
      "root_url" => ".",
      "display_name" => "Ops",
      "email_report_to" => $email_report_to,
      "database" => "opsweekly",
      "event_versioning" => "off",
      "oncall" => array(
          "provider" => "pagerduty",
          "provider_options" => array(
            "include" => explode(",", $pagerduty_include_options),
            "team_ids" => explode(",", $pagerduty_team_ids),
          ),
          "timezone" => "PST",
          "start" => "friday 18:00",
          "end" => "friday 18:00",
      ),
      "weekly_hints" => array(),
      "irc_channel" => "#ops"
    ),
);

/**
 * Weekly hint providers
 *  A 'weekly' provider, or 'hints' is designed to prompt the
 *  user to remember what they did in the last week, so they can
 *  fill out their weekly report more accurately.
 *
 *  It appears on the right hand side of the "add" screen.
 *  Select which providers you want for your team using the 'weekly_hints'
 *  key in the teams array.
 *
 **/
$weekly_providers = array(
);


/**
 * Oncall providers
 * These are used to retrieve information given a time period about the alerts the requesting
 * user received.
 **/
$oncall_providers = array(
  "pagerduty" => array(
    "display_name" => "Pagerduty",
    "lib" => "providers/oncall/pagerduty2.php",
    "options" => array(
        "base_url" => $pagerduty_base_url,
        "apikey" => $pagerduty_api_key,
    ),
  ),
);

/**
 * Sleep providers
 * These are used to track awake/asleep during alert times, and MTTS (mean time to sleep)
 * If you want to create your own sleep provider, you need to enter it's configuration here.
 *
 * Options:
 * - display_name: The name displayed in the UI for the sleep provider
 * - description: A description so the user knows to pick your provider
 * - logo: The URL path to a small (30x30px) logo for your sleep provider.
 * - options: An array of all the options you wish to present to the user.
 *            this data is passed to your provider. The key name is the option name.
 *   For each option you required, the following keys are available:
 *     - type: An HTML form field type. E.g. 'text'
 *     - name: A friendly name displayed to the user
 *     - description: The description of your option
 *     - placeholder: Text displayed inside the field as a placeholder text for the user
 * - lib: The path on disk to your provider. The provider should provide a few set functions;
 *        for more information on those, see the example plugin provided.
 * - Any other key: Some other option you want to pass to your provider that is NOT unique to
 *                  one user, but to the whole of opsweekly E.g. A web URL or other data.
 **/
$sleep_providers = array(

);

// The number of search results per page
$search_results_per_page = 25;

// Path to disk where a debug error log file can be written
$error_log_file = "/var/log/apache2/opsweekly.debug.log";

// Dev FQDN
// An alternative FQDN that will be accepted by Opsweekly for running a development copy elsewhere
// Fed into preg_replace so regexes are allowed
$dev_fqdn = "/$hostname/";
// The prod FQDN is then subsituted in place of the above string.
$prod_fqdn = $hostname;

// Global configuration for irccat, used to send messages to IRC about weekly meetings.
$irccat_hostname = '';
$irccat_port = 12345;
