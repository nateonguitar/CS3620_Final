<?php
/**
 * Created by PhpStorm.
 * User: Joshua
 * Date: 10/24/2016
 * Time: 12:55 PM
 */

namespace CS3620_Final\Controllers;

use CS3620_Final\Models\Token;
use CS3620_Final\Http\StatusCodes;
use CS3620_Final\Utilities\DatabaseConnection;
use PDO;

class TokensController
{
    public function buildToken(string $username, string $password)
    {
        try{
            $dbh = DatabaseConnection::getInstance();
        }catch(PDOException $e)
        {
            echo "Connection to Database Failed";
            echo "$e->getMessage()";
            die();
        }

        $queryGetUser = 'SELECT * FROM TabSiteUser WHERE UserUsername = :username';

        $stmtGetUser = $dbh->prepare($queryGetUser);
        $stmtGetUser->bindValue(':username', $username);
        $getUserWorked = $stmtGetUser->execute();

        if(!$getUserWorked){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            echo 'Unable to query database.';
            die();
        }

        if($stmtGetUser->rowCount() != 1){
            http_response_code(StatusCodes::NOT_FOUND);
            echo 'Bad username or password';
            die();
        }

        $user = $stmtGetUser->fetch(PDO::FETCH_ASSOC);

        if(!password_verify($password, $user['UserPassword'])){
            http_response_code(StatusCodes::NOT_FOUND);
            echo 'Bad username or password';
            die();
        }

        switch($user['UserPrivilegeLevel']){
            CASE 0:
            CASE '':
                http_response_code(StatusCodes::UNAUTHORIZED);
                echo 'Your account is banned.';
                die();
            CASE 1:
                $role = Token::ROLE_STANDARD;
                break;
            CASE 2:
                $role = Token::ROLE_MODERATOR;
                break;
            CASE 3:
                $role = Token::ROLE_ADMIN;
                break;
            default:
                http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
                echo 'unsupported role';
                die();
        }

        return (new Token())->buildToken($role, $username);
    }
}