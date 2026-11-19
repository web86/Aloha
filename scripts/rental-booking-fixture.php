<?php
use AlohaWPBridge\RentalEditor;
use AlohaWPBridge\RentalSourceSync;
use AlohaWPBridge\Pricing\Calendar;
if(!defined('WP_CLI')||!WP_CLI||wp_get_environment_type()!=='local'||home_url()!=='http://127.0.0.1:8088')exit(1);
$key=$args[0]??'';if(!preg_match('/\Am24-fixture-[a-f0-9]{16}\z/',$key))exit(1);
$post=get_posts(['post_type'=>'rental','post_status'=>'publish','name'=>$key.'-public','numberposts'=>1])[0]??null;
if(!$post||in_array($post->ID,[186,365],true))exit(1);
$visible=get_post_meta($post->ID,'_awb_source_snapshot',true);$visible['active']=true;$visible['isListed']=true;
if(!RentalEditor::writeSourceMeta($post->ID,'_awb_source_snapshot',$visible))exit(1);
$today=Calendar::today('Pacific/Honolulu');
$data=RentalSourceSync::stored($post->ID);$data['version']=1;
$data['source']['minimumNightlyPrice']=['status'=>'available','amount'=>'345.00','currency'=>'USD','periodStart'=>$today,'periodEnd'=>Calendar::add($today,364),'calculatedAt'=>gmdate(DATE_ATOM),'source'=>'open-calendar'];
$data['source']['listing']['timezone']=['status'=>'available','value'=>'Pacific/Honolulu'];
$mapping=\AlohaWPBridge\RentalEditorPanels::verifiedMapping($post->ID);
$settings=['capacity'=>11,'currency'=>'USD','minNights'=>3,'maxNights'=>28,'timezone'=>'Pacific/Honolulu','multi'=>false,'windowDays'=>365,'noticeHours'=>0,'checkInTime'=>null];
$days=[];
for($d=$today;$d<=Calendar::add($today,364);$d=Calendar::add($d,1))$days[]=['date'=>$d,'status'=>$d===Calendar::add($today,7)?'unavailable':($d===Calendar::add($today,12)?'unknown':'available'),
    'nightlyRate'=>['amount'=>'579.00','currency'=>'USD'],'minNights'=>3,'maxNights'=>$d===Calendar::add($today,9)?2:28,'cta'=>$d===Calendar::add($today,1),'ctd'=>$d===Calendar::add($today,6),'bookingConfirmed'=>$d!==Calendar::add($today,12),'reason'=>null];
$data['source']['calendar']=['version'=>1,'status'=>'partial','from'=>$today,'to'=>Calendar::add($today,364),'syncedAt'=>gmdate(DATE_ATOM),
    'identity'=>\AlohaWPBridge\Pricing\LocalCalendar::identity($mapping['provider_environment'],$mapping['guesty_listing_id']),
    'openSettings'=>$settings,'bookingSettings'=>['capacity'=>11,'currency'=>'USD','minNights'=>3,'maxNights'=>28,'suitableForChildren'=>true],'days'=>$days];
if(($args[1]??'')==='clear-calendar')unset($data['source']['calendar']);
if(!RentalEditor::writeSourceMeta($post->ID,RentalSourceSync::META,$data))exit(1);
echo 'PASS: synthetic minimum nightly rate.';
