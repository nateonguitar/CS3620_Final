<?php
/**
 * Created by PhpStorm.
 * User: theds
 * Date: 12/6/2016
 * Time: 10:25 AM
 */

namespace CS3620_Final\Models;


class Sheet implements \JsonSerializable
{
    private $SheetID;
    private $SheetTitle;
    private $SheetContents;
    private $SheetUploadDate;
    private $SheetAccepted;
    private $SheetUploaderName;
    private $UserID;
    private $Composers = array();

    function jsonSerialize()
    {
        return
            array(
                'sheet_id' => $this->SheetID,
                'title' => $this->SheetTitle,
                'contents' => $this->SheetContents,
                'upload_date' => $this->SheetUploadDate,
                'accepted' => $this->SheetAccepted,
                'uploader_name' => $this->SheetUploaderName,
                'user_id' => $this->UserID,
                'composers' => $this->Composers
            );

    }

    function __construct($SheetID, $SheetTitle, $SheetContents, $SheetUploadDate, $SheetAccepted, $SheetUploaderName, $UserID, $Composers = array())
    {
        $this->SheetID              = $SheetID;
        $this->SheetTitle           = $SheetTitle;
        $this->SheetContents        = $SheetContents;
        $this->SheetUploadDate      = $SheetUploadDate;
        $this->SheetAccepted        = $SheetAccepted;
        $this->SheetUploaderName    = $SheetUploaderName;
        $this->UserID               = $UserID;
        $this->Composers            = $Composers;
    }
}