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
    public static function deleteComposer(){
        // can call this function via DELETE http method with a body like:
        //
        // {
        //      "id":"9"
        // }

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

        $queryDeleteComposer = '
            DELETE FROM TabSiteComposer
            WHERE ComposerID = :id
        ';

        $stmtDeleteComposer = $db->prepare($queryDeleteComposer);
        $stmtDeleteComposer->bindValue(':id', $id);

        if(!$stmtDeleteComposer->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $rowsDeleted = $stmtDeleteComposer->rowCount();

        if ($rowsDeleted == 0) {
            http_response_code(StatusCodes::GONE);
            echo 'No rows deleted. Please check the ID.';
            die();
        }

        http_response_code(StatusCodes::OK);
        return array('rowsDeleted' => $rowsDeleted);
    }

    public static function editComposer($passed_in_id = null, $passed_in_name = null)
    {
        // this can either be called internally like:
        // ComposerController::editComposer($id, $name)
        // OR
        // via PUT http method with a body like
        // {
        //     "id" : "3",
        //     "name" : "Mozart"
        // }

        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        // validate inputs
        if(
            (empty($passed_in_id)    && empty($data->id))             // no id given
            || (empty($passed_in_name)  && empty($data->name))           // no name given
            || (!empty($passed_in_id)   && !ctype_digit($passed_in_id))  // bad ID passed in
            || (!empty($data->id)       && !ctype_digit($data->id))      // bad ID from json
            || (!empty($passed_in_name) && !ctype_alpha($passed_in_name))// bad name passed in
            || (!empty($data->name)     && !ctype_alpha($data->name))    // bad name from json
        ){
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }


        // composers can have capitalization
        if(!empty($data->name)){
            $name = trim($data->name);
        }
        else{
            $name = trim($passed_in_name);
        }

        if(!empty($data->name)){
            $id = $data->id;
        }
        else{
            $id = $passed_in_id;
        }

        // see if composer already exists
        $queryCheckIfComposerExists = 'SELECT * FROM TabSiteComposer WHERE ComposerName = :name';
        $stmtCheckIfComposerExists = $db->prepare($queryCheckIfComposerExists);
        $stmtCheckIfComposerExists->bindValue(':name', $name);
        if(!$stmtCheckIfComposerExists->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $composer = $stmtCheckIfComposerExists->fetch(PDO::FETCH_ASSOC);
        echo $composer['ComposerID'] . ' ' . $id;
        echo $stmtCheckIfComposerExists->rowCount();

        // if we got the name back but the returned ID does not match the passed in ID
        if($stmtCheckIfComposerExists->rowCount() == 1 && $composer['ComposerID'] != $id){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'That name already exists in the database under a different id';
            die();
        }

        $queryUpdateComposer = '
            UPDATE TabSiteComposer
            SET ComposerName = :name
            WHERE ComposerID = :id
        ';

        $stmtUpdateComposer = $db->prepare($queryUpdateComposer);
        $stmtUpdateComposer->bindValue(':id',    $id);
        $stmtUpdateComposer->bindValue(':name',  $name);

        if(!$stmtUpdateComposer->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        http_response_code(StatusCodes::OK);
        $returned_data = ComposerController::getComposerByID($id);
        return $returned_data;
    }

    public static function createComposer($passedInComposer = null)
    {

        // you can either pass in a value like:
        // ComposerController::createComposer('Mozart')
        //
        // or through POST with body:
        // {
        //     "name" : "Mozart"
        // }

        $data = (object)json_decode(file_get_contents('php://input'));

        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        // we will allow capitalization wherever for composers
        // but for now, only letters and spaces
        if(!empty($passedInComposer)){
            if(is_array($passedInComposer)){
                $composer = $passedInComposer['name'];
            }
            else{
                $composer = $passedInComposer;
            }

        }
        else if(!empty($data->name)){
            $composer = $data->name;
        }
        else{
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }


        if(empty($composer) || !ctype_alpha(str_replace(' ', '', $composer))){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Name not formatted correctly';
            die();
        }

        $composer = trim($composer);

        // see if that composer already exists
        $queryCheckIfComposerExists = 'SELECT * FROM TabSiteComposer WHERE ComposerName = :name';
        $stmtCheckIfComposerExists = $db->prepare($queryCheckIfComposerExists);
        $stmtCheckIfComposerExists->bindValue(':name', $composer);

        if(!$stmtCheckIfComposerExists->execute()) {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }


        if($stmtCheckIfComposerExists->rowCount() == 0){
            $queryInsertComposer = 'INSERT INTO TabSiteComposer ( ComposerName ) VALUES ( :name );';

            $stmtInsertComposer = $db->prepare($queryInsertComposer);
            $stmtInsertComposer->bindValue(':name', $composer);

            if(!$stmtInsertComposer->execute()){
                http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
                die();
            }

            $inserted_id = $db->lastInsertId();

            http_response_code(StatusCodes::CREATED);
            return new Composer(
                $inserted_id,
                $composer
            );
        }
        else{
            http_response_code(StatusCodes::OK);
            $return_composer = $stmtCheckIfComposerExists->fetch(PDO::FETCH_ASSOC);
            return new Composer($return_composer['ComposerID'], $return_composer['ComposerName']);
        }
    }

    public static function getAllComposers(){
        // this can be called by giving a GET request without an ID like:
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/composer/
        // or
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/composer

        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            echo  'You don\'t have permission for that, honey!';
            die();
        }

        $queryGetAllComposers = '
                    SELECT * FROM TabSiteComposer
                ';

        $stmtGetAllComposers = $db->prepare($queryGetAllComposers);

        if(!$stmtGetAllComposers->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $allComposersFormatted = array();
        $allComposers = $stmtGetAllComposers->fetchAll(PDO::FETCH_ASSOC);
        foreach($allComposers as $composer){
            array_push($allComposersFormatted, $composer);
        }

        http_response_code(StatusCodes::OK);
        return $allComposersFormatted;
    }

    public static function getComposerByID($passedInComposerID = null){
        // you can either call the API like this:
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/composer/3
        // or the API can internally get a composer like:
        // ComposerController::getComposerByID(3);

        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        // get the composerID
        // if at the end of the url like:
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/composer/3
        // this will come in as an array
        // or you can use the internal call
        if (!empty($passedInComposerID)) {
            if(is_array($passedInComposerID)){
                $composerID = $passedInComposerID['id'];
            }
            else{
                $composerID = $passedInComposerID;
            }

        } else if (is_null($passedInComposerID)) {
            $data = (object)json_decode(file_get_contents('php://input'));
            if(!empty($data->id)){
                $composerID = $data->id;
            }
        }
        else{
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }


        // make sure we have a proper integer
        if (!ctype_digit($composerID)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        $queryGetComposer = 'SELECT * FROM TabSiteComposer WHERE ComposerID = :id';

        $stmtGetComposer = $db->prepare($queryGetComposer);
        $stmtGetComposer->bindValue(':id', $composerID);
        if(!$stmtGetComposer->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        if($stmtGetComposer->rowCount() != 1){
            http_response_code(StatusCodes::NOT_FOUND);
            echo 'That composer does not exist';
            die();
        }

        $returned_data = $stmtGetComposer->fetch(PDO::FETCH_ASSOC);
        $returned_composer = new Composer(
            $composerID,
            $returned_data['ComposerName']
        );
        http_response_code(StatusCodes::OK);
        return $returned_composer;
    }
}