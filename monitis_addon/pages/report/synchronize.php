<?php

function userIdByEmail($email) {

	$arr = explode('_', $email);
	$userid = 0;
	if($arr && $arr[0]) {
		$userid = intval(substr($arr[0], 1));
	}
	return $userid;
}


$action = isset($_POST['act']) ? $_POST['act'] : '';
if(!empty($action)) {
	$monitor_id = $_POST['monitor_id'];
	$user_id = $_POST['user_id'];

	switch($action) {
		case 'unlink_monitor':
			monitisWhmcsServer::unlinkExternalMonitorById($monitor_id);
		break;
		case 'delete_monitor':
			$params = array(
				'monitor_id'=>$monitor_id,
				'user_id'=>$user_id
			);
			$resp = monitisClientApi::deleteExternalMonitor($params);
			if( $resp["status"] == 'ok') {
				MonitisApp::addMessage('Monitor successfully deleted');
			} else {
				MonitisApp::addError('Unable to delete monitor, API request failed: '.$resp['error']);
			}
			monitisLog($resp, 'action '.$action);
		break;
		case 'delete_user':
			$apikey = $_POST['apikey'];
			$resp = monitisClientApi::deleteUserByApikey($apikey);
			if( $resp["status"] == 'ok') {
				MonitisApp::addMessage('User successfully deleted');
			} else {
				MonitisApp::addError('Unable to delete user: '.$resp['error']);
			}
		break;
		case 'restore_user':
			$email = $_POST['email'];
			$apikey = $_POST['apikey'];

			$resp = monitisClientApi::restorUserByEmail($email, $apikey);
			if( $resp["status"] == 'ok') {
				MonitisApp::addMessage('User successfully recovered');
			} else {
				MonitisApp::addError('Unable to recover user: '.$resp['error']);
			}
		break;
	}
}

MonitisApp::printNotifications();
class monitisSynchronize {

	public function __construct() {}
	
	private function monitorInArray($arr, $fieldName, $fieldValue){
		
		for($i=0; $i<count($arr); $i++) {
			if(intval($arr[$i][$fieldName]) == intval($fieldValue)) {
				return $i;
			}
		}
		return -1;
	}
	public function synchronizeMonitors11($userid) {

		$mons = array();
		$links = array();
		$all = array();
		
		$linkMons = monitisClientApi::linksMonitors($userid);
		$apiMons = monitisClientApi::externalMonitors($userid);

		if($apiMons && $apiMons['testList'] && count( $apiMons['testList']) > 0 ) {
			$apiMons = $apiMons['testList'];
			if($apiMons && !$linkMons) {
				$mons = $apiMons;
			} else {
				$mons = array();
				$links = array();
				for($i=0; $i<count($apiMons); $i++) {
					$index = $this->monitorInArray($linkMons, 'monitor_id', $apiMons[$i]['id']);
					if($index < 0) {
						$mons[] = $apiMons[$i];
					} else {
					
						$all[] = array('api'=>$apiMons[$i], 'whmcs'=>$linkMons[$index]);
					} 
				}
				
				for($i=0; $i<count($linkMons); $i++) {
					$index = $this->monitorInArray($apiMons, 'id', $linkMons[$i]['monitor_id']);
					if($index < 0) {
						$links[] = $linkMons[$i];
					} 
				}
			
			}
		} elseif($links) {
			$links = $linkMons;
		}

		return array(
			'api' => $mons,
			'link' => $links,
			'oks' => $all
		);
	}
	
	public function synchronizeMonitors($userid, & $apiMons) {
		$mons = array();
		$links = array();
		$oks = array();
		
		$linkMons = monitisClientApi::linksMonitors($userid);
		
		if($apiMons && $linkMons) {
			for($i=0; $i<count($apiMons); $i++) {
				$index = $this->monitorInArray($linkMons, 'monitor_id', $apiMons[$i]['id']);
				if($index < 0) {
					$mons[] = $apiMons[$i];
				} else {
					$oks[] = array('api'=>$apiMons[$i], 'whmcs'=>$linkMons[$index]);
				}
			}
			
			for($i=0; $i<count($linkMons); $i++) {
				$index = $this->monitorInArray($apiMons, 'id', $linkMons[$i]['monitor_id']);
				if($index < 0) {
					$links[] = $linkMons[$i];
				} 
			}

		} elseif($linkMons && !$apiMons) {
			$links = $linkMons;
		} elseif(!$linkMons && $apiMons) {
			$mons = $apiMons;
		}
		return array(
			'api' => $mons,
			'link' => $links,
			'oks' => $oks
		);
	}
	//
	public function synchronizeClients() {
		
		$all = array();
		
		$subUsers = MonitisApi::clients(true);
		$clntByUsr = monitisSqlHelper::query('SELECT user_id, firstname, lastname, email, LOWER(status) as status, api_key, secret_key
			FROM mod_monitis_user
			LEFT JOIN tblclients ON tblclients.id=mod_monitis_user.user_id');
	
		for($i=0; $i<count($subUsers); $i++) {
		
			$whmcsUser = MonitisHelper::in_array($clntByUsr, 'api_key', $subUsers[$i]['apikey']);

			$userid = 0;
			// client linked
			if($whmcsUser) {
				$userid = $whmcsUser['user_id'];
			} else {
				// client unlinked
			}
			$monitors = null;
			if($userid > 0) {
				$monitors = $subUsers[$i]['monitors'];
				
				$monitors = $this->synchronizeMonitors($userid, $monitors);
			}
			$all[] = array(
				'api_user' => $subUsers[$i],
				'whmcs_user' => $whmcsUser,
				'monitors' => $monitors
				
			);
		}
		return $all;
	}
}

$oSynch = new monitisSynchronize(); 
$users = $oSynch->synchronizeClients();
?>
<style>
table.datatable td,
table.datatable th {
	text-align:left;
	padding-left: 10px;
}

table.datatable .monitors_list {
	width: 100%;
	background: #ffffff;
}

table.datatable .monitors_list td {
	padding: 2px 0px;
	margin:0px;
	font-size: 12px;
	font-family: Tahoma, Arial, Helvetica, sans-serif;
	text-align:left;
}

table.datatable .monitors_list tr:nth-child(even) td{
	background: #ffffff;
}

table.datatable .monitors_list tr:nth-child(odd) td {
	background: #fbfbfb;
}

table.datatable .monitors_list .monitor,
table.datatable .monitors_list .service {
	width: 50%;
}

table.datatable .monitors_list .active {
	/*color:#46A546;*/
	color:#333333; 
	font-weight:bold;
}
</style>

<table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3" style="">
	<tr>
		<th width="20%">Client / Sub Account</th>
		<th style="width:40%;">Monitis Account / Password</th>
		<th style="width:40%;">
			<table width="100%" border="0" cellspacing="0" cellpadding="0">
				<tr>
					<th style="width:50%;">Service</th>
					<th style="width:50%;">Monitis Monitor</th>
				</tr>
			</table>
		</th>
	</tr>
<?
$producttype = array(
	'addon'=>'Addon',
	'option'=>'Config options',
	'product'=>'Custom fields'
);

