<?php

require "livestatus_client.php";

// FIXME: Do we really want unlimited memory?
ini_set('memory_limit', -1);

header('Content-Type: application/json');

$path_parts = explode('/', $_SERVER['PATH_INFO']);
$request_method = $_SERVER['REQUEST_METHOD'];

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

$action = $path_parts[1];

$response = [ 'success' => true ];

$args = json_decode(file_get_contents("php://input"),true);

try {
    switch ($action) {

    case 'acknowledge_problem':
        $client->acknowledgeProblem($args);
        break;
       
    case 'schedule_downtime':
        $client->scheduleDowntime($args);
        break;

    case 'enable_notifications':
        $client->enableNotifications($args);
        break;

    case 'disable_notifications':
        $client->disableNotifications($args);
        break;

    default:
        $response['content'] =  $client->getQuery($action, $_GET);

    }

} catch (LiveStatusException $e) {
    $response['success'] = false;
    $response['content'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
    http_response_code($e->getCode());
}
echo json_encode($response);

?>
