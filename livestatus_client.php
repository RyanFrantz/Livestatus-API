<?php

class LiveStatusClient
{
    function __construct($socket_path)
    {
        $this->socket_path = $socket_path;
        $this->socket = null;
        $this->pretty_print = false;
    }

    private function connect()
    {
        $this->socket = stream_socket_client("unix://{$this->socket_path}");
    }

    private function parse_response($response)
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

    private function fetch_response()
    {
        $response = '';
        while ($line = fgets($this->socket))
        {
            $response .= $line;
        }

        return $this->parse_response($response);
    }

    public function run_query($query)
    {
        $this->connect();
        $this->query = $query;

        $query_string = $query->get_query_string();
        fwrite($this->socket,$query_string);
        return $this->fetch_response();
    }

}

class LiveStatusQuery
{
    function __construct($topic, $options=[], $columns=[])
    {
        $this->topic = $topic;
        $this->columns = $columns;
        $this->options['OutputFormat'] = 'json';
    }

    public function set_option($name, $value)
    {
        $this->options[$name] = $value;
    }

    public function set_columns($column_list)
    {
        $this->columns = $column_list;
    }

    public function get_query_string()
    {
        $query = [];

        $query[] = "GET {$this->topic}";
        $this->columns && $query[] = "Columns: " . join($this->columns," ");
        foreach ($this->options as $key => $value)
        {
            $query[] = "$key: $value";
        }

        $query[] = "\n";

        return join($query,"\n");

    }
}



