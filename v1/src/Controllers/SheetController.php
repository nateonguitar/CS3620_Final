<?php
/**
 * Created by PhpStorm.
 * User: theds
 * Date: 12/6/2016
 * Time: 10:32 AM
 */

namespace CS3620_Final\Controllers;

use PDO;
use CS3620_Final\Models\Sheet;
use CS3620_Final\Controllers\User;
use CS3620_Final\Models\Token;
use CS3620_Final\Http\StatusCodes;
use CS3620_Final\Utilities\DatabaseConnection;


// my IDE says there's an error in this file, but it's because somewhere along the way between function calls one of the
// object I made from another class lost it's privacy for some crazy reason.  It works with the error, but it errors out
// if I use the getID() method I made inside the model.

// I'm wondering if it's an error in PHP 7, as it's rather new.

class SheetController
{
    public static function deleteSheet(){
        // can delete a sheet via DELETE http request with body like:
        /*
        {
            "id":"9"
        }
        */

        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        if (!ctype_digit($data->id) || $data->id < 0) {
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        $id = $data->id;


        $queryDeleteSheet = '
            DELETE FROM TabSiteSheet
            WHERE SheetID = :id
        ';

        $stmtDeleteSheet = $db->prepare($queryDeleteSheet);
        $stmtDeleteSheet->bindValue(':id', $id);

        if(!$stmtDeleteSheet->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $rowsDeleted = $stmtDeleteSheet->rowCount();

        if ($rowsDeleted == 0) {
            http_response_code(StatusCodes::GONE);
            die();
        } else {
            http_response_code(StatusCodes::OK);
            $returned_array = array();
            $returned_array['rowsDeleted'] = $rowsDeleted;
            return $returned_array;
        }
    }

    public static function editSheet()
    {
        /*
        This is accessed via PUT http method with body like:
        {
            "sheet_id": "66",
            "sheet_title": "Test Title",
            "sheet_contents": " Q Q Q H Q Q Q Q H Q \nE||---11----9----8----|------------8----|--11----9----8----|------------8----|\nB||-------------------|--11-------------|------------------|--11-------------|\nG||*------------------|-----------------|------------------|-----------------|\nD||*------------------|-----------------|------------------|-----------------|\nA||-------------------|-----------------|------------------|-----------------|\nE||-------------------|-----------------|------------------|-----------------|",
            "sheet_upload_date": "2016-12-13",
            "sheet_accepted": "0",
            "sheet_uploader_name": "nateonguitar",
            "user_id": "1",
            "composers": ["bach", "beethoven", "doofus", "mozart"]
        }

        We do not need any other information because we will keep some of the original content

        */
        $data = (object)json_decode(file_get_contents('php://input'));

        $role = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($role != Token::ROLE_ADMIN && $role != Token::ROLE_MODERATOR) {
            echo $role . ' ' . Token::ROLE_ADMIN;
            http_response_code(StatusCodes::UNAUTHORIZED);
            return "You don't have permission for that, honey!";
            die();
        }

        if (!ctype_digit($data->sheet_id) || $data->sheet_id < 0) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo "id must be a valid positive integer";
            die();
        }

        if(empty($data->sheet_title)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Sheet title is empty';
            die();
        }

        if(empty($data->sheet_contents)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Sheet is empty';
            die();
        }

        if (!ctype_digit($data->sheet_accepted) || ($data->sheet_accepted != 0 && $data->sheet_accepted != 1) ) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo "Accepted must be 0 or 1";
            die();
        }

        // do not allow changing the original uploader's name, userID, or original upload date
        // we need the original sheet's information to compare some stuff to
        $queryGetSheet = 'SELECT * FROM TabSiteSheet WHERE SheetID = :id';
        $stmtGetSheet = $db->prepare($queryGetSheet);
        $stmtGetSheet->bindValue(':id', $data->sheet_id);

        if(!$stmtGetSheet->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        // if the query failed or if there was not a sheet with that id, error out
        if($stmtGetSheet->rowCount() != 1){
            http_response_code(StatusCodes::NOT_FOUND);
            echo "could not find a sheet with that id";
            die();
        }

        $sheetID  = $data->sheet_id;
        $title    = $data->sheet_title;
        $contents = $data->sheet_contents;
        $accepted = $data->sheet_accepted;


        // now update this sheet
        $query_update_sheet = '
            UPDATE TabSiteSheet
            SET 
            SheetTitle = :title,
            SheetContents = :contents,
            SheetAccepted = :accepted
            WHERE SheetID = :id
        ';

        $statement_update_sheet = $db->prepare($query_update_sheet);
        $statement_update_sheet->bindValue(':id',    $sheetID);
        $statement_update_sheet->bindValue(':title',  $title);
        $statement_update_sheet->bindValue(':contents',  $contents);
        $statement_update_sheet->bindValue(':accepted',  $accepted);

        if (!$statement_update_sheet->execute()) {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        // now we need to delete all entries from the lookup table so we can replace them with the new entries
        // might change this to an update instead, but this seemed easier at the moment
        $queryDeleteAllOfThisSheetsSheetComposers = 'DELETE FROM TabSiteSheetComposer WHERE SheetID = :sheetID';
        $stmtDeleteAllOfThisSheetsSheetComposers = $db->prepare($queryDeleteAllOfThisSheetsSheetComposers);
        $stmtDeleteAllOfThisSheetsSheetComposers->bindValue(':sheetID', $sheetID);

        if(!$stmtDeleteAllOfThisSheetsSheetComposers->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }



        // now it's time to associate this sheet with the composers listed
        $composers = $data->composers;
        $composerIDs = array();

        $name = '';
        $queryGetComposer = 'SELECT * FROM TabSiteComposer WHERE ComposerName = :name';
        $stmtGetComposer = $db->prepare($queryGetComposer);
        $stmtGetComposer->bindParam(':name', $name);

        if(!empty($composers)){
            foreach($composers as $composer){
                // first we need to see if this composer is already in the database, and if not, insert it
                $name = trim(strtolower($composer));
                if(!$stmtGetComposer->execute()){
                    http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
                    die();
                }

                // if the composer doesn't already exist let's insert it
                // then enter in either the new composerID or the existing one
                if($stmtGetComposer->rowCount() != 1 and !empty($composer)){

                    $composerIDs[] = (ComposerController::createComposer($name))->getComposerID();
                }
                else {
                    $composerIDs[] = $stmtGetComposer->fetch(PDO::FETCH_ASSOC)['ComposerID'];
                }
            }

            // it's time to enter rows into the lookup table SheetComposer
            $queryInsertIntoSheetComposer = 'INSERT INTO TabSiteSheetComposer 
                                            (
                                                SheetID,
                                                ComposerID
                                            )
                                            VALUES
                                            (
                                                :sheetID,
                                                :composerID
                                            )';

            $composerID = '';
            $stmtInsertIntoSheetComposer = $db->prepare($queryInsertIntoSheetComposer);
            $stmtInsertIntoSheetComposer->bindValue(':sheetID', $sheetID);
            foreach($composerIDs as $composerID){
                $stmtInsertIntoSheetComposer->bindParam(':composerID', $composerID);
                if(!$stmtInsertIntoSheetComposer->execute()){
                    http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
                    die();
                }
            }
        }

        http_response_code(StatusCodes::OK);
        return SheetController::getSheetByID($sheetID);
    }

    public static function createSheet()
    {

        /*
        remember as you use this API that inserted "sheet_content" should have \n after every line.

        if manually entering, your tab like:
               Q    Q    Q        H        Q        Q    Q    Q        H        Q
        E||---11----9----8----|------------8----|--11----9----8----|------------8----|
        B||-------------------|--11-------------|------------------|--11-------------|
        G||*------------------|-----------------|------------------|-----------------|
        D||*------------------|-----------------|------------------|-----------------|
        A||-------------------|-----------------|------------------|-----------------|



        will need to look like this, all text for "sheet_contents" is on the same line and uses \n separators.
        {
            "title":"Test Title",
            "contents":"       Q    Q    Q        H        Q        Q    Q    Q        H        Q     \nE||---11----9----8----|------------8----|--11----9----8----|------------8----|\nB||-------------------|--11-------------|------------------|--11-------------|\nG||*------------------|-----------------|------------------|-----------------|\nD||*------------------|-----------------|------------------|-----------------|\nA||-------------------|-----------------|------------------|-----------------|\nE||-------------------|-----------------|------------------|-----------------|",
            "user_id":"1",
            "composers":[
                "bach",
                "beethoven",
                "mozart"
            ]
        }


        just be sure when you POST a new sheet that it gets put into the json correctly.

        Json requires new lines to be done like this, but you would lose the new line characters, so DO NOT do it this way:
        "       Q    Q    Q        H        Q        Q    Q    Q        H        Q     " +
        "E||---11----9----8----|------------8----|--11----9----8----|------------8----|" +
        "B||-------------------|--11-------------|------------------|--11-------------|" +
        "G||*------------------|-----------------|------------------|-----------------|" +
        "D||*------------------|-----------------|------------------|-----------------|" +
        "A||-------------------|-----------------|------------------|-----------------|"

        Forms from HTML should format it correctly, this is only if you are testing your API call directly with something like Postman
         */
        $data = (object)json_decode(file_get_contents('php://input'));

        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        if(empty($data->title)){
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        if(empty($data->contents)){
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        // no upload date passed in, will use the current datetime
        // no "accepted" value because we will default to false
        // an admin will have to review a sheet to accept it and a PUT or PATCH will be called

        if (!ctype_digit($data->user_id) || $data->user_id < 0) {
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        $user = UserController::getUserByID($data->user_id);

        // time to insert the sheet
        $queryInsertSheet = '
        INSERT INTO TabSiteSheet
        (
            SheetTitle,
            SheetContents,
            SheetUploadDate,
            SheetAccepted,
            SheetUploaderName,
            UserID
        )
        VALUES
        (
            :title,
            :contents,
            :upload_date,
            :accepted,
            :uploader_name,
            :userID
        )
    ';
        $stmtInsertSheet = $db->prepare($queryInsertSheet);

        $stmtInsertSheet->bindValue(':title',         $data->title);
        $stmtInsertSheet->bindValue(':contents',      $data->contents);
        $stmtInsertSheet->bindValue(':upload_date',   date('Y-m-d H:i:s'));
        $stmtInsertSheet->bindValue(':accepted',      0);
        $stmtInsertSheet->bindValue(':uploader_name', $user->getUsername());
        $stmtInsertSheet->bindValue(':userID',        $user->getID());

        if (!$stmtInsertSheet->execute()) {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $insertedSheetID = $db->lastInsertId();

        // now it's time to associate this sheet with the composers listed
        $composers = $data->composers;
        $composerIDs = array();

        $name = '';
        $queryGetComposer = 'SELECT * FROM TabSiteComposer WHERE ComposerName = :name';
        $stmtGetComposer = $db->prepare($queryGetComposer);
        $stmtGetComposer->bindParam(':name', $name);

        if(!empty($composers)){
            foreach($composers as $composer){
                // first we need to see if this composer is already in the database, and if not, insert it
                $name = trim(strtolower($composer));

                if(!$stmtGetComposer->execute()){
                    http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
                    die();
                }

                // if the composer doesn't already exist let's insert it
                if($stmtGetComposer->rowCount() != 1 && !empty($composer)){

                    // for some reason it loses it's private access here so I can't use getComposerID()
                    // if I use it I get an error saying that the object doesn't have a getComposerID method
                    // it runs fine even though the IDE says there's an error
                    $composerIDs[] = (ComposerController::createComposer($name))->getComposerID();
                }
                else {
                    $composerIDs[] = $stmtGetComposer->fetch(PDO::FETCH_ASSOC)['ComposerID'];
                }
            }

            // it's time to enter rows into the lookup table SheetComposer
            $queryInsertIntoSheetComposer = 'INSERT INTO TabSiteSheetComposer 
                                                (
                                                    SheetID,
                                                    ComposerID
                                                )
                                                VALUES
                                                (
                                                    :sheetID,
                                                    :composerID
                                                )';

            $stmtInsertIntoSheetComposer = $db->prepare($queryInsertIntoSheetComposer);
            $stmtInsertIntoSheetComposer->bindValue(':sheetID', $insertedSheetID);
            foreach($composerIDs as $composerID){
                $stmtInsertIntoSheetComposer->bindParam(':composerID', $composerID);
                if(!$stmtInsertIntoSheetComposer->execute()){
                    http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
                    die();
                }
            }
        }

        http_response_code(StatusCodes::CREATED);
        return SheetController::getSheetByID($insertedSheetID);
    }

    public static function getAllSheets(){
        // can access this via GET http method with URL like:
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/sheet/
        // OR
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/sheet

        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            echo  'You don\'t have permission for that, honey!';
            die();
        }

        $queryGetAllSheets = 'SELECT * FROM TabSiteSheet';

        $stmtGetAllSheets= $db->prepare($queryGetAllSheets);

        if(!$stmtGetAllSheets->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $allSheetsFormatted = array();
        $allSheets = $stmtGetAllSheets->fetchAll(PDO::FETCH_ASSOC);

        foreach($allSheets as $sheet){
            $sheetToAdd = new Sheet(
                $sheet['SheetID'],
                $sheet['SheetTitle'],
                $sheet['SheetContents'],
                $sheet['SheetUploadDate'],
                $sheet['SheetAccepted'],
                $sheet['SheetUploaderName'],
                $sheet['UserID'],
                SheetController::getAllComposersBySheetID($sheet['SheetID'])
            );
            array_push($allSheetsFormatted, $sheetToAdd);
        }

        http_response_code(StatusCodes::OK);
        return $allSheetsFormatted;
    }

    public static function getSheetByID($sheetID = null){

        // can access this function via SheetController::getSheetByID(27)
        // OR
        // GET http method with URL like:
        // http://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/sheet/27

        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            echo $role;
            http_response_code(StatusCodes::UNAUTHORIZED);
            echo  'You don\'t have permission for that, honey!';
            die();
        }

        // get the sheetID
        if (!empty($sheetID) && is_array($sheetID)) {
            $sheetID = $sheetID['id'];
        } else if ($sheetID == null) {
            $data = (object)json_decode(file_get_contents('php://input'));
            if(!empty($data->id)){
                $sheetID = $data->id;
            }
        }

        // make sure we have a proper integer
        if (!ctype_digit($sheetID) || $sheetID < 0) {
            http_response_code(400);
            echo "id must be a positive integer";
            die();
        }



        $queryGetSheet = 'SELECT * FROM TabSiteSheet WHERE SheetID = :id';

        $stmtGetSheet = $db->prepare($queryGetSheet);
        $stmtGetSheet->bindValue(':id', $sheetID);
        if(!$stmtGetSheet->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }



        if($stmtGetSheet->rowCount() != 1){
            http_response_code(StatusCodes::NOT_FOUND);
            echo 'That sheet does not exist';
            die();
        }

        $returned_data = $stmtGetSheet->fetch(PDO::FETCH_ASSOC);
        $returned_sheet = new Sheet(
            $returned_data['SheetID'],
            $returned_data['SheetTitle'],
            $returned_data['SheetContents'],
            $returned_data['SheetUploadDate'],
            $returned_data['SheetAccepted'],
            $returned_data['SheetUploaderName'],
            $returned_data['UserID'],
            SheetController::getAllComposersBySheetID($sheetID)
        );
        http_response_code(StatusCodes::OK);
        return $returned_sheet;
    }

    private static function getAllComposersBySheetID($sheetID):array{
        $composers = array();
        $queryGetAllComposersForThisSheet = '
              SELECT ComposerName 
              FROM TabSiteSheet s 
              INNER JOIN TabSiteSheetComposer sc
              ON s.SheetID = sc.SheetID
              INNER JOIN TabSiteComposer c
              ON sc.ComposerID = c.ComposerID
              WHERE s.SheetID = :sheetID
              ';

        $stmtGetAllComposersForThisSheet = (DatabaseConnection::getInstance())->prepare($queryGetAllComposersForThisSheet);
        $stmtGetAllComposersForThisSheet->bindValue(':sheetID', $sheetID);
        if(!$stmtGetAllComposersForThisSheet->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $composersWithKeyValuePairs = $stmtGetAllComposersForThisSheet->fetchAll(PDO::FETCH_ASSOC);
        foreach($composersWithKeyValuePairs as $composerWithKeyValuePairs){
            $composers[] = $composerWithKeyValuePairs['ComposerName'];
        }
        http_response_code(StatusCodes::OK);
        return $composers;
    }
}