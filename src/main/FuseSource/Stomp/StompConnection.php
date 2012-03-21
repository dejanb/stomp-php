<?php

namespace FuseSource\Stomp;

use FuseSource\Stomp\Exception\StompConfigurationException;
use FuseSource\Stomp\Exception\StompException;

/**
 * handles the connection to a broker
 * reconnects on failures with failover
 *
 * @author Soeren Rohweder
 */
class StompConnection implements StompConnectionInterface
{

    /**
     * the passed full Broker Uri
     * 
     * @var string
     */
    protected $brokerUri;
    protected $socket;
    protected $broker = array();

    /**
     * options can contain configuration liek timeouts
     * 
     * @var array
     */
    protected $options;
    protected $connectTimeout = 2;
    protected $readTimeout = 2;
    protected $readTimeoutUsec = 0;
    protected $currentHost = -1;
    protected $attempts = 10;
    protected $defaultPort = 61613;
    

    /**
     * A StompConnection represents a connection to a broker
     * 
     * @param string $brokerUri e.q. tcp://127.0.0.1:61613
     *                          or failover://(tcp://127.0.0.1:61613, tcp://127.0.0.1:61614)?randomize=false
     * @param array $options an array of options possible options are
     *                       - connect_timeout the timout on connecting in seconds
     *                       - read_timeout read timeout in seconds
     *                       - read_timeout_usec read timeout in microseconds 
     *                                           added to read_timeout
     */
    public function __construct($brokerUri, array $options = array())
    {
        $this->brokerUri = $brokerUri;
        $this->options = $options;

        $this->parseBrokerUri();

        $this->parseOptions();

        $this->initConnection();
    }

    /**
     * parse the broker uri and write the broker entries to be used for 
     * connections
     */
    protected function parseBrokerUri()
    {
        $pattern = "|^(([a-zA-Z0-9]+)://)+\(*([a-zA-Z0-9\.:/i,-]+)\)*\??([a-zA-Z0-9=&]*)$|i";
        if (preg_match($pattern, $this->brokerUri, $regs)) {
            $scheme = $regs[2];
            $hosts = $regs[3];
            $params = $regs[4];
            if ($scheme != "failover") {
                $this->processUrl($this->brokerUri);
            } else {
                $urls = explode(",", $hosts);
                foreach ($urls as $url) {
                    $this->processUrl($url);
                }
            }
            if ($params != null) {
                parse_str($params, $this->options);
            }
        } else {
            throw new StompConfigurationException("Bad Broker URL {$this->brokerUri}");
        }
    }

    /**
     * Process broker URL
     *
     * @param string $url Broker URL
     * @throws StompException
     * @return boolean
     */
    protected function processUrl($url)
    {
        $parsed = parse_url($url);
        if ($parsed) {
            array_push($this->broker, array($parsed['host'], $parsed['port'], $parsed['scheme']));
        } else {
            throw new StompConfigurationException("Bad Broker URL $url");
        }
    }

    /**
     * parses the passed options array
     */
    protected function parseOptions()
    {
        foreach ($this->options as $optionKey => $optionValue) {
            switch ($optionKey) {
                case 'read_timeout':
                    $this->readTimeout = $optionValue;
                    break;
                case 'read_timeout_usec':
                    $this->readTimeoutUsec = $optionValue;
                    break;
                case 'connect_timeout':
                    $this->connectTimeout = $optionValue;
                    break;
                case 'connect_attempts':
                    $this->attempts = $optionValue;
                    break;
            }
        }
    }

    protected function initConnection()
    {
        if (count($this->broker) == 0) {
            throw new StompException("No broker defined");
        }

        // force disconnect, if previous established connection exists
        $this->disconnect();

        $i = $this->currentHost;
        $att = 0;
        $connected = false;
        $connect_errno = null;
        $connect_errstr = null;

        while (!$connected && $att++ < $this->attempts) {
            if (isset($this->options['randomize']) && $this->options['randomize'] == 'true') {
                $i = rand(0, count($this->broker) - 1);
            } else {
                $i = ($i + 1) % count($this->broker);
            }
            $broker = $this->broker[$i];
            $host = $broker[0];
            $port = $broker[1];
            $scheme = $broker[2];
            if ($port == null) {
                $port = $this->defaultPort;
            }
            if ($this->socket != null) {
                fclose($this->_socket);
                $this->socket = null;
            }
            $this->socket = @fsockopen($scheme . '://' . $host, $port, $connect_errno, $connect_errstr, $this->connectTimeout);
            if (!is_resource($this->socket) && $att >= $this->attempts && !array_key_exists($i + 1, $this->broker)) {
                throw new StompException("Could not connect to $host:$port ($att/{$this->attempts})");
            } else if (is_resource($this->socket)) {
                $connected = true;
                $this->currentHost = $i;
                break;
            }
        }
        if (!$connected) {
            throw new StompException("Could not connect to a broker");
        }
    }

    public function read()
    {
        $rb = 1024;
        
        $read = fread($this->socket, $rb);
        if ($read === false) {
            $this->_reconnect();
            return $this->read();
        }
        
        return $read;
    }

    public function write($data)
    {
        $r = fwrite($this->socket, $data, strlen($data));
        if ($r === false || $r == 0) {
            $this->_reconnect();
            $this->write($data);
        }        
    }

    /**
     * @throws StompException on read failure
     * @return bool
     */
    public function hasData()
    {
        $read = array($this->socket);
        $write = null;
        $except = null;

        $has_frame_to_read = @stream_select($read, $write, $except, $this->readTimeout, $this->readTimeoutUsec);

        if ($has_frame_to_read !== false)
            $has_frame_to_read = count($read);


        if ($has_frame_to_read === false) {
            throw new StompException('Check failed to determine if the socket is readable');
        } else if ($has_frame_to_read > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function isConnected()
    {
        return is_resource($this->socket);
    }

}