# Livestatus-API

A PHP library and REST API endpoint for interacting with nagios via the socket
provided by [mk-livestatus](http://mathias-kettner.com/checkmk_livestatus.html). It can both query data about object states in the
Nagios server and issue Nagios commands.

All examples in this document assume that the API is available at

    http://nagios.example.com/livestatus-api/

## Response Format

All responses are in JSON and have the following format:

    {"success": <bool>, "content": <object>}

If "success" is true, "content" will contain the requested data. If false, it
will contain

    {"code": <int>, "message": <string>}

where "code" is the mk-livestatus error code and "message" is a human-readable
explanation of the error.

## Query interface

The query interface returns a list of objects in JSON. The available endpoints are
the same as the tables available from mk-livestatus itself:

* hosts
* services - Nagios services, joined with all data from hosts
* hostgroups
* servicegroups
* contactgroups
* servicesbygroup - all services grouped by service groups
* servicesbyhostgroup - all services grouped by host groups
* hostsbygroup - all hosts grouped by host groups
* contacts
* commands - your defined Nagios commands
* timeperiods - time period definitions (currently only name and alias)
* downtimes - all scheduled host and service downtimes, joined with data from hosts and services.
* comments - all host and service comments
* log - a transparent access to the nagios logfiles
* status - general performance and status information. This table contains exactly one dataset.
* columns - a complete list of all tables and columns available via Livestatus, including descriptions!
* statehist -  sla statistics for hosts and services, joined with data from hosts, services and log.


To retrieve all records from a table, send a GET request to

    http://nagios.example.com/livestatus-api/{tablename}

For example, to get all host records from the server, GET

    http://nagios.example.com/livestatus-api/hosts

### Columns

To limit the returned data to a subset of the available fields, pass a Columns
query parameter containing a comma-separated list of column names. To fetch the
name and services list for all hosts:

    http://nagios.example.com/livestatus-api/hosts?columns=name,services

### Filters

To filter the result set to records meeting a critiera, pass one or more
Filter[] params. Each Filter is a urlencoded LQL filter (see the [mk-livestatus
documentation](http://mathias-kettner.com/checkmk_livestatus.html#H1:LQL - The Livestatus Query Language) 
for detailed LQL filter syntax). If more than one filter is specified, they are 
ANDed together. To get all hosts starting with "api" in state OK (0):

    http://nagios.example.com/livestatus-api/hosts?Filter[]=name%20~%20%5Eapi&Filter[]=state%20%3D%200

### Stats

Stats queries allow you to get a count of objects matching a criteria. Stats
queries return a list of counts and never take a Columns parameter. You can
request several Stats with a single API call. You can also restrict the objects
counted by adding Filters to your query. To count the number of hosts starting
with "api" in state OK:

    http://nagios.example.com/livestatus-api/hosts?&Stats[]=name%20~%20%5Eapi&Filter[]=state%20%3D%200
    
## Command Interface

All calls to ``livestatus-api`` to execute Nagios command **must be HTTP POST requests**.

### ``disable_notifications``

Notifications for a host, a host's service, or all of the host's services can be disabled via the ``disable_notifications`` endpoint.

#### Disable Host Notifications

Send a request that includes a valid 'host' value:

    curl -s -XPOST 'https://nagios.example.com/livestatus-api/disable_notifications' -d '{"host": "host.example.com"}'

#### Disable Notifications for a Host's Service

Send a request that includes valid 'host' and 'service' values:

    curl -s -XPOST 'https://nagios.example.com/livestatus-api/disable_notifications' -d '{"host": "host.example.com", "service": "httpd"}'

#### Disable Notifications for All of a Host's Services

Send a request that includes a valid 'host' value and set 'scope' to 'all':

    curl -s -XPOST 'https://nagios.example.com/livestatus-api/disable_notifications' -d '{"host": "host.example.com", "scope": "all"}'

### ``enable_notifications``

Notifications for a host, a host's service, or all of the host's services can be enabled via the ``enable_notifications`` endpoint.

#### Enable Host Notifications

Send a request that includes a valid 'host' value:

    curl -s -XPOST 'https://nagios.example.com/livestatus-api/enable_notifications' -d '{"host": "host.example.com"}'

#### Enable Notifications for a Host's Service

Send a request that includes valid 'host' and 'service' values:

    curl -s -XPOST 'https://nagios.example.com/livestatus-api/enable_notifications' -d '{"host": "host.example.com", "service": "httpd"}'

#### Enable Notifications for All of a Host's Services

Send a request that includes a valid 'host' value and set 'scope' to 'all':

    curl -s -XPOST 'https://nagios.example.com/livestatus-api/enable_notifications' -d '{"host": "host.example.com", "scope": "all"}'

### COMING SOON

Keep yer eyes peeled for the 'acknowledge_problem' and 'schedule_downtime' endpoints!
