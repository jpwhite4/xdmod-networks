<?php
/**
 * iGrid FTP Log file endpoint. This endpoint contains the machinery
 * to parse grid ftp server log files.
 */

namespace ETL\DataEndpoint;

use Psr\Log\LoggerInterface;
use ETL\DataEndpoint\DataEndpointOptions;

class GridFtpLogFile extends aStructuredFile implements iStructuredFile
{
    const CACHE_SIZE = 1000;

    private $subpatterns = array(
        'DATE=(?P<end_date>[0-9]{14}\.[0-9]{6})',
        'HOST=(?P<host>[^ ]+)',
        'PROG=(?P<prog>[^ ]+)',
        'NL.EVNT=FTP_INFO',
        'START=(?P<start_date>[0-9]{14}\.[0-9]{6})',
        'USER=(?P<user>[^ ]+)',
        'FILE=(?P<filename>.+)',
        'BUFFER=(?P<buffer_size>[0-9]+)',
        'BLOCK=(?P<block_size>[0-9]+)',
        'NBYTES=(?P<bytes>[0-9]+)',
        'VOLUME=(?P<volume>[^ ]+)',
        'STREAMS=(?P<stream_count>[0-9]+)',
        'STRIPES=(?P<stripe_count>[0-9]+)',
        'DEST=\[(?P<destination>[^ ]+)\]',
        'TYPE=(?P<type>[^ ]+)',
        'CODE=(?P<code>[0-9]+)',
        'TASKID=(?P<taskid>[^ ]+)',
    );

    private $pattern = null;

    private $utcTimeZone = null;

    private $geoip_lookup = null;

    private $geoip_cache = array();

    /**
     * @const string Defines the name for this endpoint that should be used in configuration files.
     * It also allows us to implement auto-discovery.
     */

    const ENDPOINT_NAME = 'gridftplog';

    /**
     * @see iDataEndpoint::__construct()
     */

    public function __construct(DataEndpointOptions $options, LoggerInterface $logger = null)
    {
        parent::__construct($options, $logger);

        $this->pattern = '#^' . implode(' ', $this->subpatterns) . '#';
        $this->utcTimeZone = new \DateTimeZone('UTC');

        if (isset($options->geoip_file)) {
            $this->geoip_lookup = new \GeoIp2\Database\Reader($options->geoip_file);
        }
    }

    private function lookupGeoIp($host) {

        if (array_key_exists($host, $this->geoip_cache)) {
            return $this->geoip_cache[$host];
        }

        $result = new \stdClass();
        $result->{"city"} = 'NA';
        $result->{"subdivision"} = 'NA';
        $result->{"country"} = 'NA';

        if ($this->geoip_lookup !== null) {
            try {
                $geoip = $this->geoip_lookup->city($host);
                $result->{"city"} = $geoip->city->name;
                $result->{"subdivision"} = $geoip->mostSpecificSubdivision->isoCode;
                $result->{"country"} = $geoip->country->isoCode;
            }
            catch (\GeoIp2\Exception\AddressNotFoundException $e) {
                $result->{"city"} = 'unknown';
                $result->{"subdivision"} = 'unknown';
                $result->{"country"} = 'unknown';
            }
            catch (\InvalidArgumentException $e) {
                // leave at the default value of 'N/A'
            }
        }

        if (count($this->geoip_cache) > self::CACHE_SIZE) {
            array_shift($this->geoip_cache);
        }
        $this->geoip_cache[$host] = $result;

        return $result;
    }

    /**
     * @see aStructuredFile::decodeRecord()
     */

    protected function decodeRecord($line)
    {
        $res = preg_match($this->pattern, $line, $matches);
        if ($res === 1) {
            $decoded = new \stdClass();

            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $decoded->{$key} = $value;
                }
            }

            $end_date = date_create_from_format('YmdHis.u', $decoded->end_date, $this->utcTimeZone);
            $decoded->{'end_time_ts'} = $end_date->format('U.u');

            $start_date = date_create_from_format('YmdHis.u', $decoded->start_date, $this->utcTimeZone);
            $decoded->{'start_time_ts'} = $start_date->format('U.u');

            $location = $this->lookupGeoIp($decoded->destination);

            $decoded->{"dest_city"} = $location->city;
            $decoded->{"dest_subdivision"} = $location->subdivision;
            $decoded->{"dest_country"} = $location->country;

            $this->recordList[] = $decoded;

        } else if ($res === 0) {
            $this->logger->warn("Unable to process " . $line);
        } else if ($res === false) {
            throw new Exception("Regex parser error: " . preg_last_error_msg());
        }

        return true;
    }

    /**
     * @see aStructuredFile::verifyData()
     */

    protected function verifyData()
    {
        return true;
    }

    /**
     * @see aStructuredFile::discoverRecordFieldNames()
     */

    protected function discoverRecordFieldNames()
    {
        // If there are no records in the file then we don't need to set the discovered
        // field names.

        if ( 0 == count($this->recordList) ) {
            return;
        }

        // Determine the record names based on the structure of the JSON that we are
        // parsing.

        reset($this->recordList);
        $record = current($this->recordList);

        if ( is_array($record) ) {

            if ( $this->hasHeaderRecord ) {

                // If we have a header record skip the first record and use its values as
                // the field names

                $this->discoveredRecordFieldNames = array_shift($this->recordList);

            } elseif ( 0 !== count($this->requestedRecordFieldNames) ) {

                // If there is no header record and the requested field names have been
                // provided, use them as the discovered field names.  If a subsequent
                // record contains fewer fields return NULL values for those fields, if a
                // subsequent record contains more fields ignore them.

                $this->discoveredRecordFieldNames = $this->requestedRecordFieldNames;

            } else {
                $this->logAndThrowException("Record field names must be specified for JSON array records");
            }

        } elseif ( is_object($record) ) {

            // Pull the record field names from the object keys

            $this->discoveredRecordFieldNames = array_keys(get_object_vars($record));

        } else {
            $this->logAndThrowException(
                sprintf("Unsupported record type in %s. Got %s, expected array or object", $this->path, gettype($record))
            );
        }

        // If no field names were requested, return all discovered fields

        if ( 0 == count($this->requestedRecordFieldNames) ) {
            $this->requestedRecordFieldNames = $this->discoveredRecordFieldNames;
        }
    }
}
