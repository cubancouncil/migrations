#!/usr/bin/env php
<?php

/* MIGRATE SCRIPT */

class Migrate {
    
    var $cmd;
    var $upList;
    var $downList;
    var $fileList;
    var $position;
    var $settings;
    
    function __construct() {
        $this->upList = array();
        $this->downList = array();
        $this->fileList = array();
        $this->position = -1;
    }
    
    function run() {
        $this->loadInfo();
        $this->loadMigrations();
        $this->loadSettings();
        
        $this->parseArgs();
    }
    
    function parseArgs() {
        global $argv, $argc;
        
        $args = array();
        if (!empty($argv)) {
            $args = array_slice($argv, 0);
            // remove command from start of array
            $this->cmd = array_shift($args);
        }
        
        
        if ($args[0] == 'update') {
            $this->moveToLatest();
        } else if ($args[0] == 'revert') {
            $this->moveBackOne();
        } else if ($args[0] == 'info') {
            $this->showInfo();
        } else if (preg_match('/^[0-9]+/', $args[0])) {
            $this->moveToFile($args[0]);
        } else {
            $this->showHelp();
        }
    }
    
    function loadMigrations() {
        global $migrate;
        
        $migrationsPath = 'migrations';
        if (is_dir($migrationsPath)) {
            foreach (glob($migrationsPath.'/[0-1]*.php') as $migrationFile) {
                $this->fileList[] = basename($migrationFile);
                include $migrationFile;
            }
        }
    }
    
    function loadInfo() {
        // if there is no .migrations file skip trying to read it
        if (!file_exists('.migrations')) {
            return;
        }
        
        $serializedInfo = file_get_contents('.migrations');
        if ($serializedInfo === false) {
            $this->showError('Could not read .migrations file');
        }
        
        $info = unserialize($serializedInfo);
        if ($info === false) {
            $this->showError('Did not understand contents of .migrations file');
        }
        
        $this->position = $this->getPositionOfFile($info['lastFile']);
    }
    
    function saveInfo() {
        
        $info = array();
        $info['lastFile'] = $this->fileList[$this->position];
        
        $serializedInfo = serialize($info);
        
        $fileHandle = fopen('.migrations', 'w');
        if ($fileHandle !== false) {
            if (fwrite($fileHandle, $serializedInfo) === false) {
                // fail
                $this->showError('Could not write .migrations file');
            }
            
            fclose($fileHandle);
        } else {
            $this->showError('Could not open .migrations file');
        }
    }
    
    function showError($msg) {
        echo 'ERROR: '.$msg."\n";
        exit;
    }
    
    function showHelp() {
        $usage = <<<EOF
Usage:
migrate.php update|revert|info|(filename)

List of Commands:
update - migrates to latest migration
revert - reverts back one migration

Example:
php migrate.php 005
This migrates the database to the point in the file in "migrations/" starting with "005"

EOF;
    }
    
    function showInfo() {
        echo 'Current Position: '.$this->lastPosition."\n";
        echo 'Files:'."\n";
        foreach ($this->fileList as $file) {
            echo $file . "\n";
        }
    }
    
    function moveToFile($migrationName) {
        $targetPosition = $this->getPositionOfFile($migrationName);
        
        if ($targetPosition >= 0) {
            $this->moveToPosition($targetPosition);
        } else {
            // fail
            showError('Did not understand version name: \''.$migrationName.'\'');
            return;
        }
    }
    
    function getPositionOfFile($migrationName) {
        $matches = preg_grep('/^'.preg_quote($migrationName,'/').'/i', $this->fileList);
        if (!empty($matches)) {
            return key($matches);
        } else {
            return -1;
        }
    }
    
    function moveToPosition($targetPosition) {
        if ($targetPosition > $this->position) {
            for ($i = $this->position + 1; $i <= $targetPosition; $i++) {
                if (array_key_exists($i, $this->upList)) {
                    call_user_func($this->upList[$i]);
                }
            }
            
            $this->position = $targetPosition;
            $this->saveInfo();
            
        } else if ($targetPosition < $this->position) {
            for ($i = $this->position; $i > $targetPosition; $i--) {
                if (array_key_exists($i, $this->downList)) {
                    call_user_func($this->downList[$i]);
                }
            }
            
            $this->position = $targetPosition;
            $this->saveInfo();
        }
    }
    
    function moveToLatest() {
        $latestFile = end($this->fileList);
        reset($this->fileList);
        
        if ($latestFile !== false) {
            $this->moveToFile($latestFile);
        }
    }
    
    function moveBackOne() {
        if ($this->position >= 0 && array_key_exists($this->position, $this->fileList)) {
            $targetPosition = $this->position - 1;
            $this->moveToPosition($targetPosition);
        }
    }
    
    function up($callback) {
        $this->upList[] = $callback;
    }
    
    function down($callback) {
        $this->downList[] = $callback;
    }
    
    function query($sql) {
        echo $sql."\n";
        if (mysql_query($sql) === false) {
            $this->showError(mysql_error());
        }
    }
    
    function loadSettings() {
        if (file_exists('settings.php')) {
            include 'settings.php';
        } else {
            $this->showError('Could not load settings.php');
        }
        
        $this->settings = $settings;
        mysql_connect($this->settings['host'], $this->settings['user'], $this->settings['pass']);
        mysql_select_db($this->settings['database']);
    }
}

$migrate = new Migrate();
require 'settings.php';

$migrate->run();