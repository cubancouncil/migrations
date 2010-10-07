<?php

class Migration000 {
    function up() {
        global $migrate;
        $migrate->query('CREATE TABLE `sample` (
          `sample_id` int(11) NOT NULL AUTO_INCREMENT,
          PRIMARY KEY (`sample_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;');
    }
    
    function down() {
        global $migrate;
        $migrate->query('DROP TABLE `sample`;');
    }
}

$migrate->up(array(Migration000, 'up'));
$migrate->down(array(Migration000, 'down'));