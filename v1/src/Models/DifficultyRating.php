<?php
/**
 * Created by PhpStorm.
 * User: theds
 * Date: 12/6/2016
 * Time: 10:25 AM
 */

namespace CS3620_Final\Models;


class DifficultyRating implements \JsonSerializable
{
    private $DifficultyRatingID;
    private $UserID;
    private $SheetID;
    private $DifficultyRating;

    function jsonSerialize()
    {
        return
            array(
                'difficulty_rating_id' => $this->DifficultyRatingID,
                'user_id' => $this->UserID,
                'sheet_id' => $this->SheetID,
                'difficulty_rating' => $this->DifficultyRating
            );

    }

    function __construct($DifficultyRatingID, $UserID, $SheetID, $DifficultyRating)
    {
        $this->DifficultyRatingID = $DifficultyRatingID;
        $this->UserID = $UserID;
        $this->SheetID = $SheetID;
        $this->DifficultyRating = $DifficultyRating;
    }

    public function getDifficultyRatingID(){
        return $this->DifficultyRatingID;
    }
    public function getUserID(){
        return $this->UserID;
    }
    public function getSheetID(){
        return $this->SheetID;
    }
    public function getDifficultyRating(){
        return $this->DifficultyRating;
    }
}