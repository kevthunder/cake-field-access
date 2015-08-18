<?php
class FieldAccessBehavior extends ModelBehavior {

	
	var $defaultSettings = array(
		'accessTypes' => array('read','update'),
		'aclProvider' => 'config',
		'exclude' => array('id'),
	);
	
	var $settings = array();
	//////////////////////////////////// Public Functions ////////////////////////////////////
	
	function __construct() {
		parent::__construct();
		$this->Aco = ClassRegistry::init('Aco');
		$this->Aro = ClassRegistry::init('Aro');
	}
	
	function setup(&$Model, $settings = array()) {
		$this->settings[$Model->alias] = Set::merge($this->defaultSettings, (array)$settings);
		
		
		$aclProvider = $this->settings[$Model->alias]['aclProvider'];
		if(is_string($aclProvider)){
			if($aclProvider == 'config'){
				$name = Inflector::camelize(strtolower(Configure::read('Acl.classname')));
			}else{
				$name = $aclProvider;
			}
			App::import('Component', 'Acl');
			if (!class_exists($name)) {
				if (App::import('Component', $name)) {
					list($plugin, $name) = pluginSplit($name);
					$name .= 'Component';
				} else {
					trigger_error(sprintf(__('Could not find %s.', true), $name), E_USER_WARNING);
				}
			}
			$aclProvider =& new $name();
		}
		$this->settings[$Model->alias]['aclProvider'] = $aclProvider;
		
		FieldAccessCollection::addModel($Model);
	}
	
	function getFieldAccess(&$Model,$aro,$inherit=true){
		if(is_numeric($aro)){
			$aro_id = $aro;
		}else{
			$aro_node = $this->Aro->node($aro);
			$aro_id = $aro_node[0]['Aro']['id'];
		}
		
		///////// cache /////////
		$cacheKey = $Model->name.':'.$aro_id;
		$cache = Cache::read('FieldAccess');
		//debug($cache);
		if(!empty($cache['access'][$cacheKey])){
			//debug($cache['access'][$cacheKey]);
			return $cache['access'][$cacheKey];
		}
		
		
		///////// build request /////////
		$acos_cond = $this->_acosFindCond($Model);
		$joins = array(
			'Aco' => array(
					'alias' => 'Aco',
					'table'=> $this->Aco->useTable,
					'type' => 'INNER',
					'conditions' => array(
						$acos_cond,
					)
				),
			'Aro' => array(
					'alias' => 'Aro',
					'table'=> $this->Aro->useTable,
					'type' => 'INNER',
					'conditions' => array(
						'Aro.Id'=>$aro_id,
					)
				)
		);
		if($inherit){
			$pJoins = array(
				'pAco' => array(
						'alias' => 'pAco',
						'table'=> $this->Aco->useTable,
						'type' => 'INNER',
						'conditions' => array(
							'pAco.id = Permission.aco_id'
						)
					),
				'pAro' => array(
						'alias' => 'pAro',
						'table'=> $this->Aro->useTable,
						'type' => 'INNER',
						'conditions' => array(
							'pAro.id = Permission.aro_id'
						)
					)
			);
			$joins['Aro']['conditions'][] = 'pAro.lft <= Aro.lft';
			$joins['Aro']['conditions'][] = 'pAro.rght >= Aro.rght';
			$joins['Aco']['conditions'][] = 'pAco.lft <= Aco.lft';
			$joins['Aco']['conditions'][] = 'pAco.rght >= Aco.rght';
			$joins = array_merge($pJoins,$joins);
		}else{
			$joins['Aro']['conditions'][] = 'Aro.id = Permission.aro_id';
			$joins['Aco']['conditions'][] = 'Aco.id = Permission.aco_id';
		}
		$findOpt = array(
			'fields'=>array(
				'Permission.*',
				'Aco.id',
				'Aco.alias',
			),
			'conditions'=>array(
			),
			'joins'=> array_values($joins),
			'recursive'=>-1,
		);
		if($inherit){
			$findOpt['order'] = array('pAro.lft DESC','pAco.lft DESC');
		}
		//debug($findOpt);
		$permissions = $this->Aco->Permission->find('all',$findOpt);
		//debug($permissions);
		
		///////// parse data /////////
		$access = array();
		$accessTypes = $this->settings[$Model->alias]['accessTypes'];
		foreach($permissions as $perm){
			foreach($accessTypes as $type){
				$p = $perm['Permission']['_'.$type];
				$f = $perm['Aco']['alias'];
				if($p == 1){
					$access[$f][$type] = true;
				}elseif($p == -1){
					$access[$f][$type] = false;
				}elseif(!isset($access[$f][$type])){
					$access[$f][$type] = false;
				}
			}
		}
		//debug($access);
		
		///////// cache /////////
		$cache['access'][$cacheKey] = $access;
		Cache::write('FieldAccess', $cache);
		
		
		return $access;
	}
	
	function beforeValidate($Model){
		if(!empty($Model->fieldAccessAro)){
			$fieldAccess = $Model->getFieldAccess($Model->fieldAccessAro);
			$exclude=array('id');
			
			foreach($fieldAccess as $field => $access){
				if(array_key_exists($field,$Model->data[$Model->alias]) && !in_array($field,$exclude) && !$access['update']){
					$Model->invalidate($field, __('You are not permitted to edit this field',true));
					debug($field);
				}
			}
		}
		//return false;
	}
	
