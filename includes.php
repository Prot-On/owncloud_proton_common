<?php

if (!defined('PROTON_COMMONS')) {
    define('PROTON_COMMONS',true);
    
    $dir = dirname(dirname(__FILE__)).'/common/3rdparty';
    set_include_path(get_include_path() . PATH_SEPARATOR . $dir);
    
    OC::$CLASSPATH['Pest']='user_proton/common/3rdparty/Pest/Pest.php';
    OC::$CLASSPATH['OCA\Proton\Util'] = 'user_proton/common/lib/util.php';
    OC::$CLASSPATH['OCA\Proton\BearerPest']='user_proton/common/lib/bearer_pest.php';
}

?>