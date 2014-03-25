<?php

require "livestatus_client.php";

ini_set('memory_limit', -1);

header('Content-Type: application/json');

$path_parts = split('/', $_SERVER['PATH_INFO']);


$client = new LiveStatusClient('/var/nagios/var/rw/live');
$client->pretty_print = true;

$topic = $path_parts[1];
$query = new LiveStatusQuery($topic);

foreach ($_GET  as $key => $val) {
    switch ($key) {
    case 'Columns':
        $columns = split(',', $val);
        $query->setColumns($columns);
        break;
    case 'Filter':
        if (is_array($val)) {
            foreach ($val as $subvalue) {
                $query->addFilter($subvalue);
            }
        } else {
            $query->addFilter($val);
        }
    default:
        $query->setOption($key, $val);
    }
}

echo $client->runQuery($query);

?>
