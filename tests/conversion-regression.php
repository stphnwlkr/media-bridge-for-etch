<?php
use UplinkPress\MediaBridgeForEtch\Image_Conversion;
use UplinkPress\MediaBridgeForEtch\Image_References;
wp_set_current_user(1);require_once ABSPATH.'wp-admin/includes/image.php';$GLOBALS['mbb_cv_passed']=0;$ids=[];$tokens=[];$options=[];
function mbb_cv_assert($ok,$message){if(!$ok)throw new RuntimeException($message);++$GLOBALS['mbb_cv_passed'];echo 'PASS: '.$message."\n";}
function mbb_cv_reject(callable $work,$label){try{$work();}catch(RuntimeException $e){mbb_cv_assert(true,$label);return;}throw new RuntimeException('Unexpected success: '.$label);}
try{
 $im=imagecreatetruecolor(32,24);ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);$up=wp_upload_bits('mbb-conversion-regression-'.wp_generate_uuid4().'.png',null,$bytes);
 $id=wp_insert_attachment(['post_title'=>'Disposable conversion regression','post_mime_type'=>'image/png','post_status'=>'inherit','post_author'=>1],$up['file']);$ids[]=$id;
 $meta=['width'=>32,'height'=>24,'file'=>_wp_relative_upload_path($up['file']),'filesize'=>filesize($up['file']),'sizes'=>[],'image_meta'=>['camera'=>'Fixture camera']];update_post_meta($id,'_wp_attachment_metadata',$meta);
 $url=wp_get_attachment_url($id);$content='<img src="'.$url.'"><!-- wp:image '.wp_json_encode(['url'=>$url]).' /-->';$post=wp_insert_post(['post_title'=>'Disposable conversion references','post_status'=>'draft','post_author'=>1,'post_content'=>wp_slash($content)]);$ids[]=$post;
 $data=['id'=>$id,'url'=>$url,'literal'=>'Do not change \\ this'];update_post_meta($post,'_etch_test_data',wp_slash($data));$option='mbb_cv_regression_'.wp_generate_uuid4();$options[]=$option;update_option($option,['url'=>$url],false);
 $plan=Image_Conversion::begin($id);$tokens[]=$plan['token'];mbb_cv_assert(count($plan['files'])===1,'Plan lists all unique physical files');
 mbb_cv_reject(fn()=>Image_Conversion::stage($plan['token'],'../outside','webp',[]),'Path traversal size rejected');
 mbb_cv_reject(fn()=>Image_Conversion::stage($plan['token'],'full','php',[]),'Executable format rejected');
 mbb_cv_reject(fn()=>Image_Conversion::stage($plan['token'],'full','webp',['tmp_name'=>$up['file'],'error'=>0]),'Local files cannot spoof HTTP uploads');
 mbb_cv_reject(fn()=>Image_Conversion::commit($plan['token']),'Incomplete conversion cannot commit');
 $no_upload=static function($caps){$caps['upload_files']=false;return $caps;};add_filter('user_has_cap',$no_upload);mbb_cv_assert(!Image_Conversion::allowed($id),'Role permission cannot bypass upload permission');remove_filter('user_has_cap',$no_upload);
 wp_set_current_user(0);mbb_cv_reject(fn()=>Image_Conversion::begin($id),'Anonymous begin rejected');mbb_cv_reject(fn()=>Image_Conversion::commit($plan['token']),'Known token cannot bypass authorization');wp_set_current_user(1);
 $editor=wp_get_image_editor($up['file']);$variant=$editor->save(get_temp_dir().'mbb-regression-'.wp_generate_uuid4().'.webp','image/webp');if(is_wp_error($variant))throw new RuntimeException($variant->get_error_message());
 $job=get_option('uplink_mbe_conversion_'.$plan['token']);$job['format']='webp';$job['quality']=80;$job['staged']=['full'=>$variant['path']];update_option('uplink_mbe_conversion_'.$plan['token'],$job,false);
 $result=Image_Conversion::commit($plan['token']);$new=wp_get_attachment_url($id);mbb_cv_assert($result['sizes']===1&&$result['references']>=3&&str_ends_with($new,'.webp'),'Commit updates content, serialized Etch and option references');
 mbb_cv_assert(get_post_mime_type($id)==='image/webp'&&is_file($up['file']),'Active format changes while original stays intact');
 mbb_cv_assert(get_post_meta($post,'_etch_test_data',true)['literal']===$data['literal'],'Serialized unrelated values remain exact');
 $summary=Image_Conversion::summary($id);mbb_cv_assert($summary['quality']===80&&count($summary['rows'])===1&&!empty($summary['date']),'Quality, date and per-size statistics persist');
 wp_update_post(['ID'=>$post,'post_content'=>wp_slash(get_post($post)->post_content.'<p>Later edit</p>')]);Image_Conversion::restore($id);
 mbb_cv_assert(get_attached_file($id)===$up['file']&&wp_get_attachment_metadata($id)===$meta,'Restore recovers original attachment identity and metadata');
 mbb_cv_assert(get_post($post)->post_content===$content.'<p>Later edit</p>','Restore preserves intervening edits');
 mbb_cv_assert(get_post_meta($post,'_etch_test_data',true)===$data&&get_option($option)['url']===$url,'Serialized references restore exactly');
 mbb_cv_assert(!Image_Conversion::active($id),'Restore permits a new conversion');
 $denied_post=wp_insert_post(['post_title'=>'Disposable denied reference','post_status'=>'draft','post_author'=>1,'post_content'=>'<img src="'.$url.'">']);$ids[]=$denied_post;
 $rollback_plan=Image_Conversion::begin($id);$tokens[]=$rollback_plan['token'];$editor=wp_get_image_editor($up['file']);$encoded=$editor->save(get_temp_dir().'mbb-rollback-'.wp_generate_uuid4().'.webp','image/webp');
 $rollback_job=get_option('uplink_mbe_conversion_'.$rollback_plan['token']);$rollback_job['format']='webp';$rollback_job['quality']=80;$rollback_job['staged']=['full'=>$encoded['path']];update_option('uplink_mbe_conversion_'.$rollback_plan['token'],$rollback_job,false);
 $deny_reference=static function($caps,$cap,$user,$args)use($denied_post){return $cap==='edit_post'&&(int)($args[0]??0)===$denied_post?['do_not_allow']:$caps;};add_filter('map_meta_cap',$deny_reference,10,4);
 try{mbb_cv_reject(fn()=>Image_Conversion::commit($rollback_plan['token']),'Inaccessible references stop the whole conversion');}finally{remove_filter('map_meta_cap',$deny_reference,10);}
 clean_post_cache($post);mbb_cv_assert(get_post($post)->post_content===$content.'<p>Later edit</p>'&&get_attached_file($id)===$up['file']&&!Image_Conversion::active($id),'Failed reference update rolls back earlier writes and keeps original active');
 $stale=Image_Conversion::begin($id);$tokens[]=$stale['token'];$changed=$meta;$changed['image_meta']['camera']='Changed';update_post_meta($id,'_wp_attachment_metadata',$changed);mbb_cv_reject(fn()=>Image_Conversion::commit($stale['token']),'Changed attachment rejects stale comparison');update_post_meta($id,'_wp_attachment_metadata',$meta);
 $map=Image_References::variants([$url=>$url.'.webp']);mbb_cv_assert(Image_References::replace($url.'.backup',$map)===$url.'.backup','Replacement never matches a different filename prefix');
 mbb_cv_assert(json_decode(Image_References::replace(wp_json_encode(['url'=>$url]),$map),true)['url']===$url.'.webp','JSON URL escaping stays valid');
 $serialized=serialize(['url'=>$url,'flag'=>false,'number'=>32]);$parsed=unserialize(Image_References::replace($serialized,$map),['allowed_classes'=>false]);mbb_cv_assert($parsed['flag']===false&&$parsed['number']===32&&$parsed['url']===$url.'.webp','Serialized lengths and scalar types stay correct');
 $object=serialize((object)['url'=>$url]);mbb_cv_assert(Image_References::replace($object,$map)===$object,'Serialized objects are neither instantiated nor rewritten');
 echo 'Conversion regression checks passed: '.$GLOBALS['mbb_cv_passed']."\n";
}finally{
 wp_set_current_user(1);foreach($tokens as$token)Image_Conversion::expire($token);foreach($options as$option)delete_option($option);
 foreach(array_reverse($ids)as$object_id){if(get_post_type($object_id)==='attachment')wp_delete_attachment($object_id,true);else wp_delete_post($object_id,true);}
}
