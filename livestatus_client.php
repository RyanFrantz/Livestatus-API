<?php

class LiveStatusException extends Exception
{
}

class LiveStatusClient
{
    function __construct($socket_path)
    {
        $this->socket_path = $socket_path;
        $this->socket = null;
        $this->query = null;
        $this->pretty_print = false;
    }

    private function _connect()
    {
        $this->socket = stream_socket_client("unix://{$this->socket_path}");
    }

    private function _jsonOpts()
    {
        $json_opts = null;
        $this->pretty_print && $json_opts = JSON_PRETTY_PRINT;
        return $json_opts;
    }

    private function _parseResponse($response)
    {
        $response = json_decode($response);
        $results = [];

        if ($this->query->stats) { 
            $results = $response;
        } else {
            $cols = $this->query->columns;

            if (! $cols) {
                $cols = array_shift($response);
            }

            foreach ($response as $row) {
                $results[] = array_combine($cols,$row);
            }
        }

        return $results;
    }

    private function _fetchResponse()
    {
        $response = '';
        $status = 500;
        if ($status_line = fgets($this->socket)) {
            list($status,$length) = explode(' ', $status_line);

            while ($line = fgets($this->socket)) {
                $response .= $line;
            }

        }
        if ($status != 200) {
            throw new LiveStatusException($response, $status);
        }

        return $this->_parseResponse($response);
    }

    public function runQuery($query)
    {
        $this->_connect();
        $this->query = $query;

        $query_string = $query->getQueryString();
        fwrite($this->socket, $query_string);
        $response = $this->_fetchResponse();
        fclose($this->socket);
        return $response;
    }

    public function verify_post_request() {
        // Defined in index.php.
        global $request_method;
        if ($request_method != "POST") {
            header('Allow: POST');
            throw new LiveStatusException("Invalid request method: {$request_method}. Use POST instead.", "405");
        }
    }

    public function runCommand($command)
    {
        $this->verify_post_request();
        $command_string = $command->getCommandString();
        $this->_connect();
        fwrite($this->socket, $command_string);
        fclose($this->socket);
    }

    function getQuery($action,$args=[])
    {
        $query = new LiveStatusQuery($action);

        foreach ($args  as $key => $val) {
            switch ($key) {
            case 'Columns':
                $columns = explode(',', $val);
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
                break;
            case 'Stats':
                if (is_array($val)) {
                    foreach ($val as $subvalue) {
                        $query->addStat($subvalue);
                    }
                } else {
                    $query->addStat($val);
                }
                break;
            default:
                $query->setOption($key, $val);
            }
        }
        return $this->runQuery($query);
    }

    public function acknowledgeProblem($args) {
        $cmd = new AcknowledgeCommand($args);
        $this->runCommand($cmd);
    }
    
    public function cancelDowntime($args) {
        $cmd = new CancelDowntimeCommand($args);
        $this->runCommand($cmd);
    }

    public function scheduleDowntime($args) {
        $cmd = new ScheduleDowntimeCommand($args);
        $this->runCommand($cmd);
    }

    public function disableNotifications($args) {
        $cmd = new DisableNotificationsCommand($args);
        $this->runCommand($cmd);
    }

    public function enableNotifications($args) {
        $cmd = new EnableNotificationsCommand($args);
        $this->runCommand($cmd);
    }

}

class LiveStatusQuery
{
    function __construct($topic, $options=[], $columns=[])
    {
        $this->topic = $topic;
        $this->columns = $columns;
        $this->filters = [];
        $this->stats = [];
        $this->options['OutputFormat'] = 'json';
        $this->options['ResponseHeader'] = 'fixed16';
    }

    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
    }

    public function setColumns($column_list)
    {
        $this->columns = $column_list;
    }

    public function addFilter($filter)
    {
        $this->filters[] = $filter;
    }

    public function addStat($stat)
    {
        $this->stats[] = $stat;
    }

    public function getQueryString()
    {
        $query = [];

        $query[] = "GET {$this->topic}";
        $this->columns && $query[] = "Columns: " . join($this->columns, ' ');

        foreach ($this->filters as $filter) {
            $query[] = "Filter: $filter";
        }

        foreach ($this->stats as $stat) {
            $query[] = "Stats: $stat";
        }

        foreach ($this->options as $key => $value) {
            $query[] = "$key: $value";
        }

        $query[] = "\n";

        return join($query, "\n");

    }
}

abstract class LiveStatusCommand
{
    function __construct($args=[])
    {
        $this->action = '';
        $this->args   = $args;
        $this->required = [];
        $this->fields = [];
    }


    private function _validateArgs()
    {
        foreach ($this->required as $field) {
            if (!array_key_exists($field, $this->args)) {
		error_log("missing $field");
                throw new LiveStatusException(
                    "Required field '$field' is missing", 
                    400
                );
            }
        }
    }

