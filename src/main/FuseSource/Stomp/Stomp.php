<?php

namespace FuseSource\Stomp;

use FuseSource\Stomp\Exception\StompException;
use FuseSource\Stomp\Message\Map;

/**
 *
 * Copyright 2005-2006 The Apache Software Foundation
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/* vim: set expandtab tabstop=3 shiftwidth=3: */

/**
 * A Stomp Connection
 *
 *
 * @package Stomp
 * @author Hiram Chirino <hiram@hiramchirino.com>
 * @author Dejan Bosanac <dejan@nighttale.net> 
 * @author Michael Caplan <mcaplan@labnet.net>
 * @version $Revision: 43 $
 */
class Stomp
{
    /**
     * Perform request synchronously
     *
     * @var boolean
     */
    public $sync = false;

    /**
     * Default prefetch size
     *
     * @var int
     */
	public $prefetchSize = 1;
    
	/**
     * Client id used for durable subscriptions
     *
     * @var string
     */
	public $clientId = null;
    
    /**
     * An instance of StompConnectionInterface 
     * 
     * @var StompConnectionInterface
     */
    protected $connection;
    protected $_brokerUri = null;
    protected $_socket = null;
    protected $_hosts = array();
    protected $_params = array();
    protected $_subscriptions = array();
    protected $_defaultPort = 61613;
    protected $_currentHost = - 1;
    protected $_attempts = 10;
    protected $_username = '';
    protected $_password = '';
    protected $_sessionId;
    protected $_read_timeout_seconds = 60;
    protected $_read_timeout_milliseconds = 0;
    protected $_connect_timeout_seconds = 60;
    
    /**
     * Constructor
     *
     * @param string $brokerUri Broker URL
     * @throws StompException
     */
    public function __construct (StompConnectionInterface $connection)
    {
        $this->connection = $connection;
    }
    /**
     * Connect to server
     *
     * @param string $username
     * @param string $password
     * @return boolean
     * @throws StompException
     */
    public function connect ($username = '', $password = '')
    {
        if ($username != '') {
            $this->_username = $username;
        }
        if ($password != '') {
            $this->_password = $password;
        }
		$headers = array('login' => $this->_username , 'passcode' => $this->_password);
		if ($this->clientId != null) {
			$headers["client-id"] = $this->clientId;
		}
		$frame = new Frame("CONNECT", $headers);
        $this->_writeFrame($frame);
        $frame = $this->readFrame();
        if ($frame instanceof Frame && $frame->command == 'CONNECTED') {
            $this->_sessionId = $frame->headers["session"];
            return true;
        } else {
            if ($frame instanceof Frame) {
                throw new StompException("Unexpected command: {$frame->command}", 0, $frame->body);
            } else {
                throw new StompException("Connection not acknowledged");
            }
        }
    }
    
