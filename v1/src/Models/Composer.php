<?php
/**
 * Created by PhpStorm.
 * User: theds
 * Date: 12/6/2016
 * Time: 10:25 AM
 */

namespace CS3620_Final\Models;


class Composer implements \JsonSerializable
{
    private $ComposerID;
    private $ComposerName;

    function jsonSerialize()
    {
        return
            array(
                'composer_id' => $this->ComposerID,
                'composer_name' => $this->ComposerName
            );

    }

    function __construct($ComposerID, $ComposerName)
    {
        $this->ComposerID = $ComposerID;
        $this->ComposerName = $ComposerName;
    }

    function getComposerID(){
        return $this->ComposerID;
    }
}