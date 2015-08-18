<?php
class FieldAccessHelper extends AppHelper {

	var $fieldBlocks = array();

	function testInputAccess($fieldName, $options = array() ){
		$mode = 'update';
		$res = $this->testAccess($fieldName, $mode, false);
		if(!$res && !empty($options['readIfNoUpdate']) && $this->testAccess($fieldName, 'read', false)){
			if(!is_array($options['readIfNoUpdate'])){
				$options['readIfNoUpdate'] = array('type' => 'definition');
			}
			$options = array_merge($options,$options['readIfNoUpdate']);
			$res = $options;
		}
		if(count($this->fieldBlocks) && $this->fieldBlocks[0] !== true){
			$this->fieldBlocks[0] = $res!==false;
		}
		return $res;
	}
	
	function testAccess($fieldName, $mode = 'read', $strict = true){
		$this->setEntity($fieldName);
		//debug($this->params['FieldAccess']);
		$model = $this->model();
		$field = $this->field();
		if(!empty($model) && empty($field)){
			$field = $model;
			$model = Inflector::classify($this->params['controller']);
		}
		//debug($model);
		//debug($field);
		if(isset($this->params['FieldAccess'][$model][$field])){
			$access = $this->params['FieldAccess'][$model][$field];
			//debug($access);
			if(empty($access[$mode])){
				//debug('remove');
				return false;
			}else{
				return true;
			}
		}
		return !$strict;
	}
	
	
	function accessAny($fields, $mode = 'read', $strict = true){
		foreach($fields as $f){
			$res = $this->testAccess($f,$mode,$strict);
			if($res) return true;
		}
		return false;
	}
	
	
	function accessFieldsBlockStart(){
		ob_start();
		array_unshift($this->fieldBlocks,null);
	}
	
	function accessFieldsBlockEnd($print = true){
		$out = ob_get_clean();
		$res = null;
		if(count($this->fieldBlocks)){
			$res = array_shift($this->fieldBlocks);
		}
		if($res === false) return null;
		if($print) echo $out;
		return $out;
	}
}
?>