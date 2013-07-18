<?
define('MONITIS_FIELD_WEBSITE', 'URL/IP');
define('MONITIS_FIELD_MONITOR', 'Monitor type');
define('MONITIS_MONITOR_TYPES', 'http,https,ping');

class WHMCS_product_db extends whmcs_db {
	private $client_id = null;
	
	public function __construct ( ) {
		//$this->client_id = $client_id;
	}

	protected function getFields( $pids) {
		$sql = 'SELECT * FROM tblcustomfields  WHERE relid in ('.$pids.')';
		return $this->query( $sql );
	}
	
	protected function productFields( $pid ) {
		$sql = 'SELECT *, tblcustomfieldsvalues.value
		FROM tblcustomfields  
		LEFT JOIN tblcustomfieldsvalues on (tblcustomfieldsvalues.fieldid = tblcustomfields.id  )
		WHERE tblcustomfields.relid ='.$pid;
		return $this->query( $sql );
	}

	
	protected function allProducts() {
		$sql = 'SELECT id, gid, name, type, description FROM tblproducts';
		return $this->query_count( $sql );
	}

	protected function deleteProduct( $pid ) {
		$sql = 'DELETE FROM mod_monitis_product WHERE product_id='.$pid;
		return $this->query_del( $sql );
	}
	public function deleteFields( $pids) {
		$sql = 'DELETE FROM tblcustomfields WHERE id in ('.$pids.')';
		return $this->query_del( $sql );
	}
//$clientSQL = "SELECT * FROM `tblclients`, `tblcustomfields`, ` tblcustomfieldsvalues` 
//WHERE `tblcustomfields.id` = '1' AND `tblcustomfieldsvalues.fieldid` = '1' AND `tblcustomfieldsvalues.value` != ''";
	protected function products() {
		$sql = 'SELECT * FROM mod_monitis_product';
		return $this->query( $sql );
	}
	
	protected function isWhmcsProduct( $pid ) {
		$sql = 'SELECT * FROM mod_monitis_product WHERE product_id='.$pid;
		return $this->query( $sql );
	}
	
	
	/////////////////////////////////////////////////////////// Addons
	public function allAddons() {
		$sql = 'SELECT id, name, description FROM tbladdons';
		return $this->query_count( $sql );
	}
	public function whmcsAddons() {
		$sql = 'SELECT * FROM mod_monitis_addon';
		return $this->query( $sql );
	}
	public function deleteAddon( $addon_id) {
		$sql = 'DELETE FROM mod_monitis_addon WHERE addon_id='.$addon_id;
		return $this->query_del( $sql );
	}
	
	//////////////////////////////////////////////////////// 

	protected function productMonitorById($monitor_id) {
		$sql = 'SELECT * FROM mod_monitis_product_monitor WHERE monitor_id='.$monitor_id;
		$vals = $this->query( $sql );
		if( $vals ){ return $vals[0];
		} else { return null; }
	}


	protected function serviceByOrderId($orderid) {

		$sql = 'SELECT ordernum, tblorders.userid as user_id, ipaddress, tblorders.status as orderstatus,
		orderid, tblhosting.id as serviceid
		FROM tblorders
		LEFT JOIN tblhosting on (tblhosting.orderid = tblorders.id )
		WHERE tblorders.id='.$orderid;
		//return $this->query( $sql );
		$vals = $this->query( $sql );
		if( $vals ){ return $vals[0];
		} else { return null; }
	}
	protected function serviceById($serviceid) {
		$sql = 'SELECT * FROM tblhosting WHERE id='.$serviceid;
		//return $this->query( $sql );
		$vals = $this->query( $sql );
		if( $vals ){ return $vals[0];
		} else { return null; }
	}
	protected function orderById($orderid) {
		$sql = 'SELECT id, ordernum, userid, status, ipaddress FROM tblorders WHERE id='.$orderid;
		return $this->query( $sql );
		//$vals = $this->query( $sql );
		//if( $vals ){ return $vals[0];
		//} else { return null; }
	}
	
	protected function orderByServiceId($serviceid) {
		$sql = 'SELECT tblhosting.id as serviceid, tblorders.userid, orderid, ordernum, domain, domainstatus, status as orderstatus, ipaddress 
			FROM tblhosting 
			LEFT JOIN tblorders on ( tblorders.id = tblhosting.orderid ) 
			WHERE tblhosting.id='.$serviceid;
		return $this->query( $sql );
		//$vals = $this->query( $sql );
		//if( $vals ){ return $vals[0];
		//} else { return null; }
	}
	
