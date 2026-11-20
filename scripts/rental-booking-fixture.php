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
if(($args[1]??'')==='range'){
    $future=(new DateTimeImmutable(substr($today,0,7).'-01'))->modify('+4 months')->format('Y-m-');
    $blocked=Calendar::add($future.'25',7);
    foreach($data['source']['calendar']['days'] as &$day){
        $day['status']=in_array($day['date'],[$future.'04',$blocked],true)?'unavailable':($day['date']===$future.'08'?'unknown':'available');
        $day['bookingConfirmed']=$day['status']!=='unknown';$day['reason']=$day['status']==='unavailable'?'already_booked':null;
        $day['cta']=$day['date']===$future.'07';$day['ctd']=in_array($day['date'],[$future.'19',$future.'20'],true);
        $day['minNights']=[$future.'05'=>1,$future.'10'=>2,$future.'15'=>3,$future.'20'=>4,$future.'25'=>7,$future.'18'=>1][$day['date']]??3;
        $day['maxNights']=$day['date']===$future.'18'?2:90;
    }unset($day);
    $data['source']['calendar']['openSettings']['maxNights']=90;$data['source']['calendar']['bookingSettings']['maxNights']=90;
}
if(($args[1]??'')==='clear-calendar')unset($data['source']['calendar']);
if(!RentalEditor::writeSourceMeta($post->ID,RentalSourceSync::META,$data))exit(1);
echo 'PASS: synthetic minimum nightly rate.';