	function setFieldAccess(&$Model,$aro,$access){
		if(is_numeric($aro)){
			$aro_id = $aro;
			$target_aro = $this->Aro->read(null,$aro);
			if(!empty($target_aro['Aro']['alias'])){
				$aro = $target_aro['Aro']['alias'];
			}else{
				$aro = array_filter(array_intersect_key($target_aro['Aro'],array_flip(array('model','foreign_key','alias'))));
			}
		}else{
			$aro_node = $this->Aro->node($aro);
			$aro_id = $aro_node[0]['id'];
		}
		$this->Acl = $this->settings[$Model->alias]['aclProvider'];
		if(!empty($access)){
			$parent_acos = $this->_parentAco($Model,true);
			$acos_cond = $this->_acosFindCond($Model);
			$existingAco = $this->Aco->find('list',array('fields'=>array('alias','id'),'conditions'=>$acos_cond));
			//debug($existingAco);
			foreach($access as $field => $perm){
				if(empty($existingAco[$field])){
					$this->Aco->create();
					$this->Aco->save(array('alias'=>$field,'parent_id'=>$parent_acos['Aco']['id']));
				}
				foreach($perm as $type => $allow){
					if($allow == -1){
						$this->Acl->deny($aro, $parent_acos['Aco']['alias'].'/'.$field, $type);
					}elseif($allow){
						$this->Acl->allow($aro, $parent_acos['Aco']['alias'].'/'.$field, $type);
					}else{
						$this->Acl->inherit($aro, $parent_acos['Aco']['alias'].'/'.$field, $type);
					}
				}
			}
			
			//////////////// clear cache ////////////////
			$findOpt = array(
				'fields'=>array('Aro.id','Aro.id'),
				'conditions'=>array(
				),
				'joins' => array(
					array(
						'alias' => 'Parent',
						'table'=>$this->Aro->useTable,
						'type' => 'INNER',
						'conditions' => array(
							'Parent.id' => $aro_id,
							'Parent.lft <= Aro.lft',
							'Parent.rght >= Aro.rght',
						)
					)
				)
			);
			$childAros = $this->Aro->find('list',$findOpt);
			$cache = Cache::read('FieldAccess');
			foreach($childAros as $c_aro_id){
				$cacheKey = $Model->name.':'.$c_aro_id;
				$cache['access'][$cacheKey] = null;
			}
			//debug($cache);
			Cache::write('FieldAccess', $cache);
			
		}
		
	}
	
	function fieldsWithAccess(&$Model,$keys = false){
		$fields = $Model->schema();
		$fields = array_diff_key($fields,array_flip($this->settings[$Model->alias]['exclude']));
		if($keys){
			$k = array();
			foreach($fields as $key => $val){
				$k[] = (string)$key;
			}
			return $k;
		}
		return $fields;
	}
	
	//////////////////////////////////// Private Functions ////////////////////////////////////
	function _acosFindCond(&$Model){
		$parent_acos = $this->_parentAco($Model);
		//debug($parent_acos);
		$fields = $Model->fieldsWithAccess(true);
		
		return array(
			'Aco.alias'=>$fields,
			'Aco.lft >'=>$parent_acos['Aco']['lft'],
			'Aco.rght <'=>$parent_acos['Aco']['rght']
		);
	}
	
	function _parentAco(&$Model,$useCache = false){
		$cacheKey = $Model->name;
		$cache = Cache::read('FieldAccess');
		if($useCache && !empty($cache['parentAco'][$cacheKey])){
			return $cache['parentAco'][$cacheKey];
		}
	
		$parent_acos = $this->Aco->find('first',array('conditions'=>array('alias'=>$Model->name.'Field')));
		if(empty($parent_acos)){
			$this->Aco->create();
			$this->Aco->save(array('alias'=>$Model->name.'Field'));
			$parent_acos = $this->Aco->find('first',array('conditions'=>array('alias'=>$Model->name.'Field'),'recursive'=>-1));
		}
		
		$cache['parentAco'][$cacheKey] = $parent_acos;
		Cache::write('FieldAccess', $cache);
		
		return $parent_acos;
	}
}


class FieldAccessCollection extends Object {
	
	var $models = array();
	
	//$_this =& FieldAccessCollection::getInstance();
	function &getInstance() {
		static $instance = array();
		if (!$instance) {
			$instance[0] =& new FieldAccessCollection();
		}
		return $instance[0];
	}
	
	function addModel(&$model){
		$_this =& FieldAccessCollection::getInstance();
		$_this->models[$model->alias] =& $model;
	}
	
	function getAllFieldAccess($aro){
		$_this =& FieldAccessCollection::getInstance();
		$access = array();
		if(!empty($_this->models)){
			if(is_numeric($aro)){
				$aro_id = $aro;
			}else{
				$Aro = ClassRegistry::init('Aro');
				$aro_node = $Aro->node($aro);
				$aro_id = $aro_node[0]['Aro']['id'];
			}
			foreach($_this->models as $alias => &$Model){
				$access[$alias] = $Model->getFieldAccess($aro_id);
			}
		}
		return $access;
	}
}
?>