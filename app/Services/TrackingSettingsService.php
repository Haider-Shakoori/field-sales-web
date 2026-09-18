<?php
namespace App\Services;
use App\Models\CompanySetting; use App\Models\Tenant;
final class TrackingSettingsService {
 public function get(Tenant $tenant): array { $d=config('tenancy.defaults'); $rows=CompanySetting::where('tenant_id',$tenant->id)->where('key','like','tracking.%')->pluck('value','key'); $get=fn($k)=>$rows->get('tracking.'.$k,$d[$k]); return [
 'work_session_start_mode'=>in_array($get('work_session_start_mode'),['manual','automatic'],true)?$get('work_session_start_mode'):'manual',
 'workday_start_time'=>$this->time($get('workday_start_time'),'08:00'),'workday_end_time'=>$this->time($get('workday_end_time'),'17:00'),
 'auto_end_session'=>$this->bool($get('auto_end_session')),'gps_tracking_enabled'=>$this->bool($get('gps_tracking_enabled')),
 'gps_moving_interval_seconds'=>$this->int($get('gps_moving_interval_seconds'),10,3600,15),'gps_stationary_interval_seconds'=>$this->int($get('gps_stationary_interval_seconds'),10,7200,60),
 'gps_stale_after_minutes'=>$this->int($get('gps_stale_after_minutes'),1,720,15),'timezone'=>(new TenantClock)->timezone($tenant),
 'privacy_policy_version'=>(string)$get('privacy_policy_version'),'updated_at'=>CompanySetting::where('tenant_id',$tenant->id)->where('key','like','tracking.%')->max('updated_at')]; }
 private function bool(mixed $v):bool{return filter_var($v,FILTER_VALIDATE_BOOLEAN);} private function int(mixed $v,int $min,int $max,int $fallback):int{$i=(int)$v;return $i>=$min&&$i<=$max?$i:$fallback;} private function time(mixed $v,string $f):string{return is_string($v)&&preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/',$v)?$v:$f;}
}
