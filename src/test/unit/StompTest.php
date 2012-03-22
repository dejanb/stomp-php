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
    protected $connectedStomp;
    /**
     * @return 
     */
    public function getConnectionMock()
    {
        return $this->getMock('FuseSource\\Stomp\\ConnectionInterface', array('read', 'write', 'hasData', 'isConnected'));
    }
    
    
    public function testConnect()
    {
        $connection = $this->getConnectableConnection();
        
        $stomp = new Stomp($connection);
        $stomp->connect();

    }
    
    public function testSendFrame()
    {
        $connection = $this->getConnectionMock();
        $connection->expects($this->at(1))
                ->method('write')
                ->with($this->equalTo(TestFrames::getSendTest123Frame()));
        $connection->expects($this->at(3))
                ->method('write')
                ->with($this->equalTo(TestFrames::getSendTest123HeaderFrame()));        
        $connection->expects($this->any())
                ->method('isConnected')
                ->will($this->returnValue(true));
        $stomp = new Stomp($connection);
        $stomp->send('queue/test', 'test123');
        $stomp->send('queue/test', 'test123', array('test' => 'test'));
        
        
    }
    
    public function testSubscribe()
    {
        $connection = $this->getConnectionMock();
        $connection->expects($this->at(1))
                ->method('write')
                ->with($this->equalTo(TestFrames::getSubscribeFrame()));
        $connection->expects($this->any())
                ->method('isConnected')
                ->will($this->returnValue(true));
        $stomp = new Stomp($connection);
        $stomp->subscribe('queue/test');
    }
    /**
     * 
     */
    public function testSecureConnect()
    {
        $connection = $this->getConnectionMock();
        $connection->expects($this->any())
                ->method('write')
                ->with(TestFrames::getAuthConnectFrame());
        $connection->expects($this->any())
                ->method('read')
                ->will($this->returnValue(TestFrames::getConnectedFrame()));
        $connection->expects($this->any())
                ->method('hasData')
                ->will($this->returnValue(true));
        $connection->expects($this->any())
                ->method('isConnected')
                ->will($this->returnValue(true));
        
        $stomp = new Stomp($connection);
        $stomp->connect('jon','doe');
    }
    
    
    /**
     *
     * @return type returns a connection possible to connect to
     */
    protected function getConnectableConnection()
    {
        $connection = $this->getConnectionMock();
        $connection->expects($this->any())
                ->method('write')
                ->with(TestFrames::getConnectFrame());
        $connection->expects($this->any())
                ->method('read')
                ->will($this->returnValue(TestFrames::getConnectedFrame()));
        $connection->expects($this->any())
                ->method('hasData')
                ->will($this->returnValue(true));
        $connection->expects($this->any())
                ->method('isConnected')
                ->will($this->returnValue(true));
        
        return $connection;
    }
}
