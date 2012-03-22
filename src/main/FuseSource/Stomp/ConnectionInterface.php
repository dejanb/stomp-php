<?php

namespace FuseSource\Stomp;

/**
 *
 * @author Soeren Rohweder
 */
interface ConnectionInterface
{
    public function read();
    
    public function write($data);
    
    public function hasData();
    
    public function isConnected();
}