
<fieldset id="FieldAccessSetting">
	<h2 class="legend"><?php echo __('Gestion des champs accessible'); ?></h2>
		<?php echo $this->Form->create($model,array('url'=>array('aro_id'=>$this->data['Aro']['id'])));?>
		
		<?php
			$this->Html->scriptBlock('
				(function( $ ) {
					$(function(){
						$("#AroId").change(function(){
							window.location = "'.$this->Html->url(array('action' => $this->params['action'])).'/aro_id:"+$("#AroId").val();
						});
					})
				})( jQuery );
			',array('inline'=>false));
			if(!empty($aros) && count($aros) > 1){
				$field = array('label'=>'User type','options'=>$aros);
				echo $this->Form->input('Aro.id',$field);
			}
		?>

		<table style="width:auto;">
			<tr>
				<th><?php __('Label'); ?></th>
				<th><?php __('Field'); ?></th>
				<th><?php __('View'); ?></th>
				<th><?php __('Edit'); ?></th>
			</tr>
					
						
			<?php 
			//debug($this->params['FieldAcess']);
			
			$modes = array('read'=>__('view',true),'update'=>__('edit',true));
			foreach ($fields as $name => $val) {
			?>
			<tr>
				<td><strong><?php __(Inflector::humanize($name)) ?></strong></td>
				<td><?php echo $name; ?></td>
				<?php foreach ($modes as $key => $val) { ?>
					<td>
					<?php
						echo $this->Form->input($model.'Field.'.$name.'.'.$key,array('type'=>'checkbox','label'=>false,'div'=>false));
					?>
					</td>
				<?php } ?>
				</tr>
			<?php } ?>
		</table>
	<?php echo $this->Form->end(__('Soumettre',true));?>
</fieldset>