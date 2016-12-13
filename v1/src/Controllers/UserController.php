<?php
/**
 * Created by PhpStorm.
 * User: theds
 * Date: 12/6/2016
 * Time: 10:32 AM
 */

namespace CS3620_Final\Controllers;

use PDO;
use CS3620_Final\Models\User;
use CS3620_Final\Models\Token;
use CS3620_Final\Http\StatusCodes;
use CS3620_Final\Utilities\DatabaseConnection;

class UserController
{
    public static function deleteUser(){
        /*
            You can delete a user via DELETE http request with a body like:
            {
                "id" : "28"
            }
        */

        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        if (!ctype_digit($data->id)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        $id = $data->id;

        $query_delete_user = '
            DELETE FROM TabSiteUser
            WHERE UserID = :id
        ';

        $stmt_delete_user = $db->prepare($query_delete_user);
        $stmt_delete_user->bindValue(':id', $id);

        if(!$stmt_delete_user->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $rowsDeleted = $stmt_delete_user->rowCount();
        if ($rowsDeleted == 0) {
            http_response_code(StatusCodes::GONE);
            echo 'No rows deleted. Please check the ID.';
            die();
        } else {
            http_response_code(StatusCodes::OK);
            $returned_array = array();
            $returned_array['rowsDeleted'] = $rowsDeleted;

            http_response_code(StatusCodes::OK);
            return (object) $returned_array;
        }
    }

    public static function editUser()
    {
        /*
        // can edit the user via PUT http method with a body like:
        {
            "user_id": "38",
            "first_name": "bruce",
            "last_name": "wayne",
            "email": "iam@batman.com",
            "username": "batman6",
            "password": "Doofus2",
            "privilege_level": "3"
        }
        */

        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        try{
            $id = $data->user_id;
            $firstName = trim(strtolower($data->first_name));
            $lastName = trim(strtolower($data->last_name));
            $email = strtolower($data->email);
            $username = trim(strtolower($data->username));
            $password = password_hash(trim(strtolower($data->password)), PASSWORD_DEFAULT );
            $privilegeLevel = $data->privilege_level;
        }
        catch(Exception $e){
            echo $e->getMessage();
        }

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            return "You don't have permission for that, honey!";
            die();
        }

        if (!ctype_digit($id)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo "Bad request";
            die();
        }

        // store only lower case, it's easy to format correctly on front end
        // first name must be only letters
        if(empty($firstName) || !ctype_alpha($firstName)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'First name not formatted correctly';
            die();
        }

        // last name must be only letters
        if(empty($lastName) || !ctype_alpha($lastName)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Last name not formatted correctly';
            die();
        }

        // validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'That is not a valid email';
            die();
        }

        // see if username is already in use (if it's not already this user's username)
        $queryCheckIfUserExists = 'SELECT * FROM TabSiteUser WHERE UserUsername = :username';
        $stmtCheckIfUserExists = $db->prepare($queryCheckIfUserExists);
        $stmtCheckIfUserExists->bindValue(':username', trim(strtolower($username)));
        if(!$stmtCheckIfUserExists->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $user = $stmtCheckIfUserExists->fetch(PDO::FETCH_ASSOC);

        // if the user exists the passed in ID better match that user
        if($stmtCheckIfUserExists->rowCount() == 1 && $user['UserID'] != $id){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'That username already exist for a different user';
            die();
        }
        else if(preg_match('/[^a-z0-9]/i', trim($username))){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Username cannot have special characters';
            die();
        }



        // password must have at least 1 special character
        if(!preg_match('/[^a-z0-9]/i', trim($password))){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Password must have at least 1 special character';
            die();
        }

        // if privilegeLevel is not a positive integer and we only allow up  to 3
        // banned:0, standard:1, moderator:2, admin:3
        if (!ctype_digit($privilegeLevel) || $privilegeLevel < 0 || $privilegeLevel > 3 ) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'invalid privilege level';
            die();
        }

        $queryUpdateUser = '
            UPDATE TabSiteUser
            SET UserFirstName = :firstName,
            UserLastName = :lastName,
            UserEmail = :email,
            UserUsername = :username,
            UserPassword = :password,
            UserPrivilegeLevel = :privilegeLevel
            WHERE UserID = :id
        ';


        $stmtUpdateUser = $db->prepare($queryUpdateUser);
        $stmtUpdateUser->bindValue(':id',             $id);
        $stmtUpdateUser->bindValue(':firstName',      $firstName);
        $stmtUpdateUser->bindValue(':lastName',       $lastName);
        $stmtUpdateUser->bindValue(':email',          $email);
        $stmtUpdateUser->bindValue(':username',       $username);
        $stmtUpdateUser->bindValue(':password',       $password);
        $stmtUpdateUser->bindValue(':privilegeLevel', $privilegeLevel);

        if(!$stmtUpdateUser->execute()) {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        http_response_code(StatusCodes::OK);
        return UserController::getUserByID($id);
    }

    public static function createUser()
    {
        /*
        // Send to this function with something like this with an HTTP POST method
        {
            "first_name" : "Bruce",
            "last_name" : "Wayne",
            "email" : "iam@batman.com",
            "username" : "batman6",
            "password" : "password!",
            "privilege_level" : "3"
        }
        */

        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        // I guess these errors aren't bad enough to need a try/catch, but it works, gotta move on
        try{
            $firstName = trim(strtolower($data->first_name));
            $lastName = trim(strtolower($data->last_name));
            $email = strtolower($data->email);
            $username = trim(strtolower($data->username));
            $password = password_hash(trim(strtolower($data->password)), PASSWORD_DEFAULT );
            $privilegeLevel = $data->privilege_level;
        }
        catch(Exception $e){
            echo $e->getMessage();
        }


        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            return "You don't have permission for that, honey!";
            die();
        }

        // store only lower case, it's easy to format correctly on front end
        // first name must be only letters
        if(empty($firstName) || !ctype_alpha($firstName)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'First name not formatted correctly';
            die();
        }

        // last name must be only letters
        if(empty($lastName) || !ctype_alpha($lastName)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Last name not formatted correctly';
            die();
        }

        // validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'That is not a valid email';
            die();
        }

