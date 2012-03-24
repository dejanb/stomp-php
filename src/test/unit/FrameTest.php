<?php

/**
 * Description of FrameTest
 *
 * @author srohweder
 */
class FrameTest extends \PHPUnit_Framework_TestCase
{
    public function testToString()
    {
        $frame = new \FuseSource\Stomp\Frame('CONNECT',array('login' => '', 'passcode' => ''));
        $this->assertEquals(TestFrames::getConnectFrame(), $frame->__toString());
    }
    
    /**
     * @expectedException FuseSource\Stomp\Exception\StompException
     * @expectedExceptionMessage test exception
     * @expectedExceptionCode 0
     */
    public function testErrorFrame()
    {
        $frame = new \FuseSource\Stomp\Frame('ERROR', array('message' => 'test exception'));
    }
}
