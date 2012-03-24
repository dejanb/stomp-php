<?php

/**
 * Description of MessageTest
 *
 * @author srohweder
 */
class MessageTest extends \PHPUnit_Framework_TestCase
{
    public function testMessage()
    {
        $message = new \FuseSource\Stomp\Message('test123', array('destination' => 'queue/test'));
        $this->assertEquals(TestFrames::getSendTest123Frame(), $message->__toString());
    }
}
