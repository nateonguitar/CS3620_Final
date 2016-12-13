<?php
/**
 * Created by PhpStorm.
 * User: theds
 * Date: 12/6/2016
 * Time: 10:32 AM
 */

namespace CS3620_Final\Controllers;

use PDO;
use CS3620_Final\Models\DifficultyRating;
use CS3620_Final\Models\Token;
use CS3620_Final\Http\StatusCodes;
use CS3620_Final\Utilities\DatabaseConnection;

class DifficultyRatingController
{
    public static function deleteDifficultyRating(){
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
            die();
        }

        if (!ctype_digit($data->id)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        $id = $data->id;

        $queryDeleteRating = '
            DELETE FROM TabSiteDifficultyRating
            WHERE DifficultyRatingID = :id
        ';

        $stmtDeleteRating = $db->prepare($queryDeleteRating);
        $stmtDeleteRating->bindValue(':id', $id);

        if(!$stmtDeleteRating->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $rowsDeleted = $stmtDeleteRating->rowCount();

        if ($rowsDeleted == 0) {
            http_response_code(StatusCodes::GONE);
            die();
        }

        http_response_code(StatusCodes::OK);
        return array('rowsDeleted' => $rowsDeleted);
    }

    public static function  editDifficultyRating($passed_in_rating_id = null, $passed_in_rating = null)
    {
        // this can either be called internally like:
        // DifficultyRatingController::editDifficultyRating($existingID, $difficultyRating);
        // OR
        // via PUT http method with a body like
        //  {
        //     "rating_id"        :"2",
        //     "difficulty_rating":"4"
        // }

        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }


        // all parameters required
        // if using internally
        // here's a bunch of validation
        if(
               !is_null($passed_in_rating_id) && !is_null($passed_in_rating)
            && ctype_digit($passed_in_rating_id) && ctype_digit($passed_in_rating)
        ){
            $ratingID = $passed_in_rating_id;
            $rating = $passed_in_rating;

        }
        // if accessed via PUT http request with json
        else if(
            !empty($data)
            && ctype_digit($data->rating_id)
            && ctype_digit($data->quality_rating)
        ){
            $ratingID = $data->rating_id;
            $rating = $data->quality_rating;
        }
        else{
            // didn't access this function properly
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        // see if composer already exists
        $queryCheckIfRatingExists = 'SELECT * FROM TabSiteDifficultyRating WHERE DifficultyRatingID = :ratingID';
        $stmtCheckIfRatingExists = $db->prepare($queryCheckIfRatingExists);
        $stmtCheckIfRatingExists->bindValue(':ratingID', $ratingID);

        if(!$stmtCheckIfRatingExists->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        // if no rating was found
        if($stmtCheckIfRatingExists->rowCount() != 1){
            http_response_code(StatusCodes::NOT_FOUND);
            die();
        }

        $queryUpdateRating = '
            UPDATE TabSiteDifficultyRating
            SET DifficultyRating = :rating
            WHERE DifficultyRatingID = :ratingID
        ';


        $statementUpdateRating = $db->prepare($queryUpdateRating);
        $statementUpdateRating->bindValue(':ratingID',    $ratingID);
        $statementUpdateRating->bindValue(':rating',  $rating);

        if (!$statementUpdateRating->execute()) {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        http_response_code(StatusCodes::OK);
        return QualityRatingController::getQualityRAtingByID($ratingID);

    }

    public static function createQualityRating()
    {
        // you can create a DifficultyRating in the DB through POST with body:
        /*
        {
            "user_id"       :    "40",
            "sheet_id"      :    "79",
            "difficulty_rating":    "10"
        }
        */

        // only accepts difficulties 0 - 10

        // because a user can only rate the difficulty of a sheet once,
        // this function will update the old one instead if it's found

        $data = (object) json_decode(file_get_contents('php://input'));

        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN && $token != Token::ROLE_MODERATOR) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        // some validation
        if(
            empty($data->user_id) || !ctype_digit($data->user_id)                      // bad user_id data type
            || empty($data->sheet_id) || !ctype_digit($data->sheet_id)                 // bad sheet_id data type
            || empty($data->difficulty_rating) || !ctype_digit($data->difficulty_rating)  // bad difficulty_rating data type
            || $data->difficulty_rating < 0 || $data->difficulty_rating > 10                 // difficulty_rating valid values

        ){
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        $userID = $data->user_id;
        $sheetID = $data->sheet_id;
        $difficultyRating = $data->difficulty_rating;

        // see if this quality rating already exists:

        $querySeeIfRatingExists = 'SELECT * FROM TabSiteDifficultyRating WHERE SheetID = :sheetID AND UserID = :userID';
        $stmtSeeIfRatingExists = $db->prepare($querySeeIfRatingExists);
        $stmtSeeIfRatingExists->bindValue(':sheetID', $sheetID);
        $stmtSeeIfRatingExists->bindValue(':userID', $userID);

        if(!$stmtSeeIfRatingExists->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        // if it exists use our editQualityRating function.
        if($stmtSeeIfRatingExists->rowCount() == 1){
            $existingID = $stmtSeeIfRatingExists->fetch(PDO::FETCH_ASSOC)['DifficultyRatingID'];
            $returnRating = DifficultyRatingController::editDifficultyRating($existingID, $sheetID, $userID, $difficultyRating);
        }
        else{

            // see if user exists
            $querySeeIfUserExists = 'SELECT * FROM TabSiteUser WHERE UserID = :userID';
            $stmtSeeIfUserExists = $db->prepare($querySeeIfUserExists);
            $stmtSeeIfUserExists->bindValue(':userID', $userID);

            if(!$stmtSeeIfUserExists->execute()){
                http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
                die();
            }

            if($stmtSeeIfUserExists->rowCount() != 1){
                http_response_code(StatusCodes::BAD_REQUEST);
                echo 'user does not exist';
                die();
            }

            // see if sheet exists
            $querySeeIfSheetExists = 'SELECT * FROM TabSiteSheet WHERE SheetID = :sheetID';
            $stmtSeeIfSheetExists = $db->prepare($querySeeIfSheetExists);
            $stmtSeeIfSheetExists->bindValue(':sheetID', $sheetID);

            if(!$stmtSeeIfSheetExists->execute()){
                http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
                die();
            }

            if($stmtSeeIfSheetExists->rowCount() != 1){
                http_response_code(StatusCodes::BAD_REQUEST);
                echo 'sheet does not exist';
                die();
            }

            // if all parameters are ok:
            $queryInsertRating = '
                INSERT INTO TabSiteDifficultyRating ( UserID, SheetID, DifficultyRating )
                VALUES ( :userID, :sheetID, :difficultyRating )
            ';

            $statementInsertRating = $db->prepare($queryInsertRating);
            $statementInsertRating->bindValue(':userID', $userID);
            $statementInsertRating->bindValue(':sheetID', $sheetID);
            $statementInsertRating->bindValue(':difficultyRating', $difficultyRating);

            if(!$statementInsertRating->execute()){
                http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
                die();
            }

            $returnRating = DifficultyRatingController::getDifficultyRatingByID($db->lastInsertId());
        }

        http_response_code(StatusCodes::CREATED);
        return $returnRating;
    }

    public static function getAllDifficultyRatings(){
        // this can be called by giving a GET request without an ID like:
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/difficultyRating/
        // or
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/difficultyRating

        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        $queryGetAllRatings = '
                    SELECT * FROM TabSiteDifficultyRating
                ';

        $stmtGetAllRatings = $db->prepare($queryGetAllRatings);

        if(!$stmtGetAllRatings->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $allRatings = $stmtGetAllRatings->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(StatusCodes::OK);
        return $allRatings;
    }

    public static function getDifficultyRatingByID($passedInRatingID = null){
        // you can either call the API like this:
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/difficultyRating/2
        // or the API can internally get a rating like:
        // DifficultyRatingController::getDifficultyRatingByID(3);

        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();
        $data = (object) json_decode(file_get_contents('php://input'));


        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        // get the rating's id

        // if neither way of getting the ID is set error out
        if(is_null($passedInRatingID) && empty($data->id)){
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        // some validation
        if(!is_null($passedInRatingID) && ctype_digit($passedInRatingID)){
            $ratingID = $passedInRatingID;
        }
        else if(!is_null($passedInRatingID) && is_array($passedInRatingID)){
            $ratingID = $passedInRatingID['id'];
        }
        else if(is_null($passedInRatingID) && !empty($data->id) && ctype_digit($data->id)){
            $ratingID = $data->id;
        }
        else if(is_array($passedInRatingID)){
            $ratingID = $passedInRatingID['id'];
        }
        else{
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }


        $queryGetRating = 'SELECT * FROM TabSiteDifficultyRating WHERE DifficultyRatingID = :id';

        $stmtGetRating = $db->prepare($queryGetRating);
        $stmtGetRating->bindValue(':id', $ratingID);

        if(!$stmtGetRating->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        if($stmtGetRating->rowCount() != 1){
            http_response_code(StatusCodes::GONE);
            die();
        }

        $returnData = $stmtGetRating->fetch(PDO::FETCH_ASSOC);

        return new DifficultyRating(
            $returnData['DifficultyRatingID'],
            $returnData['UserID'],
            $returnData['SheetID'],
            $returnData['DifficultyRating']
        );
    }
}