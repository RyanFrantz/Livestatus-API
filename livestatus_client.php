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
        return $client->runQuery($query);
    }

    private function _validateArgs($required, $args) {
        foreach ($required as $field) {
            if (!array_key_exists($field, $args)) {
                throw new LiveStatusException(
                    "Required field '$field' is missing", 
                    400
                );
            }
        }
    }

    public function acknowledgeProblem($args) {

        $method = 'ACKNOWLEDGE_SVC_PROBLEM';
        $required = [
            'host_name',
            'author',
            'comment',
        ];

        $fields = [
            'host_name' => '',
            'service_description' => '',
            'sticky'    => 1,
            'notify'    => 1,
            'author'    => '',
            'comment'   => '',
        ];


        $this->_validateArgs($required, $args);

        foreach ($fields as $field => $val) {
            if (array_key_exists($field)) {
                $fields[$field] = $args[$field];
            }
        }

        if (!$fields['service_description']) {
            unset($fields['service_description']);
            $method = 'ACKNOWLEDGE_HOST_PROBLEM';
        }

        $cmd = new LiveStatusCommand( array_merge([$method], array_values($fields)) );
        $this-runCommand($cmd);

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

class LiveStatusCommand
{
    function __construct($args=[])
    {
        $this->method = $args[0];
        $this->args   = $args;
    }

    function getCommandString()
    {
        $command = "COMMAND ";
        $command .= sprintf("[%d] ", time());
        $command .= join($args, ';');
    }
}

