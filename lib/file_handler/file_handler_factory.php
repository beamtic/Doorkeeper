<?php
/**
 *           Doorkeeper File Handler Factory
 *
 *           Do not load this factory inside other factories...
 *
 *         @author Jacob (JacobSeated)
 */
namespace doorkeeper\lib\file_handler;

// IMPORTANT
// Please avoid replacing dependencies
// Until you are sure you understand the consequences

/**
 * This factory can be instantiated from a Compositioning Root to use functionality in the file_handler library.
 */
class file_handler_factory
{

    /**
     * Function to "build" the final object with all of its dependencies
     *
     * @return object The File Handler Object
     */
    public static function build()
    {
        $superglobals = new \doorkeeper\lib\superglobals\superglobals();
        $helpers = new \doorkeeper\lib\php_helpers\php_helpers();
        $file_types = new \doorkeeper\lib\file_handler\file_types();

        return new \doorkeeper\lib\file_handler\file_handler($helpers, $superglobals, $file_types);
    }

}