	for($i=0; $i<count($users); $i++) {
		$user = $users[$i];
		
		$apiId = '';
		$account = $apiName = $apiKey = '';
		$is_api = false;
		$api = null;
		$link = $oks = null;
		$uname = '';
		if($user['api_user']) {
			$is_api = true;
			$apiId = $user['api_user']['id'];
			$account = $user['api_user']['account'];
			$apiName = $user['api_user']['firstName'].' '.$user['api_user']['lastName'];
			$uname = $user['api_user']['firstName'].' '.$user['api_user']['lastName'];
			$apiKey = $user['api_user']['apikey'];

		}
		
		$whmcsId = '';
		$whmcsName = $whmcsEmail = $whmcsKey = $whmcsSecret = '';
		$is_whmcs = false;
		if($user['whmcs_user']) {
			$is_whmcs = true;

			$whmcs = $user['whmcs_user'];
			$whmcsId = $whmcs['user_id'];
			$whmcsName = $whmcs['firstname'].' '.$whmcs['lastname'];
			
			if(empty($uname))
				$uname = $whmcs['firstname'].' '.$whmcs['lastname'];
			$whmcsEmail = $whmcs['email'];
			$whmcsKey = $whmcs['api_key'];
			$whmcsSecret = $whmcs['secret_key'];
		}

		$mons = $user['monitors'];
		if($mons) {
			$api = $mons['api'];
			$link = $mons['link'];
			$oks = $mons['oks'];
		}
?>
	<tr>
		<td>
		<?if(!empty($whmcsId)) {
			$url = MonitisHelper::adminUrl().'clientsprofile.php?action=view&userid='.$whmcsId;
		?>
			<a href="<?=$url?>" target="_blank" style="color:#1A4D80"><?=$uname?></a>
		<?} else {?>
			<?=$uname?>
		<?}?>
		</td>
		<td class="account">
		<? if(!empty($apiId)) {
				$userid = userIdByEmail($account);
		?>
				<div><?=$account?></div><div><?=MonitisConf::$apiKey?>_<?=$userid?></div>
		<?}?>
		</td>
		<td>
			<table class="monitors_list" width="100%" border="0" cellspacing="0" cellpadding="0">
		<? for($j=0; $j<count($oks); $j++) {
			$stl = 'active';
			if($oks[$j]['api']['suspended']) 
				$stl = '';
			$lbl = 'Service: '.$oks[$j]['whmcs']['order_id'].'/'.$oks[$j]['whmcs']['service_id'];
			$url = MonitisHelper::adminServicerUrl($oks[$j]['whmcs']['user_id'], $oks[$j]['whmcs']['service_id']);
		?>
				<tr>
					<td class="service"><a href="<?=$url?>" target="_blank"><?=$lbl?></a></td>
					<td class="monitor <?=$stl?>"><?=$oks[$j]['api']['name']?></td>
				</tr>
			
		<?	}
			for($j=0; $j<count($api); $j++) {
				$stl = 'active';
				if($oks[$j]['api']['suspended']) 
					$stl = '';
		?>
				<tr>
					<td class="service">&nbsp;</td>
					<td class="monitor <?=$stl?>"><?=$api[$j]['name']?></td>
				</tr>
		<?	}	
			for($j=0; $j<count($link); $j++) {
				$lbl = 'Service: '.$link[$j]['order_id'].'/'.$link[$j]['service_id'];
				$url = MonitisHelper::adminServicerUrl($link[$j]['user_id'], $link[$j]['service_id']);
		?>
				<tr>
					<td class="service"><a href="<?=$url?>" target="_blank"><?=$lbl?></a></td>
					<td class="monitor">&nbsp;</td>
				</tr>
		<?	} ?>
			</table>
		
		</td>
	</tr>
<? } ?>
</table>