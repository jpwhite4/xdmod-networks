# XDMoD Networks Module

The XDMoD Networks Module is an optional module for
tracking usage network data with Open XDMoD.

For more information, including information about additional Open XDMoD
capabilities provided as optional modules, please visit
[the Open XDMoD website](https://open.xdmod.org).

# Install and configuration.

The networks module can use a GeoLite2 database to display location
information based on the IP address from server logs. The IP to
location mapping is performed at data ingest time. The code has been
tested with [MaxMind's GeoLite2 City database](https://dev.maxmind.com/geoip/geoip2/geolite2/).

The database file is not distributed with Open XDMoD and must be
obtained seperately. If no database is present then all location
information will be marked as 'Unknown'. The database is not
required for the Open XDMoD module to display and process 
server log data.

TODO: specify GeoIP data file location
TODO: specify webserver log file location


0) Add the `modw_networks` schema to the database.
1) Add a new resource to XDMoD using the XDMoD setup program. Resource type should be 'Grid'.
2) run `xdmod-ingestor` to load the new resource into the database.

# Usage

Prerequisites:
1) Make sure you have added the Grid resource via `xdmod-setup` and run `xdmod-ingestor` to load the resource
   into XDMoD's datawarehouse.

To ingest the gridftp ogs. You need to specify the XDMoD resource name in the variable `GRIDFTP_RESOURCE_CODE` 
For example:

    /usr/share/xdmod/tools/etl/etl_overseer.php -p networks.log-ingestion -d GRIDFTP_RESOURCE_CODE=torx -v debug
