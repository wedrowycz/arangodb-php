<?php

/**
 * ArangoDB PHP client: connection options
 *
 * @package   ArangoDBClient
 * @author    Jan Steemann
 * @copyright Copyright 2012, triagens GmbH, Cologne, Germany
 */

namespace ArangoDBClient;

/**
 * Simple container class for connection options.
 *
 * This class also provides the default values for the connection
 * options and will perform a simple validation of them.<br>
 * It provides array access to its members.<br>
 * <br>
 *
 * @package   ArangoDBClient
 * @since     0.2
 */
class ConnectionOptions implements \ArrayAccess
{
    /**
     * The current options
     *
     * @var array
     */
    private $_values = [];

    /**
     * The connection endpoint object
     *
     * @var Endpoint
     */
    private $_endpoint;
    
    /**
     * The index into the endpoints array that we will connect to (or are currently
     * connected to). This index will be increased in case the currently connected
     * server tells us there is a different leader. We will then simply connect
     * to the new leader, adjusting this index. If we don't know the new leader
     * we will try the next server from the list of endpoints until we find the leader
     * or have tried to often
     *
     * @var int
     */
    private $_currentEndpointIndex = 0;

    /**
     * Endpoint string index constant
     */
    const OPTION_ENDPOINT = 'endpoint';

    /**
     * Host name string index constant (deprecated, use endpoint instead)
     */
    const OPTION_HOST = 'host';

    /**
     * Port number index constant (deprecated, use endpoint instead)
     */
    const OPTION_PORT = 'port';

    /**
     * Timeout value index constant
     */
    const OPTION_TIMEOUT = 'timeout';
    
    /**
     * Number of tries for failover constant
     */
    const OPTION_FAILOVER_TRIES = 'failoverTries';

    /**
     * Trace function index constant
     */
    const OPTION_TRACE = 'trace';

    /**
     * "verify certificates" index constant
     */
    const OPTION_VERIFY_CERT = 'verifyCert';

    /**
     * "allow self-signed" index constant
     */
    const OPTION_ALLOW_SELF_SIGNED = 'allowSelfSigned';

    /**
     * ciphers allowed to be used in SSL
     */
    const OPTION_CIPHERS = 'ciphers';

    /**
     * Enhanced trace
     */
    const OPTION_ENHANCED_TRACE = 'enhancedTrace';

    /**
     * "Create collections if they don't exist" index constant
     */
    const OPTION_CREATE = 'createCollection';

    /**
     * Update revision constant
     */
    const OPTION_REVISION = 'rev';

    /**
     * Update policy index constant
     */
    const OPTION_UPDATE_POLICY = 'policy';

    /**
     * Update keepNull constant
     */
    const OPTION_UPDATE_KEEPNULL = 'keepNull';

    /**
     * Replace policy index constant
     */
    const OPTION_REPLACE_POLICY = 'policy';

    /**
     * Delete policy index constant
     */
    const OPTION_DELETE_POLICY = 'policy';

    /**
     * Wait for sync index constant
     */
    const OPTION_WAIT_SYNC = 'waitForSync';

    /**
     * Limit index constant
     */
    const OPTION_LIMIT = 'limit';

    /**
     * Skip index constant
     */
    const OPTION_SKIP = 'skip';

    /**
     * Batch size index constant
     */
    const OPTION_BATCHSIZE = 'batchSize';

    /**
     * Wait for sync index constant
     */
    const OPTION_JOURNAL_SIZE = 'journalSize';

    /**
     * Wait for sync index constant
     */
    const OPTION_IS_SYSTEM = 'isSystem';

    /**
     * Wait for sync index constant
     */
    const OPTION_IS_VOLATILE = 'isVolatile';

    /**
     * Authentication user name
     */
    const OPTION_AUTH_USER = 'AuthUser';

    /**
     * Authentication password
     */
    const OPTION_AUTH_PASSWD = 'AuthPasswd';

    /**
     * Authentication type
     */
    const OPTION_AUTH_TYPE = 'AuthType';

    /**
     * Connection
     */
    const OPTION_CONNECTION = 'Connection';

    /**
     * Reconnect flag
     */
    const OPTION_RECONNECT = 'Reconnect';

    /**
     * Batch flag
     */
    const OPTION_BATCH = 'Batch';

    /**
     * Batchpart flag
     */
    const OPTION_BATCHPART = 'BatchPart';

    /**
     * Database flag
     */
    const OPTION_DATABASE = 'database';

    /**
     * UTF-8 CHeck Flag
     */
    const OPTION_CHECK_UTF8_CONFORM = 'CheckUtf8Conform';

    /**
     * Set defaults, use options provided by client and validate them
     *
     *
     * @param array $options - initial options
     *
     * @throws \ArangoDBClient\ClientException
     */
    public function __construct(array $options)
    {
        $this->_values = array_merge(self::getDefaults(), $options);
        $this->validate();
    }

    /**
     * Get all options
     *
     * @return array - all options as an array
     */
    public function getAll()
    {
        return $this->_values;
    }