        // see if username is already in use
        $queryCheckIfUserExists = 'SELECT * FROM TabSiteUser WHERE UserUsername = :username';
        $stmtCheckIfUserExists = $db->prepare($queryCheckIfUserExists);
        $stmtCheckIfUserExists->bindValue(':username', trim(strtolower($username)));
        if(!$stmtCheckIfUserExists->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        if($stmtCheckIfUserExists->rowCount() > 0){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'That username already exists';
            die();
        }
        else if(preg_match('/[^a-z0-9]/i', trim($username))){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Username cannot have special characters';
            die();
        }

        // password must have at least 1 special character
        if(!preg_match('/[^a-z0-9]/i', trim($password))){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Password must have at least 1 special character';
            die();
        }

        // if privilegeLevel is not a positive integer and we only allow up  to 3
        // banned:0, standard:1, moderator:2, admin:3
        if (!ctype_digit($privilegeLevel) || $privilegeLevel < 0 || $privilegeLevel > 3 ) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'invalid privilege level';
            die();
        }



        $query_insert_user = '
        INSERT INTO TabSiteUser
        (
            UserFirstName,
            UserLastName,
            UserEmail,
            UserUsername,
            UserPassword,
            UserPrivilegeLevel
        )
        VALUES
        (
            :firstName,
            :lastName,
            :email,
            :username,
            :password,
            :privilegeLevel
        )
    ';
        $statementInsertUser = $db->prepare($query_insert_user);
        $statementInsertUser->bindValue(':firstName',      $firstName);
        $statementInsertUser->bindValue(':lastName',       $lastName);
        $statementInsertUser->bindValue(':email',          $email);
        $statementInsertUser->bindValue(':username',       $username);
        $statementInsertUser->bindValue(':password',       $password);
        $statementInsertUser->bindValue(':privilegeLevel', $privilegeLevel);

        if(!$statementInsertUser->execute()) {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $inserted_id = $db->lastInsertId();
        $returned_user = new User(
            $inserted_id,
            $firstName,
            $lastName,
            $email,
            $username,
            $password,
            $privilegeLevel
        );
        http_response_code(StatusCodes::CREATED);
        return $returned_user;
    }

    public static function getAllUsers(){
        // you can get all users via POST http method with URL like:
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/user/
        // OR
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/user

        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        $queryGetAllUsers = '
                    SELECT * FROM TabSiteUser
                ';

        $stmtGetAllUsers = $db->prepare($queryGetAllUsers);

        if(!$stmtGetAllUsers->execute()) {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $allUsers = $stmtGetAllUsers->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(StatusCodes::OK);
        return $allUsers;
    }

    public static function getUserByID($userID = null){
        // the URL should be something like
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/user/1
        // with a GET http request

        // This API can also call this function internally to get a user by using:
        // UserController::getUserByID($id);

        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            echo  'You don\'t have permission for that, honey!';
            die();
        }

        // get the userID
        if (!empty($userID) && is_array($userID)) {
            $userID = $userID['id'];
        } else if ($userID == null) {
            $data = (object)json_decode(file_get_contents('php://input'));
            if(!empty($data->id)){
                $userID = $data->id;
            }
        }

        // make sure we have a proper integer
        if (!ctype_digit($userID)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        $queryGetUser = 'SELECT * FROM TabSiteUser WHERE UserID = :userID';

        $stmtGetUser = $db->prepare($queryGetUser);
        $stmtGetUser->bindValue(':userID', $userID);
        if(!$stmtGetUser->execute()) {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        if($stmtGetUser->rowCount() != 1){
            http_response_code(StatusCodes::NOT_FOUND);
            echo 'That user does not exist';
            die();
        }

        $returned_data = $stmtGetUser->fetch(PDO::FETCH_ASSOC);
        http_response_code(StatusCodes::OK);
        return new User(
            $returned_data['UserID'],
            $returned_data['UserFirstName'],
            $returned_data['UserLastName'],
            $returned_data['UserEmail'],
            $returned_data['UserUsername'],
            $returned_data['UserPassword'],
            $returned_data['UserPrivilegeLevel']
        );
    }
}