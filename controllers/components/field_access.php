<?php 
class FieldAccessComponent extends Object
{
	var $components = array();
	var $controller = null;
	
	function initialize(&$controller) {
		$this->controller =& $controller;
		$controller->helpers[] = 'FieldAccess.FieldAccess';
		Configure::write('Sparkform.preprocessors.FieldAccess.FieldAccess','testInputAccess');
	}
	
	function startup(&$controller) {
		if(!empty($controller->user)){
			$aro = array('model'=>'User','foreign_key'=>$controller->user['User']['id']);
		}
		if (!empty($aro)){
			//////// set aro in models ////////
			foreach($controller->modelNames as $alias){
				if(!empty($controller->{$alias}) && !empty($controller->{$alias}->Behaviors) ){
					$Model = $controller->{$alias};
				}else{
					//debug($alias);
					$Model = ClassRegistry::init($alias);
				}
				if($Model && $Model->Behaviors->attached('FieldAccess')){
					$Model->fieldAccessAro = $aro;
				}
			}
		}
	}
	
	function beforeRender(&$controller) {
		if(!empty($controller->user)){
			$aro = array('model'=>'User','foreign_key'=>$controller->user['User']['id']);
		}
		if (!empty($aro)){
			//////// set FieldAccess helper data ////////
			if (class_exists('FieldAccessCollection')) {
				$controller->params['FieldAccess'] = FieldAccessCollection::getAllFieldAccess($aro);
				//debug($controller->params['FieldAccess']);
			}
		}
	}
	
	function fieldSettingFormData($Model,$options){
		$defOpt = array(
			'arosCond' => array(),
			'aro_id' => null,
			'redirect' => null,
		);
		$opt = array_merge($defOpt,$options);
		
		$aro_id = $opt['aro_id'];
		if(!empty($this->controller->params['named']['aro_id'])){
			$aro_id = $this->controller->params['named']['aro_id'];
		}
		if(!empty($this->controller->data['Aro']['id'])){
			$aro_id = $this->controller->data['Aro']['id'];
		}
		$this->Aro = ClassRegistry::init('Aro');
		$this->Aco = ClassRegistry::init('Aco');
		
		//$this->Aco->save(array('alias'=>'AllRegions'));
		//$this->Acl->allow('administrators', 'AllRegions');
		//$this->Acl->allow('global', 'AllRegions');
		
		if(!empty($opt['arosCond']['parent_aros'])){
			$parent_cond = $opt['arosCond']['parent_aros'];
			unset($opt['arosCond']['parent_aros']);
			if(is_numeric($parent_cond)){
				$parent_cond = array('id' => $parent_cond);
			}elseif(!is_array($parent_cond)){
				$parent_cond = array('alias' => $parent_cond);
			}
			$parent_aros = $this->Aro->find('first',array('conditions'=>$parent_cond));
			if(!empty($parent_aros)){
				$opt['arosCond']['lft >']=$parent_aros['Aro']['lft'];
				$opt['arosCond']['rght <']=$parent_aros['Aro']['rght'];
			}
		}
		$this->Aro->displayField = 'alias';
		$parent_aros = $this->Aro->find('first',array('conditions'=>array('alias'=>'moderators')));
		
		$aros = $this->Aro->generatetreelist($opt['arosCond']);
		
		
		if(empty($aro_id)){
			reset($aros);
			$aro_id = key($aros);
		}
		$fields = $Model->fieldsWithAccess();
		if(!empty($this->controller->data)){
			$Model->setFieldAccess($aro_id,$this->controller->data[$Model->alias.'Field']);
			
			if(!empty($opt['redirect'])){
				$this->controller->redirect($opt['redirect']);
			}
		}else{
			$this->controller->data[$Model->alias.'Field'] = $Model->getFieldAccess($aro_id);
		}
		
		$this->controller->data['Aro']['id'] = $aro_id;
		
		return array('aros'=>$aros,'fields'=>$fields,'model'=>$Model->alias,'plugin'=>'field_access');
	}
	
}

?>