    /**
     * Set and validate a specific option, necessary for ArrayAccess
     *
     * @throws Exception
     *
     * @param string $offset - name of option
     * @param mixed  $value  - value for option
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->_values[$offset] = $value;
        $this->validate();
    }

    /**
     * Check whether an option exists, necessary for ArrayAccess
     *
     * @param string $offset -name of option
     *
     * @return bool - true if option exists, false otherwise
     */
    public function offsetExists($offset)
    {
        return isset($this->_values[$offset]);
    }

    /**
     * Remove an option and validate, necessary for ArrayAccess
     *
     * @throws Exception
     *
     * @param string $offset - name of option
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->_values[$offset]);
        $this->validate();
    }

    /**
     * Get a specific option, necessary for ArrayAccess
     *
     * @throws ClientException
     *
     * @param string $offset - name of option
     *
     * @return mixed - value of option, will throw if option is not set
     */
    public function offsetGet($offset)
    {
        if (!array_key_exists($offset, $this->_values)) {
            throw new ClientException('Invalid option ' . $offset);
        }

        return $this->_values[$offset];
    }

    /**
     * Get the endpoint object for the connection
     *
     * @throws ClientException
     * @return Endpoint - endpoint object
     */
    public function getEndpoint()
    {
        if ($this->_endpoint === null) {
            // will also validate the endpoint
            $this->_endpoint = new Endpoint($this->getCurrentEndpoint());
        }

        return $this->_endpoint;
    }
    
    /**
     * Get the current endpoint to use
     *
     * @return string - Endpoint string to connect to
     */
    public function getCurrentEndpoint() 
    {
        if (!is_array($this->_values[self::OPTION_ENDPOINT])) {
            return $this->_values[self::OPTION_ENDPOINT];
        }
        return $this->_values[self::OPTION_ENDPOINT][$this->_currentEndpointIndex];
    }
    
    /**
     * Whether or not we have multiple endpoints to connect to
     *
     * @return bool - true if we have more than one endpoint to connect to
     */
    public function haveMultipleEndpoints() 
    {
        return is_array($this->_values[self::OPTION_ENDPOINT]) && (count($this->_values[self::OPTION_ENDPOINT]) > 1);
    }

    /**
     * Add a new endpoint to the list of endpoints
     * if the endpoint is already in the list, it will not be added again
     * as a side-effect, this method will modify _currentEndpointIndex
     *
     * @param string $endpoint - the endpoint to add
     *
     * @return void
     */
    public function addEndpoint($endpoint) 
    {
        // can only add an endpoint here if the list of endpoints is already an array
        if (!is_array($this->_values[self::OPTION_ENDPOINT])) {
            // make it an array now
            $this->_values[self::OPTION_ENDPOINT] = [ $this->_values[self::OPTION_ENDPOINT] ];
        }
        $found = array_search($endpoint, $this->_values[self::OPTION_ENDPOINT]);
        if ($found === false) {
            // a new endpoint we have not seen before
            $this->_values[self::OPTION_ENDPOINT][] = $endpoint;
            $this->_currentEndpointIndex = count($this->_values[self::OPTION_ENDPOINT]) - 1;
        } else {
            // we have already got this endpoint
            $this->_currentEndpointIndex = $found;
        }
    }
                            
    /**
     * Return the next endpoint from the list of endpoints
     *
     * @return string - the next endpoint
     */
    public function nextEndpoint() 
    {
        if (!is_array($this->_values[self::OPTION_ENDPOINT])) {
            return $this->_values[self::OPTION_ENDPOINT];
        }

        $numberOfEndpoints = count($this->_values[self::OPTION_ENDPOINT]);
        $this->_currentEndpointIndex++;
        if ($this->_currentEndpointIndex >= $numberOfEndpoints) {
            $this->_currentEndpointIndex = 0;
        }

        return $this->_values[self::OPTION_ENDPOINT][$this->_currentEndpointIndex];
    }

