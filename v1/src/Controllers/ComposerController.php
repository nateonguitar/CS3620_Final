<?php
/**
 * Created by PhpStorm.
 * User: theds
 * Date: 12/6/2016
 * Time: 10:32 AM
 */

namespace CS3620_Final\Controllers;

use PDO;
use CS3620_Final\Models\Composer;
use CS3620_Final\Models\Token;
use CS3620_Final\Http\StatusCodes;
use CS3620_Final\Utilities\DatabaseConnection;

class ComposerController
{
    public static function deleteComposer($data){
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

        $query_delete_composer = '
            DELETE FROM TabSiteComposer
            WHERE ComposerID = :id
        ';

        $stmt_delete_composer = $db->prepare($query_delete_composer);
        $stmt_delete_composer->bindValue(':id', $id);

        $deleteComposerWorked = $stmt_delete_composer->execute();

        if($deleteComposerWorked){
            $rowsDeleted = $stmt_delete_composer->rowCount();
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

    public static function editComposer($data)
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
        if(empty($data->name) || !ctype_alpha($data->name)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'First name not formatted correctly';
            die();
        }

        // see if username is already in use (if it's not already this user's username)
        $queryCheckIfComposerExists = 'SELECT * FROM TabSiteComposer WHERE ComposerName = :name';
        $stmtCheckIfComposerExists = $db->prepare($queryCheckIfComposerExists);
        $stmtCheckIfComposerExists->bindValue(':name', trim(strtolower($data->name)));
        $CheckIfComposerExistsWorked = $stmtCheckIfComposerExists->execute();

        if($CheckIfComposerExistsWorked){
            $composer = $stmtCheckIfComposerExists->fetch(PDO::FETCH_ASSOC);

            if($stmtCheckIfComposerExists->rowCount() == 1 && $composer['ComposerID'] != $data->id){
                http_response_code(StatusCodes::BAD_REQUEST);
                echo 'That username already exist for a different user';
                die();
            }
        }
        else{
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            echo 'wha happened?';
            die();
        }

        $id = $data->id;
        $name = trim(strtolower($data->name));

        $query_update_composer = '
            UPDATE TabSiteComposer
            SET ComposerName = :name
            WHERE ComposerID = :id
        ';


        $statement_update_composer = $db->prepare($query_update_composer);
        $statement_update_composer->bindValue(':id',    $id);
        $statement_update_composer->bindValue(':name',  $name);

        $update_composer_worked = $statement_update_composer->execute();

        if ($update_composer_worked) {
            http_response_code(StatusCodes::OK);

            $returned_data = ComposerController::getComposerByID($id);
            return $returned_data;
        } else {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            return 'Hey dawg, that didn\'t work!';
        }
    }

    public static function createComposer($data)
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
        // name must be only letters
        if(empty($data->name) || !ctype_alpha($data->name)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Name not formatted correctly';
            die();
        }

        // see if that composer already exists
        $queryCheckIfComposerExists = 'SELECT * FROM TabSiteComposer WHERE ComposerName = :name';
        $stmtCheckIfComposerExists = $db->prepare($queryCheckIfComposerExists);
        $stmtCheckIfComposerExists->bindValue(':name', trim(strtolower($data->name)));
        $CheckIfComposerExistsWorked = $stmtCheckIfComposerExists->execute();

        if($CheckIfComposerExistsWorked){
            if($stmtCheckIfComposerExists->rowCount() > 0){
                http_response_code(StatusCodes::BAD_REQUEST);
                echo 'That composer already exists';
                die();
            }
        }
        else{
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            echo 'wha happened?';
            die();
        }

        $name = trim(strtolower($data->name));

        $query_insert_composer = '
        INSERT INTO TabSiteComposer
        (
            ComposerName
        )
        VALUES
        (
            :name
        )
    ';
        $statement_insert_composer = $db->prepare($query_insert_composer);
        $statement_insert_composer->bindValue(':name',      $name);

        $insert_composer_worked = $statement_insert_composer->execute();

        if ($insert_composer_worked) {
            $inserted_id = $db->lastInsertId();
            $returned_composer = new Composer(
                $inserted_id,
                $name
            );
            http_response_code(StatusCodes::CREATED);

            return $returned_composer;
        } else {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            return 'Hey dawg, that didn\'t work!';
        }

    }

    public static function getAllComposers(){
        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            echo  'You don\'t have permission for that, honey!';
            die();
        }

        $query_get_all_composers = '
                    SELECT * FROM TabSiteComposer
                ';

        $stmt_get_all_comoposers = $db->prepare($query_get_all_composers);

        $getAllComposersWorked = $stmt_get_all_comoposers->execute();

        if($getAllComposersWorked){
            $allComposersFormatted = array();
            $allComposers = $stmt_get_all_comoposers->fetchAll(PDO::FETCH_ASSOC);
            foreach($allComposers as $composer){
                $composerToAdd = new Composer(
                    $composer['ComposerID'],
                    $composer['ComposerName']
                );

                array_push($allComposersFormatted, $composer);
            }

            http_response_code(StatusCodes::OK);
            return $allComposersFormatted;
        }
    }

    public static function getComposerByID($composerID = null){
        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            echo  'You don\'t have permission for that, honey!';
            die();
        }

        // get the composerID
        if (!empty($composerID) && is_array($composerID)) {
            $composerID = $composerID['id'];
        } else if ($composerID == null) {
            $data = (object)json_decode(file_get_contents('php://input'));
            if(!empty($data->id)){
                $composerID = $data->id;
            }
        }

        // make sure we have a proper integer
        if (!ctype_digit($composerID)) {
            http_response_code(400);
            echo "<h1>Error: Bad Request</h1>";
            die();
        }



        $queryGetComposer = 'SELECT * FROM TabSiteComposer WHERE ComposerID = :id';

        $stmtGetComposer = $db->prepare($queryGetComposer);
        $stmtGetComposer->bindValue(':id', $composerID);
        $getComposerWorked = $stmtGetComposer->execute();


        if ($getComposerWorked) {
            if($stmtGetComposer->rowCount() != 1){
                http_response_code(StatusCodes::NOT_FOUND);
                echo 'That user does not exist';
                die();
            }

            $returned_data = $stmtGetComposer->fetch(PDO::FETCH_ASSOC);
            $returned_composer = new Composer(
                $composerID,
                $returned_data['ComposerName']
            );
            http_response_code(StatusCodes::OK);
            return $returned_composer;
        } else {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            return 'That didn\'t work';
        }

    }
}