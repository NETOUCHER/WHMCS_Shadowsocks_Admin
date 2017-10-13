<?php
    include('./qrlib.php');
    $text = $_GET["text"];
/*****************************************
    if(strpos($_SERVER['HTTP_REFERER'], 'example.com') == FALSE)
    {
      QRcode::png("Fuck your self idiot.");
      exit;
    }
******************************************/
    QRcode::png($text,false,QR_ECLEVEL_M);
?>