    /**
     * Check if client session has ben established
     *
     * @return boolean
     */
    public function isConnected ()
    {
        return !empty($this->_sessionId) && $this->getConnection()->isConnected();
    }
    /**
     * Current stomp session ID
     *
     * @return string
     */
    public function getSessionId()
    {
        return $this->_sessionId;
    }
    /**
     * Send a message to a destination in the messaging system 
     *
     * @param string $destination Destination queue
     * @param string|Frame $msg Message
     * @param array $properties
     * @param boolean $sync Perform request synchronously
     * @return boolean
     */
    public function send ($destination, $msg, $properties = array(), $sync = null)
    {
        if ($msg instanceof Frame) {
            $msg->headers['destination'] = $destination;
            if (is_array($properties)) $msg->headers = array_merge($msg->headers, $properties);
            $frame = $msg;
        } else {
            $headers = $properties;
            $headers['destination'] = $destination;
            $frame = new Frame('SEND', $headers, $msg);
        }
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);
        return $this->_waitForReceipt($frame, $sync);
    }
    /**
     * Prepair frame receipt
     *
     * @param Frame $frame
     * @param boolean $sync
     */
    protected function _prepareReceipt (Frame $frame, $sync)
    {
        $receive = $this->sync;
        if ($sync !== null) {
            $receive = $sync;
        }
        if ($receive == true) {
            $frame->headers['receipt'] = md5(microtime());
        }
    }
    /**
     * Wait for receipt
     *
     * @param Frame $frame
     * @param boolean $sync
     * @return boolean
     * @throws StompException
     */
    protected function _waitForReceipt (Frame $frame, $sync)
    {

        $receive = $this->sync;
        if ($sync !== null) {
            $receive = $sync;
        }
        if ($receive == true) {
            $id = (isset($frame->headers['receipt'])) ? $frame->headers['receipt'] : null;
            if ($id == null) {
                return true;
            }
            $frame = $this->readFrame();
            if ($frame instanceof Frame && $frame->command == 'RECEIPT') {
                if ($frame->headers['receipt-id'] == $id) {
                    return true;
                } else {
                    throw new StompException("Unexpected receipt id {$frame->headers['receipt-id']}", 0, $frame->body);
                }
            } else {
                if ($frame instanceof Frame) {
                    throw new StompException("Unexpected command {$frame->command}", 0, $frame->body);
                } else {
                    throw new StompException("Receipt not received");
                }
            }
        }
        return true;
    }
    /**
     * Register to listen to a given destination
     *
     * @param string $destination Destination queue
     * @param array $properties
     * @param boolean $sync Perform request synchronously
     * @return boolean
     * @throws StompException
     */
    public function subscribe ($destination, $properties = null, $sync = null)
    {
        $headers = array('ack' => 'client');
		$headers['activemq.prefetchSize'] = $this->prefetchSize;
		if ($this->clientId != null) {
			$headers["activemq.subcriptionName"] = $this->clientId;
		}
        if (isset($properties)) {
            foreach ($properties as $name => $value) {
                $headers[$name] = $value;
            }
        }
        $headers['destination'] = $destination;
        $frame = new Frame('SUBSCRIBE', $headers);
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);
        if ($this->_waitForReceipt($frame, $sync) == true) {
            $this->_subscriptions[$destination] = $properties;
            return true;
        } else {
            return false;
        }
    }
    /**
     * Remove an existing subscription
     *
     * @param string $destination
     * @param array $properties
     * @param boolean $sync Perform request synchronously
     * @return boolean
     * @throws StompException
     */
    public function unsubscribe ($destination, $properties = null, $sync = null)
    {
        $headers = array();
        if (isset($properties)) {
            foreach ($properties as $name => $value) {
                $headers[$name] = $value;
            }
        }
        $headers['destination'] = $destination;
        $frame = new Frame('UNSUBSCRIBE', $headers);
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);
        if ($this->_waitForReceipt($frame, $sync) == true) {
            unset($this->_subscriptions[$destination]);
            return true;
        } else {
            return false;
        }
    }
    /**
     * Start a transaction
     *
     * @param string $transactionId
     * @param boolean $sync Perform request synchronously
     * @return boolean
     * @throws StompException
     */
    public function begin ($transactionId = null, $sync = null)
    {
        $headers = array();
        if (isset($transactionId)) {
            $headers['transaction'] = $transactionId;
        }
        $frame = new Frame('BEGIN', $headers);
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);
        return $this->_waitForReceipt($frame, $sync);
    }
    /**
     * Commit a transaction in progress
     *
     * @param string $transactionId
     * @param boolean $sync Perform request synchronously
     * @return boolean
     * @throws StompException
     */
    public function commit ($transactionId = null, $sync = null)
    {
        $headers = array();
        if (isset($transactionId)) {
            $headers['transaction'] = $transactionId;
        }
        $frame = new Frame('COMMIT', $headers);
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);
        return $this->_waitForReceipt($frame, $sync);
    }
    /**
     * Roll back a transaction in progress
     *
     * @param string $transactionId
     * @param boolean $sync Perform request synchronously
     */
    public function abort ($transactionId = null, $sync = null)
    {
        $headers = array();
        if (isset($transactionId)) {
            $headers['transaction'] = $transactionId;
        }
        $frame = new Frame('ABORT', $headers);
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);
        return $this->_waitForReceipt($frame, $sync);
    }
    /**
     * Acknowledge consumption of a message from a subscription
	 * Note: This operation is always asynchronous
     *
     * @param string|Frame $messageMessage ID
     * @param string $transactionId
     * @return boolean
     * @throws StompException
     */
    public function ack ($message, $transactionId = null)
    {
        if ($message instanceof Frame) {
            $headers = $message->headers;
            if (isset($transactionId)) {
                $headers['transaction'] = $transactionId;
            }			
            $frame = new Frame('ACK', $headers);
            $this->_writeFrame($frame);
            return true;
        } else {
            $headers = array();
            if (isset($transactionId)) {
                $headers['transaction'] = $transactionId;
            }
            $headers['message-id'] = $message;
            $frame = new Frame('ACK', $headers);
            $this->_writeFrame($frame);
            return true;
        }
    }
    /**
     * Graceful disconnect from the server
     *
     */
    public function disconnect()
    {
		$headers = array();

		if ($this->clientId != null) {
			$headers["client-id"] = $this->clientId;
		}

        if (is_resource($this->_socket)) {
            $this->_writeFrame(new Frame('DISCONNECT', $headers));
            fclose($this->_socket);
        }
        $this->_socket = null;
        $this->_sessionId = null;
        $this->_currentHost = -1;
        $this->_subscriptions = array();
        $this->_username = '';
        $this->_password = '';
    }
    /**
     * Write frame to server
     *
     * @param Frame $stompFrame
     */
    protected function _writeFrame (Frame $stompFrame)
    {
        if (!$this->getConnection()->isConnected()) {
            throw new StompException('Socket connection hasn\'t been established');
        }

        $data = $stompFrame->__toString();
        $this->getConnection()->write($data);
    }
        
    /**
     * Read response frame from server
     *
     * @return Frame False when no frame to read
     */
    public function readFrame ()
    {
        if (!$this->hasFrameToRead()) {
            return false;
        }
        
        $data = '';
        $end = false;
        
        do {
            $data .= $this->getConnection()->read();
            if (strpos($data, "\x00") !== false) {
                $end = true;
                $data = rtrim($data, "\n");
            }
            $len = strlen($data);
        } while ($len < 2 || $end == false);
        
        list ($header, $body) = explode("\n\n", $data, 2);
        $header = explode("\n", $header);
        $headers = array();
        $command = null;
        foreach ($header as $v) {
            if (isset($command)) {
                list ($name, $value) = explode(':', $v, 2);
                $headers[$name] = $value;
            } else {
                $command = $v;
            }
        }
        $frame = new Frame($command, $headers, trim($body));
        if (isset($frame->headers['transformation']) && $frame->headers['transformation'] == 'jms-map-json') {
            return new Map($frame);
        } else {
            return $frame;
        }
        return $frame;
    }
    
    /**
     * Check if there is a frame to read
     *
     * @return boolean
     */
    public function hasFrameToRead()
    {
        return $this->getConnection()->hasData();
    }
    
    /**
     * Reconnects and renews subscriptions (if there were any)
     * Call this method when you detect connection problems     
     */
    protected function _reconnect ()
    {
        $subscriptions = $this->_subscriptions;
        
        $this->connect($this->_username, $this->_password);
        foreach ($subscriptions as $dest => $properties) {
            $this->subscribe($dest, $properties);
        }
    }
    /**
     * Graceful object desruction
     *
     */
    public function __destruct()
    {
        $this->disconnect();
    }
    
    protected function getConnection()
    {
        return $this->connection;
    }
}