	protected function orderByAddonId($addonid) {
		$sql = 'SELECT tblhostingaddons.id as hostingaddonsid, hostingid, addonid, name, tblorders.userid, orderid, ordernum, tblorders.status as orderstatus, ipaddress
			FROM tblhostingaddons 
			LEFT JOIN tblorders on ( tblorders.id = tblhostingaddons.orderid ) 
			WHERE tblhostingaddons.addonid='.$addonid;
		return $this->query( $sql );
	//	$vals = $this->query( $sql );
	//	if( $vals ){ return $vals[0];
	//	} else { return null; }
	}
	protected function addonServiceById( $serviceid ) {
		//$sql = 'SELECT * FROM tblhostingaddons WHERE id='.$serviceid;
		$sql = 'SELECT tblhostingaddons.id as hostingaddonsid, hostingid, addonid, name, tblorders.userid, orderid, ordernum, tblorders.status as orderstatus, ipaddress
			FROM tblhostingaddons 
			LEFT JOIN tblorders on ( tblorders.id = tblhostingaddons.orderid ) 
			WHERE tblhostingaddons.id='.$serviceid;
		//return $this->query( $sql );
		$vals = $this->query( $sql );
		if( $vals ){ return $vals[0];
		} else { return null; }
	}
}

class productClass extends WHMCS_product_db {
	
	public function __construct () {}
	
	private function _idsList( & $list, $fieldName ){
		$ids = array();
		if( count($list) > 0 ) {
			$cnt = count($list);
			for($i=0; $i<$cnt;$i++) {
				if( empty($fieldName) )
					$ids[] = $list[$i];
				else
					$ids[] = $list[$i][$fieldName];
			}
			$ids = array_unique($ids); 
		}
		return $ids;
	}
	private function field_by_pid( $pid, & $fields) {
		$flds = array();
		for($i=0; $i<count($fields); $i++) {
			if( $fields[$i]['relid'] == $pid )
				$flds[] = $fields[$i];
		}
		return $flds;
	}
	private function isProductAssociate( $pid, & $whmcs) {

		for($i=0; $i<count($whmcs); $i++) {
			if( $whmcs[$i]['product_id'] == $pid )
				return $whmcs[$i];
		}
		return null;
	}	
	public function getproducts() {


		$products = $this->allProducts();
		$whmcsProducts = $this->products();

		if( $products ) {
			$pIds = $this->_idsList( $products, 'id' );
			$pIds_str = implode(",", $pIds);
			//$fields = $oWHMCS->getFields($pIds_str);
			$fields = $this->getFields($pIds_str);
//echo "****** tblcustomfields <br>";
//_dump( $fields );
			for($i=0; $i<count($products); $i++) {
				
				$productId = $products[$i]['id'];
				$flds = $this->field_by_pid( $productId, $fields );

				$monitorType = false;
				$website_field = false;
				for($j=0; $j<count($flds); $j++){
					if( $flds[$j]['fieldname'] == MONITIS_FIELD_WEBSITE ) { 
						$website_field = true;
					}
					if( $flds[$j]['fieldname'] == MONITIS_FIELD_MONITOR) {
						$monitorType = true;
					}
				}
				if( $monitorType && $website_field ) {
					$products[$i]['monitorType'] = true;
				} else {
					$products[$i]['monitorType'] = false;
				}
				$whmcsItem = $this->isProductAssociate( $productId, $whmcsProducts);
				if( $whmcsItem )
					$products[$i]['isWhmcsItem'] = true;
				else
					$products[$i]['isWhmcsItem'] = false;
					
				$products[$i]['customfields'] = $flds;

			}
//_dump( $products );			
			return $products;
		}
		return null;
	}

	public function activateProduct( $pid ) {
		$values = array('product_id'=>$pid);
		insert_query('mod_monitis_product', $values);
	}
	public function deactivateProduct( $pid ) {

		$this->deleteProduct( $pid );	
	}
	

	public function updateField( $field_id, $values ) {
		$where = array('id' => $field_id );
		update_query('tblcustomfields',$values,$where);
	}
	public function updateProduct( $productId, $website_id, $monType_id, $settings ) {

		$website_update = $settings['website'];
		$where = array('id' => $website_id );
		update_query('tblcustomfields',$website_update,$where);
		
//echo "****** website_update <br>";
//_dump( $website_update );
//_dump( $where );
		$monType_update = $settings['monitor_type'];
		$where = array('id' => $monType_id );
		update_query('tblcustomfields', $monType_update, $where);		
	}
	
}


?>