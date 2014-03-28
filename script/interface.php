<?php

require ('../config.php');

$get = GETPOST('get','alpha');
$put = GETPOST('put','alpha');
	
_put($db, $put);
_get($db, $get);

function _get(&$db, $case) {
	switch ($case) {
		case 'tasks' :
			
			print json_encode(_tasks($db, (int)$_REQUEST['id_project'], $_REQUEST['status']));

			break;
		case 'task' :
			
			print json_encode(_task($db, (int)GETPOST('id')));

			break;
			
		case 'velocity':
			
			print json_encode(_velocity($db, (int)$_REQUEST['id_project']));
			
			break;
	}

}

function _velocity(&$db, $id_project) {
global $langs;
	
	$Tab=array();
	
	$velocity = scrum_getVelocity($db, $id_project);
	$Tab['current'] = convertSecondToTime($velocity).$langs->trans('HoursPerDay');
	
	if( (float)DOL_VERSION <= 3.4 ) {
		// ne peut pas gérér la résolution car pas de temps plannifié			
	}
	else {
		
		if($velocity>0) {
			
			$time = time();
			$res=$db->query("SELECT SUM(planned_workload-duration_effective) as duration 
				FROM ".MAIN_DB_PREFIX."projet_task 
				WHERE fk_projet=".$id_project." AND progress>0 AND progress<100");
			if($obj=$db->fetch_object($res)) {
				//time rest in second
				$time_end_inprogress = $time + $obj->duration / $velocity * 86400;
			}
			
			if($time_end_inprogress<$time)$time_end_inprogress = $time;
			
			$res=$db->query("SELECT SUM(planned_workload-duration_effective) as duration 
				FROM ".MAIN_DB_PREFIX."projet_task 
				WHERE fk_projet=".$id_project." AND progress=0");
			if($obj=$db->fetch_object($res)) {
				//time rest in second
				$time_end_todo = $time_end_inprogress + $obj->duration / $velocity * 86400;
			}
			
			if($time_end_todo<$time)$time_end_todo = $time;
			
			if($time_end_todo>$time_end_inprogress) $Tab['todo']=', '.$langs->trans('EndedThe').' '.date('d/m/Y', $time_end_todo);
			$Tab['inprogress']=', '.$langs->trans('EndedThe').' '.date('d/m/Y', $time_end_inprogress);
			
			
		}
		
		
		
	}
	
	return $Tab;
	
}

function _as_array(&$object, $recursif=false) {
global $langs;
	$Tab=array();
		if(get_class($object)=='Task') {
			
			$object->aff_time = convertSecondToTime($object->duration_effective);
			$object->aff_planned_workload = convertSecondToTime($object->planned_workload);
	
			$object->long_description.='';
			if($object->date_start>0) $object->long_description .= $langs->trans('TaskDateStart').' : '.dol_print_date($object->date_start).'<br />';
			if($object->date_end>0) $object->long_description .= $langs->trans('TaskDateEnd').' : '.dol_print_date($object->date_end).'<br />';
			
			$object->long_description.=$object->description;
				
		}
	
		foreach ($object as $key => $value) {
				
			if(is_object($value) || is_array($value)) {
				if($recursif) $Tab[$key] = _as_array($recursif, $value);
				else $Tab[$key] = $value;
			}
			else if(strpos($key,'date_')===0){
				
				$Tab['time_'.$key] = $value;	
				
				if(empty($value))$Tab[$key] = '0000-00-00 00:00:00';
				else $Tab[$key] = date('Y-m-d H:i:s',$value);
			}
			else{
				$Tab[$key]=$value;
			}
		}
		return $Tab;
	
}

function _put(&$db, $case) {
	switch ($case) {
		case 'task' :
			
			print json_encode(_task($db, (int)GETPOST('id'), $_REQUEST));
			
			break;
			
		case 'sort-task' :
			
			_sort_task($db, $_REQUEST['TTaskID']);
			
			break;
	}

}
function _sort_task(&$db, $TTask) {
	
	foreach($TTask as $rank=>$id) {
		$task=new Task($db);
		$task->fetch($id);
		$task->rang = $rank;
		$task->update($db);
	}
	
}
function _set_values(&$object, $values) {
	
	foreach($values as $k=>$v) {
		
		if(isset($object->{$k})) {
			
			$object->{$k} = $v;
			
		}
		
	}
	
}
function _task(&$db, $id_task, $values=array()) {
global $user;

	$task=new Task($db);
	if($id_task) $task->fetch($id_task);
	
	if(!empty($values)){
		_set_values($task, $values);
	
		if($values['status']=='inprogress') {
			if($task->progress==0)$task->progress = 5;
			else if($task->progress==100)$task->progress = 95;
		}
		else if($values['status']=='finish') {
			$task->progress = 100;
		}	
		else if($values['status']=='todo') {
			$task->progress = 0;
		}	
	
		$task->status = $values['status'];
	}
	//$task->velocity_ok = _is_current_velocity_ok($task);
	
	$task->update($user);
	
	return _as_array($task);
}

function _tasks(&$db, $id_project, $status) {
		
	if($status=='ideas') {
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet_task 
		WHERE fk_projet=$id_project AND progress=0 AND datee IS NULL
		ORDER BY rang";
	}	
	else if($status=='todo') {
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet_task 
		WHERE fk_projet=$id_project AND progress=0 ORDER BY rang";
	}
	else if($status=='inprogress') {
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet_task 
		WHERE fk_projet=$id_project AND progress>0 AND progress<100 ORDER BY rang";
	}
	else if($status=='finish') {
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet_task 
		WHERE fk_projet=$id_project AND progress=100 
		ORDER BY rang";
	}
		
	$res = $db->query($sql);	
		
		
	$TTask = array();
	while($obj = $db->fetch_object($res)) {
		$t=new Task($db);
		$t->fetch($obj->rowid);
		
		$TTask[] = array_merge( _as_array($t) , array('status'=>$status));
	}
	
	return $TTask;
}
