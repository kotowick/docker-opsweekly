<?php
/**
 * Pagerduty on call provider
 */
/** Plugin specific variables required
 * Global Config:
 *  - base_url: The path to your Pagerduty API, e.g. https://company.pagerduty.com/api/v1
 *  - username: A user that can access your Pagerduty account using the API
 *  - password: The password for the user above
 *
 * Team Config:
 *  - pagerduty_service_id: The service ID that this team uses for alerts to be collected
 *
 */
/**
 * getOnCallNotifications - Returns the notifications for a given time period and parameters
 *
 * Parameters:
 *   $on_call_name - The username of the user compiling this report
 *   $provider_global_config - All options from config.php in $oncall_providers - That is, global options.
 *   $provider_team_config - All options from config.php in $teams - That is, specific team configuration options
 *   $start - The unix timestamp of when to start looking for notifications
 *   $end - The unix timestamp of when to stop looking for notifications
 *
 * Returns 0 or more notifications as array()
 * - Each notification should have the following keys:
 *    - time: Unix timestamp of when the alert was sent to the user
 *    - hostname: Ideally contains the hostname of the problem. Must be populated but feel free to make bogus if not applicable.
 *    - service: Contains the service name or a description of the problem. Must be populated. Perhaps use "Host Check" for host alerts.
 *    - output: The plugin output, e.g. from Nagios, describing the issue so the user can reference easily/remember issue
 *    - state: The level of the problem. One of: CRITICAL, WARNING, UNKNOWN, DOWN
 */
