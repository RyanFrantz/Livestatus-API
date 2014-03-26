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
        return $this->_fetchResponse();
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