    protected function _processArgs()
    {
        foreach ($this->fields as $field => $val) {
            if (array_key_exists($field, $this->args)) {
                $this->fields[$field] = $this->args[$field];
            }
        }
        $this->args = $this->fields;
    }

    function getCommandString()
    {
        $this->_validateArgs();
        $this->_processArgs();
        $command = "COMMAND ";
        $command .= sprintf("[%d] ", time());
        $command .= "{$this->action};";
        $command .= join($this->args, ';');
        $command .= "\n\n";
        return $command;
    }
}

class AcknowledgeCommand extends LiveStatusCommand
{
    function __construct($args=[])
    {
        parent::__construct($args);
        $this->action = 'ACKNOWLEDGE_SVC_PROBLEM';
        $this->required = [
            'host',
            'author',
            'comment',
        ];

        $this->fields = [
            'host' => '',
            'service' => '',
            'sticky'    => 1,
            'notify'    => 1,
            'persistent'=> 1,
            'author'    => '',
            'comment'   => '',
        ];
    }

    function _processArgs()
    {
        parent::_processArgs();

        if (!$this->args['service']) {
            unset($this->args['service']);
            $this->action = 'ACKNOWLEDGE_HOST_PROBLEM';
        }
    }
}

class CancelDowntimeCommand extends LiveStatusCommand
{
    function __construct($args=[])
    {
        parent::__construct($args);
        $this->action = 'DEL_HOST_DOWNTIME';
        $this->required = [
            'downtime_id'
        ];

        $this->fields = [
            'downtime_id'       => '',
            'service'           => null,
        ];
    }

    function _processArgs()
    {
        parent::_processArgs();

        if (isset($this->args['service'])) {
            $this->action = 'DEL_SVC_DOWNTIME';
        }
    }
}

class ScheduleDowntimeCommand extends LiveStatusCommand
{
    function __construct($args=[])
    {
        parent::__construct($args);
        $this->action = 'SCHEDULE_SVC_DOWNTIME';
        $this->required = [
            'host',
            'author',
            'comment',
        ];

        $this->fields = [
            'host'       => '',
            'service'    => '',
            'start_time' => 0,
            'end_time'   => 0,
            'fixed'      => 1,
            'trigger_id' => 0,
            'duration'   => 0,
            'author'     => '',
            'comment'    => '',
        ];
    }

    function _processArgs()
    {
        parent::_processArgs();

        if (!$this->args['service']) {
            unset($this->args['service']);
            $this->action = 'SCHEDULE_HOST_DOWNTIME';
        }

        $this->args['start_time'] = time();
        $this->args['end_time'] = time() + $this->args['duration'];

    }
}

class DisableNotificationsCommand extends LiveStatusCommand
{
    function __construct($args=[])
    {
        parent::__construct($args);
        $this->action = 'DISABLE_SVC_NOTIFICATIONS';
        $this->required = [
            'host',
        ];

        // The 'scope' field helps define if we want to disable notifications
        // for all the host's services. Its only valid value is 'all' and it's
        // not required/used by any external Nagios commands.
        $this->fields = [
            'host'       => '',
            'service'    => '',
            'scope'      => '',
        ];
    }

    function _processArgs()
    {
        parent::_processArgs();

        // Do we want to disable all services under the given host?
        if ($this->args['scope'] && $this->args['scope'] == "all") {
            // Unset the 'service' arg if present; it's redundant in this context.
            unset($this->args['service']);
            $this->action = 'DISABLE_HOST_SVC_NOTIFICATIONS';
        } elseif (!$this->args['service']) {
            unset($this->args['service']);
            $this->action = 'DISABLE_HOST_NOTIFICATIONS';
        }
    }
}

class EnableNotificationsCommand extends LiveStatusCommand
{
    function __construct($args=[])
    {
        parent::__construct($args);
        $this->action = 'ENABLE_SVC_NOTIFICATIONS';
        $this->required = [
            'host',
        ];

        // The 'scope' field helps define if we want to enable notifications
        // for all the host's services. Its only valid value is 'all' and it's
        // not required/used by any external Nagios commands.
        $this->fields = [
            'host'       => '',
            'service'    => '',
            'scope'      => '',
        ];
    }

    function _processArgs()
    {
        parent::_processArgs();

        // Do we want to enable all services under the given host?
        if ($this->args['scope'] && $this->args['scope'] == "all") {
            // Unset the 'service' arg if present; it's redundant in this context.
            unset($this->args['service']);
            $this->action = 'ENABLE_HOST_SVC_NOTIFICATIONS';
        } elseif (!$this->args['service']) {
            unset($this->args['service']);
            $this->action = 'ENABLE_HOST_NOTIFICATIONS';
        }
    }
}