function getOnCallNotifications($name, $global_config, $team_config, $start, $end) {
    $base_url = $global_config['base_url'];
    if(isset($global_config['username'])) {
        $username = $global_config['username'];
    } else {
        $username = NULL;
    }
    if(isset($global_config['password'])) {
        $password = $global_config['password'];
    } else {
        $password = NULL;
    }
    $apikey = $global_config['apikey'];
    $service_id = $team_config['pagerduty_service_id'];
    $team_ids = $team_config['team_ids'];
    $include = $team_config['include'];

    if ($base_url !== '' && $username !== '' && $password !== '') {

        // loop through PagerDuty's maximum incidents count per API request.
        $running_total = 0;
        do {
        // Connect to the Pagerduty API and collect all incidents in the time period.
            $parameters = array(
                'since' => date('c', $start),
                'include' => $include,
                'team_ids' => $team_ids,
                'until' => date('c', $end),
                'offset' => $running_total,
            );
            $incident_json = doPagerdutyAPICall('/incidents', $parameters, $base_url, $username, $password, $apikey);
            if (!$incidents = json_decode($incident_json)) {
                return 'Could not retrieve incidents from Pagerduty! Please check your login details';
            }
            // skip if no incidents are recorded
            if (count($incidents->incidents) == 0) {
                continue;
            }

            logline("Total incidents: " . $incidents->total);
            logline("Limit in this request: " . $incidents->limit);
            logline("Offset: " . $incidents->offset);
            $running_total += count($incidents->incidents);
            logline("Running total: " . $running_total);
            foreach ($incidents->incidents as $incident) {
                $time = strtotime($incident->created_at);
                $state = $incident->urgency;
                // try to determine and set the service
                if (isset($incident->service->summary)) {
                    $service = $incident->service->summary;
                } elseif (isset($incident->trigger_summary_data->SERVICEDESC)) {
                    $service = $incident->trigger_summary_data->SERVICEDESC;
                } elseif (isset($incident->trigger_summary_data->extracted_fields->SERVICEDESC)) {
                    $service = $incident->trigger_summary_data->extracted_fields->SERVICEDESC;
                } elseif (isset($incident->trigger_summary_data->details->Trigger->Namespace)) {
                    $service = $incident->trigger_summary_data->details->Trigger->Namespace;
                } elseif (isset($incident->trigger_summary_data->contexts)) {
                    $service = "Pingdom";
                } else {
                    $service = "unknown";
                }
                $output = '<a href="' . $incident->html_url . '" target="_blank">Incident Url</a>';
                $output .= "\n";

                // Add to the output all the trigger_summary_data info

                if (isset($incident->description)){
                  $output .= "<strong>Summary</strong>: {$incident->description}\n";
                } else {
                  $output .= "<strong>Summary</strong>: unknown\n";
                }
                if (isset($incident->status)){
                  $output .= "<strong>Status</strong>: {$incident->status}\n";
                } else {
                  $output .= "<strong>Status</strong>: unknown\n";
                }
                if (isset($incident->first_trigger_log_entry->summary)){
                  $output .= "<strong>Trigger</strong>: {$incident->first_trigger_log_entry->summary}\n";
                } else {
                  $output .= "<strong>Trigger</strong>: unknown\n";
                }
                if (isset($incident->last_status_change_by->summary)){
                  $output .= "<strong>Last Changed By</strong>: {$incident->last_status_change_by->summary}\n";
                } else {
                  $output .= "<strong>Last Changed By</strong>: unknown\n";
                }

                if (isset($incident->url)) {
                    $output .= $incident->url;
                } else {
                    $output .= NULL;
                }
                // try to determine the hostname
                if (isset($incident->first_trigger_log_entry->channel->details->Hostname)){
                  $hostname = $incident->first_trigger_log_entry->channel->details->Hostname;
                } elseif (isset($incident->trigger_summary_data->HOSTNAME)) {
                    $hostname = $incident->trigger_summary_data->HOSTNAME;
                } elseif (isset($incident->trigger_summary_data->details->Trigger->Dimensions[0]->value)) {
                    $hostname = $incident->trigger_summary_data->details->Trigger->Dimensions[0]->value;
                } elseif (isset($incident->trigger_summary_data->details->host)) {
                    $hostname = $incident->trigger_summary_data->details->host;
                } else {
                    // fallback is to just say it was pagerduty that sent it in
                    $hostname = "Pagerduty";
                }
                $notifications[] = array("time" => $time, "hostname" => $hostname, "service" => $service, "output" => $output, "state" => $state);
            }
        } while ($running_total < $incidents->total);
        // if no incidents are reported, don't generate the table
        if (count($notifications) == 0 ) {
            return array();
        } else {
            return $notifications;
        }
    } else {
        return false;
    }
}
function doPagerdutyAPICall($path, $parameters, $pagerduty_baseurl, $pagerduty_username, $pagerduty_password, $pagerduty_apikey) {
    if (isset($pagerduty_apikey)) {
        $context = stream_context_create(array(
            'http' => array(
                'header'  => "Authorization: Token token=$pagerduty_apikey"
            )
        ));
    } else {
        $context = stream_context_create(array(
            'http' => array(
                'header'  => "Authorization: Basic " . base64_encode("$pagerduty_username:$pagerduty_password")
            )
        ));
    }
    $params = null;
    foreach ($parameters as $key => $value) {
        if (isset($params)) {
            $params .= '&';
        } else {
            $params = '?';
        }
        if (is_array($value)){
          $value_size = count($value);
          foreach($value as $tmp){
            $value_size--;
            $params .= sprintf('%s[]=%s', $key, $tmp);
            if ($value_size > 0){
              $params .= '&';
            }
          }
        } else {
          $params .= sprintf('%s=%s', $key, $value);
        }
    }
    logline($params);
    return file_get_contents($pagerduty_baseurl . $path . $params, false, $context);
}
function whoIsOnCall($schedule_id, $time = null) {
    $until = $since = date('c', isset($time) ? $time : time());
    $parameters = array(
        'since' => $since,
        'until' => $until,
        'overflow' => 'true',
    );
    $json = doPagerdutyAPICall("/schedules/{$schedule_id}/entries", $parameters);
    if (false === ($scheddata = json_decode($json))) {
        return false;
    }
    if ($scheddata->total == 0) {
        return false;
    }
    if ($scheddata->entries['0']->user->name == "") {
        return false;
    }
    $oncalldetails = array();
    $oncalldetails['person'] = $scheddata->entries['0']->user->name;
    $oncalldetails['email'] = $scheddata->entries['0']->user->email;
    $oncalldetails['start'] = strtotime($scheddata->entries['0']->start);
    $oncalldetails['end'] = strtotime($scheddata->entries['0']->end);
    return $oncalldetails;
}
function sanitizePagerDutyServiceId($service_id) {
    $pattern = '/^[A-Z0-9]{7}$/';
    if (preg_match($pattern, $service_id)) {
        return true;
    } else {
        return false;
    }
}
?>