    /**
     * Get the default values for the options
     *
     * @return array - array of default connection options
     */
    private static function getDefaults()
    {
        return [
            self::OPTION_ENDPOINT           => null,
            self::OPTION_HOST               => null,
            self::OPTION_PORT               => DefaultValues::DEFAULT_PORT,
            self::OPTION_FAILOVER_TRIES     => DefaultValues::DEFAULT_FAILOVER_TRIES,
            self::OPTION_TIMEOUT            => DefaultValues::DEFAULT_TIMEOUT,
            self::OPTION_CREATE             => DefaultValues::DEFAULT_CREATE,
            self::OPTION_UPDATE_POLICY      => DefaultValues::DEFAULT_UPDATE_POLICY,
            self::OPTION_REPLACE_POLICY     => DefaultValues::DEFAULT_REPLACE_POLICY,
            self::OPTION_DELETE_POLICY      => DefaultValues::DEFAULT_DELETE_POLICY,
            self::OPTION_REVISION           => null,
            self::OPTION_WAIT_SYNC          => DefaultValues::DEFAULT_WAIT_SYNC,
            self::OPTION_BATCHSIZE          => null,
            self::OPTION_JOURNAL_SIZE       => DefaultValues::DEFAULT_JOURNAL_SIZE,
            self::OPTION_IS_SYSTEM          => false,
            self::OPTION_IS_VOLATILE        => DefaultValues::DEFAULT_IS_VOLATILE,
            self::OPTION_CONNECTION         => DefaultValues::DEFAULT_CONNECTION,
            self::OPTION_TRACE              => null,
            self::OPTION_ENHANCED_TRACE     => false,
            self::OPTION_VERIFY_CERT        => DefaultValues::DEFAULT_VERIFY_CERT,
            self::OPTION_ALLOW_SELF_SIGNED  => DefaultValues::DEFAULT_ALLOW_SELF_SIGNED,
            self::OPTION_CIPHERS            => DefaultValues::DEFAULT_CIPHERS,
            self::OPTION_AUTH_USER          => null,
            self::OPTION_AUTH_PASSWD        => null,
            self::OPTION_AUTH_TYPE          => DefaultValues::DEFAULT_AUTH_TYPE,
            self::OPTION_RECONNECT          => false,
            self::OPTION_BATCH              => false,
            self::OPTION_BATCHPART          => false,
            self::OPTION_DATABASE           => '_system',
            self::OPTION_CHECK_UTF8_CONFORM => DefaultValues::DEFAULT_CHECK_UTF8_CONFORM
        ];
    }

    /**
     * Return the supported authorization types
     *
     * @return array - array with supported authorization types
     */
    private static function getSupportedAuthTypes()
    {
        return ['Basic'];
    }

    /**
     * Return the supported connection types
     *
     * @return array - array with supported connection types
     */
    private static function getSupportedConnectionTypes()
    {
        return ['Close', 'Keep-Alive'];
    }

    /**
     * Validate the options
     *
     * @throws ClientException
     * @return void - will throw if an invalid option value is found
     */
    private function validate()
    {
        if (isset($this->_values[self::OPTION_HOST]) && !is_string($this->_values[self::OPTION_HOST])) {
            throw new ClientException('host should be a string');
        }

        if (isset($this->_values[self::OPTION_PORT]) && !is_int($this->_values[self::OPTION_PORT])) {
            throw new ClientException('port should be an integer');
        }

        // can use either endpoint or host/port
        if (isset($this->_values[self::OPTION_HOST], $this->_values[self::OPTION_ENDPOINT])) {
            throw new ClientException('must not specify both host and endpoint');
        }

        if (isset($this->_values[self::OPTION_HOST]) && !isset($this->_values[self::OPTION_ENDPOINT])) {
            // upgrade host/port to an endpoint
            $this->_values[self::OPTION_ENDPOINT] = [ 'tcp://' . $this->_values[self::OPTION_HOST] . ':' . $this->_values[self::OPTION_PORT] ];
            unset($this->_values[self::OPTION_HOST]);
        }

        // set up a new endpoint, this will also validate it
        $this->getEndpoint();

        $ep = $this->getCurrentEndpoint();
        $type = Endpoint::getType($ep);
        if ($type === Endpoint::TYPE_UNIX) {
            // must set port to 0 for UNIX domain sockets
            $this->_values[self::OPTION_PORT] = 0;
        } elseif ($type === Endpoint::TYPE_SSL) {
            // must set port to 0 for SSL connections
            $this->_values[self::OPTION_PORT] = 0;
        } else {
          if (preg_match("/:(\d+)$/", $ep, $match)) {
            // get port number from endpoint, to not confuse developers when dumping
            // connection details
            $this->_values[self::OPTION_PORT] = (int) $match[1];
          }
        }
        
        if (is_array($this->_values[self::OPTION_ENDPOINT])) {
            // the number of endpoints we have determines the number of failover attempts we'll try
            $this->_values[self::OPTION_FAILOVER_TRIES] = count($this->_values[self::OPTION_ENDPOINT]);
        }

        if (isset($this->_values[self::OPTION_AUTH_TYPE]) && !in_array(
                $this->_values[self::OPTION_AUTH_TYPE],
                self::getSupportedAuthTypes(), true
            )
        ) {
            throw new ClientException('unsupported authorization method');
        }

        if (isset($this->_values[self::OPTION_CONNECTION]) && !in_array(
                $this->_values[self::OPTION_CONNECTION],
                self::getSupportedConnectionTypes(), true
            )
        ) {
            throw new ClientException(
                sprintf(
                    "unsupported connection value '%s'",
                    $this->_values[self::OPTION_CONNECTION]
                )
            );
        }

        UpdatePolicy::validate($this->_values[self::OPTION_UPDATE_POLICY]);
        UpdatePolicy::validate($this->_values[self::OPTION_REPLACE_POLICY]);
        UpdatePolicy::validate($this->_values[self::OPTION_DELETE_POLICY]);
    }
}

class_alias(ConnectionOptions::class, '\triagens\ArangoDb\ConnectionOptions');
