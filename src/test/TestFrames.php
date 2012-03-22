<?php

/**
 * Description of TestFrames
 *
 * @author srohweder
 */
class TestFrames
{
    public static function getConnectFrame()
    {
        return <<<EOF
CONNECT
login: 
passcode: 

\x00
EOF;
    }

    public static function getAuthConnectFrame()
    {
        return <<<EOF
CONNECT
login: jon
passcode: doe

\x00
EOF;
    }
    
    public static function getConnectedFrame()
    {
        return <<<EOF
CONNECTED
session:1

\x00
EOF;
    }
    
    public static function getReceiptFrame()
    {
        return <<<EOF
RECEIPT

\x00
EOF;
    }
    
    public static function getSendTest123Frame()
    {
        return <<<EOF
SEND
destination: queue/test

test123\x00
EOF;
    }
    
    public static function getSendTest123HeaderFrame()
    {
        return <<<EOF
SEND
test: test
destination: queue/test

test123\x00
EOF;
    }
    
    public function getSubscribeFrame()
    {
        return <<<EOF
SUBSCRIBE
ack: client
activemq.prefetchSize: 1
destination: queue/test

\x00
EOF;
    }
}
