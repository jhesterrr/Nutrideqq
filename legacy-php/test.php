<?php
$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?key=AIzaSyBhpE1gKWosdJbydn9M-FdGuUfSxAd32ck');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
echo $res;
?>
