<?php

require_once('deps/test-more-php/Test-More-OO.php');
require_once('deps/JSONMessage.php/src/JSONMessage.php');
require_once('deps/SQLAbstract.php/src/SQLAbstract.php');
require_once('deps/SQLAbstract.php/src/SQLAbstractPDO.php');
require_once('src/JSONModel.php');

class JSONModelTest {
    public $sql;
    public $tables;
    public $views;
    public $types;
    function __construct ($prefix, $tables, $views, $types) {
        $this->sql = new SQLAbstractTest($prefix);
        $this->tables = $tables;
        $this->views = $views;
        $this->types = $types;
    }
}