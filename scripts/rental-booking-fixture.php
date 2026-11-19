<?php
use AlohaWPBridge\RentalEditor;
use AlohaWPBridge\RentalSourceSync;
use AlohaWPBridge\Pricing\Calendar;
if(!defined('WP_CLI')||!WP_CLI||wp_get_environment_type()!=='local'||home_url()!=='http://127.0.0.1:8088')exit(1);
$key=$args[0]??'';if(!preg_match('/\Am24-fixture-[a-f0-9]{16}\z/',$key))exit(1);
$post=get_posts(['post_type'=>'rental','post_status'=>'publish','name'=>$key.'-public','numberposts'=>1])[0]??null;
if(!$post||in_array($post->ID,[186,365],true))exit(1);
$today=Calendar::today('Pacific/Honolulu');
$data=RentalSourceSync::stored($post->ID);$data['version']=1;
$data['source']['minimumNightlyPrice']=['status'=>'available','amount'=>'345.00','currency'=>'USD','periodStart'=>$today,'periodEnd'=>Calendar::add($today,364),'calculatedAt'=>gmdate(DATE_ATOM),'source'=>'open-calendar'];
$data['source']['listing']['timezone']=['status'=>'available','value'=>'Pacific/Honolulu'];
if(!RentalEditor::writeSourceMeta($post->ID,RentalSourceSync::META,$data))exit(1);
echo 'PASS: synthetic minimum nightly rate.';
