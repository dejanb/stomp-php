<?php

namespace FuseSource\Stomp;

/**
 *
 * @author Soeren Rohweder
 */
interface StompConnectionInterface
{
    public function read();
    
    public function write($data);
    
    public function hasData();
    
    public function isConnected();
}