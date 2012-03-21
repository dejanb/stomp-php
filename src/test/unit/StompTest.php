<?php

use FuseSource\Stomp\Exception\StompException;
use FuseSource\Stomp\Exception\StompConfigurationException;
use FuseSource\Stomp\Stomp;

/**
 * Description of StompTest
 *
 * @author Soeren Rohweder
 */
class StompTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return 
     */
    public function getConnectionMock()
    {
        return $this->getMock('FuseSource\\Stomp\\StompConnectionInterface', array('read', 'write', 'hasData', 'isConnected'));
    }
    
    public function getConnectFrame()
    {
        return <<<EOF
CONNECT
login: 
passcode: 

\x00
EOF;
    }
    
    public function getConnectedFrame()
    {
        return <<<EOF
CONNECTED
session:1


\x00
EOF;
    }
    
    public function testConnect()
    {
        $connection = $this->getConnectionMock();
        $connection->expects($this->any())
                ->method('write')
                ->with($this->getConnectFrame());
        $connection->expects($this->any())
                ->method('read')
                ->will($this->returnValue($this->getConnectedFrame()));
        $connection->expects($this->any())
                ->method('hasData')
                ->will($this->returnValue(true));
        $connection->expects($this->any())
                ->method('isConnected')
                ->will($this->returnValue(true));
        
        $stomp = new Stomp($connection);
        $stomp->connect();
    }
}
