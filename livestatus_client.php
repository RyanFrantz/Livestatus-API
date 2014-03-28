<?php

class LiveStatusException extends Exception
{
    public function toJson() {
        $error = [ "Error" => [ "Code" => $this->code, "Message" => $this->message]];
        return json_encode($error);
    }
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

        return json_encode($results,$this->_jsonOpts());
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

    public function runCommand($command)
    {
        $this->_connect();
        $command_string = $command->getCommandString();
        fwrite($this->socket, $command_string);
        fclose($this->socket);
    }

    function getQuery($method,$args=[]) 
    {
        $query = new LiveStatusQuery($method);

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
        $this->method = '';
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
        $command .= "{$this->method};";
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
        $this->method = 'ACKNOWLEDGE_SVC_PROBLEM';
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
            $this->method = 'ACKNOWLEDGE_HOST_PROBLEM';
        }
    }
}
