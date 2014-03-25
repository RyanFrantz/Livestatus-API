<?php

include "livestatus_client.php";

ini_set('memory_limit',-1);

header('Content-Type: application/json');

$path_parts = split('/',$_SERVER['PATH_INFO']);


$client = new LiveStatusClient('/var/nagios/var/rw/live');
$client->pretty_print = true;

$topic = $path_parts[1];
$query = new LiveStatusQuery($topic);

foreach ($_GET  as $key => $val)
{
    if ($key == 'Columns') {
        $columns = split(',',$val);
        $query->set_columns($columns);
    }
    else {
        $query->set_option($key,$val);
    }
}

echo $client->run_query($query);

?>
