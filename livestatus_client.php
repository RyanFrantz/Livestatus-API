<?php

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

    private function _parseResponse($response)
    {
        $response = json_decode($response);
        $results = [];
        $cols = $this->query->columns;

        if (! $cols) {
            $cols = array_shift($response);
        }

        foreach ($response as $row) {
            $results[] = array_combine($cols,$row);
        }

        $json_opts = null;

        $this->pretty_print && $json_opts = JSON_PRETTY_PRINT;

        return json_encode($results,$json_opts);
    }

    private function _fetchResponse()
    {
        $response = '';
        while ($line = fgets($this->socket))
        {
            $response .= $line;
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
        $this->options['OutputFormat'] = 'json';
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

    public function getQueryString()
    {
        $query = [];

        $query[] = "GET {$this->topic}";
        $this->columns && $query[] = "Columns: " . join($this->columns, ' ');

        foreach ($this->filters as $filter) {
            $query[] = "Filter: $filter";
        }

        foreach ($this->options as $key => $value) {
            $query[] = "$key: $value";
        }

        $query[] = "\n";

        return join($query, "\n");

    }
}



