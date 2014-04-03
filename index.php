<?php

require "livestatus_client.php";

ini_set('memory_limit', -1);
#ini_set('always_populate_raw_post_data', 1);

header('Content-Type: application/json');

$path_parts = explode('/', $_SERVER['PATH_INFO']);


$client = new LiveStatusClient('/var/nagios/var/rw/live');
$client->pretty_print = true;

/*
$commands = [
    'acknowledege_problem'
    'schedule_downtime',
    'enable_notifications',
    'disable_notifications'
];
*/

$method = $path_parts[1];

$response = [ 'success' => true ];

if (isset($HTTP_RAW_POST_DATA)) {
    $args = json_decode($HTTP_RAW_POST_DATA,true);
}

try {
    switch ($method) {

    case 'acknowledge_problem':
        $client->acknowledgeProblem($args);
        break;
       
    case 'schedule_downtime':
        $client->scheduleDowntime($args);
        break;

    case 'enable_notifications':
        break;

    case 'disable_notifications':
        $client->disableNotifications($args);
        break;

    default:
        $response['content'] =  $client->getQuery($method, $_GET);

    }

} catch (LiveStatusException $e) {
    $response['success'] = false;
    $response['content'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
    http_response_code($e->getCode());
}
echo json_encode($response);

?>
