<?php
/**
 * Created by PhpStorm.
 * User: theds
 * Date: 12/6/2016
 * Time: 10:25 AM
 */

namespace CS3620_Final\Models;


class User implements \JsonSerializable
{
    private $UserID;
    private $UserFirstName;
    private $UserLastName;
    private $UserEmail;
    private $UserUsername;
    private $UserPassword;
    private $UserPrivilegeLevel;

    function jsonSerialize()
    {
        return
            array(
                'user_id' => $this->UserID,
                'user_first_name' => $this->UserFirstName,
                'user_last_name' => $this->UserLastName,
                'user_email' => $this->UserEmail,
                'user_username' => $this->UserUsername,
                'user_password' => $this->UserPassword,
                'user_privilege level' => $this->UserPrivilegeLevel
            );

    }

    function __construct($UserID, $UserFirstName, $UserLastName, $UserEmail, $UserUsername, $UserPassword, $UserPrivilegeLevel)
    {
        $this->UserID = $UserID;
        $this->UserFirstName = $UserFirstName;
        $this->UserLastName = $UserLastName;
        $this->UserEmail = $UserEmail;
        $this->UserUsername = $UserUsername;
        $this->UserPassword = $UserPassword;
        $this->UserPrivilegeLevel = $UserPrivilegeLevel;
    }
}