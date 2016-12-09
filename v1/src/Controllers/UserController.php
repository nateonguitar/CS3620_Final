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
    public static function deleteUser($data){
        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            return "You don't have permission for that, honey!";
            die();
        }

        if (!ctype_digit($data->id)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo "Bad request";
            die();
        }

        $id = $data->id;

        $query_delete_user = '
            DELETE FROM TabSiteUser
            WHERE UserID = :id
        ';

        $stmt_delete_user = $db->prepare($query_delete_user);
        $stmt_delete_user->bindValue(':id', $id);

        $deleteUserWorked = $stmt_delete_user->execute();

        if($deleteUserWorked){
            $rowsDeleted = $stmt_delete_user->rowCount();
            if ($rowsDeleted == 0) {
                http_response_code(StatusCodes::GONE);
                echo 'No rows deleted. Please check the ID.';
                die();
            } else {
                http_response_code(StatusCodes::OK);
                $returned_array = array();
                $returned_array['rowsDeleted'] = $rowsDeleted;
                return $returned_array;
            }
        } else {

            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);

            echo 'Hey dawg, that didn\'t work!';
            die();
        }



    }

    public static function editUser($data)
    {
        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            return "You don't have permission for that, honey!";
            die();
        }

        if (!ctype_digit($data->id)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo "Bad request";
            die();
        }

        // store only lower case, it's easy to format correctly on front end
        // first name must be only letters
        if(empty($data->firstName) || !ctype_alpha($data->firstName)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'First name not formatted correctly';
            die();
        }

        // last name must be only letters
        if(empty($data->lastName) || !ctype_alpha($data->lastName)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Last name not formatted correctly';
            die();
        }

        // validate email
        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'That is not a valid email';
            die();
        }

        // see if username is already in use (if it's not already this user's username)
        $queryCheckIfUserExists = 'SELECT * FROM TabSiteUser WHERE UserUsername = :username';
        $stmtCheckIfUserExists = $db->prepare($queryCheckIfUserExists);
        $stmtCheckIfUserExists->bindValue(':username', trim(strtolower($data->username)));
        $CheckIfUserExistsWorked = $stmtCheckIfUserExists->execute();

        if($CheckIfUserExistsWorked){
            $user = $stmtCheckIfUserExists->fetch(PDO::FETCH_ASSOC);

            if($stmtCheckIfUserExists->rowCount() == 1 && $user['UserID'] != $data->id){
                http_response_code(StatusCodes::BAD_REQUEST);
                echo 'That username already exist for a different user';
                die();
            }
            else if(preg_match('/[^a-z0-9]/i', trim($data->username))){
                http_response_code(StatusCodes::BAD_REQUEST);
                echo 'Username cannot have special characters';
                die();
            }
        }
        else{
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            echo 'wha happened?';
            die();
        }

        // password must have at least 1 special character
        if(!preg_match('/[^a-z0-9]/i', trim($data->password))){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Password must have at least 1 special character';
            die();
        }

        // if privilegeLevel is not a positive integer and we only allow up  to 3
        // banned:0, standard:1, moderator:2, admin:3
        if (!ctype_digit($data->privilegeLevel) || $data->privilegeLevel < 0 || $data->privilegeLevel > 3 ) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'invalid privilege level';
            die();
        }
        $id = $data->id;
        $firstName = trim(strtolower($data->firstName));
        $lastName = trim(strtolower($data->lastName));
        $email = strtolower($data->email);
        $username = trim(strtolower($data->username));
        $password = password_hash(trim(strtolower($data->password)), PASSWORD_DEFAULT );
        $privilegeLevel = $data->privilegeLevel;

        $query_update_user = '
            UPDATE TabSiteUser
            SET UserFirstName = :firstName,
            UserLastName = :lastName,
            UserEmail = :email,
            UserUsername = :username,
            UserPassword = :password,
            UserPrivilegeLevel = :privilegeLevel
            WHERE UserID = :id
        ';


        $statement_update_user = $db->prepare($query_update_user);
        $statement_update_user->bindValue(':id',             $id);
        $statement_update_user->bindValue(':firstName',      $firstName);
        $statement_update_user->bindValue(':lastName',       $lastName);
        $statement_update_user->bindValue(':email',          $email);
        $statement_update_user->bindValue(':username',       $username);
        $statement_update_user->bindValue(':password',       $password);
        $statement_update_user->bindValue(':privilegeLevel', $privilegeLevel);

        $update_user_worked = $statement_update_user->execute();

        if ($update_user_worked) {
            http_response_code(StatusCodes::OK);

            $returned_data = UserController::getUserByID($id);
            return $returned_data;
        } else {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            return 'Hey dawg, that didn\'t work!';
        }
    }

    public static function createUser($data)
    {
        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            return "You don't have permission for that, honey!";
            die();
        }

        // store only lower case, it's easy to format correctly on front end
        // first name must be only letters
        if(empty($data->firstName) || !ctype_alpha($data->firstName)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'First name not formatted correctly';
            die();
        }

        // last name must be only letters
        if(empty($data->lastName) || !ctype_alpha($data->lastName)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Last name not formatted correctly';
            die();
        }

        // validate email
        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'That is not a valid email';
            die();
        }

        // see if username is already in use
        $queryCheckIfUserExists = 'SELECT * FROM TabSiteUser WHERE UserUsername = :username';
        $stmtCheckIfUserExists = $db->prepare($queryCheckIfUserExists);
        $stmtCheckIfUserExists->bindValue(':username', trim(strtolower($data->username)));
        $CheckIfUserExistsWorked = $stmtCheckIfUserExists->execute();

        if($CheckIfUserExistsWorked){
            if($stmtCheckIfUserExists->rowCount() > 0){
                http_response_code(StatusCodes::BAD_REQUEST);
                echo 'That username already exists';
                die();
            }
            else if(preg_match('/[^a-z0-9]/i', trim($data->username))){
                http_response_code(StatusCodes::BAD_REQUEST);
                echo 'Username cannot have special characters';
                die();
            }
        }
        else{
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            echo 'wha happened?';
            die();
        }

        // password must have at least 1 special character
        if(!preg_match('/[^a-z0-9]/i', trim($data->password))){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Password must have at least 1 special character';
            die();
        }

        // if privilegeLevel is not a positive integer and we only allow up  to 3
        // banned:0, standard:1, moderator:2, admin:3
        if (!ctype_digit($data->privilegeLevel) || $data->privilegeLevel < 0 || $data->privilegeLevel > 3 ) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'invalid privilege level';
            die();
        }

        $firstName = trim(strtolower($data->firstName));
        $lastName = trim(strtolower($data->lastName));
        $email = strtolower($data->email);
        $username = trim(strtolower($data->username));
        $password = password_hash(trim(strtolower($data->password)), PASSWORD_DEFAULT );
        $privilegeLevel = $data->privilegeLevel;

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
        $statement_insert_user = $db->prepare($query_insert_user);
        $statement_insert_user->bindValue(':firstName',      $firstName);
        $statement_insert_user->bindValue(':lastName',       $lastName);
        $statement_insert_user->bindValue(':email',          $email);
        $statement_insert_user->bindValue(':username',       $username);
        $statement_insert_user->bindValue(':password',       $password);
        $statement_insert_user->bindValue(':privilegeLevel', $privilegeLevel);

        $insert_user_worked = $statement_insert_user->execute();

        if ($insert_user_worked) {
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
        } else {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            return 'Hey dawg, that didn\'t work!';
        }

    }

    public static function getAllUsers(){
        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            echo  'You don\'t have permission for that, honey!';
            die();
        }

        $query_get_all_users = '
                    SELECT * FROM TabSiteUser
                ';

        $stmt_get_all_users = $db->prepare($query_get_all_users);

        $getAllUsersWorked = $stmt_get_all_users->execute();

        if($getAllUsersWorked){
            $allUsersFormatted = array();
            $allUsers = $stmt_get_all_users->fetchAll(PDO::FETCH_ASSOC);
            foreach($allUsers as $user){
                $userToAdd = new User(
                    $user['UserID'],
                    $user['UserFirstName'],
                    $user['UserLastName'],
                    $user['UserEmail'],
                    $user['UserUsername'],
                    $user['UserPassword'],
                    $user['UserPrivilegeLevel']
                );

                array_push($allUsersFormatted, $user);
            }

            http_response_code(StatusCodes::OK);
            return $allUsersFormatted;
        }
    }

    public static function getUserByID($userID = null){
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
            http_response_code(400);
            echo "<h1>Error: Bad Request</h1>";
            die();
        }



        $queryGetUser = 'SELECT * FROM TabSiteUser WHERE UserID = :userID';

        $stmtGetUser = $db->prepare($queryGetUser);
        $stmtGetUser->bindValue(':userID', $userID);
        $getUserWorked = $stmtGetUser->execute();


        if ($getUserWorked) {
            if($stmtGetUser->rowCount() != 1){
                http_response_code(StatusCodes::NOT_FOUND);
                echo 'That user does not exist';
                die();
            }

            $returned_data = $stmtGetUser->fetch(PDO::FETCH_ASSOC);
            $returned_user = new User(
                $userID,
                $returned_data['UserFirstName'],
                $returned_data['UserLastName'],
                $returned_data['UserEmail'],
                $returned_data['UserUsername'],
                $returned_data['UserPassword'],
                $returned_data['UserPrivilegeLevel']
            );
            http_response_code(StatusCodes::OK);
            return $returned_user;
        } else {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            return 'That didn\'t work';
        }

    }
}